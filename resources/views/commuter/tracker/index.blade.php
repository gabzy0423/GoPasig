@extends('layouts.commuter')

@section('title', 'GoPasig - Live Bus Tracker')

@section('content')
    <!-- Mount the Tracker Livewire Component -->
    <livewire:commuter.tracker />
@endsection

@section('scripts')
@php
    // Fetch stops data categorized by route
    $routes = \App\Models\Route::getCanonicalProductionCached();
    $stops = \App\Models\Stop::getAllCached()->whereIn('route_id', $routes->pluck('id'));
    $stopsByRoute = $stops->groupBy('route_id');

    $stopsData = $routes->flatMap(function($route) use ($stopsByRoute) {
        $color = $route->color ?: '#003F87';
        $routeStops = $stopsByRoute->get($route->id, collect());
        return $routeStops->map(function($stop) use ($route, $color) {
            return [
                'id' => $stop->id,
                'name' => $stop->name,
                'lat' => (float) $stop->lat,
                'lng' => (float) $stop->lng,
                'route_id' => $route->id,
                'route_color' => $color,
                'route_name' => $route->name,
            ];
        });
    });

    // Fetch route polylines
    $activeTripsForMap = \App\Models\Trip::where('status', 'ongoing')->with('routeVariant')->get();
    $routeMapGeometry = app(\App\Services\RouteMapGeometryService::class);
    $routesData = $routes->map(function($r) use ($activeTripsForMap, $routeMapGeometry) {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'color' => $r->color ?: '#003F87',
            'coords' => ($mapGeometry = $routeMapGeometry->forRoute($r, $activeTripsForMap))['polyline_coordinates'],
            'geometry_source' => $mapGeometry['source'],
            'geometry_status' => $mapGeometry['geometry_status'],
            'variant_geometries' => $mapGeometry['variant_geometries']
        ];
    });
@endphp

<script>
    // Bind stops & routes raw JSON securely for the external tracker script
    window.GoPasig = {
        stopsData: @json($stopsData),
        routesData: @json($routesData)
    };
</script>
<script src="{{ asset('js/commuter-dashboard/tracker.js') }}"></script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&callback=initMap" async defer></script>
@endsection
