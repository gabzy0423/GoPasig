<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MaintenanceRecord;
use App\Models\Bus;
use App\Models\Route;
use App\Models\ColorPalette;
use App\Models\SystemSetting;
use App\Services\MaintenanceService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MaintenanceManagementController extends Controller
{
    /**
     * Display the Fleet Maintenance logs.
     */
    public function indexPage(Request $request)
    {
        $logTypeFilter = $request->input('type', 'all');
        $logStatusFilter = $request->input('status', 'all');
        $search = $request->input('search', '');

        $maintenanceSummary = $this->getMaintenanceSummary();
        $busHealth = $this->getBusHealth();
        $upcomingSchedule = $this->getUpcomingSchedule();

        $query = $this->getFilteredLogsQuery($logTypeFilter, $logStatusFilter);

        if ($request->filled('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('technician_name', 'like', "%{$search}%")
                  ->orWhere('inspector_name', 'like', "%{$search}%")
                  ->orWhereHas('bus', function ($bq) use ($search) {
                      $bq->where('plate_number', 'like', "%{$search}%");
                  });
            });
        }

        $maintenanceLogs = $query->paginate(15)->withQueryString();

        return view('fleet.maintenance.index', compact(
            'maintenanceSummary',
            'busHealth',
            'upcomingSchedule',
            'maintenanceLogs',
            'logTypeFilter',
            'logStatusFilter',
            'search'
        ));
    }

    /**
     * Display a specific maintenance record.
     */
    public function showPage($id)
    {
        $record = MaintenanceRecord::with('bus')->findOrFail($id);
        
        $previousTickets = MaintenanceRecord::where('bus_id', $record->getRawOriginal('bus_id'))
            ->where('id', '!=', $record->id)
            ->latest('created_at')
            ->take(5)
            ->get();

        return view('fleet.maintenance.view', compact('record', 'previousTickets'));
    }

    /**
     * Start confirmation page.
     */
    public function startPage($id)
    {
        $record = MaintenanceRecord::with('bus')->findOrFail($id);
        if ($record->status !== 'scheduled') {
            return redirect()->route('fleet.maintenance')->with('error', 'Only scheduled maintenance tickets can be started.');
        }
        return view('fleet.maintenance.start', compact('record'));
    }

    /**
     * Complete checklist form page.
     */
    public function completePage($id)
    {
        $record = MaintenanceRecord::with('bus')->findOrFail($id);
        if ($record->status !== 'in_progress') {
            return redirect()->route('fleet.maintenance')->with('error', 'Only in progress maintenance tickets can be completed.');
        }
        return view('fleet.maintenance.complete', compact('record'));
    }

    /**
     * Start service action.
     */
    public function startService($id)
    {
        $record = MaintenanceRecord::findOrFail($id);
        if ($record->status !== 'scheduled') {
            return redirect()->route('fleet.maintenance.show', $id)->with('error', 'Only scheduled maintenance tickets can be started.');
        }

        DB::transaction(function () use ($record) {
            $record->update(['status' => 'in_progress']);
            MaintenanceService::syncMaintenanceWithBusStatus($record);
        });

        return redirect()->route('fleet.maintenance.show', $id)->with('success', 'Maintenance service started successfully.');
    }

    /**
     * Complete service action.
     */
    public function completeService($id, Request $request)
    {
        $record = MaintenanceRecord::with(['bus', 'inspectionAttempts'])->findOrFail($id);

        // Immutability guard — completed records cannot be modified
        if ($record->status === 'completed') {
            return redirect()->route('fleet.maintenance.show', $id)
                ->with('error', 'This maintenance record is already completed and is immutable. To address remaining issues, the Admin should create a new maintenance ticket.');
        }

        if ($record->status !== 'in_progress') {
            return redirect()->route('fleet.maintenance.show', $id)
                ->with('error', 'Only in-progress maintenance tickets can be submitted for inspection.');
        }

        $validated = $request->validate([
            'inspector_name'       => 'required|string|max:255',
            'bus_condition'        => 'required|in:Excellent,Good,Fair,Needs Follow-up',
            'maintenance_result'   => 'required|in:Passed Inspection,Passed with Observation,Failed Inspection',
            'inspection_checklist' => 'required|array',
            'parts_replaced'       => 'nullable|string',
            'labor_cost'           => 'nullable|numeric|min:0',
            'parts_cost'           => 'nullable|numeric|min:0',
            'other_cost'           => 'nullable|numeric|min:0',
            'technician_notes'     => 'required|string',
            'recommendation'       => 'nullable|string',
        ]);

        // Backend: Recommendation required for Passed with Observation and Failed Inspection
        if (in_array($validated['maintenance_result'], ['Passed with Observation', 'Failed Inspection'])
            && empty(trim($validated['recommendation'] ?? ''))
        ) {
            return back()->withInput()->with('error',
                $validated['maintenance_result'] === 'Passed with Observation'
                    ? 'Recommendation is required for buses that passed with observations.'
                    : 'Recommendation is required before recording a failed inspection.'
            );
        }

        // All checklist items must be checked
        $checklist     = $validated['inspection_checklist'];
        $requiredItems = ['brakes', 'battery', 'tires', 'lights', 'test_drive'];
        foreach ($requiredItems as $item) {
            if (empty($checklist[$item])) {
                return back()->withInput()->with('error', 'Please complete all safety checklist items before submitting.');
            }
        }

        // -- Business Rule: derive all downstream fields from maintenance_result --
        $result           = $validated['maintenance_result'];
        $roadworthy       = $result !== 'Failed Inspection';
        $hasObservation   = $result === 'Passed with Observation';
        $inspectionPassed = $result !== 'Failed Inspection';
        $releaseBus       = $result !== 'Failed Inspection';

        DB::transaction(function () use ($record, $validated, $roadworthy, $hasObservation, $releaseBus, $inspectionPassed, $result) {
            $labor          = floatval($validated['labor_cost'] ?? 0);
            $parts          = floatval($validated['parts_cost'] ?? 0);
            $other          = floatval($validated['other_cost'] ?? 0);
            $totalCost      = $labor + $parts + $other;

            // Compute sequential attempt number
            $attemptNo = (\App\Models\MaintenanceInspection::where('maintenance_record_id', $record->id)->max('attempt_no') ?? 0) + 1;

            // Always record this inspection attempt in the audit trail
            \App\Models\MaintenanceInspection::create([
                'maintenance_record_id' => $record->id,
                'attempt_no'            => $attemptNo,
                'inspector_name'        => $validated['inspector_name'],
                'bus_condition'         => $validated['bus_condition'],
                'maintenance_result'    => $result,
                'roadworthy'            => $roadworthy,
                'inspection_passed'     => $inspectionPassed,
                'inspection_checklist'  => $validated['inspection_checklist'],
                'parts_replaced'        => $validated['parts_replaced'] ?? null,
                'labor_cost'            => $labor,
                'parts_cost'            => $parts,
                'other_cost'            => $other,
                'cost_php'              => $totalCost,
                'technician_notes'      => $validated['technician_notes'],
                'recommendation'        => $validated['recommendation'] ?? null,
                'inspected_at'          => now(),
            ]);

            $bus = Bus::find($record->getRawOriginal('bus_id'));

            if ($inspectionPassed) {
                // -- PASSED or PASSED WITH OBSERVATION --------------------------
                // Compute actual duration from scheduled_at to now
                $scheduledAt    = Carbon::parse($record->scheduled_at);
                $actualDuration = max(0, $scheduledAt->diffInMinutes(now()));

                // Mark the ticket as completed and copy final result to maintenance_record
                $record->update([
                    'status'                  => 'completed',
                    'completed_at'            => now(),
                    'inspector_name'          => $validated['inspector_name'],
                    'bus_condition'           => $validated['bus_condition'],
                    'roadworthy'              => $roadworthy,
                    'maintenance_result'      => $result,
                    'inspection_checklist'    => $validated['inspection_checklist'],
                    'parts_replaced'          => $validated['parts_replaced'] ?? null,
                    'labor_cost'              => $labor,
                    'parts_cost'             => $parts,
                    'other_cost'              => $other,
                    'cost_php'               => $totalCost,
                    'actual_duration_minutes' => $actualDuration,
                    'technician_notes'        => $validated['technician_notes'],
                    'recommendation'          => $validated['recommendation'] ?? null,
                    'inspection_passed'       => true,
                    'inspected_by'            => $validated['inspector_name'],
                    'inspected_at'            => now(),
                ]);

                // Release bus to Standby (inactive) and sync observation flag
                if ($bus) {
                    \App\Services\BusStateService::transition(
                        $bus,
                        Bus::STATUS_INACTIVE,
                        "Maintenance completed: {$result}"
                    );
                    $bus->update(['has_observation' => $hasObservation]);
                }
            } else {
                // -- FAILED INSPECTION ------------------------------------------
                // Ticket stays in_progress — the bus remains in maintenance
                // Record the last failed attempt details on the record for reference
                $record->update([
                    'inspection_passed' => false,
                    'inspected_by'      => $validated['inspector_name'],
                    'inspected_at'      => now(),
                    'inspector_name'    => $validated['inspector_name'],
                    'maintenance_result' => $result,
                    'recommendation'    => $validated['recommendation'] ?? null,
                ]);
                // Bus stays in maintenance — no transition
                if ($bus) {
                    $bus->update(['has_observation' => false]);
                }
            }
        });

        if ($inspectionPassed) {
            $resultLabel = match($result) {
                'Passed Inspection'       => 'The vehicle passed all inspections and has been returned to Standby.',
                'Passed with Observation' => 'The vehicle is operational and has been returned to Standby. An observation flag has been attached.',
                default                   => '',
            };
            return redirect()->route('fleet.maintenance.show', $id)
                ->with('success', "Maintenance completed. {$resultLabel}");
        } else {
            return redirect()->route('fleet.maintenance.show', $id)
                ->with('warning', 'Inspection result recorded: FAILED. The vehicle remains under Maintenance. The bus cannot be dispatched until a subsequent inspection passes. Please address the reported issues and re-inspect.');
        }
    }

    /**
     * Cancel service action.
     */
    public function cancelService($id)
    {
        $record = MaintenanceRecord::findOrFail($id);
        if ($record->status !== 'scheduled') {
            return redirect()->route('fleet.maintenance.show', $id)->with('error', 'Only scheduled maintenance tickets can be cancelled.');
        }

        DB::transaction(function () use ($record) {
            $record->update(['status' => 'cancelled']);
            MaintenanceService::syncMaintenanceWithBusStatus($record);
        });

        return redirect()->route('fleet.maintenance.show', $id)->with('success', 'Maintenance service cancelled successfully.');
    }

    /**
     * Delete service action.
     */
    public function destroyWeb($id)
    {
        $record = MaintenanceRecord::findOrFail($id);
        if ($record->status === 'completed') {
            return redirect()->route('fleet.maintenance')->with('error', 'Completed maintenance records are immutable and cannot be deleted.');
        }

        $busId = $record->getRawOriginal('bus_id');
        
        DB::transaction(function () use ($record) {
            $bus = Bus::find($record->getRawOriginal('bus_id'));
            if ($bus) {
                $restoreStatus = $bus->previous_status ?? \App\Models\Bus::STATUS_ACTIVE;
                if ($bus->status !== $restoreStatus) {
                    \App\Services\BusStateService::transition($bus, $restoreStatus, 'Maintenance record deleted');
                }
            }
            $record->delete();
        });

        $bus = Bus::find($busId);
        if ($bus) {
            $bus->syncObservationStatus();
        }

        return redirect()->route('fleet.maintenance')->with('success', 'Maintenance record deleted successfully.');
    }

    /**
     * Get JSON data for maintenance dashboard AJAX refreshing.
     * Updated columns: Remove Route & Cost, Add Inspector Name
     */
    public function getMaintenanceData(Request $request)
    {
        $logTypeFilter = $request->input('type', 'all');
        $logStatusFilter = $request->input('status', 'all');

        $maintenanceSummary = $this->getMaintenanceSummary();
        $busHealth = $this->getBusHealth();
        $upcomingSchedule = $this->getUpcomingSchedule();
        
        $maintenanceLogs = $this->getFilteredLogsQuery($logTypeFilter, $logStatusFilter)->paginate(15);

        $transformedLogs = collect($maintenanceLogs->items())->map(function ($row) {
            return [
                'id' => $row->id,
                'maintenance_date' => $row->scheduled_at->timezone('Asia/Manila')->format('M d, Y H:i'),
                'bus_id' => $row->bus_id, // plate number through accessor
                'type' => $row->type,
                'description' => $row->description,
                'technician_name' => $row->technician_name ?: 'â€”',
                'inspected_by' => $row->inspected_by ?: 'â€”',
                'status' => $row->status,
            ];
        });

        return response()->json([
            'success' => true,
            'summary' => $maintenanceSummary,
            'busHealth' => $busHealth,
            'upcomingSchedule' => $upcomingSchedule->map(function ($entry) {
                return [
                    'id' => $entry->id,
                    'scheduled_date' => Carbon::parse($entry->scheduled_date)->timezone('Asia/Manila')->format('M d, Y h:i A'),
                    'bus_id' => $entry->bus_id,
                    'description' => $entry->description,
                ];
            }),
            'logs' => [
                'current_page' => $maintenanceLogs->currentPage(),
                'last_page' => $maintenanceLogs->lastPage(),
                'per_page' => $maintenanceLogs->perPage(),
                'total' => $maintenanceLogs->total(),
                'data' => $transformedLogs,
            ],
        ]);
    }

    /**
     * Get specific maintenance record details for drawer.
     */
    public function getRecordDetails($id)
    {
        $record = MaintenanceRecord::with('bus.route')->find($id);
        if (!$record) {
            return response()->json(['success' => false, 'message' => 'Record not found'], 404);
        }

        return response()->json([
            'success' => true,
            'record' => [
                'id' => $record->id,
                'bus_id' => $record->getRawOriginal('bus_id'),
                'bus_plate' => $record->bus_id, // plate number through accessor
                'type' => $record->type,
                'description' => $record->description,
                'scheduled_at' => $record->scheduled_at ? $record->scheduled_at->format('Y-m-d\TH:i') : '',
                'scheduled_at_formatted' => $record->scheduled_at ? $record->scheduled_at->timezone('Asia/Manila')->format('F d, Y \a\t h:i A') : '',
                'technician_name' => $record->technician_name ?: '',
                'cost_php' => $record->cost_php,
                'cost_formatted' => number_format((float)$record->cost_php, 2),
                'status' => $record->status,
                'expected_duration_minutes' => $record->expected_duration_minutes,
                'inspected_by' => $record->inspected_by ?: '',
                'inspection_passed' => $record->inspection_passed,
                'maintenance_result' => $record->maintenance_result ?: '',
                'inspector_name' => $record->inspector_name ?: '',
                'bus_condition' => $record->bus_condition ?: '',
                'roadworthy' => (bool)$record->roadworthy,
                'recommendation' => $record->recommendation ?: '',
                'inspection_checklist' => $record->inspection_checklist ?: [],
                'labor_cost' => $record->labor_cost,
                'parts_cost' => $record->parts_cost,
                'other_cost' => $record->other_cost,
                'parts_replaced' => $record->parts_replaced ?: '',
                'technician_notes' => $record->technician_notes ?: '',
            ]
        ]);
    }

    /**
     * Get specific bus unit profile for drawer.
     */
    public function getBusProfile($id)
    {
        $bus = Bus::with(['route', 'maintenanceRecords' => function ($q) {
            $q->orderByDesc('scheduled_at')->take(5);
        }])->where('id', $id)->orWhere('plate_number', $id)->first();

        if (!$bus) {
            return response()->json(['success' => false, 'message' => 'Bus not found'], 404);
        }

        $recentRecords = $bus->maintenanceRecords->map(function ($hist) {
            return [
                'id' => $hist->id,
                'type' => $hist->type,
                'date' => $hist->scheduled_at->timezone('Asia/Manila')->format('Y-m-d'),
                'status' => $hist->status,
            ];
        });

        $completionTime = null;
        if ($bus->status === 'maintenance') {
            $maintRecord = MaintenanceRecord::where('bus_id', $bus->id)
                ->whereIn('status', ['scheduled', 'in_progress'])
                ->orderBy('scheduled_at', 'desc')
                ->first();
            if ($maintRecord && $maintRecord->scheduled_at && $maintRecord->expected_duration_minutes) {
                $completionTime = $maintRecord->scheduled_at->copy()->addMinutes($maintRecord->expected_duration_minutes)->timezone('Asia/Manila')->format('M d h:i A');
            } elseif ($maintRecord && $maintRecord->scheduled_at) {
                $completionTime = $maintRecord->scheduled_at->timezone('Asia/Manila')->format('M d h:i A') . ' (+?m)';
            }
        }

        return response()->json([
            'success' => true,
            'bus' => [
                'id' => $bus->id,
                'plate_number' => $bus->plate_number,
                'assigned_route' => $bus->route ? $bus->route->name : 'No Assigned Route',
                'status' => $bus->status,
                'capacity' => $bus->capacity,
                'passengers' => $bus->passengers,
                'recent_services' => $recentRecords,
                'completion_time' => $completionTime,
                'has_observation' => (bool)$bus->has_observation,
            ]
        ]);
    }

    /**
     * Store or Update a Maintenance Log entry.
     */
    public function storeOrUpdate(Request $request)
    {
        $id = $request->input('id');
        $isEditing = !empty($id);

        // ISSUE-029 FIX: Maintenance types are now read from SystemSetting so admins
        // can add/remove types (e.g. 'Safety Check') without editing source code.
        // Store a JSON array in system_settings with key 'maintenance_types'.
        $allowedTypes = SystemSetting::getArray('maintenance_types', ['Preventive', 'Corrective', 'Inspection']);

        $rules = [
            'bus_id'                     => 'required|exists:buses,id',
            'type'                       => ['required', Rule::in($allowedTypes)],
            'description'                => 'required|string|min:5',
            'scheduled_at'               => 'required|date',
            'technician_name'            => 'nullable|string|max:100',
            'cost_php'                   => 'nullable|numeric|min:0',
            'status'                     => 'required|in:scheduled,in_progress,completed,cancelled',
            'expected_duration_minutes'  => 'nullable|integer|min:1',
        ];

        $validated = $request->validate($rules);

        // Block scheduling maintenance if the bus is mid-trip
        if (in_array($validated['status'], ['scheduled', 'in_progress'])) {
            $bus = Bus::find($validated['bus_id']);
            if ($bus && $bus->status !== 'maintenance') {
                $ongoingTrip = \App\Models\Trip::where('bus_id', $validated['bus_id'])->where('status', 'ongoing')->exists();
                if ($ongoingTrip) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot schedule maintenance: The bus currently has an ongoing trip.'
                    ], 422);
                }
            }
        }

        $data = [
            'bus_id' => $validated['bus_id'],
            'type' => $validated['type'],
            'description' => $validated['description'],
            'scheduled_at' => Carbon::parse($validated['scheduled_at']),
            'technician_name' => $validated['technician_name'] ?: null,
            'cost_php' => $validated['cost_php'] !== null ? floatval($validated['cost_php']) : 0.00,
            'status' => $validated['status'],
            // ISSUE-028 FIX: Fallback duration is now configurable via SystemSetting
            // instead of the hardcoded 120. Set key 'maintenance_default_duration_minutes'.
            'expected_duration_minutes' => $validated['expected_duration_minutes'] !== null
                ? intval($validated['expected_duration_minutes'])
                : (int) SystemSetting::get('maintenance_default_duration_minutes', 120),
        ];

        if ($isEditing) {
            $record = MaintenanceRecord::findOrFail($id);
            $oldStatus = $record->status;
            $record->update($data);

            MaintenanceService::handleBusStatusSideEffects($record->getRawOriginal('bus_id'), $oldStatus, $validated['status'], $id);
            $msg = 'Maintenance schedule updated successfully.';
        } else {
            $record = MaintenanceRecord::create($data);
            MaintenanceService::handleBusStatusSideEffects($record->getRawOriginal('bus_id'), null, $validated['status']);
            $msg = 'Maintenance scheduled successfully.';
        }

        return response()->json([
            'success' => true,
            'message' => $msg,
            'record_id' => $record->id
        ]);
    }

    /**
     * Update maintenance record status.
     */
    public function updateStatus($id, Request $request)
    {
        if (empty($id) || $id === 'null' || $id === 'undefined' || !is_numeric($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Maintenance Record ID provided.'
            ], 400);
        }

        $record = MaintenanceRecord::find($id);
        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Maintenance record not found.'
            ], 404);
        }

        $oldStatus = $record->status;
        $status = $request->input('status');

        if ($status === 'completed') {
            $validated = $request->validate([
                'status' => 'required|in:completed',
                'inspector_name' => 'required|string|max:255',
                'bus_condition' => 'required|in:Excellent,Good,Fair,Needs Follow-up',
                'roadworthy' => 'required|boolean',
                'maintenance_result' => 'required|in:Passed Inspection,Passed with Observation,Failed Inspection',
                'inspection_checklist' => 'required|array',
                'parts_replaced' => 'nullable|string',
                'labor_cost' => 'nullable|numeric|min:0',
                'parts_cost' => 'nullable|numeric|min:0',
                'other_cost' => 'nullable|numeric|min:0',
                'technician_notes' => 'required|string',
                'recommendation' => 'nullable|string',
            ]);

            // Enforce conditional validation on recommendation
            if (in_array($validated['maintenance_result'], ['Passed with Observation', 'Failed Inspection']) && empty($validated['recommendation'])) {
                return response()->json([
                    'success' => false,
                    'message' => $validated['maintenance_result'] === 'Passed with Observation'
                        ? 'Recommendation is required for buses with observations.'
                        : 'Recommendation is required before closing the maintenance record.'
                ], 422);
            }

            // Enforce checklist items validation
            $checklist = $validated['inspection_checklist'];
            $requiredItems = ['brakes', 'battery', 'tires', 'lights', 'test_drive'];
            foreach ($requiredItems as $item) {
                if (empty($checklist[$item])) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Please complete all safety checklist items.'
                    ], 422);
                }
            }

            DB::transaction(function () use ($record, $validated, $oldStatus) {
                $labor = floatval($validated['labor_cost'] ?? 0);
                $parts = floatval($validated['parts_cost'] ?? 0);
                $other = floatval($validated['other_cost'] ?? 0);
                $totalCost = $labor + $parts + $other;

                $scheduledAt = Carbon::parse($record->scheduled_at);
                $actualDuration = max(0, $scheduledAt->diffInMinutes(now()));

                $record->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                    'inspector_name' => $validated['inspector_name'],
                    'bus_condition' => $validated['bus_condition'],
                    'roadworthy' => $validated['roadworthy'],
                    'maintenance_result' => $validated['maintenance_result'],
                    'inspection_checklist' => $validated['inspection_checklist'],
                    'parts_replaced' => $validated['parts_replaced'] ?? null,
                    'labor_cost' => $labor,
                    'parts_cost' => $parts,
                    'other_cost' => $other,
                    'cost_php' => $totalCost,
                    'actual_duration_minutes' => $actualDuration,
                    'technician_notes' => $validated['technician_notes'],
                    'recommendation' => $validated['recommendation'] ?? null,
                    // Keep compatibility with legacy fields
                    'inspection_passed' => $validated['maintenance_result'] !== 'Failed Inspection',
                    'inspected_by' => $validated['inspector_name'],
                    'inspected_at' => now(),
                ]);

                // Transition bus status
                $bus = Bus::find($record->getRawOriginal('bus_id'));
                if ($bus) {
                    if ($validated['maintenance_result'] === 'Failed Inspection') {
                        // Failed Inspection: bus status remains in maintenance!
                        $bus->update([
                            'has_observation' => false
                        ]);

                        \App\Models\BusStatusAuditLog::logStatusChange(
                            busId: $bus->id,
                            newStatus: Bus::STATUS_MAINTENANCE,
                            oldStatus: Bus::STATUS_MAINTENANCE,
                            userId: \Illuminate\Support\Facades\Auth::id() ?: 1,
                            reason: 'Maintenance completed: Failed inspection. Bus remains in maintenance.',
                        );
                    } else {
                        // Passed / Passed with Observation: bus returns to Standby (inactive)
                        $hasObs = $validated['maintenance_result'] === 'Passed with Observation';
                        
                        // First, update has_observation on the bus model
                        $bus->update([
                            'has_observation' => $hasObs
                        ]);

                        // Transition status using BusStateService
                        \App\Services\BusStateService::transition($bus, Bus::STATUS_INACTIVE, 'Maintenance completed: ' . $validated['maintenance_result']);
                    }
                }
            });

            return response()->json([
                'success' => true,
                'message' => 'Maintenance status updated.'
            ]);
        } else {
            $validated = $request->validate([
                'status' => 'required|in:scheduled,in_progress,cancelled'
            ]);

            $record->update(['status' => $status]);
            MaintenanceService::handleBusStatusSideEffects($record->getRawOriginal('bus_id'), $oldStatus, $status, $id);

            return response()->json([
                'success' => true,
                'message' => 'Maintenance status updated.'
            ]);
        }
    }

    /**
     * Delete maintenance record.
     */
    public function destroy($id)
    {
        if (empty($id) || $id === 'null' || $id === 'undefined' || !is_numeric($id)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Maintenance Record ID provided.'
            ], 400);
        }

        $record = MaintenanceRecord::find($id);
        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Maintenance record not found.'
            ], 404);
        }
        $busId = $record->getRawOriginal('bus_id');
        $oldStatus = $record->status;
        
        if ($oldStatus === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Completed maintenance records are immutable and cannot be deleted to preserve the audit trail.'
            ], 422);
        }

        $record->delete();

        // Revert bus status if deleted record was in_progress
        if ($oldStatus === 'in_progress') {
            MaintenanceService::handleBusStatusSideEffects($busId, $oldStatus, 'cancelled', $id);
        }

        // Recalculate observation status for the bus
        $bus = Bus::find($busId);
        if ($bus) {
            $bus->syncObservationStatus();
        }

        return response()->json([
            'success' => true,
            'message' => 'Maintenance record deleted.'
        ]);
    }

    /**
     * Export maintenance log to CSV.
     */
    public function exportCsv(Request $request)
    {
        $logTypeFilter = $request->input('type', 'all');
        $logStatusFilter = $request->input('status', 'all');

        $records = $this->getFilteredLogsQuery($logTypeFilter, $logStatusFilter)->get();

        $csvHeader = ['Date', 'Bus Plate', 'Type', 'Description', 'Technician', 'Inspector', 'Status'];
        $csvData = [];
        $csvData[] = implode(',', $csvHeader);

        foreach ($records as $row) {
            $csvRow = [
                $row->scheduled_at->timezone('Asia/Manila')->format('Y-m-d H:i'),
                $row->bus_id, // plate number through accessor
                $row->type,
                '"' . str_replace('"', '""', $row->description) . '"',
                $row->technician_name ?: 'â€”',
                $row->inspected_by ?: 'â€”',
                $row->status,
            ];
            $csvData[] = implode(',', $csvRow);
        }

        $csvString = implode("\n", $csvData);
        $fileName = 'maintenance_logs_' . now()->format('YmdHis') . '.csv';

        return response()->streamDownload(function () use ($csvString) {
            echo $csvString;
        }, $fileName, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }

    /**
     * Calculate maintenance dashboard summary metrics.
     */
    public function getMaintenanceSummary()
    {
        $totalFleet = Bus::count();
        $activeUnits = Bus::where('status', 'active')->count();
        $underMaintenance = Bus::where('status', 'maintenance')->count();
        $breakdownCount = Bus::whereIn('status', ['maintenance', 'breakdown'])->count();

        // Due for service within configurable warning days
        $dueWindowDays = (int) SystemSetting::get('maintenance_due_warning_days', 7);
        $dueForService = MaintenanceRecord::where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now(), now()->addDays($dueWindowDays)])
            ->count();

        return (object) [
            'total_fleet' => $totalFleet,
            'active_units' => $activeUnits,
            'under_maintenance' => $underMaintenance,
            'breakdown_count' => $breakdownCount,
            'due_for_service' => $dueForService,
        ];
    }

    /**
     * Fetch all buses status and route assignment mapping.
     * AUTO-SYNC: Checks if bus marked as "maintenance" actually has active records
     */
    public function getBusHealth()
    {
        $palette = ColorPalette::getColors('routes');

        return Bus::with('route')->get()->map(function ($bus) use ($palette) {
            // AUTO-SYNC CHECK: If bus is maintenance but no active records, unlock it
            if ($bus->status === \App\Models\Bus::STATUS_MAINTENANCE) {
                $hasActiveMaintenance = MaintenanceRecord::where('bus_id', $bus->id)
                    ->whereIn('status', ['scheduled', 'in_progress'])
                    ->exists();
                
                if (!$hasActiveMaintenance) {
                    $restoreStatus = $bus->previous_status ?? \App\Models\Bus::STATUS_ACTIVE;
                    \App\Services\BusStateService::transition($bus, $restoreStatus, 'Auto-sync: No active maintenance records');
                }
            }

            $routeColor = '#888780'; // default: unassigned
            if ($bus->route) {
                $routeColor = $bus->route->color ?? ($palette[($bus->route->id - 1) % count($palette)] ?? '#003F87');
            }

            $completionTime = null;
            if ($bus->status === 'maintenance') {
                $maintRecord = MaintenanceRecord::where('bus_id', $bus->id)
                    ->whereIn('status', ['scheduled', 'in_progress'])
                    ->orderBy('scheduled_at', 'desc')
                    ->first();
                if ($maintRecord && $maintRecord->scheduled_at && $maintRecord->expected_duration_minutes) {
                    $completionTime = $maintRecord->scheduled_at->copy()->addMinutes($maintRecord->expected_duration_minutes)->timezone('Asia/Manila')->format('M d h:i A');
                } elseif ($maintRecord && $maintRecord->scheduled_at) {
                    $completionTime = $maintRecord->scheduled_at->timezone('Asia/Manila')->format('M d h:i A') . ' (+?m)';
                }
            }

            return (object) [
                'id'             => $bus->id,
                'bus_id'         => $bus->plate_number,
                'assigned_route' => $bus->route ? $bus->route->name : null,
                'route_color'    => $routeColor,
                'status'         => $bus->status,
                'completion_time'=> $completionTime,
                'has_observation'=> (bool)$bus->has_observation,
            ];
        });
    }

    /**
     * Get upcoming schedule for next 30 days.
     */
    public function getUpcomingSchedule()
    {
        $scheduleWindowDays = (int) SystemSetting::get('maintenance_schedule_window_days', 30);
        return MaintenanceRecord::with('bus')
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now(), now()->addDays($scheduleWindowDays)])
            ->orderBy('scheduled_at')
            ->get()
            ->map(function ($row) {
                return (object) [
                    'id' => $row->id,
                    'scheduled_date' => $row->scheduled_at,
                    'bus_id' => $row->bus_id, // plate number through accessor
                    'description' => $row->type . ' â€” ' . $row->description,
                ];
            });
    }

    /**
     * Build base query for maintenance logs.
     */
    public function getFilteredLogsQuery($typeFilter = 'all', $statusFilter = 'all')
    {
        $query = MaintenanceRecord::with('bus.route');

        if ($typeFilter !== 'all') {
            $query->where('type', $typeFilter);
        }

        if ($statusFilter !== 'all') {
            $statusMap = [
                'Scheduled' => 'scheduled',
                'In progress' => 'in_progress',
                'Done' => 'completed',
                'Cancelled' => 'cancelled'
            ];
            $dbStatus = $statusMap[$statusFilter] ?? $statusFilter;
            if ($dbStatus) {
                $query->where('status', $dbStatus);
            }
        }

        $query->orderByDesc('scheduled_at');

        return $query;
    }
}
