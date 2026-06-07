<section id="screen-monitor" class="hidden" style="display: none;">
<style>
    /* Custom Leaflet Map styling */
    #map {
        height: 100%;
        width: 100%;
        background: #F1EFE8;
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
</style>

<div class="h-full flex flex-col space-y-4">
    <!-- PAGE TITLE ROW -->
    <div class="flex items-center justify-between shrink-0">
        <div>
            <h1 class="text-[20px] font-medium text-[#001F44]">Fleet monitor</h1>
            <div class="flex items-center gap-1.5 text-[13px] text-slate-500 mt-0.5">
                <span class="font-mono-custom" id="bus-tracked-count">12 buses tracked</span>
                <span class="text-slate-300">·</span>
                <div class="flex items-center gap-1">
                    <span class="pulse-dot"></span>
                    <span>Live</span>
                </div>
            </div>
        </div>

        <!-- Filter controls -->
        <div class="flex items-center gap-3">
            <!-- Route Filter Chips -->
            <div class="flex items-center gap-1 bg-slate-100 p-0.5 rounded-lg border border-black/5">
                <button onclick="filterByRoute('all')" id="chip-route-all" class="route-chip px-2.5 py-1 text-[12px] font-medium rounded-md transition-colors bg-white text-[#001F44] border border-black/5 shadow-sm">All</button>
                @foreach($routes as $route)
                <button onclick="filterByRoute('{{ $route->id }}')" id="chip-route-{{ $route->id }}" class="route-chip px-2.5 py-1 text-[12px] font-medium rounded-md transition-colors text-slate-500 hover:text-[#001F44]">{{ $route->name }}</button>
                @endforeach
            </div>

            <!-- Status filter dropdown -->
            <select id="status-filter" onchange="filterByStatus(this.value)" class="text-[13px] border border-black/15 bg-white rounded-lg px-3 py-1.5 font-medium text-[#001F44] outline-none">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="near-full">Near Full</option>
                <option value="breakdown">Breakdown</option>
                <option value="idle">Idle</option>
            </select>
        </div>
    </div>

    <!-- MAIN MONITOR GRID -->
    <div class="flex-grow flex gap-4 min-h-0">
        <!-- LEFT: MAP PANEL (68%) -->
        <div class="w-[68%] bg-white border border-black/10 rounded-xl relative overflow-hidden flex flex-col h-[calc(100vh-160px)]">
            <!-- Map Canvas -->
            <div id="map" class="flex-grow"></div>

            <!-- Map Controls Overlay -->
            <div class="absolute top-3 left-3 flex flex-col gap-1.5 bg-white border border-black/10 rounded-lg p-1.5 shadow-sm z-[1000]">
                <button onclick="mapZoomIn()" class="w-8 h-8 flex items-center justify-center text-[#001F44] hover:bg-slate-50 rounded transition-colors" title="Zoom In">
                    <i class="ti ti-plus text-[16px]"></i>
                </button>
                <button onclick="mapZoomOut()" class="w-8 h-8 flex items-center justify-center text-[#001F44] hover:bg-slate-50 rounded transition-colors" title="Zoom Out">
                    <i class="ti ti-minus text-[16px]"></i>
                </button>
                <button onclick="mapRecenter()" class="w-8 h-8 flex items-center justify-center text-[#001F44] hover:bg-slate-50 rounded transition-colors" title="Re-center Map">
                    <i class="ti ti-current-location text-[16px]"></i>
                </button>
                <div class="h-px bg-black/8 my-0.5"></div>
                <button onclick="toggleMapLayers()" class="w-8 h-8 flex items-center justify-center text-[#001F44] hover:bg-slate-50 rounded transition-colors" title="Toggle Layer">
                    <i class="ti ti-layers text-[16px]"></i>
                </button>
            </div>
        </div>

        <!-- RIGHT: VEHICLE LIST PANEL (32%) -->
        <div class="w-[32%] bg-white border border-black/10 rounded-xl flex flex-col h-[calc(100vh-160px)]">
            <!-- Panel Header -->
            <div class="px-4 py-3 border-b border-black/10 flex items-center justify-between shrink-0">
                <span class="text-[13px] font-semibold text-[#001F44]" id="list-header-count">12 vehicles</span>
                <select id="sort-dropdown" onchange="sortVehicles(this.value)" class="text-[12px] border border-black/10 bg-white rounded px-2 py-1 text-slate-600 outline-none">
                    <option value="status">Sort: Status</option>
                    <option value="plate">Sort: Plate</option>
                    <option value="occupancy">Sort: Occupancy</option>
                </select>
            </div>

            <!-- Search input -->
            <div class="px-4 py-2 border-b border-black/10 flex items-center gap-2 shrink-0 bg-slate-50/50">
                <i class="ti ti-search text-slate-400 text-[16px]"></i>
                <input type="text" id="vehicle-search" oninput="searchVehicles(this.value)" placeholder="Search plate or driver…" class="text-[13px] bg-transparent outline-none w-full border-none p-0 text-[#001F44] placeholder-slate-400">
            </div>

            <!-- Vehicle list -->
            <div class="flex-grow overflow-y-auto divide-y divide-black/6" id="vehicle-list-container">
                <!-- Javascript will inject list rows here -->
            </div>

            <!-- Panel Footer -->
            <div class="p-3 border-t border-black/10 shrink-0 bg-white">
                <button onclick="openMessageAllModal()" class="w-full h-9 flex items-center justify-center gap-1.5 rounded-lg border border-black/15 bg-white text-[12px] font-medium text-[#001F44] hover:bg-[#F5F8FF] transition-colors">
                    <i class="ti ti-speakerphone text-[14px]"></i>
                    <span>Send message to all drivers</span>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SEND SUPPORT MESSAGE MODAL -->
<div id="support-modal" class="fixed inset-0 bg-[#001F44]/40 flex items-center justify-center z-[2000] hidden">
    <div class="bg-white border border-black/12 rounded-xl shadow-xl w-full max-w-[420px] overflow-hidden flex flex-col animate-fade-in-up">
        <!-- Header -->
        <div class="px-4 py-3 border-b border-black/10 flex items-center justify-between">
            <span class="text-[15px] font-medium text-[#001F44]" id="modal-driver-title">Message driver — Juan dela Cruz</span>
            <button onclick="closeSupportModal()" class="text-slate-400 hover:text-slate-600 transition-colors">
                <i class="ti ti-x text-[18px]"></i>
            </button>
        </div>
        
        <!-- Body -->
        <div class="p-4 space-y-4">
            <!-- Recipient Chip -->
            <div class="flex">
                <span class="inline-flex rounded-full bg-[#E6F1FB] text-[#0C447C] px-3 py-1 text-[11px] font-semibold tracking-wide uppercase font-mono-custom" id="modal-recipient-chip">
                    PJY-8821 · Route A
                </span>
            </div>

            <!-- Textarea -->
            <div>
                <textarea id="modal-message-text" rows="3" placeholder="Type a message to this driver…" class="w-full border border-black/15 rounded-lg p-3 text-[13px] text-[#001F44] placeholder-slate-400 outline-none focus:border-[#003F87] transition-colors resize-none"></textarea>
            </div>

            <!-- Quick Messages -->
            <div>
                <span class="text-[11px] font-medium uppercase tracking-wider text-slate-400 block mb-2">Quick messages</span>
                <div class="flex flex-wrap gap-1.5">
                    <button onclick="populateMessage('Return to terminal')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 transition-colors rounded-lg text-[12px] text-slate-700">Return to terminal</button>
                    <button onclick="populateMessage('Maintain schedule')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 transition-colors rounded-lg text-[12px] text-slate-700">Maintain schedule</button>
                    <button onclick="populateMessage('Reduce speed')" class="px-3 py-1.5 bg-slate-100 hover:bg-slate-200 transition-colors rounded-lg text-[12px] text-slate-700">Reduce speed</button>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="px-4 py-3 border-t border-black/10 bg-slate-50 flex items-center justify-end gap-2">
            <button onclick="closeSupportModal()" class="h-9 px-4 rounded-lg text-[13px] font-medium text-slate-500 hover:bg-slate-100 transition-colors">Cancel</button>
            <button onclick="sendSupportMessage()" class="h-9 px-4 rounded-lg text-[13px] font-medium bg-[#001F44] hover:bg-[#00172F] text-white transition-colors">Send message</button>
        </div>
    </div>
</div>

<!-- TOAST CONTAINER -->
<div id="toast" class="toast-notification">
    <i class="ti ti-circle-check text-[#EAF3DE]"></i>
    <span id="toast-message">Message sent successfully!</span>
</div>



<script>
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
            status: '{{ $bus->status === 'active' ? ($bus->capacity > 0 && ($bus->passengers / $bus->capacity >= 0.8) ? 'near-full' : 'active') : ($bus->status === 'maintenance' ? 'breakdown' : 'idle') }}',
            statusLabel: '{{ $bus->status === 'active' ? ($bus->capacity > 0 && ($bus->passengers / $bus->capacity >= 0.8) ? 'Near Full' : 'Active') : ($bus->status === 'maintenance' ? 'Breakdown' : 'Idle') }}',
            speed: '{{ $bus->speed }} km/h',
            pax: {{ $bus->passengers }},
            cap: {{ $bus->capacity }},
            nextStop: '{{ $bus->next_stop ?? "Terminal" }}',
            eta: '{{ $bus->eta ? $bus->eta . " min" : "--" }}',
            issue: '{{ $busIssues[$bus->plate_number] ?? ($bus->status === 'maintenance' ? "In Maintenance" : "") }}'
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

    function initMonitorMap() {
        // Initialize Map
        map = L.map('map', {
            zoomControl: false,
            attributionControl: false
        }).setView([14.5670, 121.0600], 13.5);

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
            @if($route->polyline_coordinates)
                routesMap['{{ $route->id }}'] = L.polyline({!! json_encode($route->polyline_coordinates) !!}, {
                    color: palette[{{ $index }} % palette.length],
                    weight: 3,
                    opacity: 0.85
                }).addTo(map);
            @endif
        @endforeach

        // Draw Stops
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

        // Check if there are query string parameters to select a bus
        const urlParams = new URLSearchParams(window.location.search);
        const focusBus = urlParams.get('bus');
        const focusRoute = urlParams.get('route');
        if (focusBus) {
            selectBus(focusBus);
        } else if (focusRoute) {
            filterByRoute(focusRoute);
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", initMonitorMap);
    } else {
        initMonitorMap();
    }

    function getBusIconHTML(bus) {
        let iconName = 'bus';
        if (bus.status === 'breakdown') iconName = 'alert-circle';
        
        return `<div class="bus-marker ${bus.status}">
                    <i class="ti ti-${iconName}"></i>
                    <div class="bus-pulse"></div>
                </div>`;
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
            
            // Tooltip popup HTML
            let popupContent = `
                <div class="bg-white p-3 space-y-3">
                    <div class="flex justify-between items-center border-b border-black/6 pb-2">
                        <span class="font-mono-custom text-[13px] font-bold text-[#001F44]">${bus.plate}</span>
                        <span class="rounded-full px-2 py-0.2 text-[10px] font-semibold tracking-wide uppercase ${getStatusChipClass(bus.status)}">${bus.statusLabel}</span>
                    </div>
                    <div class="space-y-1.5 text-[12px] text-[#001F44]">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-id text-slate-400 text-[14px]"></i>
                            <span>${bus.driver}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="ti ti-route text-slate-400 text-[14px]"></i>
                            <span>${bus.routeName}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="ti ti-gauge text-slate-400 text-[14px]"></i>
                            <span>${bus.speed}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <i class="ti ti-users text-slate-400 text-[14px]"></i>
                            <span class="flex items-center gap-1">
                                ${bus.pax} / ${bus.cap} pax
                                ${bus.pax >= 40 ? '<span class="rounded bg-[#FCEBEB] text-[#A32D2D] px-1 text-[9px] font-medium">Near full</span>' : ''}
                            </span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-500">
                            <i class="ti ti-clock text-[14px]"></i>
                            <span>Next: <strong>${bus.nextStop}</strong> · ETA ${bus.eta}</span>
                        </div>
                    </div>
                    <button onclick="openSupportModal('${bus.plate}', '${bus.driver}', '${bus.route}')" class="w-full h-7 rounded border border-black/15 bg-white text-[11px] font-medium text-[#001F44] hover:bg-[#F5F8FF] transition-colors mt-1">
                        Send support message
                    </button>
                </div>
            `;

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
            const activeClass = currentSelectedBus === bus.plate ? 'bg-[#F5F8FF] border-l-[3px] border-[#003F87]' : '';
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
            row.className = `p-3.5 border-b border-black/6 cursor-pointer hover:bg-[#F5F8FF]/50 transition-colors ${activeClass}`;
            row.onclick = () => selectBus(bus.plate);

            row.innerHTML = `
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="h-2 w-2 rounded-full ${leftDotColor}"></span>
                        <span class="font-mono-custom font-semibold text-[#001F44] text-[13px]">${bus.plate}</span>
                    </div>
                    <span class="rounded-full px-2 py-0.2 text-[10px] font-semibold tracking-wide uppercase ${getStatusChipClass(bus.status)}">${bus.statusLabel}</span>
                </div>
                
                <div class="flex items-center gap-1 text-[12px] text-slate-500 mt-1">
                    <i class="ti ti-id text-[13px]"></i>
                    <span>${bus.driver}</span>
                </div>

                <div class="flex items-center justify-between text-[11px] mt-1.5">
                    <span class="px-1.5 py-0.5 rounded font-medium ${routeBadgeColor}">Route ${bus.route}</span>
                    <span class="font-mono-custom font-medium text-slate-600">${bus.pax}/${bus.cap} pax (${pct}%)</span>
                </div>

                <!-- Progress bar -->
                <div class="w-full bg-slate-100 rounded-full h-1 mt-2 overflow-hidden">
                    <div class="h-full rounded-full ${getProgressBarColor(bus.pax, bus.cap)}" style="width: ${pct}%"></div>
                </div>

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
            btn.className = btn.className.replace('bg-white text-[#001F44] border border-black/5 shadow-sm', 'text-slate-500 hover:text-[#001F44]');
        });

        const activeBtn = document.getElementById(`chip-route-${route}`);
        if (activeBtn) {
            activeBtn.className = activeBtn.className.replace('text-slate-500 hover:text-[#001F44]', 'bg-white text-[#001F44] border border-black/5 shadow-sm');
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

    // Modal Actions
    function openSupportModal(plate, driver, route) {
        document.getElementById('modal-driver-title').innerText = `Message driver — ${driver}`;
        document.getElementById('modal-recipient-chip').innerText = `${plate} · Route ${route}`;
        document.getElementById('modal-message-text').value = '';
        document.getElementById('support-modal').classList.remove('hidden');
    }

    function openMessageAllModal() {
        document.getElementById('modal-driver-title').innerText = `Message all active drivers`;
        document.getElementById('modal-recipient-chip').innerText = `All Active Routes`;
        document.getElementById('modal-message-text').value = '';
        document.getElementById('support-modal').classList.remove('hidden');
    }

    function closeSupportModal() {
        document.getElementById('support-modal').classList.add('hidden');
    }

    function populateMessage(text) {
        document.getElementById('modal-message-text').value = text;
    }

    function sendSupportMessage() {
        const text = document.getElementById('modal-message-text').value;
        if (!text) {
            alert('Please enter a message before sending.');
            return;
        }

        // Close modal
        closeSupportModal();

        // Show Toast
        const toast = document.getElementById('toast');
        document.getElementById('toast-message').innerText = `Message broadcasted successfully!`;
        toast.classList.add('show');

        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
</script>
</section>
