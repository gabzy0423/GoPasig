<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Alert;
use App\Models\RouteDuration;
use App\Models\RouteVariant;
use App\Models\SystemSetting;
use App\Models\Terminal;
use App\Services\CommuterDashboardCacheService;
use App\Services\CommuterEtaProvenanceService;

class CommuterRoutes extends Component
{
    public $routes = [];
    public $selectedRouteId = null;
    public $routeStops = [];
    public $activeBuses = [];
    public $previousRouteId = null;

    #[\Livewire\Attributes\On('buses-updated')]
    public function onBusesUpdated()
    {
        if ($this->selectedRouteId) {
            $this->selectRoute($this->selectedRouteId);
        }
    }

    public function mount()
    {
        $this->loadRoutes();
    }

    public function loadRoutes()
    {
        $routes = Route::getCanonicalProductionCached();
        $stopsByRoute = Stop::getAllCached()->whereIn('route_id', $routes->pluck('id'))->groupBy('route_id');
        $defaultVariantsByRoute = RouteVariant::with(['stops' => fn ($query) => $query->orderBy('sequence')])
            ->where('is_default', true)
            ->get()
            ->keyBy('route_id');
        $activeBusesByRoute = CommuterDashboardCacheService::getActiveBuses()->groupBy('route_id');
        $etaProvenanceService = app(CommuterEtaProvenanceService::class);

        $this->routes = $routes->values()->map(function ($route) use ($stopsByRoute, $defaultVariantsByRoute, $activeBusesByRoute, $etaProvenanceService) {
            $defaultVariant = $defaultVariantsByRoute->get($route->id);
            $stops = $defaultVariant ? $defaultVariant->stops : $stopsByRoute->get($route->id, collect());

            $origin = $defaultVariant?->origin_name
                ?: ($stops->first() ? $stops->first()->name : SystemSetting::get('default_terminal_name', Terminal::getDefaultName()));
            $destination = $defaultVariant?->destination_name
                ?: ($stops->last() ? $stops->last()->name : SystemSetting::get('default_terminal_label', Terminal::getDefaultName()));

            $activeBusesOnRoute = $activeBusesByRoute->get($route->id, collect());
            $activeBusCount = $activeBusesOnRoute->count();
            $nextBus = $activeBusCount > 0 ? $activeBusesOnRoute->sortBy('eta')->first() : null;
            $nextBusProvenance = $nextBus ? $etaProvenanceService->forBus($nextBus) : null;
            $fallbackDuration = RouteDuration::getDuration($route->id, now()->englishDayOfWeek, null);

            return [
                'route_id' => $route->id,
                'route_variant_id' => $defaultVariant?->id,
                'direction' => $defaultVariant?->direction,
                'route_code' => $route->name,
                'route_name' => $route->name . ' - ' . ($route->description ?? $route->name),
                'origin' => $origin,
                'destination' => $destination,
                'route_color' => $route->color ?: SystemSetting::get('default_route_color', '#003F87'),
                'total_stops' => $stops->count(),
                'pickup_point_count' => $stops->where('stop_type', 'pickup_point')->count(),
                'designated_stop_count' => $stops->where('stop_type', 'designated_stop')->count(),
                'est_travel_minutes' => $route->travel_time_minutes ?: $fallbackDuration,
                'active_bus_count' => $activeBusCount,
                'next_bus_eta' => $nextBusProvenance?->minutes,
                'next_bus_eta_label' => $nextBusProvenance?->label,
                'next_bus_eta_provenance_state' => $nextBusProvenance?->state,
                'stop_names' => $stops->pluck('name')->toArray(),
            ];
        })->values()->toArray();
    }

    public function selectRoute($routeId)
    {
        $this->selectedRouteId = $routeId ? (int) $routeId : null;
        $this->previousRouteId = $this->selectedRouteId;

        if (!$this->selectedRouteId) {
            $this->routeStops = [];
            $this->activeBuses = [];
            return;
        }

        $route = Route::getCanonicalProductionCached()->firstWhere('id', $this->selectedRouteId);

        if (! $route) {
            $this->selectedRouteId = null;
            $this->routeStops = [];
            $this->activeBuses = [];
            return;
        }

        $defaultVariant = RouteVariant::with(['stops' => fn ($query) => $query->orderBy('sequence')])
            ->where('route_id', $this->selectedRouteId)
            ->where('is_default', true)
            ->first();

        $stops = $defaultVariant
            ? $defaultVariant->stops
            : Stop::getAllCached()->where('route_id', $this->selectedRouteId)->sortBy('sequence')->values();

        $buses = CommuterDashboardCacheService::getActiveBuses()->where('route_id', $this->selectedRouteId);
        $etaProvenanceService = app(CommuterEtaProvenanceService::class);

        $this->routeStops = $stops->values()->map(function ($stop) use ($buses, $etaProvenanceService) {
            $bestBus = $buses->sortBy('eta')->first();
            $stopId = $stop->canonical_stop_id ?? $stop->id;
            $routeVariantStopId = $stop instanceof \App\Models\RouteVariantStop ? $stop->id : null;
            $etaProvenance = $bestBus ? $etaProvenanceService->forBus($bestBus, $stopId, $routeVariantStopId) : null;

            return [
                'stop_id' => $stopId,
                'route_variant_stop_id' => $routeVariantStopId,
                'stop_name' => $stop->name,
                'stop_sequence' => $stop->sequence,
                'stop_type' => $stop->stop_type ?? 'designated_stop',
                'next_bus_id' => $bestBus?->id,
                'next_bus_eta_minutes' => $etaProvenance?->minutes,
                'next_bus_eta_label' => $etaProvenance?->label,
                'next_bus_eta_provenance_state' => $etaProvenance?->state,
            ];
        })->toArray();

        $this->activeBuses = $buses->map(function ($bus) use ($etaProvenanceService) {
            $driverName = $bus->driver_name;
            if (!$driverName) {
                $driver = \App\Models\Driver::where('assigned_bus', $bus->plate_number)->first();
                $driverName = $driver?->name ?? 'No Driver Assigned';
            }

            $etaProvenance = $etaProvenanceService->forBus($bus);

            return [
                'bus_id' => $bus->id,
                'plate_number' => $bus->plate_number,
                'driver_name' => $driverName,
                'status' => $bus->eta >= $bus->getRouteDelayThreshold() ? 'Delayed' : 'On Time',
                'passengers_onboard' => $bus->passengers,
                'capacity' => $bus->capacity,
                'next_stop_name' => $bus->next_stop ?: 'Terminal',
                'next_stop_eta_minutes' => $etaProvenance->minutes,
                'next_stop_eta_label' => $etaProvenance->label,
                'next_stop_eta_provenance_state' => $etaProvenance->state,
            ];
        })->toArray();

        $routeModel = Route::find($this->selectedRouteId);
        $stopsOrdered = $stops->values();
        $mapStops = $stopsOrdered
            ->filter(fn ($s) => $s->lat !== null && $s->lng !== null)
            ->map(fn ($s) => [
                'name' => $s->name,
                'lat' => (float) $s->lat,
                'lng' => (float) $s->lng,
                'stop_type' => $s->stop_type ?? 'designated_stop',
            ])->values();

        $this->dispatch('routeDetailsLoaded', [
            'routeId' => $this->selectedRouteId,
            'routeVariantId' => $defaultVariant?->id,
            'direction' => $defaultVariant?->direction,
            'originName' => $defaultVariant?->origin_name,
            'destinationName' => $defaultVariant?->destination_name,
            'polyline' => $defaultVariant ? ($defaultVariant->polyline_coordinates ?: []) : ($routeModel ? $routeModel->polyline_coordinates : []),
            'color' => $routeModel ? ($routeModel->color ?: SystemSetting::get('default_route_color', '#003F87')) : SystemSetting::get('default_route_color', '#003F87'),
            'stops' => $mapStops->toArray(),
        ]);
    }

    public function setArrivalAlert($stopId, $minutesBefore)
    {
        if (!$stopId || !is_numeric($stopId)) {
            return;
        }

        Alert::create([
            'stop_id' => (int) $stopId,
            'minutes_before' => (int) $minutesBefore,
            'status' => 'active',
        ]);

        $stop = Stop::find($stopId);

        $this->dispatch('alert-created', [
            'stop_name' => $stop ? $stop->name : 'Selected Stop',
            'minutes' => (int) $minutesBefore,
        ]);
    }

    public function render()
    {
        if ($this->selectedRouteId) {
            $selectedRoute = Route::find($this->selectedRouteId);
            if ($selectedRoute && ! Route::getCanonicalProductionCached()->contains('id', $selectedRoute->id)) {
                $this->dispatch('route-suspended', [
                    'message' => 'This route has been suspended due to an operational issue. Please select another route.'
                ]);
                $this->selectedRouteId = null;
                $this->routeStops = [];
                $this->activeBuses = [];
            }
        }

        if ($this->selectedRouteId !== $this->previousRouteId) {
            $this->selectRoute($this->selectedRouteId);
        }

        $this->loadRoutes();

        return view('livewire.commuter.commuter-routes');
    }
}
