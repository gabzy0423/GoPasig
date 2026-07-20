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
    public function getCommuterRouteHealth(Route $route, $activeBusesOnRoute): string
    {
        if ($route->status === 'Suspended') {
            return 'Disrupted';
        }

        $hasActiveSuspension = ServiceAlert::activeAlerts()
            ->where(function ($query) use ($route) {
                $query->where('route_id', $route->id)
                      ->orWhere('affected_routes', 'like', '%' . $route->name . '%');
            })
            ->where('type', 'suspension')
            ->exists();

        if ($hasActiveSuspension) {
            return 'Disrupted';
        }

        $hasActiveDelay = ServiceAlert::activeAlerts()
            ->where(function ($query) use ($route) {
                $query->where('route_id', $route->id)
                      ->orWhere('affected_routes', 'like', '%' . $route->name . '%');
            })
            ->whereIn('type', ['delay', 'maintenance'])
            ->exists();

        $hasDelayedBuses = $activeBusesOnRoute->filter(function ($bus) {
            return $bus->eta >= $bus->getRouteDelayThreshold();
        })->isNotEmpty();

        if ($hasActiveDelay || $hasDelayedBuses) {
            return 'Minor Delay';
        }

        return 'On Track';
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
