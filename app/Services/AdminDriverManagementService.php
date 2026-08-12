<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Route;
use App\Models\SystemSetting;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDriverManagementService
{
    /**
     * Build the Admin Driver Management payload from actual operational records.
     *
     * @return array{drivers: array<int, array<string, mixed>>, stats: array<string, int>}
     */
    public function build(): array
    {
        $drivers = Driver::query()->orderByDesc('created_at')->get();

        if ($drivers->isEmpty()) {
            return [
                'drivers' => [],
                'stats' => $this->emptyStats(),
            ];
        }

        $driverIds = $drivers->pluck('id');
        $todayStart = now('Asia/Manila')->startOfDay()->utc();
        $todayEnd = now('Asia/Manila')->endOfDay()->utc();
        $incidentStart = now('Asia/Manila')->subDays(30)->startOfDay()->utc();
        $licenseWarningDays = (int) SystemSetting::get('license_expiry_warning_threshold_days', 30);
        $licenseWarningDate = now('Asia/Manila')->startOfDay()->addDays($licenseWarningDays)->toDateString();
        $officialRouteIds = Route::query()
            ->publicCommuterActiveService()
            ->pluck('id');

        $tripsByDriver = Trip::query()
            ->with([
                'bus:id,plate_number,passengers,status',
                'route:id,name,color',
            ])
            ->whereIn('driver_id', $driverIds)
            ->orderByDesc('id')
            ->get()
            ->groupBy('driver_id');

        $activeTripsByDriver = $tripsByDriver->map(function (Collection $trips) {
            return $trips
                ->whereIn('status', ['ongoing', 'dispatched'])
                ->sortBy(fn (Trip $trip) => $trip->status === 'ongoing' ? 0 : 1)
                ->first();
        });

        $boardedTodayByDriver = TripPassengerEvent::query()
            ->whereIn('driver_id', $driverIds)
            ->where('event_type', TripPassengerEvent::TYPE_BOARDED)
            ->whereBetween('recorded_at', [$todayStart, $todayEnd])
            ->select('driver_id', DB::raw('SUM(passenger_delta) as total'))
            ->groupBy('driver_id')
            ->pluck('total', 'driver_id');

        $tripIds = $tripsByDriver->flatten(1)->pluck('id');
        $boardedByTrip = $tripIds->isEmpty()
            ? collect()
            : TripPassengerEvent::query()
                ->whereIn('trip_id', $tripIds)
                ->where('event_type', TripPassengerEvent::TYPE_BOARDED)
                ->select('trip_id', DB::raw('SUM(passenger_delta) as total'))
                ->groupBy('trip_id')
                ->pluck('total', 'trip_id');

        $incidentsByDriver = Incident::query()
            ->whereIn('driver_id', $driverIds)
            ->whereBetween('reported_at', [$incidentStart, $todayEnd])
            ->get()
            ->groupBy('driver_id');

        $todayOperationalIncidentsByDriver = Incident::query()
            ->whereIn('driver_id', $driverIds)
            ->whereHas('trip.route', fn ($query) => $query->publicCommuterActiveService())
            ->where(function ($query) use ($todayStart, $todayEnd) {
                $query->whereBetween('reported_at', [$todayStart, $todayEnd])
                    ->orWhere(function ($fallback) use ($todayStart, $todayEnd) {
                        $fallback->whereNull('reported_at')
                            ->whereBetween('created_at', [$todayStart, $todayEnd]);
                    });
            })
            ->get()
            ->groupBy('driver_id');

        $busesByPlate = Bus::query()
            ->whereIn('plate_number', $drivers->pluck('assigned_bus')->filter()->unique())
            ->get()
            ->keyBy('plate_number');

        $routes = Route::query()->get(['id', 'name', 'color']);
        $routesById = $routes->keyBy('id');
        $routesByName = $routes->keyBy('name');

        $payload = $drivers->map(function (Driver $driver) use (
            $activeTripsByDriver,
            $boardedByTrip,
            $boardedTodayByDriver,
            $busesByPlate,
            $incidentsByDriver,
            $officialRouteIds,
            $routesById,
            $routesByName,
            $todayStart,
            $todayEnd,
            $todayOperationalIncidentsByDriver,
            $tripsByDriver,
        ) {
            /** @var Collection<int, Trip> $driverTrips */
            $driverTrips = $tripsByDriver->get($driver->id, collect());
            /** @var Trip|null $activeTrip */
            $activeTrip = $activeTripsByDriver->get($driver->id);

            $officialTripsToday = $driverTrips->filter(function (Trip $trip) use ($officialRouteIds, $todayStart, $todayEnd) {
                if (! $officialRouteIds->contains($trip->route_id)) {
                    return false;
                }

                return ($trip->status === 'completed' && $this->isWithin($trip->ended_at, $todayStart, $todayEnd))
                    || ($trip->status === 'ongoing' && $this->isWithin($trip->started_at, $todayStart, $todayEnd));
            });
            $completedToday = $officialTripsToday->where('status', 'completed')->count();
            $ongoingToday = $officialTripsToday->where('status', 'ongoing')->count();
            $operationalTripsToday = $completedToday + $ongoingToday;
            $todayOperationalIncidents = $todayOperationalIncidentsByDriver->get($driver->id, collect());
            $qualifyingIncidentsToday = $todayOperationalIncidents
                ->filter(fn (Incident $incident) => Incident::isBreakdown($incident->type)
                    || Incident::isAccident($incident->type))
                ->count();
            $operationalScoreToday = DriverPerformanceService::calculateOperationalScore(
                $operationalTripsToday,
                $qualifyingIncidentsToday,
            );

            $assignedBus = $activeTrip?->bus
                ?? $busesByPlate->get(trim((string) $driver->assigned_bus));
            $assignedRoute = $activeTrip?->route
                ?? $this->resolveRoute($driver->assigned_route, $routesById, $routesByName);
            $assignmentIsConsistent = $assignedBus !== null && $assignedRoute !== null;

            if (! $activeTrip && ! $assignmentIsConsistent) {
                $assignedBus = null;
                $assignedRoute = null;
            }

            $eligibility = CentralDispatchEligibilityService::driver($driver);
            [$operationalKey, $operationalLabel] = $this->operationalState(
                $driver,
                $activeTrip,
                $assignmentIsConsistent,
                (bool) $eligibility['eligible'],
            );

            $tripHistory = $driverTrips
                ->take(10)
                ->map(function (Trip $trip) use ($boardedByTrip) {
                    $recordedAt = $trip->ended_at
                        ?? $trip->started_at
                        ?? $trip->dispatched_at
                        ?? $trip->created_at;

                    return [
                        'trip_no' => 'TRIP-' . str_pad((string) $trip->id, 4, '0', STR_PAD_LEFT),
                        'date' => $recordedAt?->copy()->timezone('Asia/Manila')->format('M j, Y g:i A') ?? 'No timestamp',
                        'bus' => $trip->bus?->plate_number ?? 'Unassigned',
                        'route' => $trip->route?->name ?? 'No route',
                        'status' => ucfirst((string) $trip->status),
                        'recorded_boarded' => (int) ($boardedByTrip->get($trip->id) ?? 0),
                    ];
                })
                ->values()
                ->all();

            $incidentCount = $incidentsByDriver->get($driver->id, collect())->count();

            return [
                'id' => $driver->id,
                'first_name' => $driver->first_name,
                'last_name' => $driver->last_name,
                'emp_id' => $driver->emp_id,
                'license_number' => $driver->license_number,
                'license_expiry' => $driver->license_expiry?->toDateString(),
                'status' => $driver->status,
                'employment_status' => $driver->status,
                'employment_label' => ucfirst((string) $driver->status),
                'stored_operational_status' => $driver->operational_status,
                'operational_status' => $operationalKey,
                'operational_label' => $operationalLabel,
                'assigned_bus' => $assignedBus?->plate_number,
                'assigned_route' => $assignedRoute?->id,
                'assigned_route_name' => $assignedRoute?->name,
                'assignment_is_consistent' => $assignmentIsConsistent,
                'has_active_trip' => $activeTrip !== null,
                'active_trip' => $activeTrip ? [
                    'id' => $activeTrip->id,
                    'trip_no' => 'TRIP-' . str_pad((string) $activeTrip->id, 4, '0', STR_PAD_LEFT),
                    'status' => ucfirst((string) $activeTrip->status),
                    'bus' => $activeTrip->bus?->plate_number ?? 'Unassigned',
                    'route' => $activeTrip->route?->name ?? 'No route',
                    'onboard_passengers' => $activeTrip->status === 'ongoing'
                        ? (int) ($activeTrip->bus?->passengers ?? 0)
                        : null,
                ] : null,
                'trips_today' => $operationalTripsToday,
                'completed_trips_today' => $completedToday,
                'completed_trips_total' => $driverTrips->where('status', 'completed')->count(),
                'pax_today' => (int) ($boardedTodayByDriver->get($driver->id) ?? 0),
                'address' => $driver->address,
                'contact_number' => $driver->contact_number,
                'emergency_contact' => $driver->emergency_contact,
                'performance_score' => $operationalScoreToday,
                'performance_score_basis' => 'actual_operations_today',
                'performance_score_trips_run' => $operationalTripsToday,
                'performance_score_qualifying_incidents' => $qualifyingIncidentsToday,
                'incidents_30' => $incidentCount,
                'trip_history' => $tripHistory,
                'trip_history_total' => $driverTrips->count(),
                'dispatch_eligible' => (bool) $eligibility['eligible'],
                'dispatch_reason' => $eligibility['reason'],
            ];
        })->values();

        return [
            'drivers' => $payload->all(),
            'stats' => [
                'driving' => $payload->where('operational_status', 'driving')->count(),
                'available' => $payload->where('operational_status', 'available')->count(),
                'assigned' => $payload->where('operational_status', 'assigned')->count(),
                'suspended' => $payload->where('operational_status', 'suspended')->count(),
                'unavailable' => $payload->whereIn('operational_status', ['unavailable', 'off-duty'])->count(),
                'completed_today' => $payload->sum('completed_trips_today'),
                // Backward-compatible keys for any older consumers.
                'on_duty' => $payload->where('operational_status', 'driving')->count(),
                'off_duty' => $payload->where('operational_status', 'off-duty')->count(),
                'expiring' => $drivers->filter(function (Driver $driver) use ($licenseWarningDate) {
                    if (! $driver->license_expiry) {
                        return false;
                    }

                    return $driver->license_expiry->toDateString() <= $licenseWarningDate;
                })->count(),
            ],
        ];
    }

    private function isWithin(?CarbonInterface $timestamp, CarbonInterface $start, CarbonInterface $end): bool
    {
        return $timestamp !== null && $timestamp->betweenIncluded($start, $end);
    }

    private function resolveRoute(mixed $assignedRoute, Collection $routesById, Collection $routesByName): ?Route
    {
        $value = trim((string) $assignedRoute);

        if ($value === '') {
            return null;
        }

        return is_numeric($value)
            ? $routesById->get((int) $value)
            : $routesByName->get($value);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function operationalState(
        Driver $driver,
        ?Trip $activeTrip,
        bool $hasConsistentAssignment,
        bool $dispatchEligible,
    ): array {
        if ($driver->status === 'suspended') {
            return ['suspended', 'Suspended'];
        }

        if ($driver->status !== 'active') {
            return ['off-duty', 'Off Duty'];
        }

        if ($activeTrip?->status === 'ongoing') {
            return ['driving', 'Driving'];
        }

        if ($activeTrip?->status === 'dispatched' || $hasConsistentAssignment) {
            return ['assigned', 'Assigned'];
        }

        if ($dispatchEligible) {
            return ['available', 'Available'];
        }

        return ['unavailable', 'Unavailable'];
    }

    /**
     * @return array<string, int>
     */
    private function emptyStats(): array
    {
        return [
            'driving' => 0,
            'available' => 0,
            'assigned' => 0,
            'suspended' => 0,
            'unavailable' => 0,
            'completed_today' => 0,
            'on_duty' => 0,
            'off_duty' => 0,
            'expiring' => 0,
        ];
    }
}
