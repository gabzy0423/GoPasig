<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Incident;
use App\Models\Trip;
use App\Models\Route;
use App\Services\IncidentWorkflowService;
use Carbon\Carbon;

class IncidentController extends Controller
{
    public function __construct(protected IncidentWorkflowService $incidentWorkflow) {}

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

        $routes = Route::publicCommuterActiveService()->get(['id', 'name']);
        $ongoingTrips = $this->incidentWorkflow->eligibleOngoingTripsQuery()
            ->with(['bus', 'driver', 'route'])
            ->get();

        // Get active and resolved incidents using internal helper
        $activeIncidents = $this->getFilteredIncidents('active', $dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter, $activeSort);
        $resolvedIncidents = $this->getFilteredIncidents('resolved', $dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter, $activeSort);
        $metrics = $this->getIncidentMetrics($dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter);

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
        $metrics = $this->getIncidentMetrics($dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter);
        $ongoingTrips = $this->incidentWorkflow->eligibleOngoingTripsQuery()
            ->with(['bus', 'driver', 'route'])
            ->get()
            ->map(fn (Trip $trip) => [
                'id' => $trip->id,
                'bus_plate' => $trip->bus?->plate_number ?? 'Unknown Bus',
                'driver_name' => trim(($trip->driver?->first_name ?? '').' '.($trip->driver?->last_name ?? '')) ?: 'Unknown Driver',
                'route_name' => $trip->route?->name ?? 'Unknown Route',
                'direction' => $trip->direction,
            ])
            ->values();

        return response()->json([
            'activeIncidents' => $activeIncidents,
            'resolvedIncidents' => $resolvedIncidents,
            'incidentMetrics' => $metrics,
            'ongoingTrips' => $ongoingTrips,
        ]);
    }

    /**
     * Get ongoing trip details for auto-fill form properties.
     */
    public function getTripDetails($id)
    {
        $trip = $this->incidentWorkflow->eligibleOngoingTripsQuery()
            ->with(['bus', 'driver', 'route'])
            ->find($id);
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
            'type' => ['required', 'string', \Illuminate\Validation\Rule::in(Incident::getTypes())],
            'description' => 'required|string|min:5|max:2000',
        ]);

        try {
            $incident = $this->incidentWorkflow->reportForTrip(
                (int) $validated['trip_id'],
                $validated['type'],
                $validated['description'],
                'incident reports',
                auth()->id()
            );
        } catch (\DomainException|\App\Exceptions\InvalidStatusTransitionException $exception) {
            return response()->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

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

        try {
            $this->incidentWorkflow->updateStatus(
                (int) $id,
                $validated['status'],
                'incident reports',
                auth()->id()
            );
        } catch (\DomainException $exception) {
            $status = $exception->getMessage() === 'Incident record not found.' ? 404 : 422;

            return response()->json(['success' => false, 'message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'success' => true,
            'message' => 'Incident status updated successfully.'
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

        $incidents = $this->getFilteredIncidentsQuery($dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter)
            ->orderByDesc('reported_at')
            ->get();

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
        $query = Incident::with(['trip.bus', 'trip.route', 'driver'])
            ->whereHas('trip.route', fn ($routeQuery) => $routeQuery->publicCommuterActiveService());

        if ($dateStart) {
            $query->where('reported_at', '>=', Carbon::parse($dateStart, 'Asia/Manila')->startOfDay()->utc());
        }
        if ($dateEnd) {
            $query->where('reported_at', '<=', Carbon::parse($dateEnd, 'Asia/Manila')->endOfDay()->utc());
        }

        if ($routeFilter !== 'all') {
            $query->whereHas('trip.route', function ($q) use ($routeFilter) {
                if (is_numeric($routeFilter)) {
                    $q->whereKey((int) $routeFilter);
                    return;
                }

                $q->where('name', $routeFilter);
            });
        }

        if ($typeFilter !== 'all') {
            $query->where('type', $typeFilter);
        }

        if ($statusFilter !== 'all') {
            $statusMap = [
                'Open' => 'reported',
                'Under Investigation' => 'under_review',
                'Resolved' => 'resolved'
            ];
            $dbStatus = $statusMap[$statusFilter] ?? $statusFilter;
            if (in_array($dbStatus, ['reported', 'under_review', 'resolved'], true)) {
                $query->where('status', $dbStatus);
            }
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

        // Transform collection to match properties of Volt components for easy transition
        return $query->get()->map(function($inc) {
            $busPlate = ($inc->trip && $inc->trip->bus) ? $inc->trip->bus->plate_number : 'N/A';
            $driverName = $inc->driver ? "{$inc->driver->first_name} {$inc->driver->last_name}" : 'N/A';
            $routeName = ($inc->trip && $inc->trip->route) ? $inc->trip->route->name : 'N/A';
            $incidentIdStr = $inc->incident_id ?? ('INC-' . str_pad($inc->id, 4, '0', STR_PAD_LEFT));

            return (object) [
                'id' => $inc->id,
                'incident_id' => $incidentIdStr,
                'title' => trim($busPlate) . ' - ' . $inc->type . ': ' . $inc->description,
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
    public function getIncidentMetrics($dateStart = null, $dateEnd = null, $routeFilter = 'all', $typeFilter = 'all', $statusFilter = 'all')
    {
        $filteredIncidents = $this->getFilteredIncidentsQuery($dateStart, $dateEnd, $routeFilter, $typeFilter, $statusFilter)->get();

        $totalPeriod = $filteredIncidents->count();
        $open = $filteredIncidents->where('status', 'reported')->count();
        $underReview = $filteredIncidents->where('status', 'under_review')->count();
        $resolvedPeriod = $filteredIncidents->where('status', 'resolved')->count();

        // Calculate average resolution time
        $resolvedIncidents = $filteredIncidents
            ->where('status', 'resolved')
            ->filter(fn (Incident $incident) => $incident->reported_at !== null && $incident->updated_at !== null);
        
        $avgMinutes = 0;
        if ($resolvedIncidents->count() > 0) {
            $totalMinutes = $resolvedIncidents->sum(function ($inc) {
                return Carbon::parse($inc->reported_at)->diffInMinutes(Carbon::parse($inc->updated_at), true);
            });
            $avgMinutes = round($totalMinutes / $resolvedIncidents->count());
        }

        return (object) [
            'total_today' => $totalPeriod,
            'open' => $open,
            'under_investigation' => $underReview,
            'resolved_today' => $resolvedPeriod,
            'avg_resolution_minutes' => $avgMinutes,
        ];
    }
}
