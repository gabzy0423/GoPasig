<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'GoPasig Fleet Ops')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/pasig_logo.png') }}">
    
    <!-- Google Fonts: Plus Jakarta Sans & DM Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    
    <!-- Tabler Webfont Icons -->
    <link rel="stylesheet" href="{{ asset('css/tabler-icons.css') }}">
    
    <!-- Leaflet Map CSS & JS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <!-- Google Maps API & Leaflet Mutant Plugin -->
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}"></script>
    <script src="https://unpkg.com/leaflet.gridlayer.googlemutant@latest/dist/Leaflet.GoogleMutant.js"></script>

    <!-- Chart.js & Chart.js Annotation Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>

    <!-- Apache ECharts -->
    <script src="{{ asset('js/echarts.min.js') }}"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @yield('styles')
    @livewireStyles
</head>
<body class="fleet-layout h-full overflow-hidden select-none antialiased bg-slate-50">
    @yield('content')
    @livewireScripts

    @if(request()->routeIs('fleet.dashboard'))
        <!-- Fleet Dashboard Scripts -->
        <script src="{{ asset('js/fleet-dashboard/overview.js') }}?v={{ time() }}" defer></script>
        <script src="{{ asset('js/fleet-dashboard/navigation.js') }}?v={{ filemtime(public_path('js/fleet-dashboard/navigation.js')) }}" defer></script>
        <script src="{{ asset('js/fleet-dashboard/performance.js') }}?v={{ time() }}" defer></script>
        <script src="{{ asset('js/fleet-dashboard/schedule-compliance.js') }}?v={{ time() }}" defer></script>
        <script src="{{ asset('js/fleet-dashboard/incidents.js') }}?v={{ time() }}" defer></script>
        <script src="{{ asset('js/fleet-dashboard/maintenance-management.js') }}?v={{ time() }}" defer></script>
        <script src="{{ asset('js/fleet-dashboard/announcements.js') }}?v={{ time() }}" defer></script>
        <script src="{{ asset('js/fleet-dashboard/analytics.js') }}?v={{ time() }}" defer></script>
        <script src="{{ asset('js/fleet-dashboard/dispatch-intelligence.js') }}?v={{ time() }}" defer></script>
        <script src="{{ asset('js/fleet-dashboard/commuter-trips.js') }}?v={{ time() }}" defer></script>
        <script src="{{ asset('js/fleet-dashboard/commuter-sessions.js') }}?v={{ time() }}" defer></script>
    @endif
</body>
</html>
