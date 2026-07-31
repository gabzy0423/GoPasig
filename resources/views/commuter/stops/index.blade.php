@extends('layouts.commuter')

@section('title', 'GoPasig - Shuttle Bus Stops')

@section('content')
    <!-- Mount the Stops Livewire Component -->
    <livewire:commuter.commuter-stops />
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
    $routesData = $routes->map(function($r) {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'color' => $r->color ?: '#003F87',
            'coords' => $r->polyline_coordinates
        ];
    });
@endphp

<script>
    let map;
    let markers = {};
    let polylines = [];
    const stopsData = @json($stopsData);
    const routesData = @json($routesData);

    function initMap() {
        // Initialize Map
        map = new google.maps.Map(document.getElementById('map'), {
            center: { lat: 14.5670, lng: 121.0600 },
            zoom: 13,
            disableDefaultUI: true,
            styles: [
                {
                    "featureType": "poi",
                    "elementType": "labels",
                    "stylers": [{ "visibility": "off" }]
                }
            ]
        });

        // Draw Polylines
        routesData.forEach(route => {
            if (route.coords && route.coords.length > 0) {
                const pathCoords = route.coords.map(c => ({ lat: parseFloat(c[0]), lng: parseFloat(c[1]) }));
                const polyline = new google.maps.Polyline({
                    path: pathCoords,
                    geodesic: true,
                    strokeColor: route.color,
                    strokeOpacity: 0.8,
                    strokeWeight: 4,
                    map: map
                });
                polylines.push(polyline);
            }
        });

        // Plot Stop Markers
        stopsData.forEach(stop => {
            // Draw marker
            const marker = new google.maps.Marker({
                position: { lat: stop.lat, lng: stop.lng },
                map: map,
                title: stop.name,
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 6.5,
                    fillColor: '#FFFFFF',
                    fillOpacity: 1,
                    strokeColor: stop.route_color,
                    strokeWeight: 3
                }
            });

            // Click listener
            marker.addListener('click', () => {
                map.setZoom(15.5);
                map.panTo(marker.getPosition());
                
                // Trigger Livewire selectStop action dynamically!
                const livewireEl = document.querySelector('[wire\\:id]');
                if (livewireEl) {
                    const componentId = livewireEl.getAttribute('wire:id');
                    const component = Livewire.find(componentId);
                    if (component) {
                        component.selectStop(stop.id);
                    }
                }
            });

            markers[stop.id] = marker;
        });

        // Zoom buttons
        const zoomInBtn = document.getElementById('zoom-in');
        const zoomOutBtn = document.getElementById('zoom-out');
        if (zoomInBtn) {
            zoomInBtn.addEventListener('click', () => {
                map.setZoom(map.getZoom() + 1);
            });
        }
        if (zoomOutBtn) {
            zoomOutBtn.addEventListener('click', () => {
                map.setZoom(map.getZoom() - 1);
            });
        }
    }

    function focusStopOnMap(stopId, lat, lng, name) {
        if (!map) return;
        
        map.setZoom(15.5);
        map.panTo({ lat: parseFloat(lat), lng: parseFloat(lng) });

        // Highlight marker symbol dynamically if possible
        const marker = markers[stopId];
        if (marker) {
            // briefly pulse or bounce marker
            marker.setAnimation(google.maps.Animation.BOUNCE);
            setTimeout(() => {
                marker.setAnimation(null);
            }, 750);
        }
    }

    // Bind callback globally for Google Maps async script loading
    window.initMap = initMap;
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}&callback=initMap" async defer></script>
@endsection
