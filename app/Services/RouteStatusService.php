<?php

namespace App\Services;

use App\Models\Route;
use App\Models\ServiceAlert;
use App\Models\Bus;
use App\Models\Incident;
use Illuminate\Support\Facades\DB;

class RouteStatusService
{
    /**
     * Compute route health status for commuter view.
     *
     * @param Route $route
     * @param \Illuminate\Support\Collection $activeBusesOnRoute
     * @return string
     */
    public function getCommuterRouteHealth(Route $route, $activeBusesOnRoute, $activeAlerts = null, ?int $defaultDelayThreshold = null): string
    {
        if ($route->status === 'Suspended') {
            return 'Disrupted';
        }

        $activeAlerts ??= ServiceAlert::activeAlerts()->publicCommuterVisible()->get();
        $defaultDelayThreshold ??= Bus::getDelayThreshold();

        $routeAlerts = $activeAlerts->filter(function ($alert) use ($route) {
            return (int) ($alert->route_id ?? 0) === (int) $route->id
                || ($alert->affected_routes && stripos($alert->affected_routes, $route->name) !== false);
        });

        if ($routeAlerts->contains(fn ($alert) => $alert->type === 'suspension')) {
            return 'Disrupted';
        }

        $activeIncidents = DB::table('incidents')
            ->join('trips', 'incidents.trip_id', '=', 'trips.id')
            ->where('trips.route_id', $route->id)
            ->whereIn('incidents.status', ['reported', 'under_review'])
            ->select('incidents.type')
            ->get();

        $hasDisruptedIncident = $activeIncidents->contains(function ($incident) {
            return Incident::isBreakdown($incident->type) || Incident::isAccident($incident->type);
        });

        if ($hasDisruptedIncident) {
            return 'Disrupted';
        }

        $hasDelayIncident = $activeIncidents->contains(function ($incident) {
            return Incident::isTrafficDelay($incident->type);
        });
        $hasDelayAlert = $routeAlerts->contains(fn ($alert) => in_array($alert->type, ['delay', 'maintenance'], true));
        $hasDelayedBus = $activeBusesOnRoute->contains(fn ($bus) => $bus->eta >= ($bus->route?->delay_threshold_minutes ?: $defaultDelayThreshold));

        return $hasDelayIncident || $hasDelayAlert || $hasDelayedBus ? 'Minor Delay' : 'On Track';
    }

    /**
     * Compute route health status for fleet overview.
     *
     * @param Route $route
     * @param int $busesOnRoute
     * @return string
     */
    public function getFleetRouteHealth(Route $route, int $busesOnRoute): string
    {
        $activeIncidents = DB::table('incidents')
            ->join('trips', 'incidents.trip_id', '=', 'trips.id')
            ->where('trips.route_id', $route->id)
            ->whereIn('incidents.status', ['reported', 'under_review'])
            ->select('incidents.type')
            ->get();

        $hasDisruptedIncident = $activeIncidents->contains(function ($inc) {
            return Incident::isBreakdown($inc->type) || Incident::isAccident($inc->type);
        });

        $hasDelayIncident = $activeIncidents->contains(function ($inc) {
            return Incident::isTrafficDelay($inc->type);
        });

        if ($busesOnRoute === 0) {
            return 'No Active Buses';
        } elseif ($hasDisruptedIncident) {
            return 'Disrupted';
        } elseif ($hasDelayIncident) {
            return 'Minor Delay';
        } elseif ($busesOnRoute < ($route->min_buses_required ?? (int) \App\Models\SystemSetting::get('route_min_buses_required', 2))) {
            return 'Low Coverage';
        } else {
            return 'On Track';
        }
    }
}
