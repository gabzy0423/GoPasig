<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GoPasig Fleet Ops')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/pasig_logo.png') }}">
    
    <!-- Tabler Webfont Icons -->
    <link rel="stylesheet" href="{{ asset('css/tabler-icons.css') }}">
    
    <!-- Leaflet Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Leaflet Draw Plugin -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.js" defer></script>
    <!-- Google Maps API & Leaflet Mutant Plugin -->
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}" defer></script>
    <script src="https://unpkg.com/leaflet.gridlayer.googlemutant@latest/dist/Leaflet.GoogleMutant.js" defer></script>

    <!-- Chart.js & Chart.js Annotation Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js" defer></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full text-slate-900 antialiased font-sans">
    @yield('content')

    <!-- Admin Dashboard Scripts -->
    <script src="{{ asset('js/shared/ui-feedback.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/request-lifecycle.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/dashboard-data.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/navigation.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/buses.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/dispatch.js') }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/maintenance.js') }}" defer></script>
    <script src="{{ asset('js/route-map-ux.js') }}?v={{ filemtime(public_path('js/route-map-ux.js')) }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/fleet-map.js') }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/overview-map-simulation.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/analytics-data.js') }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/analytics-charts.js') }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/analytics-renderers.js') }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/analytics-interactions.js') }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/drivers.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/routes.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/route-editor.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/alerts.js') }}" defer></script>
    <script src="{{ asset('js/admin-dashboard/settings.js') }}?v={{ time() }}" defer></script>
    <script src="{{ asset('js/shared/staff-profile.js') }}?v={{ time() }}" defer></script>
    @livewireScriptConfig
</body>
</html>


