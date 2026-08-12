<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\Incident;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\ServiceAlert;
use App\Models\Trip;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AdminOperationsOverviewService
{
    private const TIMEZONE = 'Asia/Manila';

    public function __construct(
        private readonly RouteServiceScheduleEvaluator $scheduleEvaluator
    ) {}

    public function snapshot(?CarbonInterface $at = null): array
    {
        $at = Carbon::instance($at ?? Carbon::now(self::TIMEZONE))
            ->copy()
            ->timezone(self::TIMEZONE);
        $dayStartUtc = $at->copy()->startOfDay()->utc();
        $dayEndUtc = $at->copy()->endOfDay()->utc();

        $routes = Route::publicCommuterVisible()
            ->with(['variants.serviceSchedules'])
            ->get();
        $routeIds = $routes->pluck('id')->all();

        $ongoingTrips = Trip::query()
            ->whereIn('route_id', $routeIds)
            ->where('status', 'ongoing')
            ->with('bus')
            ->get();
        $inServiceBusIds = $ongoingTrips
            ->filter(fn (Trip $trip) => $trip->bus
                && in_array($trip->bus->status, Bus::commuterServiceStatuses(), true))
            ->pluck('bus_id')
            ->unique()
            ->values();

        $buses = Bus::all();
        $standbyBusIds = $buses
            ->filter(fn (Bus $bus) => CentralDispatchEligibilityService::busIsEligible($bus))
            ->pluck('id')
            ->values();

        $completedToday = Trip::query()
            ->whereIn('route_id', $routeIds)
            ->where('status', 'completed')
            ->whereBetween('ended_at', [$dayStartUtc, $dayEndUtc])
            ->count();
        $underMaintenance = $buses->where('status', Bus::STATUS_MAINTENANCE)->count();
        $breakdownBuses = $buses->where('status', Bus::STATUS_BREAKDOWN)->count();
        $activeServiceAlerts = ServiceAlert::activeAlerts()->publicCommuterVisible()->count();
        $openIncidents = Incident::query()
            ->whereIn('status', ['reported', 'under_review'])
            ->whereHas('trip', fn ($query) => $query->whereIn('route_id', $routeIds))
            ->count();
        $openDisruptions = $activeServiceAlerts + $openIncidents + $breakdownBuses;

        $unavailableBuses = max(
            0,
            $buses->count() - $inServiceBusIds->merge($standbyBusIds)->unique()->count()
        );

        return [
            'generated_at' => $at->toIso8601String(),
            'metrics' => [
                'buses_in_service' => $inServiceBusIds->count(),
                'completed_today' => $completedToday,
                'under_maintenance' => $underMaintenance,
                'open_disruptions' => $openDisruptions,
            ],
            'disruption_breakdown' => [
                'incidents' => $openIncidents,
                'service_alerts' => $activeServiceAlerts,
                'breakdowns' => $breakdownBuses,
            ],
            'system_health' => $this->systemHealth($openDisruptions, $underMaintenance),
            'fleet_status' => [
                'total' => $buses->count(),
                'in_service' => $inServiceBusIds->count(),
                'standby' => $standbyBusIds->count(),
                'unavailable' => $unavailableBuses,
            ],
            'official_schedules' => $routes
                ->map(fn (Route $route) => $this->routeSchedule($route, $at))
                ->values()
                ->all(),
        ];
    }

    private function routeSchedule(Route $route, CarbonInterface $at): array
    {
        return [
            'route_id' => (int) $route->id,
            'route_name' => $route->name,
            'route_color' => $route->color,
            'directions' => $route->variants
                ->sortBy(fn (RouteVariant $variant) => $variant->direction === 'outbound' ? 0 : 1)
                ->map(fn (RouteVariant $variant) => $this->directionSchedule($route, $variant, $at))
                ->values()
                ->all(),
        ];
    }

    private function directionSchedule(
        Route $route,
        RouteVariant $variant,
        CarbonInterface $at
    ): array {
        $windows = $this->scheduleEvaluator
            ->activeWindowsForRouteOn($route, $at)
            ->where('route_variant_id', $variant->id)
            ->values();
        $current = $this->scheduleEvaluator->currentWindowForVariant($variant, $at);
        $next = $this->scheduleEvaluator->nextWindowForVariant($variant, $at);

        if (strcasecmp((string) $route->status, 'suspended') === 0) {
            $status = 'Suspended';
            $state = 'suspended';
        } elseif ($current) {
            $status = 'In service';
            $state = 'in_service';
        } elseif ($next) {
            $status = 'Starts ' . $this->timeLabel($next->first_trip_time);
            $state = 'starts_later';
        } elseif ($windows->isNotEmpty()) {
            $status = 'Service ended';
            $state = 'ended';
        } elseif ($variant->serviceSchedules->contains(fn (RouteServiceSchedule $schedule) => $schedule->is_active)) {
            $status = 'No service today';
            $state = 'closed';
        } else {
            $status = 'Not configured';
            $state = 'missing';
        }

        return [
            'direction' => $variant->direction,
            'label' => $this->directionLabel($variant),
            'status' => $status,
            'state' => $state,
            'windows' => $windows
                ->map(fn (RouteServiceSchedule $window) => $this->windowLabel($window))
                ->all(),
        ];
    }

    private function systemHealth(int $openDisruptions, int $underMaintenance): array
    {
        if ($openDisruptions > 0) {
            return [
                'state' => 'critical',
                'label' => 'Service Disruption',
            ];
        }

        if ($underMaintenance > 0) {
            return [
                'state' => 'degraded',
                'label' => 'System Degraded',
            ];
        }

        return [
            'state' => 'nominal',
            'label' => 'Systems Nominal',
        ];
    }

    private function directionLabel(RouteVariant $variant): string
    {
        $direction = ucfirst((string) $variant->direction);

        if ($variant->origin_name && $variant->destination_name) {
            return $direction . ': ' . $variant->origin_name . ' -> ' . $variant->destination_name;
        }

        return $direction;
    }

    private function windowLabel(RouteServiceSchedule $window): string
    {
        return $this->timeLabel($window->first_trip_time)
            . ' - '
            . $this->timeLabel($window->last_trip_time);
    }

    private function timeLabel(?string $time): string
    {
        if (! $time) {
            return 'Unavailable';
        }

        $normalized = strlen($time) === 5 ? $time . ':00' : substr($time, 0, 8);

        return Carbon::createFromFormat('H:i:s', $normalized, self::TIMEZONE)->format('g:i A');
    }
}
