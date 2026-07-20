<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Incident;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Bus;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class IncidentController extends Controller
{
    /**
     * Display the Incidents view.
     */
    public function index(Request $request)
    {
        $dateStart = $request->input('date_start', now()->startOfMonth()->format('Y-m-d'));
        $dateEnd = $request->input('date_end', now()->endOfMonth()->format('Y-m-d'));
        $routeFilter = $request->input('route_filter', 'all');
        $typeFilter = $request->input('type_filter', 'all');
        $statusFilter = $request->input('status_filter', 'all');
        $activeSort = $request->input('active_sort', 'newest');

        $routes = Route::orderBy('id')->get(['id', 'name']);
        $ongoingTrips = Trip::where('status', 'ongoing')->with(['bus', 'driver', 'route'])->get();

        $metrics = $this->getIncidentMetrics();

        // Get active and resolved incidents using internal helper
        $activeIncidents = $this->getFilteredIncidents('active', $dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter, $activeSort);
        $resolvedIncidents = $this->getFilteredIncidents('resolved', $dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter, $activeSort);

        return view('fleet.incidents.index', [
            'dateStart' => $dateStart,
            'dateEnd' => $dateEnd,
            'routeFilter' => $routeFilter,
            'typeFilter' => $typeFilter,
            'statusFilter' => $statusFilter,
            'activeSort' => $activeSort,
            'routes' => $routes,
            'ongoingTrips' => $ongoingTrips,
            'incidentMetrics' => $metrics,
            'activeIncidents' => $activeIncidents,
            'resolvedIncidents' => $resolvedIncidents,
        ]);
    }

    /**
     * Get JSON data for frontend AJAX refreshing.
     */
    public function getIncidentsData(Request $request)
    {
        $dateStart = $request->input('date_start', now()->startOfMonth()->format('Y-m-d'));
        $dateEnd = $request->input('date_end', now()->endOfMonth()->format('Y-m-d'));
        $routeFilter = $request->input('route_filter', 'all');
        $typeFilter = $request->input('type_filter', 'all');
        $statusFilter = $request->input('status_filter', 'all');
        $activeSort = $request->input('active_sort', 'newest');

        $activeIncidents = $this->getFilteredIncidents('active', $dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter, $activeSort);
        $resolvedIncidents = $this->getFilteredIncidents('resolved', $dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter, $activeSort);
        $metrics = $this->getIncidentMetrics();

        return response()->json([
            'activeIncidents' => $activeIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'incidentMetrics' => $metrics,
        ]);
    }

    /**
     * Get ongoing trip details for auto-fill form properties.
     */
    public function getTripDetails($id)
    {
        $trip = Trip::with(['bus', 'driver', 'route'])->find($id);
        if (!$trip) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }

        return response()->json([
            'success' => true,
            'bus_plate' => $trip->bus ? $trip->bus->plate_number : 'N/A',
            'driver_name' => $trip->driver ? "{$trip->driver->first_name} {$trip->driver->last_name}" : 'N/A',
            'route_name' => $trip->route ? $trip->route->name : 'N/A',
        ]);
    }

    /**
     * Log a new incident record.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'trip_id' => 'required|exists:trips,id',
            'type' => ['required', 'string', \Illuminate\Validation\Rule::in(\App\Models\Incident::getTypes())],
            'description' => 'required|string|min:5',
            'status' => 'required|in:reported,under_review,resolved',
        ]);

        $trip = Trip::find($validated['trip_id']);
        
        $incident = Incident::create([
            'trip_id' => $validated['trip_id'],
            'driver_id' => $trip->driver_id,
            'type' => $validated['type'],
            'description' => $validated['description'],
            'status' => $validated['status'],
            'reported_at' => now(),
        ]);

        // If the incident type matches Breakdown or Accident, flag bus for breakdown status
        if (Incident::isBreakdown($validated['type']) || Incident::isAccident($validated['type'])) {
            if ($trip && $trip->bus) {
                \App\Services\BusStateService::transition($trip->bus, \App\Models\Bus::STATUS_BREAKDOWN, 'Incident Report: ' . $validated['type']);
            }
        } else {
            // For other incidents, create an informational audit log without status change
            if ($trip && $trip->bus) {
                $driver = \App\Models\Driver::find($trip->driver_id);
                \App\Models\BusStatusAuditLog::create([
                    'bus_id'     => $trip->bus->id,
                    'old_status' => $trip->bus->status,
                    'new_status' => $trip->bus->status,
                    'reason'     => 'Incident Report: ' . $validated['type'],
                    'changed_by' => $driver ? $driver->user_id : auth()->id(),
                ]);
            }
        }

        // Recalculate driver performance score
        \App\Services\DriverPerformanceService::recalculate($trip->driver_id);

        return response()->json([
            'success' => true,
            'message' => 'Incident logged successfully.',
            'incident' => $incident
        ]);
    }

    /**
     * Update incident status in DB.
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:reported,under_review,resolved',
        ]);

        $incident = Incident::with(['trip.bus'])->find($id);
        if (!$incident) {
            return response()->json(['success' => false, 'message' => 'Incident not found.'], 404);
        }

        $oldType = $incident->type;
        $oldStatus = $incident->status;
        $newStatus = $validated['status'];

        $incident->update(['status' => $newStatus]);

        return response()->json([
            'success' => true,
            'message' => 'Incident status updated successfully.'
        ]);
    }

    /**
     * Delete an incident record.
     */
    public function destroy($id)
    {
        $incident = Incident::find($id);
        if (!$incident) {
            return response()->json(['success' => false, 'message' => 'Incident not found.'], 404);
        }

        $incident->delete();

        return response()->json([
            'success' => true,
            'message' => 'Incident deleted successfully.'
        ]);
    }

    /**
     * Export incidents report as CSV.
     */
    public function exportCsv(Request $request)
    {
        $dateStart = $request->input('date_start');
        $dateEnd = $request->input('date_end');
        $routeFilter = $request->input('route_filter', 'all');
        $typeFilter = $request->input('type_filter', 'all');
        $statusFilter = $request->input('status_filter', 'all');

        $incidents = $this->getFilteredIncidentsQuery($dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter)->get();

        $filename = 'incidents_report_' . now()->format('YmdHis') . '.csv';

        $rows = [];
        $rows[] = ['Incident ID', 'Type', 'Description', 'Bus Plate', 'Driver Name', 'Route Name', 'Status', 'Reported At'];

        foreach ($incidents as $inc) {
            $busPlate = ($inc->trip && $inc->trip->bus) ? $inc->trip->bus->plate_number : 'N/A';
            $driverName = $inc->driver ? "{$inc->driver->first_name} {$inc->driver->last_name}" : 'N/A';
            $routeName = ($inc->trip && $inc->trip->route) ? $inc->trip->route->name : 'N/A';

            $rows[] = [
                $inc->incident_id ?? ('INC-' . str_pad($inc->id, 4, '0', STR_PAD_LEFT)),
                $inc->type,
                $inc->description,
                $busPlate,
                $driverName,
                $routeName,
                $inc->status,
                Carbon::parse($inc->reported_at)->timezone('Asia/Manila')->format('Y-m-d H:i:s'),
            ];
        }

        $csvContent = implode("\n", array_map(function ($row) {
            return implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', $row));
        }, $rows));

        return response()->streamDownload(function () use ($csvContent) {
            echo $csvContent;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Helper: Build filtered query
     */
    protected function getFilteredIncidentsQuery($dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter)
    {
        $query = Incident::with(['trip.bus', 'trip.route', 'driver']);

        if ($dateStart) {
            $query->where('reported_at', '>=', Carbon::parse($dateStart)->startOfDay());
        }
        if ($dateEnd) {
            $query->where('reported_at', '<=', Carbon::parse($dateEnd)->endOfDay());
        }

        if ($routeFilter !== 'all') {
            $query->whereHas('trip.route', function ($q) use ($routeFilter) {
                $q->where('name', $routeFilter);
            });
        }

        if ($typeFilter !== 'all') {
            $query->where('type', $typeFilter);
        }

        return $query;
    }

    /**
     * Helper: get incidents list based on group (active/resolved)
     */
    public function getFilteredIncidents($statusGroup, $dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter, $activeSort)
    {
        $query = $this->getFilteredIncidentsQuery($dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter);

        if ($statusGroup === 'active') {
            $query->whereIn('status', ['reported', 'under_review']);
            
            if ($activeSort === 'priority') {
                $breakdownType = Incident::getBreakdownType();
                $accidentType = Incident::getAccidentType();
                $query->orderByRaw("CASE WHEN type IN (?, ?) THEN 0 ELSE 1 END", [$accidentType, $breakdownType])
                      ->orderByRaw("CASE WHEN status = 'reported' THEN 0 ELSE 1 END")
                      ->orderByDesc('reported_at');
            } else {
                $query->orderByDesc('reported_at');
            }
        } elseif ($statusGroup === 'resolved') {
            $query->where('status', 'resolved')->orderByDesc('reported_at');
        }

        if ($statusFilter !== 'all') {
            $statusMap = [
                'Open' => 'reported',
                'Under Investigation' => 'under_review',
                'Resolved' => 'resolved'
            ];
            $dbStatus = $statusMap[$statusFilter] ?? null;
            if ($dbStatus) {
                $query->where('status', $dbStatus);
            }
        }

        // Transform collection to match properties of Volt components for easy transition
        return $query->get()->map(function($inc) {
            $busPlate = ($inc->trip && $inc->trip->bus) ? $inc->trip->bus->plate_number : 'N/A';
            $driverName = $inc->driver ? "{$inc->driver->first_name} {$inc->driver->last_name}" : 'N/A';
            $routeName = ($inc->trip && $inc->trip->route) ? $inc->trip->route->name : 'N/A';
            $incidentIdStr = $inc->incident_id ?? ('INC-' . str_pad($inc->id, 4, '0', STR_PAD_LEFT));

            return (object) [
                'id' => $inc->id,
                'incident_id' => $incidentIdStr,
                'title' => trim($busPlate) . ' — ' . $inc->type . ': ' . $inc->description,
                'type' => $inc->type,
                'description' => $inc->description,
                'bus_plate' => $busPlate,
                'driver_name' => $driverName,
                'route_name' => $routeName,
                'status' => $inc->status,
                'reported_at' => Carbon::parse($inc->reported_at),
                'updated_at' => Carbon::parse($inc->updated_at),
            ];
        });
    }

    /**
     * Compute metrics
     */
    public function getIncidentMetrics()
    {
        $today = Carbon::today();
        
        $totalToday = Incident::whereDate('reported_at', $today)->count();
        $open = Incident::where('status', 'reported')->count();
        $underReview = Incident::where('status', 'under_review')->count();
        $resolvedToday = Incident::where('status', 'resolved')
            ->whereDate('updated_at', $today)
            ->count();

        // Calculate average resolution time
        $resolvedIncidents = Incident::where('status', 'resolved')
            ->whereNotNull('reported_at')
            ->whereNotNull('updated_at')
            ->get();
        
        $avgMinutes = 0;
        if ($resolvedIncidents->count() > 0) {
            $totalMinutes = $resolvedIncidents->sum(function ($inc) {
                return Carbon::parse($inc->updated_at)->diffInMinutes(Carbon::parse($inc->reported_at));
            });
            $avgMinutes = round($totalMinutes / $resolvedIncidents->count());
        }

        return (object) [
            'total_today' => $totalToday,
            'open' => $open,
            'under_investigation' => $underReview,
            'resolved_today' => $resolvedToday,
            'avg_resolution_minutes' => $avgMinutes,
        ];
    }
}
