<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MaintenanceRecord;
use App\Models\Bus;
use App\Models\Route;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class MaintenanceManagementController extends Controller
{
    /**
     * Display the Maintenance dashboard.
     */
    public function index(Request $request)
    {
        $logTypeFilter = $request->input('type', 'all');
        $logStatusFilter = $request->input('status', 'all');

        $maintenanceSummary = $this->getMaintenanceSummary();
        $busHealth = $this->getBusHealth();
        $upcomingSchedule = $this->getUpcomingSchedule();
        $maintenanceLogs = $this->getFilteredLogsQuery($logTypeFilter, $logStatusFilter)->paginate(15);

        return view('fleet.maintenance.index', compact(
            'maintenanceSummary',
            'busHealth',
            'upcomingSchedule',
            'maintenanceLogs',
            'logTypeFilter',
            'logStatusFilter'
        ));
    }

    /**
     * Get JSON data for maintenance dashboard AJAX refreshing.
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
                'assigned_route' => $row->assigned_route ?: '—',
                'type' => $row->type,
                'description' => $row->description,
                'technician_name' => $row->technician_name ?: '—',
                'cost_php' => number_format((float)$row->cost_php, 2),
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

        $rules = [
            'bus_id' => 'required|exists:buses,id',
            'type' => 'required|in:Preventive,Corrective,Inspection',
            'description' => 'required|string|min:5',
            'scheduled_at' => 'required|date',
            'technician_name' => 'nullable|string|max:100',
            'cost_php' => 'nullable|numeric|min:0',
            'status' => 'required|in:scheduled,in_progress,completed,cancelled',
        ];

        $validated = $request->validate($rules);

        $data = [
            'bus_id' => $validated['bus_id'],
            'type' => $validated['type'],
            'description' => $validated['description'],
            'scheduled_at' => Carbon::parse($validated['scheduled_at']),
            'technician_name' => $validated['technician_name'] ?: null,
            'cost_php' => $validated['cost_php'] !== null ? floatval($validated['cost_php']) : 0.00,
            'status' => $validated['status'],
        ];

        if ($isEditing) {
            $record = MaintenanceRecord::findOrFail($id);
            $oldStatus = $record->status;
            $record->update($data);

            $this->handleBusStatusSideEffects($record->getRawOriginal('bus_id'), $oldStatus, $validated['status'], $id);
            $msg = 'Maintenance schedule updated successfully.';
        } else {
            $record = MaintenanceRecord::create($data);
            $this->handleBusStatusSideEffects($record->getRawOriginal('bus_id'), null, $validated['status']);
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
        $validated = $request->validate([
            'status' => 'required|in:scheduled,in_progress,completed,cancelled'
        ]);

        $record = MaintenanceRecord::findOrFail($id);
        $oldStatus = $record->status;
        $status = $validated['status'];

        $record->update(['status' => $status]);
        $this->handleBusStatusSideEffects($record->getRawOriginal('bus_id'), $oldStatus, $status, $id);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance status updated.'
        ]);
    }

    /**
     * Delete maintenance record.
     */
    public function destroy($id)
    {
        $record = MaintenanceRecord::findOrFail($id);
        $busId = $record->getRawOriginal('bus_id');
        $oldStatus = $record->status;
        
        $record->delete();

        // Revert bus status if deleted record was in_progress
        if ($oldStatus === 'in_progress') {
            $this->handleBusStatusSideEffects($busId, $oldStatus, 'cancelled', $id);
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

        $csvHeader = ['Date', 'Bus Plate', 'Route', 'Type', 'Description', 'Technician', 'Cost (PHP)', 'Status'];
        $csvData = [];
        $csvData[] = implode(',', $csvHeader);

        foreach ($records as $row) {
            $csvRow = [
                $row->scheduled_at->timezone('Asia/Manila')->format('Y-m-d H:i'),
                $row->bus_id, // plate number through accessor
                $row->assigned_route ?: 'All Routes',
                $row->type,
                '"' . str_replace('"', '""', $row->description) . '"',
                $row->technician_name ?: '—',
                $row->cost_php,
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
        $breakdownCount = Bus::where('status', 'inactive')->count();

        // Due for service within 7 days
        $dueForService = MaintenanceRecord::where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now(), now()->addDays(7)])
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
     */
    public function getBusHealth()
    {
        $palette = [
            '#378ADD', // Route 1 → blue
            '#639922', // Route 2 → green
            '#BA7517', // Route 3 → amber
            '#E24B4A', // Route 4 → red
            '#6B21A8', // Route 5 → purple
            '#0F6E56', // Route 6 → teal
            '#D97706', // Route 7 → orange
            '#0891B2', // Route 8 → cyan
        ];

        return Bus::with('route')->get()->map(function ($bus) use ($palette) {
            $routeColor = '#888780'; // default: unassigned
            if ($bus->route) {
                $routeColor = $palette[($bus->route->id - 1) % count($palette)];
            }

            return (object) [
                'id'             => $bus->id,
                'bus_id'         => $bus->plate_number,
                'assigned_route' => $bus->route ? $bus->route->name : null,
                'route_color'    => $routeColor,
                'status'         => $bus->status,
            ];
        });
    }

    /**
     * Get upcoming schedule for next 30 days.
     */
    public function getUpcomingSchedule()
    {
        return MaintenanceRecord::with('bus')
            ->where('status', 'scheduled')
            ->whereBetween('scheduled_at', [now(), now()->addDays(30)])
            ->orderBy('scheduled_at')
            ->get()
            ->map(function ($row) {
                return (object) [
                    'id' => $row->id,
                    'scheduled_date' => $row->scheduled_at,
                    'bus_id' => $row->bus_id, // plate number through accessor
                    'description' => $row->type . ' — ' . $row->description,
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

    /**
     * Handle transitions in bus status.
     */
    public function handleBusStatusSideEffects($busId, $oldStatus, $newStatus, $editingId = null)
    {
        $bus = Bus::find($busId);
        if (!$bus) return;

        if ($newStatus === 'in_progress') {
            $bus->update(['status' => 'maintenance']);
        } elseif ($newStatus === 'completed') {
            $bus->update(['status' => 'active']);
        } elseif ($newStatus === 'cancelled' || $newStatus === 'scheduled') {
            $query = MaintenanceRecord::where('bus_id', $busId)
                ->whereIn('status', ['in_progress']);
            if ($editingId) {
                $query->where('id', '!=', $editingId);
            }
            $hasOtherActive = $query->exists();
            
            if (!$hasOtherActive) {
                $bus->update(['status' => 'active']);
            }
        }
    }
}
