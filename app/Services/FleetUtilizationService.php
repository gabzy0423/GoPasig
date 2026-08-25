<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Route;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class FleetUtilizationService
{
    private const OPERATIONAL_TRIP_STATUSES = ['completed', 'ongoing'];

    public function snapshot(?CarbonInterface $at = null): array
    {
        $now = $at ? Carbon::instance($at)->setTimezone('Asia/Manila') : Carbon::now('Asia/Manila');
        $todayStart = $now->copy()->startOfDay();
        $todayEnd = $now->copy()->endOfDay();
        $chartStart = $todayStart->copy()->subDays(29);

        $officialRoutes = Route::publicCommuterActiveService()->get();
        $officialRouteIds = $officialRoutes->modelKeys();
        $routeMap = $officialRoutes->keyBy('id');
        $buses = Bus::with('route')->orderBy('plate_number')->get();
        $busIds = $buses->modelKeys();

        $todayTrips = $this->operationalTrips($officialRouteIds, $todayStart, $todayEnd)
            ->get();
        $activityTrips = $this->activityTrips($officialRouteIds, $chartStart, $todayEnd)
            ->get();
        $latestActivityTrips = $this->latestActivityTrips($officialRouteIds)->get();
        $passengerEvents = TripPassengerEvent::query()
            ->whereIn('route_id', $officialRouteIds)
            ->whereIn('bus_id', $busIds)
            ->whereBetween('recorded_at', [$todayStart, $todayEnd])
            ->get(['bus_id', 'event_type', 'passenger_delta']);

        $todayTripsByBus = $todayTrips->groupBy('bus_id');
        $activityTripsByBus = $activityTrips->groupBy('bus_id');
        $latestActivityTripsByBus = $latestActivityTrips->groupBy('bus_id');
        $eventsByBus = $passengerEvents->groupBy('bus_id');

        $busCards = $buses->map(function (Bus $bus) use ($todayTripsByBus, $activityTripsByBus, $latestActivityTripsByBus, $eventsByBus, $routeMap) {
            $todayBusTrips = $todayTripsByBus->get($bus->id, collect());
            $activityBusTrips = $activityTripsByBus->get($bus->id, collect());
            $latestBusTrips = $latestActivityTripsByBus->get($bus->id, collect());
            $events = $eventsByBus->get($bus->id, collect());
            $ongoingTrip = $activityBusTrips
                ->where('status', 'ongoing')
                ->sortByDesc(fn (Trip $trip) => $trip->started_at?->timestamp ?? 0)
                ->first();

            $routeId = $ongoingTrip?->route_id;
            if (! $routeId && $routeMap->has($bus->route_id)) {
                $routeId = $bus->route_id;
            }
            $route = $routeId ? $routeMap->get($routeId) : null;

            $recordedBoarded = (int) $events
                ->where('event_type', TripPassengerEvent::TYPE_BOARDED)
                ->sum('passenger_delta');
            $peakLoad = (int) ($todayBusTrips->max('peak_passengers') ?? 0);
            $capacity = (int) $bus->capacity;
            $utilization = $capacity > 0
                ? min(100, (int) round(($peakLoad / $capacity) * 100))
                : 0;
            $lastActivity = $this->latestActivity($latestBusTrips);
            $status = $this->displayStatus($bus, $ongoingTrip);

            return [
                'plate' => $bus->plate_number,
                'status' => $status['label'],
                'status_class' => $status['class'],
                'trips' => $todayBusTrips->count(),
                'boarded' => $recordedBoarded,
                'peak_load' => $peakLoad,
                'capacity' => $capacity,
                'distance' => null,
                'util' => $utilization,
                'route' => $route?->id,
                'routeLabel' => $route?->name ?? 'Standby',
                'routeColor' => $route?->color ?? '#64748B',
                'last' => $lastActivity?->format('g:i A') ?? 'No trip activity',
            ];
        })->sort(function (array $left, array $right) {
            return [$right['trips'], $right['peak_load'], $left['plate']] <=> [$left['trips'], $left['peak_load'], $right['plate']];
        })->values()->all();

        return [
            'routes' => $officialRoutes->map(fn (Route $route) => [
                'id' => (int) $route->id,
                'name' => $route->name,
                'color' => $route->color,
            ])->values()->all(),
            'busCards' => $busCards,
            'chartData' => $this->dailyDeploymentData($activityTrips, $chartStart, $todayStart, $buses->count()),
            'distanceDeferred' => true,
            'generatedAt' => $now->toIso8601String(),
        ];
    }

    private function operationalTrips(array $routeIds, CarbonInterface $start, CarbonInterface $end)
    {
        return Trip::query()
            ->whereIn('route_id', $routeIds)
            ->whereIn('status', self::OPERATIONAL_TRIP_STATUSES)
            ->whereNotNull('started_at')
            ->where(function ($query) use ($start, $end) {
                $query
                    ->whereBetween('started_at', [$start, $end])
                    ->orWhereBetween('ended_at', [$start, $end])
                    ->orWhere(function ($overlap) use ($start, $end) {
                        $overlap
                            ->where('started_at', '<', $start)
                            ->where(function ($endQuery) use ($end) {
                                $endQuery->whereNull('ended_at')->orWhere('ended_at', '>', $end);
                            });
                    });
            });
    }

    private function activityTrips(array $routeIds, CarbonInterface $start, CarbonInterface $end)
    {
        return Trip::query()
            ->whereIn('route_id', $routeIds)
            ->whereIn('status', self::OPERATIONAL_TRIP_STATUSES)
            ->whereNotNull('bus_id')
            ->whereNotNull('started_at')
            ->where(function ($query) use ($start, $end) {
                $query
                    ->whereBetween('started_at', [$start, $end])
                    ->orWhereBetween('ended_at', [$start, $end])
                    ->orWhere(function ($overlap) use ($start, $end) {
                        $overlap
                            ->where('started_at', '<', $start)
                            ->where(function ($endQuery) use ($end) {
                                $endQuery->whereNull('ended_at')->orWhere('ended_at', '>', $end);
                            });
                    });
            });
    }

    private function latestActivityTrips(array $routeIds)
    {
        return Trip::query()
            ->whereIn('route_id', $routeIds)
            ->whereNotNull('bus_id')
            ->where(function ($query) {
                $query
                    ->whereNotNull('dispatched_at')
                    ->orWhereNotNull('started_at')
                    ->orWhereNotNull('ended_at');
            });
    }

    private function latestActivity(Collection $trips): ?Carbon
    {
        $timestamps = $trips->flatMap(fn (Trip $trip) => [
            $trip->dispatched_at,
            $trip->started_at,
            $trip->ended_at,
        ])->filter();

        if ($timestamps->isEmpty()) {
            return null;
        }

        return Carbon::instance($timestamps->sortByDesc(fn (CarbonInterface $timestamp) => $timestamp->timestamp)->first());
    }

    private function displayStatus(Bus $bus, ?Trip $ongoingTrip): array
    {
        if ($ongoingTrip && in_array((string) $bus->status, ['active', 'operating'], true)) {
            return ['label' => 'Operating', 'class' => 'bg-[#E6F1FB] text-[#0C447C]'];
        }

        return match ((string) $bus->status) {
            'ready' => ['label' => 'Ready', 'class' => 'bg-[#EAF3DE] text-[#3B6D11]'],
            'operating', 'active' => ['label' => 'Standby', 'class' => 'bg-slate-100 text-slate-700'],
            'maintenance' => ['label' => 'Maintenance', 'class' => 'bg-[#FCEBEB] text-[#A32D2D]'],
            'breakdown' => ['label' => 'Breakdown', 'class' => 'bg-[#FAEEDA] text-[#854F0B]'],
            'inactive' => ['label' => 'Inactive', 'class' => 'bg-slate-100 text-slate-500'],
            default => ['label' => ucfirst((string) $bus->status), 'class' => 'bg-slate-100 text-slate-700'],
        };
    }

    private function dailyDeploymentData(Collection $trips, CarbonInterface $start, CarbonInterface $today, int $fleetCount): array
    {
        $deployedByDate = [];
        for ($date = $start->copy()->startOfDay(); $date->lte($today); $date->addDay()) {
            $deployedByDate[$date->toDateString()] = [];
        }

        foreach ($trips as $trip) {
            $tripStart = Carbon::instance($trip->started_at)->setTimezone('Asia/Manila')->startOfDay();
            $tripEnd = $trip->ended_at
                ? Carbon::instance($trip->ended_at)->setTimezone('Asia/Manila')->startOfDay()
                : $today->copy()->startOfDay();
            $tripStart = $tripStart->max($start->copy()->startOfDay());
            $tripEnd = $tripEnd->min($today->copy()->startOfDay());

            for ($date = $tripStart->copy(); $date->lte($tripEnd); $date->addDay()) {
                $key = $date->toDateString();
                if (array_key_exists($key, $deployedByDate)) {
                    $deployedByDate[$key][$trip->bus_id] = true;
                }
            }
        }

        return collect($deployedByDate)->map(function (array $busIds, string $date) use ($fleetCount) {
            $deployed = count($busIds);

            return [
                'date' => Carbon::parse($date, 'Asia/Manila')->format('j M'),
                'deployed' => $deployed,
                'fleet' => $fleetCount,
                'deployed_percent' => $fleetCount > 0 ? (int) round(($deployed / $fleetCount) * 100) : 0,
            ];
        })->values()->all();
    }
}
