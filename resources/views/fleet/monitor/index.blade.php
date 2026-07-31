<section id="screen-monitor" class="hidden animate-fade-in" style="display: none;">
<style>
    /* Custom Leaflet Map styling */
    #map {
        height: 100%;
        min-height: 520px;
        width: 100%;
        background: #F1EFE8;
    }

    @media (min-width: 1024px) {
        #map {
            min-height: 0;
        }
    }
    
    .bus-marker-container {
        background: transparent;
        border: none;
    }
    
    .bus-marker {
        width: 34px;
        height: 34px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #FFFFFF;
        font-size: 14px;
        box-shadow: 0 3px 6px rgba(0, 0, 0, 0.16);
        position: relative;
        border: 2px solid #FFFFFF;
        transition: transform 0.2s ease;
    }
    .bus-marker:hover {
        transform: scale(1.1);
    }
    .bus-marker.active {
        background-color: #003F87;
    }
    .bus-marker.near-full {
        background-color: #BA7517;
    }
    .bus-marker.breakdown {
        background-color: #E24B4A;
    }
    .bus-marker.idle {
        background-color: #888780;
    }
    .bus-direction-arrow {
        position: absolute;
        top: -10px;
        left: 50%;
        width: 0;
        height: 0;
        border-left: 5px solid transparent;
        border-right: 5px solid transparent;
        border-bottom: 11px solid #001F44;
        transform-origin: 50% 27px;
        opacity: 0.88;
        filter: drop-shadow(0 1px 1px rgba(0,0,0,0.25));
    }

    /* Pulse animation for active buses */
    .bus-marker.active .bus-pulse {
        position: absolute;
        width: calc(100% + 4px);
        height: calc(100% + 4px);
        border-radius: 50%;
        border: 2px solid #003F87;
        animation: marker-pulse 1.8s infinite ease-out;
        pointer-events: none;
        left: -4px;
        top: -4px;
    }
    @keyframes marker-pulse {
        0% {
            transform: scale(1);
            opacity: 0.9;
        }
        100% {
            transform: scale(1.6);
            opacity: 0;
        }
    }

    /* Custom Leaflet Popup Styling */
    .custom-leaflet-popup .leaflet-popup-content-wrapper {
        background: #FFFFFF !important;
        border: 0.5px solid rgba(0,0,0,0.12) !important;
        border-radius: 12px !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important;
        color: #001F44 !important;
        padding: 0 !important;
        overflow: hidden;
    }
    .custom-leaflet-popup .leaflet-popup-content {
        margin: 0 !important;
        width: 230px !important;
        font-family: 'DM Sans', sans-serif !important;
    }
    .custom-leaflet-popup .leaflet-popup-tip {
        background: #FFFFFF !important;
        border: 0.5px solid rgba(0,0,0,0.12) !important;
        box-shadow: none !important;
    }
    .custom-leaflet-popup .leaflet-popup-close-button {
        display: none !important;
    }

    /* Toast Notification */
    .toast-notification {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background-color: #001F44;
        color: #FFFFFF;
        padding: 12px 18px;
        border-radius: 8px;
        box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        display: flex;
        align-items: center;
        gap: 10px;
        z-index: 1000;
        font-family: 'DM Sans', sans-serif;
        font-size: 13px;
        transform: translateY(100px);
        opacity: 0;
        transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .toast-notification.show {
        transform: translateY(0);
        opacity: 1;
    }
    .map-ui-enter {
        animation: map-ui-enter 180ms ease-out both;
        will-change: opacity, transform;
    }
    .map-ui-enter-down { --map-ui-x: 0; --map-ui-y: -6px; }
    .map-ui-enter-side { --map-ui-x: 6px; --map-ui-y: 0; }
    @keyframes map-ui-enter {
        from { opacity: 0; transform: translate(var(--map-ui-x, 0), var(--map-ui-y, 0)); }
        to { opacity: 1; transform: translate(0, 0); }
    }
    .scrollbar-none::-webkit-scrollbar { display: none; }
    .scrollbar-none { -ms-overflow-style: none; scrollbar-width: none; }
    .map-chip-strip { overscroll-behavior-inline: contain; scroll-padding-inline: 12px; }
    .map-panel-scroll {
        scrollbar-color: rgba(148, 163, 184, 0.7) transparent;
        scrollbar-width: thin;
    }
    .map-panel-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
    .map-panel-scroll::-webkit-scrollbar-thumb {
        background: rgba(148, 163, 184, 0.55);
        border-radius: 999px;
        border: 2px solid transparent;
        background-clip: content-box;
    }
    .map-panel-scroll::-webkit-scrollbar-thumb:hover { background: rgba(100, 116, 139, 0.7); background-clip: content-box; }
    #vehicle-list-container > div { outline: none; }
    @media (prefers-reduced-motion: reduce) {
        .map-ui-enter { animation: none; will-change: auto; }
        .bus-marker.active .bus-pulse { animation: none; }
    }</style>

<div class="relative min-h-[560px] overflow-visible rounded-[18px] border border-slate-200/80 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.16)] ring-1 ring-white/60 lg:left-1/2 lg:h-[calc(100dvh-56px-3rem)] lg:w-[calc(100vw-240px-3rem)] lg:-translate-x-1/2 lg:overflow-hidden">
    <!-- Map Canvas -->
    <div id="map" class="h-[520px] w-full lg:h-full"></div>

    <!-- Floating toolbar -->
    <div class="map-ui-enter map-ui-enter-down relative z-[1000] m-3 flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white/90 p-2.5 shadow-[0_14px_34px_rgba(15,23,42,0.16)] ring-1 ring-white/70 backdrop-blur-md transition duration-150 lg:absolute lg:left-4 lg:right-[392px] lg:top-4 lg:m-0 xl:right-[408px]">
        <div class="flex min-w-0 flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
            <!-- Compact page identity -->
            <div class="min-w-[170px] shrink-0 select-none">
                <h1 class="text-sm font-black text-slate-900">Trace Buses</h1>
                <div class="mt-0.5 flex items-center gap-1 text-[10px] font-semibold text-slate-400">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Fleet</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span class="font-bold text-slate-600">Live Monitor</span>
                </div>
            </div>

            <!-- Live state and route filters -->
            <div class="flex min-w-0 flex-1 flex-col gap-2 md:flex-row md:items-center">
                <div class="flex shrink-0 items-center gap-1.5 rounded-xl border border-emerald-100 bg-emerald-50/90 px-2.5 py-2 text-[12px] text-slate-600 shadow-sm">
                    <span class="font-mono-custom" id="bus-tracked-count">12 buses tracked</span>
                    <span class="text-slate-300">/</span>
                    <div class="flex items-center gap-1">
                        <span class="pulse-dot"></span>
                        <span>Live</span>
                    </div>
                </div>

                <div class="map-chip-strip scrollbar-none flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto rounded-xl border border-slate-200/80 bg-slate-100/80 p-1 whitespace-nowrap">
                    <button onclick="filterByRoute('all')" id="chip-route-all" class="route-chip shrink-0 rounded-lg border border-slate-200/80 bg-white px-3 py-1.5 text-[12px] font-semibold text-[#001F44] shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#003F87]/20">All</button>
                    @foreach($routes as $route)
                    <button onclick="filterByRoute('{{ $route['id'] }}')" id="chip-route-{{ $route['id'] }}" class="route-chip shrink-0 rounded-lg px-3 py-1.5 text-[12px] font-semibold text-slate-500 transition-colors hover:bg-white/80 hover:text-[#001F44] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#003F87]/20">{{ $route['name'] }}</button>
                    @endforeach
                </div>
            </div>

            <!-- Status filter -->
            <div class="flex shrink-0 items-center gap-2 sm:justify-end">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Status:</span>
                <select id="status-filter" onchange="filterByStatus(this.value)" class="rounded-xl border border-slate-200/90 bg-white px-3 py-2 text-[13px] font-semibold text-[#001F44] outline-none transition focus:border-[#003F87] focus-visible:ring-2 focus-visible:ring-[#003F87]/20">
                    <option value="all">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="near-full">Near Full</option>
                    <option value="breakdown">Breakdown</option>
                    <option value="idle">Idle</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Map Controls Overlay -->
    <div class="map-ui-enter map-ui-enter-down absolute left-4 top-[92px] z-[1000] flex flex-col gap-1.5 rounded-xl border border-slate-200/80 bg-white/90 p-1.5 shadow-[0_12px_28px_rgba(15,23,42,0.14)] ring-1 ring-white/60 backdrop-blur-md max-lg:top-[536px]">
        <button onclick="mapZoomIn()" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#001F44] transition-colors hover:bg-[#E6F1FB]/70" aria-label="Zoom in" title="Zoom in">
            <i class="ti ti-plus text-[16px]"></i>
        </button>
        <button onclick="mapZoomOut()" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#001F44] transition-colors hover:bg-[#E6F1FB]/70" aria-label="Zoom out" title="Zoom out">
            <i class="ti ti-minus text-[16px]"></i>
        </button>
        <button onclick="mapRecenter()" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#001F44] transition-colors hover:bg-[#E6F1FB]/70" aria-label="Re-center map" title="Re-center map">
            <i class="ti ti-current-location text-[16px]"></i>
        </button>
        <div class="my-0.5 h-px bg-black/8"></div>
        <button onclick="toggleMapLayers()" class="flex h-8 w-8 items-center justify-center rounded-lg text-[#001F44] transition-colors hover:bg-[#E6F1FB]/70" aria-label="Toggle map layer" title="Toggle map layer">
            <i class="ti ti-layers text-[16px]"></i>
        </button>
    </div>

    <!-- Floating Vehicle Operations Panel -->
    <div class="map-ui-enter map-ui-enter-side relative z-[1000] m-3 flex max-h-[640px] flex-col rounded-[18px] border border-slate-200/90 bg-white/90 shadow-[0_18px_45px_rgba(15,23,42,0.16)] ring-1 ring-white/70 backdrop-blur-md transition duration-150 lg:absolute lg:bottom-4 lg:right-4 lg:top-[76px] lg:m-0 lg:max-h-none lg:w-[320px] xl:w-[360px]">
        <!-- Panel Header -->
        <div class="sticky top-0 z-10 flex shrink-0 items-center justify-between border-b border-slate-100 bg-white/80 px-4 py-3.5 backdrop-blur">
            <span class="text-[13px] font-semibold text-[#001F44]" id="list-header-count">12 vehicles</span>
            <select id="sort-dropdown" onchange="sortVehicles(this.value)" class="rounded-xl border border-slate-200/90 bg-white px-2.5 py-1.5 text-[12px] font-medium text-slate-600 outline-none transition focus:border-[#003F87] focus-visible:ring-2 focus-visible:ring-[#003F87]/20 focus-visible:ring-2 focus-visible:ring-[#003F87]/20">
                <option value="status">Sort: Status</option>
                <option value="plate">Sort: Plate</option>
                <option value="occupancy">Sort: Occupancy</option>
            </select>
        </div>

        <!-- Search input -->
        <div class="flex shrink-0 items-center gap-2 border-b border-slate-100 bg-slate-50/70 px-4 py-3 focus-within:bg-white focus-within:ring-2 focus-within:ring-[#003F87]/10">
            <i class="ti ti-search text-[16px] text-slate-400"></i>
            <input type="text" id="vehicle-search" oninput="searchVehicles(this.value)" placeholder="Search plate or driver..." class="w-full border-none bg-transparent p-0 text-[13px] font-medium text-[#001F44] outline-none placeholder-slate-400">
        </div>

        <!-- Vehicle list -->
        <div class="map-panel-scroll flex-grow overflow-y-auto divide-y divide-slate-100" id="vehicle-list-container">
            <!-- Javascript will inject list rows here -->
        </div>
    </div>
</div>

<!-- TOAST CONTAINER -->
<div id="toast" class="toast-notification">
    <i class="ti ti-circle-check text-[#EAF3DE]"></i>
    <span id="toast-message">Message sent successfully!</span>
</div>



<script>
    const SPEED_DISPLAY_DEADBAND_KMH = 0.5;

    function normalizeDisplaySpeedKmh(speedKmh, movementState = null) {
        if (String(movementState || '').toUpperCase() === 'STATIONARY') {
            return 0;
        }

        const roundedSpeed = Math.round((Number(speedKmh) || 0) * 10) / 10;
        return roundedSpeed < SPEED_DISPLAY_DEADBAND_KMH ? 0 : roundedSpeed;
    }

    function formatDisplaySpeedKmh(speedKmh, movementState = null) {
        return normalizeDisplaySpeedKmh(speedKmh, movementState) + ' km/h';
    }


    function validDisplayHeading(heading) {
        if (heading === null || heading === undefined || heading === '') return null;
        const value = Number(heading);
        if (!Number.isFinite(value) || value < 0 || value >= 360) return null;
        return value;
    }

    function gpsQualityLabel(state) {
        switch (String(state || 'UNKNOWN').toUpperCase()) {
            case 'GOOD': return 'GPS Good';
            case 'DEGRADED': return 'GPS Degraded';
            case 'STALE': return 'GPS Stale';
            case 'BLOCKED': return 'GPS Blocked';
            default: return 'GPS Unknown';
        }
    }

    function gpsQualityChipClass(state) {
        switch (String(state || 'UNKNOWN').toUpperCase()) {
            case 'GOOD': return 'bg-emerald-50 text-emerald-700 border border-emerald-100';
            case 'DEGRADED': return 'bg-amber-50 text-amber-700 border border-amber-100';
            case 'STALE': return 'bg-rose-50 text-rose-700 border border-rose-100';
            case 'BLOCKED': return 'bg-slate-100 text-slate-600 border border-slate-200';
            default: return 'bg-slate-50 text-slate-500 border border-slate-200';
        }
    }
    function statusLabelFromOperationalStatus(status) {
        const normalized = String(status || '').toLowerCase();
        switch (normalized) {
            case 'moving': return 'Moving';
            case 'stopped': return 'Stopped';
            case 'idle': return 'Idle';
            case 'offline': return 'Offline';
            case 'breakdown': return 'Breakdown';
            case 'maintenance': return 'Maintenance';
            case 'active': return 'Active';
            default: return status || 'Unknown';
        }
    }

    function statusKeyFromOperationalStatus(status, isNearFull = false) {
        const normalized = String(status || '').toLowerCase();
        if (normalized === 'moving') return isNearFull ? 'near-full' : 'active';
        if (normalized === 'stopped' || normalized === 'idle') return 'idle';
        if (normalized === 'offline') return 'breakdown';
        if (normalized === 'breakdown' || normalized === 'maintenance' || normalized === 'active' || normalized === 'near-full') return normalized;
        return 'idle';
    }

    // Live Data for Buses
    let buses = [
        @foreach($buses as $bus)
        {
            plate: '{{ $bus->plate_number }}',
            driver: '{{ $bus->driver_name ?? "No Driver" }}',
            route: '{{ $bus->route_id ?? "None" }}',
            routeName: '{{ $bus->route ? $bus->route->name . " — " . $bus->route->description : "Unassigned" }}',
            lat: {{ $bus->lat ?? 14.5593 }},
            lng: {{ $bus->lng ?? 121.0805 }},
            operationalStatus: '{{ $bus->vehiclePosition->status ?? $bus->status }}',
            status: statusKeyFromOperationalStatus('{{ $bus->vehiclePosition->status ?? $bus->status }}', {{ $bus->capacity > 0 && ($bus->passengers / $bus->capacity >= 0.8) ? 'true' : 'false' }}),
            statusLabel: statusLabelFromOperationalStatus('{{ $bus->vehiclePosition->status ?? $bus->status }}'),
            speed: formatDisplaySpeedKmh({{ round(((float) ($bus->vehiclePosition->speed ?? $bus->speed ?? 0)) * 3.6, 1) }}, '{{ $bus->vehiclePosition->movement_state ?? '' }}'),
            pax: {{ $bus->passengers }},
            cap: {{ $bus->capacity }},
            nextStop: '{{ $bus->next_stop ?? "Terminal" }}',
            eta: '{{ $bus->eta ? $bus->eta . " min" : "--" }}',
            issue: '{{ $busIssues[$bus->plate_number] ?? ($bus->status === 'maintenance' ? "In Maintenance" : "") }}',
            nearestStop: 'None',
            currentStop: 'None',
            upcomingStop: 'None',
            currentFence: 'Outside Geofence',
            dwellTimeSeconds: 0,
            routeAdherence: 'On Route',
            corridorDistance: 0,
            completedStops: 0,
            remainingStops: 0,
            completionPercentage: 0,
            tripId: null,
            coordinateSource: '{{ $bus->vehiclePosition ? "vehicle_position" : "bus_fallback" }}',
            hasLiveTelemetry: {{ $bus->vehiclePosition ? 'true' : 'false' }},
            movementState: '{{ $bus->vehiclePosition->movement_state ?? '' }}',
            movementConfidence: {{ $bus->vehiclePosition && $bus->vehiclePosition->movement_confidence !== null ? (float) $bus->vehiclePosition->movement_confidence : 'null' }},
            movementReason: '{{ $bus->vehiclePosition->movement_reason ?? '' }}',
            movementStateUpdatedAt: '{{ $bus->vehiclePosition && $bus->vehiclePosition->movement_state_updated_at ? $bus->vehiclePosition->movement_state_updated_at->toIso8601String() : '' }}',
            stationaryDurationSeconds: {{ $bus->vehiclePosition && $bus->vehiclePosition->movement_state === 'STATIONARY' && $bus->vehiclePosition->movement_state_updated_at ? $bus->vehiclePosition->movement_state_updated_at->diffInSeconds(now()) : 'null' }},
            gpsQualityState: '{{ $bus->vehiclePosition->gps_quality_state ?? 'UNKNOWN' }}',
            gpsQualityReason: '{{ $bus->vehiclePosition->gps_quality_reason ?? '' }}',
            gpsFixAgeSeconds: {{ $bus->vehiclePosition && $bus->vehiclePosition->gps_fix_age_seconds !== null ? (int) $bus->vehiclePosition->gps_fix_age_seconds : 'null' }},
            lastGpsFixAt: '{{ $bus->vehiclePosition && $bus->vehiclePosition->last_gps_fix_at ? $bus->vehiclePosition->last_gps_fix_at->toIso8601String() : '' }}',
            stateMismatch: false,
            stateMismatchDetails: null,
            lastGpsAt: '{{ $bus->vehiclePosition && $bus->vehiclePosition->last_updated_at ? $bus->vehiclePosition->last_updated_at->toIso8601String() : "" }}'
        },
        @endforeach
    ];

    // Stops locations
    const stops = [
        @foreach($stops as $stop)
        { name: '{{ $stop->name }}', lat: {{ $stop->lat }}, lng: {{ $stop->lng }} },
        @endforeach
    ];

    const palette = ['#003F87', '#3B6D11', '#854F0B', '#E24B4A', '#378ADD', '#639922', '#BA7517'];
    const MONITOR_DEFAULT_CENTER = [14.5670, 121.0600];
    const MONITOR_DEFAULT_ZOOM = 13.5;
    const MONITOR_INITIAL_ROUTE_IDS = new Set(['1', '2', '3']);

    let map;
    let markersMap = {}; // mapping plate -> Leaflet marker
    let routesMap = {};  // mapping routeName -> Polyline
    let stopMarkers = [];
    let currentRouteFilter = 'all';
    let currentStatusFilter = 'all';
    let searchQuery = '';
    let sortKey = 'status';
    let currentSelectedBus = null;

    // Map layer styles
    let lightTile, satelliteTile;
    let activeLayer = 'light';

    function applyInitialMonitorViewport() {
        if (!map) return;

        const canonicalBounds = L.latLngBounds([]);
        Object.entries(routesMap).forEach(([key, polyline]) => {
            const routeId = key.split('-')[0];
            if (!MONITOR_INITIAL_ROUTE_IDS.has(routeId)) return;

            const bounds = typeof polyline.getBounds === 'function' ? polyline.getBounds() : null;
            if (bounds && bounds.isValid()) {
                canonicalBounds.extend(bounds);
            }
        });

        if (canonicalBounds.isValid()) {
            map.fitBounds(canonicalBounds, {
                paddingTopLeft: [24, 96],
                paddingBottomRight: [384, 32],
                maxZoom: 14
            });
            return;
        }

        map.setView(MONITOR_DEFAULT_CENTER, MONITOR_DEFAULT_ZOOM);
    }

    function initMonitorMap() {
        // Initialize Map
        map = L.map('map', {
            zoomControl: false,
            attributionControl: false
        }).setView(MONITOR_DEFAULT_CENTER, MONITOR_DEFAULT_ZOOM);

        // Map Tile Layers
        try {
            lightTile = L.gridLayer.googleMutant({
                type: 'roadmap'
            }).addTo(map);

            satelliteTile = L.gridLayer.googleMutant({
                type: 'satellite'
            });
        } catch (error) {
            console.error("Google Maps Mutant failed to load on fleet monitor map:", error);
            lightTile = L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                maxZoom: 20
            }).addTo(map);

            satelliteTile = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
                maxZoom: 18
            });
        }

        // Draw Route Polylines
        @foreach($routes as $index => $route)
            @if($route['map_geometry_source'] === 'route_variant')
                @foreach($route['map_variant_geometries'] as $variantGeometry)
                    @if($variantGeometry['polyline_coordinates'])
                        routesMap['{{ $route['id'] }}-{{ $variantGeometry['route_variant_id'] }}'] = L.polyline({!! json_encode($variantGeometry['polyline_coordinates']) !!}, {
                            color: palette[{{ $index }} % palette.length],
                            weight: 3,
                            opacity: 0.85
                        }).addTo(map);
                    @endif
                @endforeach
            @elseif($route['polyline_coordinates'])
                routesMap['{{ $route['id'] }}'] = L.polyline({!! json_encode($route['polyline_coordinates']) !!}, {
                    color: palette[{{ $index }} % palette.length],
                    weight: 3,
                    opacity: 0.85
                }).addTo(map);
            @endif
        @endforeach        // Draw Stops
        stops.forEach(stop => {
            let m = L.circleMarker([stop.lat, stop.lng], {
                radius: 4.5,
                fillColor: '#FFFFFF',
                fillOpacity: 1,
                color: '#003F87',
                weight: 1.5
            }).addTo(map);
            m.bindTooltip(stop.name, { direction: 'top', className: 'text-[11px] font-sans font-medium px-2 py-0.5 rounded border border-black/10' });
            stopMarkers.push(m);
        });

        // Draw Bus Markers
        renderBusMarkers();

        // Populate Vehicle List
        renderVehicleList();
        applyInitialMonitorViewport();

        // Check if there are query string parameters to select a bus
        const urlParams = new URLSearchParams(window.location.search);
        const focusBus = urlParams.get('bus');
        const focusRoute = urlParams.get('route');
        if (focusBus) {
            selectBus(focusBus);
        } else if (focusRoute) {
            filterByRoute(focusRoute);
        }
        
        // Trigger initial telemetry and overlay load immediately
        pollBusGpsPositions();

        // Start polling interval
        setInterval(pollBusGpsPositions, GPS_POLL_INTERVAL_MS);

        // Start ticking display updates for GPS age every 5s
        setInterval(() => {
            document.querySelectorAll('.last-gps-time').forEach(el => {
                const gpsAt = el.getAttribute('data-gps-at');
                if (gpsAt) {
                    el.innerText = formatTimeSince(gpsAt);
                }
            });
        }, 5000);
    }
    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", initMonitorMap);
    } else {
        initMonitorMap();
    }

    function formatTimeSince(isoString) {
        if (!isoString) return 'Never';
        const date = new Date(isoString);
        if (isNaN(date.getTime())) return 'Never';
        
        const now = new Date();
        const diffMs = now - date;
        const diffSecs = Math.max(0, Math.floor(diffMs / 1000));
        
        if (diffSecs < 60) {
            return `${diffSecs} sec ago`;
        }
        const diffMins = Math.floor(diffSecs / 60);
        if (diffMins < 60) {
            return `${diffMins} min ago`;
        }
        const diffHours = Math.floor(diffMins / 60);
        return `${diffHours} hr ago`;
    }

    function getBusIconHTML(bus) {
        let iconName = 'bus';
        if (bus.status === 'breakdown') iconName = 'alert-circle';
        const displayHeading = validDisplayHeading(bus.displayHeading);
        const directionArrow = displayHeading !== null
            ? `<div class="bus-direction-arrow" style="transform: translateX(-50%) rotate(${displayHeading}deg);"></div>`
            : '';
        
        return `<div class="bus-marker ${bus.status}">
                     ${directionArrow}
                     <i class="ti ti-${iconName}"></i>
                     <div class="bus-pulse"></div>
                 </div>`;
    }

    function getPopupContentHTML(bus) {
        let iconName = 'bus';
        if (bus.status === 'breakdown') iconName = 'alert-circle';
        
        let adherenceBadge = '';
        if (bus.routeAdherence === 'On Route') {
            adherenceBadge = `<span class="bg-[#EAF3DE] text-[#3B6D11] px-1.5 py-0.5 rounded text-[10px] font-semibold">On Route</span>`;
        } else {
            adherenceBadge = `<span class="bg-[#FCEBEB] text-[#E24B4A] px-1.5 py-0.5 rounded text-[10px] font-semibold">${bus.routeAdherence}</span>`;
        }

        let dwellTimerHtml = '';
        if (bus.dwellTimeSeconds && bus.dwellTimeSeconds > 0) {
            let minutes = Math.floor(bus.dwellTimeSeconds / 60);
            let seconds = bus.dwellTimeSeconds % 60;
            let timeStr = minutes > 0 ? `${minutes}m ${seconds}s` : `${seconds}s`;
            dwellTimerHtml = `
                <div class="flex items-center gap-2 text-slate-600">
                    <i class="ti ti-hourglass text-[14px]"></i>
                    <span>Dwell: <strong>${timeStr}</strong></span>
                </div>
            `;
        }

        return `
            <div class="bg-white p-3 space-y-3">
                <div class="flex justify-between items-center border-b border-black/6 pb-2">
                    <span class="font-mono-custom text-[13px] font-bold text-[#001F44]">${bus.plate}</span>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase ${getStatusChipClass(bus.status)}">${bus.statusLabel}</span>
                </div>
                <div class="space-y-1.5 text-[12px] text-[#001F44]">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-id text-slate-400 text-[14px]"></i>
                        <span>Driver: <strong>${bus.driver}</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-route text-slate-400 text-[14px]"></i>
                        <span>Route: <strong>${bus.routeName}</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-gauge text-slate-400 text-[14px]"></i>
                        <span>Speed: <strong>${bus.speed}</strong></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-users text-slate-400 text-[14px]"></i>
                        <span class="flex items-center gap-1">
                            Pax: <strong>${bus.pax} / ${bus.cap}</strong>
                            ${bus.pax >= 40 ? '<span class="rounded bg-[#FCEBEB] text-[#A32D2D] px-1 text-[9px] font-medium">Near full</span>' : ''}
                        </span>
                    </div>
                    <div class="h-px bg-black/5 my-1"></div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-map-pin text-[14px] text-purple-600"></i>
                        <span>Fence: <strong>${bus.currentFence || 'Open Road'}</strong></span>
                    </div>
                    ${dwellTimerHtml}
                    <div class="flex items-center gap-2">
                        <i class="ti ti-compass text-[14px] text-blue-600"></i>
                        <span>Adherence: ${adherenceBadge}</span>
                    </div>
                    <div class="flex items-center gap-1.5 text-[11px]">
                        <i class="ti ti-satellite text-[13px] text-slate-400"></i>
                        <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${gpsQualityChipClass(bus.gpsQualityState)}">${gpsQualityLabel(bus.gpsQualityState)}</span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-500 text-[11px]">
                        <i class="ti ti-ruler text-[13px]"></i>
                        <span>Nearest Stop: <strong>${bus.nearestStop || 'None'}</strong></span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-500 text-[11px]">
                        <i class="ti ti-clock text-[13px]"></i>
                        <span>Next: <strong>${bus.nextStop}</strong> · ETA ${bus.eta}</span>
                    </div>
                    <div class="flex items-center gap-2 text-slate-500 text-[11px]">
                        <i class="ti ti-history text-[13px]"></i>
                        <span>Last GPS: <strong class="last-gps-time" data-gps-at="${bus.lastGpsAt}">${formatTimeSince(bus.lastGpsAt)}</strong></span>
                    </div>
                </div>
                <button onclick="openSupportModal('${bus.plate}', '${bus.driver}', '${bus.route}')" class="w-full h-7 rounded border border-black/15 bg-white text-[11px] font-medium text-[#001F44] hover:bg-[#F5F8FF] transition-colors mt-1">
                    Send support message
                </button>
            </div>
        `;
    }

    function renderBusMarkers() {
        // Clear existing markers
        for (let plate in markersMap) {
            map.removeLayer(markersMap[plate]);
        }
        markersMap = {};

        // Filter buses
        let filtered = getFilteredBuses();

        filtered.forEach(bus => {
            const icon = L.divIcon({
                html: getBusIconHTML(bus),
                className: 'bus-marker-container',
                iconSize: [34, 34],
                iconAnchor: [17, 17],
                popupAnchor: [0, -17]
            });

            let marker = L.marker([bus.lat, bus.lng], { icon: icon }).addTo(map);
            
            let popupContent = getPopupContentHTML(bus);

            marker.bindPopup(popupContent, {
                className: 'custom-leaflet-popup',
                closeButton: false
            });

            marker.on('click', () => {
                currentSelectedBus = bus.plate;
                highlightListRow(bus.plate);
            });

            markersMap[bus.plate] = marker;
        });
    }

    function getStatusChipClass(status) {
        switch (status) {
            case 'active': return 'bg-[#E6F1FB] text-[#0C447C]';
            case 'near-full': return 'bg-[#FAEEDA] text-[#854F0B]';
            case 'breakdown': return 'bg-[#FCEBEB] text-[#A32D2D]';
            case 'offline': return 'bg-[#FCEBEB] text-[#A32D2D]';
            case 'stopped': return 'bg-[#F1EFE8] text-[#5F5E5A]';
            case 'idle': return 'bg-[#F1EFE8] text-[#5F5E5A]';
            default: return 'bg-slate-100 text-slate-600';
        }
    }

    function getProgressBarColor(pax, cap) {
        let pct = (pax / cap) * 100;
        if (pct < 80) return 'bg-[#003F87]';
        if (pct < 95) return 'bg-[#BA7517]';
        return 'bg-[#E24B4A]';
    }

    function getFilteredBuses() {
        return buses.filter(bus => {
            let routeMatch = currentRouteFilter === 'all' || bus.route === currentRouteFilter;
            let statusMatch = currentStatusFilter === 'all' || bus.status === currentStatusFilter;
            let searchMatch = searchQuery === '' || 
                              bus.plate.toLowerCase().includes(searchQuery.toLowerCase()) || 
                              bus.driver.toLowerCase().includes(searchQuery.toLowerCase());
            return routeMatch && statusMatch && searchMatch;
        });
    }

    function renderVehicleList() {
        const container = document.getElementById('vehicle-list-container');
        container.innerHTML = '';

        let filtered = getFilteredBuses();

        // Sort
        filtered.sort((x, y) => {
            if (sortKey === 'status') {
                return x.status.localeCompare(y.status);
            } else if (sortKey === 'plate') {
                return x.plate.localeCompare(y.plate);
            } else if (sortKey === 'occupancy') {
                return y.pax - x.pax;
            }
            return 0;
        });

        // Update indicators
        document.getElementById('bus-tracked-count').innerText = `${filtered.length} buses tracked`;
        document.getElementById('list-header-count').innerText = `${filtered.length} vehicles`;

        if (filtered.length === 0) {
            container.innerHTML = `
                <div class="p-6 text-center text-slate-400 text-[13px]">
                    No vehicles found matching filters
                </div>
            `;
            return;
        }

        filtered.forEach(bus => {
            const activeClass = currentSelectedBus === bus.plate ? 'bg-[#F5F8FF] border-l-[3px] border-[#003F87] shadow-sm' : 'border-l-[3px] border-transparent';
            const leftDotColor = bus.status === 'active' ? 'bg-[#003F87]' : 
                                 bus.status === 'near-full' ? 'bg-[#BA7517]' : 
                                 bus.status === 'breakdown' ? 'bg-[#E24B4A]' : 'bg-[#888780]';
            
            const warningRow = bus.issue ? `
                <div class="mt-1.5 text-[11px] font-medium flex items-center gap-1 ${bus.status === 'breakdown' ? 'text-[#A32D2D]' : 'text-[#854F0B]'}">
                    <i class="ti ti-alert-triangle"></i>
                    <span>${bus.issue}</span>
                </div>
            ` : '';

            let pct = Math.round((bus.pax / bus.cap) * 100);

            const badgeColors = {
                '1': 'bg-[#E6F1FB] text-[#003F87]',
                '2': 'bg-[#EAF3DE] text-[#3B6D11]',
                '3': 'bg-[#FAEEDA] text-[#854F0B]',
                '4': 'bg-[#FCEBEB] text-[#E24B4A]'
            };
            let routeBadgeColor = badgeColors[bus.route] || 'bg-slate-100 text-slate-700';

            const row = document.createElement('div');
            row.id = `bus-row-${bus.plate}`;
            row.className = `p-3.5 border-b border-slate-100 cursor-pointer hover:bg-[#F5F8FF]/55 transition-colors focus-within:bg-[#F5F8FF]/55 ${activeClass}`;
            row.onclick = () => selectBus(bus.plate);
            let spatialStatusHtml = '';
            if (bus.currentFence && bus.currentFence !== 'Outside Geofence') {
                let timerStr = '';
                if (bus.dwellTimeSeconds && bus.dwellTimeSeconds > 0) {
                    let mins = Math.floor(bus.dwellTimeSeconds / 60);
                    let secs = bus.dwellTimeSeconds % 60;
                    timerStr = mins > 0 ? ` (${mins}m ${secs}s)` : ` (${secs}s)`;
                }
                spatialStatusHtml += `
                    <div class="flex items-center gap-1.5 text-[11px] font-semibold text-purple-600 mt-1 select-none">
                        <i class="ti ti-map-pin text-[12px]"></i>
                        <span>Fence: ${bus.currentFence}${timerStr}</span>
                    </div>
                `;
            }

            if (bus.routeAdherence && bus.routeAdherence !== 'On Route') {
                spatialStatusHtml += `
                    <div class="flex items-center gap-1.5 text-[11px] font-bold text-red-500 mt-1 select-none">
                        <i class="ti ti-alert-triangle text-[12px]"></i>
                        <span>${bus.routeAdherence} (${bus.corridorDistance || 0}m off)</span>
                    </div>
                `;
            } else {
                spatialStatusHtml += `
                    <div class="flex items-center gap-1.5 text-[11px] text-slate-400 mt-0.5 select-none">
                        <i class="ti ti-compass text-[12px]"></i>
                        <span>Near: ${bus.nearestStop || 'None'}</span>
                    </div>
                `;
            }

            let detailsHtml = '';
            if (currentSelectedBus === bus.plate) {
                const currentStop = bus.currentStop || 'None';
                const upcomingStop = bus.upcomingStop || 'None';
                const nearestStop = bus.nearestStop || 'None';
                const currentFence = bus.currentFence || 'Outside Geofence';
                const routeStatus = bus.routeAdherence || 'On Route';
                const deviationSeverity = (bus.routeAdherence && bus.routeAdherence !== 'On Route') ? bus.routeAdherence.replace(' Deviation', '') : 'None';
                const completedStops = bus.completedStops ?? 0;
                const remainingStops = bus.remainingStops ?? 0;
                const completionPercentage = bus.completionPercentage ?? 0;
                const eta = bus.eta || '--';
                const speed = bus.speed || '0 km/h';

                detailsHtml = `
                    <div class="mt-3 rounded-xl border border-slate-200/80 bg-slate-50/80 p-3 text-[11px] text-slate-700 space-y-2 select-none">
                        <div class="grid grid-cols-2 gap-x-4 gap-y-2">
                            <div>
                                <span class="text-slate-400 block font-medium">Current Stop</span>
                                <strong class="text-[#001F44] text-[12px]">${currentStop}</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Upcoming Stop</span>
                                <strong class="text-[#001F44] text-[12px]">${upcomingStop}</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Nearest Stop</span>
                                <strong class="text-[#001F44] text-[12px]">${nearestStop}</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Current Geofence</span>
                                <strong class="text-purple-600 text-[12px]">${currentFence}</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Route Status</span>
                                <strong class="text-[12px] ${routeStatus === 'On Route' ? 'text-green-600' : 'text-red-500'}">${routeStatus}</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Deviation Severity</span>
                                <strong class="text-[12px] ${deviationSeverity === 'None' ? 'text-slate-600' : 'text-red-500'}">${deviationSeverity}</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Completed / Remaining</span>
                                <strong class="text-[#001F44] text-[12px]">${completedStops} / ${remainingStops}</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Trip Completion %</span>
                                <strong class="text-[#001F44] text-[12px]">${completionPercentage}%</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">ETA</span>
                                <strong class="text-[#001F44] text-[12px]">${eta}</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Current Speed</span>
                                <strong class="text-[#001F44] text-[12px]">${speed}</strong>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-medium">Last GPS Update</span>
                                <strong class="text-[#001F44] text-[12px] last-gps-time" data-gps-at="${bus.lastGpsAt}">${formatTimeSince(bus.lastGpsAt)}</strong>
                            </div>
                        </div>
                    </div>
                `;
            }

            row.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full ${leftDotColor}"></span>
                        <span class="font-mono-custom font-semibold text-[#001F44] text-[13px]">${bus.plate}</span>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase ${getStatusChipClass(bus.status)}">${bus.statusLabel}</span>
                </div>
                
                <div class="flex items-center gap-1 text-[12px] text-slate-500 mt-1">
                    <i class="ti ti-id text-[13px]"></i>
                    <span>${bus.driver}</span>
                </div>

                <div class="flex items-center gap-1.5 text-[11px] mt-1">
                    <i class="ti ti-satellite text-[12px] text-slate-400"></i>
                    <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${gpsQualityChipClass(bus.gpsQualityState)}">${gpsQualityLabel(bus.gpsQualityState)}</span>
                </div>

                ${spatialStatusHtml}

                <div class="flex items-center justify-between text-[11px] mt-1.5">
                    <span class="px-1.5 py-0.5 rounded font-medium ${routeBadgeColor}">Route ${bus.route}</span>
                    <span class="font-mono-custom font-medium text-slate-600">${bus.pax}/${bus.cap} pax (${pct}%)</span>
                </div>

                <!-- Progress bar -->
                <div class="w-full bg-slate-100 rounded-full h-1 mt-2 overflow-hidden">
                    <div class="h-full rounded-full ${getProgressBarColor(bus.pax, bus.cap)}" style="width: ${pct}%"></div>
                </div>

                ${detailsHtml}

                ${warningRow}
            `;
            container.appendChild(row);
        });
    }

    function selectBus(plate) {
        currentSelectedBus = plate;
        let bus = buses.find(b => b.plate === plate);
        if (!bus) return;

        // Recenter map on bus
        map.setView([bus.lat, bus.lng], 15.5);

        // Open popup
        if (markersMap[plate]) {
            markersMap[plate].openPopup();
        }

        highlightListRow(plate);
    }

    function highlightListRow(plate) {
        // Remove highlights
        buses.forEach(b => {
            const rowElement = document.getElementById(`bus-row-${b.plate}`);
            if (rowElement) {
                rowElement.className = rowElement.className.replace('bg-[#F5F8FF] border-l-[3px] border-[#003F87]', '');
            }
        });

        // Add highlight
        const activeRow = document.getElementById(`bus-row-${plate}`);
        if (activeRow) {
            activeRow.className += ' bg-[#F5F8FF] border-l-[3px] border-[#003F87]';
            activeRow.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    // Filters
    function filterByRoute(route) {
        currentRouteFilter = route;
        
        // Update chip UI
        document.querySelectorAll('.route-chip').forEach(btn => {
            btn.className = btn.className.replace('bg-white text-[#001F44] border border-slate-200/80 shadow-sm', 'text-slate-500 hover:bg-white/80 hover:text-[#001F44]');
        });

        const activeBtn = document.getElementById(`chip-route-${route}`);
        if (activeBtn) {
            activeBtn.className = activeBtn.className.replace('text-slate-500 hover:bg-white/80 hover:text-[#001F44]', 'bg-white text-[#001F44] border border-slate-200/80 shadow-sm');
        }

        // Highlight map route polyline
        for (let rKey in routesMap) {
            if (route === 'all') {
                routesMap[rKey].setStyle({ opacity: 0.85, weight: 3 });
            } else if (rKey === route) {
                routesMap[rKey].setStyle({ opacity: 1, weight: 5 });
            } else {
                routesMap[rKey].setStyle({ opacity: 0.15, weight: 2 });
            }
        }

        renderBusMarkers();
        renderVehicleList();
    }

    function filterByStatus(status) {
        currentStatusFilter = status;
        renderBusMarkers();
        renderVehicleList();
    }

    function searchVehicles(val) {
        searchQuery = val;
        renderBusMarkers();
        renderVehicleList();
    }

    function sortVehicles(val) {
        sortKey = val;
        renderVehicleList();
    }

    // Map actions
    function mapZoomIn() {
        map.zoomIn();
    }

    function mapZoomOut() {
        map.zoomOut();
    }

    function mapRecenter() {
        map.setView([14.5670, 121.0600], 13.5);
    }

    function toggleMapLayers() {
        if (activeLayer === 'light') {
            map.removeLayer(lightTile);
            satelliteTile.addTo(map);
            activeLayer = 'satellite';
            
            // Adjust stop markers for satellite
            stopMarkers.forEach(m => m.setStyle({ color: '#378ADD' }));
        } else {
            map.removeLayer(satelliteTile);
            lightTile.addTo(map);
            activeLayer = 'light';
            stopMarkers.forEach(m => m.setStyle({ color: '#003F87' }));
        }
    }
    // ─── Real-time GPS Position Polling ─────────────────────────────────────
    // The Blade template populates `buses[]` once at page load from the database.
    // Without polling, bus markers stay frozen at their page-load coordinates even
    // while drivers are actively reporting GPS telemetry. This interval fetches
    // fresh positions from the backend and moves markers without a full re-render.
    const GPS_POLL_INTERVAL_MS = {{ (int) \App\Models\SystemSetting::get('map_gps_polling_interval_ms', 5000) }};
    const GPS_POLL_URL = '{{ route("fleet.api.bus-gps-positions") }}';

    // Animate smoothly to new position using Ease in-out interpolation
    function animateMarker(marker, newLat, newLng, duration) {
        const startLat = marker.getLatLng().lat;
        const startLng = marker.getLatLng().lng;
        const startTime = performance.now();

        function step(currentTime) {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);

            // Ease in-out interpolation
            const ease = progress < 0.5
                ? 2 * progress * progress
                : -1 + (4 - 2 * progress) * progress;

            const lat = startLat + (newLat - startLat) * ease;
            const lng = startLng + (newLng - startLng) * ease;

            marker.setLatLng([lat, lng]);

            if (progress < 1) {
                requestAnimationFrame(step);
            }
        }

        requestAnimationFrame(step);
    }

    let geofenceLayers = [];
    let corridorLayers = [];
    let overlaysLoaded = false;

    function loadSpatialOverlays(geofences, corridors) {
        if (!map) return;
        if (overlaysLoaded) return;
        if (!geofences || !corridors) return;
        overlaysLoaded = true;

        geofenceLayers.forEach(l => map.removeLayer(l));
        geofenceLayers = [];
        corridorLayers.forEach(l => map.removeLayer(l));
        corridorLayers = [];

        // 1. Draw Geofences
        geofences.forEach(gf => {
            let layer;
            if (gf.geometry && gf.geometry.type === 'Polygon') {
                layer = L.polygon(gf.geometry.coordinates, {
                    color: '#7000cc',
                    weight: 1.5,
                    fillColor: '#7000cc',
                    fillOpacity: 0.1,
                    dashArray: '4, 4'
                }).addTo(map);
            } else {
                layer = L.circle([gf.lat, gf.lng], {
                    radius: gf.radius || 30,
                    color: '#7000cc',
                    weight: 1.5,
                    fillColor: '#7000cc',
                    fillOpacity: 0.1,
                    dashArray: '4, 4'
                }).addTo(map);
            }
            layer.bindTooltip(`${gf.name} (${gf.type})`, { sticky: true });
            geofenceLayers.push(layer);
        });

        // 2. Draw Corridors
        corridors.forEach(corr => {
            if (corr.geometry && corr.geometry.type === 'LineString') {
                let latLngs = corr.geometry.coordinates.map(coord => [coord[1], coord[0]]);
                
                let bufferLayer = L.polyline(latLngs, {
                    color: '#00cc88',
                    weight: corr.buffer_width * 2,
                    opacity: 0.12,
                    lineCap: 'round',
                    lineJoin: 'round'
                }).addTo(map);

                let centerLayer = L.polyline(latLngs, {
                    color: '#00cc88',
                    weight: 1.5,
                    opacity: 0.6,
                    dashArray: '5, 5'
                }).addTo(map);

                corridorLayers.push(bufferLayer);
                corridorLayers.push(centerLayer);
            }
        });
    }

    async function pollBusGpsPositions() {
        if (!map) return;
        try {
            const response = await fetch(GPS_POLL_URL);
            if (!response.ok) return;
            const data = await response.json();
            
            // Draw overlays once
            if (data.geofences && data.corridors) {
                loadSpatialOverlays(data.geofences, data.corridors);
            }

            if (!data.buses || !Array.isArray(data.buses)) return;

            data.buses.forEach(function(fresh) {
                const newLat = parseFloat(fresh.lat);
                const newLng = parseFloat(fresh.lng);
                if (isNaN(newLat) || isNaN(newLng)) return;

                // Update in-memory JS bus array
                const idx = buses.findIndex(function(b) { return b.plate === fresh.plate_number; });
                if (idx !== -1) {
                    buses[idx].lat              = newLat;
                    buses[idx].lng              = newLng;
                    buses[idx].movementState    = fresh.movement_state ?? null;
                    buses[idx].movementConfidence = fresh.movement_confidence ?? null;
                    buses[idx].movementReason   = fresh.movement_reason ?? null;
                    buses[idx].movementStateUpdatedAt = fresh.movement_state_updated_at ?? null;
                    buses[idx].stationaryDurationSeconds = fresh.stationary_duration_seconds ?? null;
                    buses[idx].gpsQualityState = fresh.gps_quality_state ?? 'UNKNOWN';
                    buses[idx].gpsQualityReason = fresh.gps_quality_reason ?? null;
                    buses[idx].gpsFixAgeSeconds = fresh.gps_fix_age_seconds ?? null;
                    buses[idx].lastGpsFixAt = fresh.last_gps_fix_at ?? null;
                    buses[idx].operationalStatus = fresh.operational_status ?? fresh.status ?? null;
                    buses[idx].status = statusKeyFromOperationalStatus(buses[idx].operationalStatus, (fresh.capacity || buses[idx].cap) > 0 && ((fresh.passengers ?? buses[idx].pax) / (fresh.capacity || buses[idx].cap) >= 0.8));
                    buses[idx].statusLabel = statusLabelFromOperationalStatus(buses[idx].operationalStatus);
                    buses[idx].speed            = formatDisplaySpeedKmh(fresh.speed_kmh ?? 0, fresh.movement_state ?? null);
                    buses[idx].speedMps         = fresh.speed_mps ?? null;
                    buses[idx].heading          = fresh.heading ?? null;
                    buses[idx].displayHeading   = fresh.display_heading ?? null;
                    buses[idx].headingSource    = fresh.heading_source ?? 'unavailable';
                    buses[idx].headingUpdatedAt = fresh.heading_updated_at ?? null;
                    buses[idx].pax              = fresh.passengers ?? buses[idx].pax;
                    buses[idx].nextStop         = fresh.next_stop  ?? buses[idx].nextStop;
                    buses[idx].eta              = fresh.eta        ? fresh.eta + ' min' : '--';
                    
                    buses[idx].nearestStop      = fresh.nearest_stop ?? 'None';
                    buses[idx].currentStop      = fresh.current_stop ?? 'None';
                    buses[idx].upcomingStop     = fresh.upcoming_stop ?? 'None';
                    buses[idx].currentFence     = fresh.current_fence ?? 'Outside Geofence';
                    buses[idx].dwellTimeSeconds = fresh.dwell_time_seconds ?? 0;
                    buses[idx].routeAdherence   = fresh.route_adherence ?? 'On Route';
                    buses[idx].corridorDistance = fresh.corridor_distance ?? 0;
                    buses[idx].tripId           = fresh.trip_id ?? null;
                    buses[idx].coordinateSource = fresh.coordinate_source ?? 'bus_fallback';
                    buses[idx].hasLiveTelemetry = !!fresh.has_live_telemetry;
                    buses[idx].stateMismatch    = !!fresh.state_mismatch;
                    buses[idx].stateMismatchDetails = fresh.state_mismatch_details ?? null;
                    buses[idx].lastGpsAt        = fresh.last_gps_at ?? '';

                    if (fresh.trip_progress) {
                        buses[idx].completedStops = fresh.trip_progress.completed_stops ?? 0;
                        buses[idx].remainingStops = fresh.trip_progress.remaining_stops ?? 0;
                        buses[idx].completionPercentage = fresh.trip_progress.completion_percentage ?? 0;
                    } else {
                        buses[idx].completedStops = 0;
                        buses[idx].remainingStops = 0;
                        buses[idx].completionPercentage = 0;
                    }

                    // Update driver / route from live trip data
                    if (fresh.driver_name) buses[idx].driver    = fresh.driver_name;
                    if (fresh.route_id)    buses[idx].route     = String(fresh.route_id);
                    if (fresh.route_name)  buses[idx].routeName = fresh.route_name;
                }

                // Smoothly animate the Leaflet marker to the updated position
                if (markersMap[fresh.plate_number]) {
                    animateMarker(markersMap[fresh.plate_number], newLat, newLng, 2000);
                    markersMap[fresh.plate_number].setIcon(L.divIcon({
                        html: getBusIconHTML(buses[idx]),
                        className: 'bus-marker-container',
                        iconSize: [34, 34],
                        iconAnchor: [17, 17],
                        popupAnchor: [0, -17]
                    }));
                    markersMap[fresh.plate_number].setPopupContent(getPopupContentHTML(buses[idx]));
                }
            });

            // Refresh list sidebar
            renderVehicleList();
        } catch (err) {
            console.warn('Fleet GPS poll error:', err);
        }
    }

    // ────────────────────────────────────────────────────────────────────────
</script>
</section>












