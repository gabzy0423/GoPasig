<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Bus;
use App\Models\Alert;
use App\Models\RouteDuration;
use App\Models\SystemSetting;
use App\Models\Terminal;

class CommuterRoutes extends Component
{
    public $routes = [];
    public $selectedRouteId = null;
    public $routeStops = [];
    public $activeBuses = [];

    public function mount()
    {
        $this->loadRoutes();
    }

    public function loadRoutes()
    {
        // Fetch routes from the database
        $routes = Route::getAllCached();
        $stopsByRoute = Stop::getAllCached()->groupBy('route_id');
        $activeBusesByRoute = Bus::where('status', 'active')->get()->groupBy('route_id');

        $this->routes = $routes->map(function ($route) use ($stopsByRoute, $activeBusesByRoute) {
            $stops = $stopsByRoute->get($route->id, collect());

            // Determine origin (first stop) and destination (last stop)
            $origin = $stops->first() ? $stops->first()->name : SystemSetting::get('default_terminal_name', Terminal::getDefaultName());
            $destination = $stops->last() ? $stops->last()->name : SystemSetting::get('default_terminal_label', Terminal::getDefaultName());

            // Clean up the origin/destination name if it has long text, or keep it as is
            // Filter active buses for this route
            $activeBusesOnRoute = $activeBusesByRoute->get($route->id, collect());
            $activeBusCount = $activeBusesOnRoute->count();

            // Next bus ETA is the minimum ETA of active buses
            $nextBusEta = $activeBusCount > 0 ? $activeBusesOnRoute->min('eta') : null;

            // Get dynamic duration fallback from database
            $fallbackDuration = RouteDuration::getDuration($route->id, now()->englishDayOfWeek, null);

            return [
                'route_id' => $route->id,
                'route_code' => 'R' . $route->id,
                'route_name' => 'Route ' . $route->id . ' — ' . ($route->description ?? $route->name),
                'origin' => $origin,
                'destination' => $destination,
                'route_color' => $route->color ?: SystemSetting::get('default_route_color', '#003F87'),
                'total_stops' => $stops->count(),
                'est_travel_minutes' => $route->travel_time_minutes ?: $fallbackDuration,
                'active_bus_count' => $activeBusCount,
                'next_bus_eta' => $nextBusEta,
                'stop_names' => $stops->pluck('name')->toArray(), // for client-side search!
            ];
        })->toArray();
    }

    public function selectRoute($routeId)
    {
        $this->selectedRouteId = $routeId ? (int) $routeId : null;

        if (!$this->selectedRouteId) {
            $this->routeStops = [];
            $this->activeBuses = [];
            return;
        }

        // 1. Fetch all stops for the selected route from cached collection
        $stops = Stop::getAllCached()
            ->where('route_id', $this->selectedRouteId)
            ->sortBy('sequence');

        // 2. Fetch all active buses on this route
        $buses = Bus::where('route_id', $this->selectedRouteId)
            ->where('status', 'active')
            ->get();

        // 3. Map stops and estimate arrival times
        $route = Route::getAllCached()->firstWhere('id', $this->selectedRouteId);
        $fallbackDuration = RouteDuration::getDuration($this->selectedRouteId, now()->englishDayOfWeek, null);
        $routeTravelTime  = $route ? ($route->travel_time_minutes ?: $fallbackDuration) : $fallbackDuration;

        $stopsOrdered    = $stops->values();
        $totalStopsCount = $stopsOrdered->count();

        // Distance-weighted cumulative offsets: offset[i] = minutes from departure to stop i
        $offsets = Stop::getDistanceWeightedOffsets($stopsOrdered, $routeTravelTime);

        // Map sequence → offset minutes
        $seqToOffset = [];
        foreach ($stopsOrdered as $idx => $s) {
            $seqToOffset[$s->sequence] = $offsets[$idx] ?? ($idx * ($routeTravelTime / max(1, $totalStopsCount - 1)));
        }

        $this->routeStops = $stopsOrdered->map(function ($stop) use ($buses, $seqToOffset, $routeTravelTime, $totalStopsCount) {
            $nextBusId         = null;
            $nextBusEtaMinutes = null;

            foreach ($buses as $bus) {
                $busNextStop = Stop::getAllCached()
                    ->where('route_id', $this->selectedRouteId)
                    ->where('name', $bus->next_stop)
                    ->first();

                $busNextSequence = $busNextStop ? $busNextStop->sequence : 1;

                if ($stop->sequence >= $busNextSequence) {
                    // Extra minutes = distance-weighted offset difference between target and bus's next stop
                    $extraMins  = ($seqToOffset[$stop->sequence] ?? 0) - ($seqToOffset[$busNextSequence] ?? 0);
                    $etaAtStop  = round($bus->eta + $extraMins);

                    if ($nextBusEtaMinutes === null || $etaAtStop < $nextBusEtaMinutes) {
                        $nextBusEtaMinutes = $etaAtStop;
                        $nextBusId = $bus->id;
                    }
                }
            }

            return [
                'stop_id'              => $stop->id,
                'stop_name'            => $stop->name,
                'stop_sequence'        => $stop->sequence,
                'next_bus_id'          => $nextBusId,
                'next_bus_eta_minutes' => $nextBusEtaMinutes,
            ];
        })->toArray();

        // 4. Map active buses
        $this->activeBuses = $buses->map(function ($bus) {
            $driverName = $bus->driver_name;
            if (!$driverName) {
                $driver = \App\Models\Driver::where('assigned_bus', $bus->plate_number)->first();
                $driverName = $driver?->name ?? 'No Driver Assigned';
            }

            return [
                'bus_id' => $bus->id,
                'plate_number' => $bus->plate_number,
                'driver_name' => $driverName,
                'status' => $bus->eta >= $bus->getRouteDelayThreshold() ? 'Delayed' : 'On Time', // On Time / Delayed
                'passengers_onboard' => $bus->passengers,
                'capacity' => $bus->capacity,
                'next_stop_name' => $bus->next_stop ?: 'Terminal',
                'next_stop_eta_minutes' => $bus->eta,
            ];
        })->toArray();

        // Find the route object to pass map details
        $route = Route::find($this->selectedRouteId);

        // Dispatch updated route coordinates and stops for the interactive desktop map
        $this->dispatch('routeDetailsLoaded', [
            'routeId' => $this->selectedRouteId,
            'polyline' => $route ? $route->polyline_coordinates : [],
            'color' => $route ? ($route->color ?: SystemSetting::get('default_route_color', '#003F87')) : SystemSetting::get('default_route_color', '#003F87'),
            'stops' => $stops->map(function ($s) {
                return [
                    'name' => $s->name,
                    'lat' => (float) $s->lat,
                    'lng' => (float) $s->lng,
                ];
            })->toArray(),
        ]);
    }

    public function setArrivalAlert($stopId, $minutesBefore)
    {
        if (!$stopId)
            return;

        // Store the alert in the database alerts table
        Alert::create([
            'stop_id' => $stopId,
            'minutes_before' => (int) $minutesBefore,
            'status' => 'active',
        ]);

        $stop = Stop::find($stopId);

        // Dispatch browser event to trigger system notifications and confirmation toasts
        $this->dispatch('alert-created', [
            'stop_name' => $stop ? $stop->name : 'Selected Stop',
            'minutes' => (int) $minutesBefore,
        ]);
    }

    public function render()
    {
        // Keep active buses, stops, and ETAs updated in real-time when polling occurs
        if ($this->selectedRouteId) {
            $this->selectRoute($this->selectedRouteId);
        }

        // Always reload active counts for routes list
        $this->loadRoutes();

        return view('livewire.commuter.commuter-routes');
    }
}
