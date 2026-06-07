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
    <!-- Google Maps API & Leaflet Mutant Plugin -->
    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}"></script>
    <script src="https://unpkg.com/leaflet.gridlayer.googlemutant@latest/dist/Leaflet.GoogleMutant.js"></script>

    <!-- Chart.js & Chart.js Annotation Plugin -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@3.0.1/dist/chartjs-plugin-annotation.min.js"></script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full text-slate-900 antialiased font-sans">
    @yield('content')

    <!-- Admin Dashboard Scripts -->
    <script src="{{ asset('js/admin-dashboard/dashboard-data.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/admin-dashboard/navigation.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/admin-dashboard/buses.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/admin-dashboard/dispatch.js') }}"></script>
    <script src="{{ asset('js/admin-dashboard/maintenance.js') }}"></script>
    <script src="{{ asset('js/admin-dashboard/fleet-map.js') }}"></script>
    <script src="{{ asset('js/admin-dashboard/overview-map-simulation.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/admin-dashboard/analytics-data.js') }}"></script>
    <script src="{{ asset('js/admin-dashboard/analytics-charts.js') }}"></script>
    <script src="{{ asset('js/admin-dashboard/analytics-renderers.js') }}"></script>
    <script src="{{ asset('js/admin-dashboard/analytics-interactions.js') }}"></script>
    <script src="{{ asset('js/admin-dashboard/drivers.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/admin-dashboard/routes.js') }}?v={{ time() }}"></script>
    <script src="{{ asset('js/admin-dashboard/alerts.js') }}"></script>
    @livewireScripts
</body>
</html>
