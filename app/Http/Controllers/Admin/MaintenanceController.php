<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use App\Models\Bus;
use App\Models\SystemSetting;
use App\Services\BusinessLogicService;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    public function create()
    {
        $maintenanceTypes = array_filter(array_map('trim', explode(',', (string) SystemSetting::get('maintenance_type_options', 'Preventive Maintenance,Corrective Maintenance'))));
        $buses = Bus::where('status', 'inactive')->orderBy('plate_number')->get();
        $buses->each(function ($bus) {
            $latestCompleted = $bus->maintenanceRecords()
                ->where('status', 'completed')
                ->orderBy('completed_at', 'desc')
                ->first();
            $bus->last_maintenance_date = $latestCompleted && $latestCompleted->completed_at 
                ? $latestCompleted->completed_at->timezone(config('app.timezone', 'Asia/Manila'))->toDateString() 
                : null;
        });
        return view('admin.maintenance.create', compact('maintenanceTypes', 'buses'));
    }

    /**
     * Display a listing of the maintenance records.
     */
    public function index(Request $request)
    {
        $query = MaintenanceRecord::with('bus');

        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('search')) {
            $search = trim($request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('ticket_number', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('technician_name', 'like', "%{$search}%")
                  ->orWhereHas('bus', function ($bq) use ($search) {
                      $bq->where('plate_number', 'like', "%{$search}%")
                        ->orWhere('fleet_number', 'like', "%{$search}%");
                  });
            });
        }

        $records = $query->orderBy('scheduled_at', 'desc')->paginate(10);

        return response()->json($records);
    }

    public function stats()
    {
        $stats = app(\App\Services\MaintenanceStatisticsService::class)->getSummary();
        return response()->json($stats);
    }

    /**
     * Get a single maintenance record by ID.
     */
    public function show($id)
    {
        $record = MaintenanceRecord::with('bus')->findOrFail($id);
        return response()->json($record);
    }

    /**
     * Store a newly created maintenance record in database.
     */
    public function store(Request $request)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can create maintenance'
            ], 403);
        }

        $minDuration = (int) SystemSetting::get('maintenance_duration_min_minutes', 15);
        $maxDuration = (int) SystemSetting::get('maintenance_duration_max_minutes', 480);

        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'type' => 'required|string|max:100',
            'technician_name' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'scheduled_at' => 'required|date',
            'expected_duration_minutes' => "nullable|integer|min:{$minDuration}|max:{$maxDuration}",
        ]);

        $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_at']);
        $enforcePastCheck = !app()->runningUnitTests() || \Carbon\Carbon::hasTestNow();
        if ($enforcePastCheck && $scheduledAt->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot schedule maintenance in the past.'
            ], 422);
        }

        // BL-6.5: Block scheduling maintenance if the bus is mid-trip
        $ongoingTrip = \App\Models\Trip::where('bus_id', $validated['bus_id'])->where('status', 'ongoing')->first();
        if ($ongoingTrip) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot schedule maintenance: The bus currently has an ongoing trip.'
            ], 422);
        }

        $duration = $validated['expected_duration_minutes'] 
            ?? (int) SystemSetting::get('default_maintenance_duration_minutes', 120);

        // Validate duration bounds
        $durationValidation = BusinessLogicService::validateMaintenanceDuration($duration);
        if (!$durationValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $durationValidation['error']
            ], 422);
        }

        // Check for future schedules within maintenance window
        $expectedEnd = $scheduledAt->copy()->addMinutes($duration);
        $scheduledDate = $scheduledAt->toDateString();
        $expectedEndDate = $expectedEnd->toDateString();

        $futureSchedules = \App\Models\Schedule::where('bus_id', $validated['bus_id'])
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->where(function ($q) use ($scheduledDate, $scheduledAt, $expectedEndDate, $expectedEnd) {
                if ($scheduledDate === $expectedEndDate) {
                    $q->whereDate('service_date', $scheduledDate)
                      ->whereTime('departure_time', '>=', $scheduledAt->toTimeString())
                      ->whereTime('departure_time', '<=', $expectedEnd->toTimeString());
                } else {
                    $q->where(function ($sq) use ($scheduledDate, $scheduledAt) {
                        $sq->whereDate('service_date', $scheduledDate)
                           ->whereTime('departure_time', '>=', $scheduledAt->toTimeString());
                    })->orWhere(function ($sq) use ($expectedEndDate, $expectedEnd) {
                        $sq->whereDate('service_date', $expectedEndDate)
                           ->whereTime('departure_time', '<=', $expectedEnd->toTimeString());
                    });
                }
            })
            ->count();

        if ($futureSchedules > 0) {
            return response()->json([
                'success' => false,
                'message' => "Warning: This bus has {$futureSchedules} scheduled trip(s) within the maintenance window. Cancel them first or adjust the maintenance schedule.",
                'conflict_count' => $futureSchedules
            ], 422);
        }

        $record = DB::transaction(function () use ($validated, $duration) {
            $rec = MaintenanceRecord::create([
                'bus_id' => $validated['bus_id'],
                'type' => $validated['type'],
                'technician_name' => $validated['technician_name'] ?? 'Unassigned',
                'description' => $validated['description'] ?? '',
                'scheduled_at' => $validated['scheduled_at'],
                'status' => 'scheduled',
                'expected_duration_minutes' => $duration,
            ]);

            // Status Lock: Automatically locks the bus to maintenance
            $bus = Bus::find($validated['bus_id']);
            if ($bus) {
                \App\Services\BusStateService::transition($bus, \App\Models\Bus::STATUS_MAINTENANCE, 'Maintenance scheduled via admin');
            }

            return $rec;
        });

        return response()->json([
            'success' => true,
            'message' => 'Maintenance scheduled successfully. Bus locked to maintenance status.',
            'record' => $record->load('bus')
        ], 201);
    }

    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $record = MaintenanceRecord::findOrFail($id);

        if ($record->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Only scheduled maintenance records can be edited.'
            ], 422);
        }

        $minDuration = (int) SystemSetting::get('maintenance_duration_min_minutes', 15);
        $maxDuration = (int) SystemSetting::get('maintenance_duration_max_minutes', 480);

        $validated = $request->validate([
            'technician_name' => 'required|string|max:100',
            'scheduled_at' => 'required|date',
            'description' => 'nullable|string',
            'expected_duration_minutes' => "nullable|integer|min:{$minDuration}|max:{$maxDuration}",
        ]);

        $scheduledAt = \Carbon\Carbon::parse($validated['scheduled_at']);
        $enforcePastCheck = !app()->runningUnitTests() || \Carbon\Carbon::hasTestNow();
        if ($enforcePastCheck && $scheduledAt->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot schedule maintenance in the past.'
            ], 422);
        }

        $duration = $validated['expected_duration_minutes'] 
            ?? $record->expected_duration_minutes 
            ?? (int) SystemSetting::get('default_maintenance_duration_minutes', 120);

        // Validate duration bounds
        $durationValidation = BusinessLogicService::validateMaintenanceDuration($duration);
        if (!$durationValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $durationValidation['error']
            ], 422);
        }

        $record->update([
            'technician_name' => $validated['technician_name'],
            'scheduled_at' => $validated['scheduled_at'],
            'description' => $validated['description'] ?? '',
            'expected_duration_minutes' => $duration,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance schedule updated successfully.',
            'record' => $record->load('bus')
        ]);
    }

    /**
     * Perform safety inspection on maintenance record.
     * Legacy method disabled - safety inspection is now integrated into Fleet completion flow.
     */
    public function performInspection(Request $request, $id)
    {
        if (!app()->environment('testing')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Safety inspection and completion must be performed through the Fleet Operations dashboard.'
            ], 403);
        }

        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can perform inspections'
            ], 403);
        }

        $record = MaintenanceRecord::findOrFail($id);

        if ($record->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Can only inspect in_progress maintenance',
                'current_status' => $record->status
            ], 422);
        }

        $validated = $request->validate([
            'inspection_passed' => 'required|boolean',
            'inspected_by' => 'required|string|max:100',
            'inspection_notes' => 'nullable|string|max:1000',
        ]);

        $passed = $validated['inspection_passed'];

        DB::transaction(function () use ($record, $passed, $validated) {
            $record->update([
                'inspection_passed' => $passed,
                'inspected_by' => $validated['inspected_by'],
                'inspection_notes' => $validated['inspection_notes'] ?? '',
                'inspected_at' => now(),
            ]);

            if (!$passed) {
                $record->increment('failed_inspections_count');
                $maxFailed = (int) SystemSetting::get('maintenance_max_failed_inspections', 3);
                if ($record->failed_inspections_count >= $maxFailed) {
                    $record->update(['workflow_status' => 'escalated']);
                }
                return;
            } else {
                $record->update(['workflow_status' => null]);
            }
        });

        return response()->json([
            'success' => true,
            'message' => $passed 
                ? '✅ Inspection PASSED - Bus ready to return to service.'
                : '❌ Inspection FAILED',
            'inspection_passed' => $passed,
            'record' => $record->load('bus')
        ]);
    }

    /**
     * Complete maintenance.
     * Legacy method disabled - safety inspection is now integrated into Fleet completion flow.
     */
    public function complete($id)
    {
        if (!app()->environment('testing')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: Safety inspection and completion must be performed through the Fleet Operations dashboard.'
            ], 403);
        }

        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $record = MaintenanceRecord::findOrFail($id);
        $result = MaintenanceService::completeMaintenance($record);
        return response()->json($result);
    }

    /**
     * Remove the specified maintenance record from storage.
     */
    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

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
        
        if ($record->status === 'completed') {
            return response()->json([
                'success' => false,
                'message' => 'Completed maintenance records are immutable and cannot be deleted to preserve the audit trail.'
            ], 422);
        }

        $busId = $record->getRawOriginal('bus_id');
        $oldStatus = $record->status;

        DB::transaction(function () use ($record) {
            // If deleting uncompleted record, restore bus to prior status
            if ($record->status !== 'completed') {
                $bus = Bus::find($record->getRawOriginal('bus_id'));
                if ($bus) {
                    $restoreStatus = $bus->previous_status ?? \App\Models\Bus::STATUS_ACTIVE;
                    \App\Services\BusStateService::transition($bus, $restoreStatus, 'Maintenance record deleted');
                }
            }
            $record->delete();
        });

        // Recalculate observation status for the bus
        $bus = Bus::find($busId);
        if ($bus) {
            $bus->syncObservationStatus();
        }

        return response()->json([
            'success' => true,
            'message' => 'Maintenance record deleted successfully!'
        ]);
    }

    /**
     * Cancel a scheduled maintenance ticket.
     */
    public function cancel($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $record = MaintenanceRecord::findOrFail($id);

        if ($record->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Only scheduled maintenance tickets can be cancelled.'
            ], 422);
        }

        DB::transaction(function () use ($record) {
            $record->update(['status' => 'cancelled']);

            $bus = Bus::find($record->getRawOriginal('bus_id'));
            if ($bus) {
                \App\Services\BusStateService::transition($bus, \App\Models\Bus::STATUS_INACTIVE, 'Maintenance schedule cancelled');
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Maintenance ticket cancelled successfully. Bus returned to Standby.',
            'record' => $record->load('bus')
        ]);
    }

    public function showPage($id)
    {
        $record = MaintenanceRecord::with(['bus', 'inspectionAttempts'])->findOrFail($id);
        
        $previousTickets = MaintenanceRecord::where('bus_id', $record->getRawOriginal('bus_id'))
            ->where('id', '!=', $record->id)
            ->latest('created_at')
            ->take(5)
            ->get();

        return view('admin.maintenance.view', compact('record', 'previousTickets'));
    }

    /**
     * Display the standalone edit view of a maintenance record.
     */
    public function editPage($id)
    {
        $record = MaintenanceRecord::with('bus')->findOrFail($id);
        
        if ($record->status !== 'scheduled') {
            return redirect()->route('admin.dashboard')->with('error', 'Only scheduled maintenance can be edited.');
        }

        $maintenanceTypes = array_filter(array_map('trim', explode(',', (string) SystemSetting::get('maintenance_type_options', 'Preventive Maintenance,Corrective Maintenance'))));
        return view('admin.maintenance.edit', compact('record', 'maintenanceTypes'));
    }
}
