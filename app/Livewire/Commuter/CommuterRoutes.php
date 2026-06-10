<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Bus;
use App\Models\Alert;
use App\Models\RouteDuration;
use App\Models\SystemSetting;

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
        $this->routes = Route::with(['stops', 'buses'])->get()->map(function ($route) {
            $stops = $route->stops;

            // Determine origin (first stop) and destination (last stop)
            $origin = $stops->first() ? $stops->first()->name : SystemSetting::get('default_terminal_name', 'SPED Terminal');
            $destination = $stops->last() ? $stops->last()->name : SystemSetting::get('default_terminal_label', 'Terminal');

            // Clean up the origin/destination name if it has long text, or keep it as is
            // Filter active buses for this route
            $activeBusesOnRoute = $route->buses->where('status', 'active');
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
                'route_color' => $route->color ?: '#003F87',
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

        // 1. Fetch all stops for the selected route
        $stops = Stop::where('route_id', $this->selectedRouteId)
            ->orderBy('sequence')
            ->get();

        // 2. Fetch all active buses on this route
        $buses = Bus::where('route_id', $this->selectedRouteId)
            ->where('status', 'active')
            ->get();

        // 3. Map stops and estimate arrival times
        $route = Route::find($this->selectedRouteId);
        $fallbackDuration = RouteDuration::getDuration($this->selectedRouteId, now()->englishDayOfWeek, null);
        $routeTravelTime = $route ? ($route->travel_time_minutes ?: $fallbackDuration) : $fallbackDuration;
        $totalStopsCount = $stops->count();
        $averageInterval = $totalStopsCount > 1 ? ($routeTravelTime / ($totalStopsCount - 1)) : 5;

        $this->routeStops = $stops->map(function ($stop) use ($buses, $averageInterval) {
            $nextBusId = null;
            $nextBusEtaMinutes = null;

            foreach ($buses as $bus) {
                // Find next_stop sequence to see if it hasn't passed this stop yet
                $busNextStop = Stop::where('route_id', $this->selectedRouteId)
                    ->where('name', $bus->next_stop)
                    ->first();

                $busNextSequence = $busNextStop ? $busNextStop->sequence : 1;

                if ($stop->sequence >= $busNextSequence) {
                    // Approximate ETA at subsequent stops: bus.eta + (difference in sequence) * averageInterval
                    $etaAtStop = round($bus->eta + ($stop->sequence - $busNextSequence) * $averageInterval);

                    if ($nextBusEtaMinutes === null || $etaAtStop < $nextBusEtaMinutes) {
                        $nextBusEtaMinutes = $etaAtStop;
                        $nextBusId = $bus->id;
                    }
                }
            }

            return [
                'stop_id' => $stop->id,
                'stop_name' => $stop->name,
                'stop_sequence' => $stop->sequence,
                'next_bus_id' => $nextBusId,
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
                'status' => $bus->eta >= Bus::getDelayThreshold() ? 'Delayed' : 'On Time', // On Time / Delayed
                'passengers_onboard' => $bus->passengers,
                'capacity' => $bus->capacity ?: SystemSetting::get('default_bus_capacity'),
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
            'color' => $route ? ($route->color ?: '#003F87') : '#003F87',
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
