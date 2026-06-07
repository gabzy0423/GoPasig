@extends('layouts.commuter')

@section('title', 'GoPasig - Live Bus Tracker')

@section('content')
    <!-- Mount the Tracker Livewire Component -->
    <livewire:commuter.tracker />
@endsection

@section('scripts')
@php
    // Fetch stops data categorized by route
    $stopsData = \App\Models\Route::with('stops')->get()->flatMap(function($route) {
        $color = $route->color ?: '#003F87';
        return $route->stops->map(function($stop) use ($route, $color) {
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
    $routesData = \App\Models\Route::all()->map(function($r) {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'color' => $r->color ?: '#003F87',
            'coords' => $r->polyline_coordinates
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
