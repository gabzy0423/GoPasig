    // ==================== LIVE FLEET MAP MODULE ====================

    // Initialize Leaflet Map (Centered on Pasig City Metro Manila, Zoom 13)
    function initLiveFleetMap() {
        if (liveMap !== null) {
            liveMap.invalidateSize();
            return;
        }

        // Initialize Map centered on Pasig coords
        liveMap = L.map('live-map-canvas', { 
            zoomControl: false,
            attributionControl: false
        }).setView([14.5764, 121.0851], 13.2);

        try {
            // Load official Google Maps roadmap layer using Google Maps API
            L.gridLayer.googleMutant({
                type: 'roadmap'
            }).addTo(liveMap);
        } catch (error) {
            console.error("Google Maps Mutant failed to load, falling back to CartoDB:", error);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
                maxZoom: 20
            }).addTo(liveMap);
        }

        // Zoom Control to bottom-right (clean layout)
        L.control.zoom({ position: 'bottomright' }).addTo(liveMap);

        // Render everything
        renderMapPolylines();
        renderMapStops();
        renderMapMarkers();
        updateFleetSidebarList();
        updateFleetSummaryStats();

        // Leaflet Invalidate Size trigger
        liveMap.invalidateSize();
    }

    // Render Routes Polylines
    function renderMapPolylines() {
        // Clear existing polylines if any
        for (let key in mapPolylinesMap) {
            liveMap.removeLayer(mapPolylinesMap[key]);
        }
        mapPolylinesMap = {};

        routesDataDb.forEach(route => {
            if (route.polyline_coordinates && route.polyline_coordinates.length > 0) {
                const color = routeColors[route.id.toString()] || '#003F87';
                mapPolylinesMap[route.id.toString()] = L.polyline(route.polyline_coordinates, {
                    color: color,
                    weight: 3.5,
                    opacity: 0.85
                }).addTo(liveMap);
            }
        });
    }

    // Render Designated Stop Circles (White circles, 8px, stroke 1.5px)
    function renderMapStops() {
        // Clear existing stops
        mapStopCircles.forEach(circle => liveMap.removeLayer(circle));
        mapStopCircles = [];

        routesDataDb.forEach(route => {
            if (route.stops && route.stops.length > 0) {
                const strokeColor = routeColors[route.id.toString()] || '#003F87';
                route.stops.forEach(stop => {
                    const circle = L.circleMarker([parseFloat(stop.lat), parseFloat(stop.lng)], {
                        radius: 4.5,
                        fillColor: 'white',
                        fillOpacity: 1,
                        color: strokeColor,
                        weight: 2
                    }).bindTooltip(stop.name, {
                        direction: 'top',
                        className: 'font-sans font-bold text-[9px] px-1.5 py-0.5 rounded shadow-sm border border-slate-100'
                    }).addTo(liveMap);
                    
                    mapStopCircles.push(circle);
                });
            }
        });
    }

    // Render animated pulsing circle bus markers colored by status
    function renderMapMarkers() {
        if (liveMap === null) return;
        // Clear existing markers
        for (let key in mapMarkersMap) {
            liveMap.removeLayer(mapMarkersMap[key]);
        }
        mapMarkersMap = {};

        fleetData.forEach(bus => {
            // Apply filtering conditions
            const matchesRoute = activeRouteFilter === 'all' || bus.route === activeRouteFilter;
            const matchesStatus = activeStatusFilter === 'all' || bus.status === activeStatusFilter;

            if (matchesRoute && matchesStatus) {
                const color = statusColors[bus.status] || '#888780';
                
                // DivIcon to render pulsing animated border and Tabler Bus Icon inside
                const iconHtml = `
                    <div class="relative flex items-center justify-center">
                        ${bus.status === 'Active' || bus.status === 'Delayed' ? `
                            <span class="absolute inline-flex h-8 w-8 animate-ping rounded-full opacity-20" style="background-color: ${color};"></span>
                        ` : ''}
                        
                        <div class="relative flex h-7 w-7 items-center justify-center rounded-full text-white border-2 border-white shadow-md transition-all duration-300" 
                             style="background-color: ${color};" id="map-marker-pin-${bus.id}">
                            <i class="ti ti-bus text-[10px]"></i>
                        </div>
                    </div>
                `;

                const divIcon = L.divIcon({
                    html: iconHtml,
                    className: 'custom-bus-marker-icon',
                    iconSize: [28, 28],
                    iconAnchor: [14, 14],
                    popupAnchor: [0, -14]
                });

                // Create and bind popover popup to marker
                const statusChip = getStatusChipHtml(bus.status);
                const popupContent = `
                    <div class="w-[220px] font-sans p-1 text-slate-800 leading-normal">
                        <div class="flex items-center justify-between border-b border-slate-100 pb-2 mb-2 shrink-0">
                            <span class="text-xs font-black tracking-tight text-slate-900">${bus.plate}</span>
                            ${statusChip}
                        </div>
                        <div class="space-y-1.5 text-xs font-semibold text-slate-600">
                            <div class="flex items-center gap-1.5">
                                <i class="ti ti-id text-slate-400"></i>
                                <span>Driver: <strong class="text-slate-800">${bus.driver}</strong></span>
                            </div>
                            <div class="flex justify-between border-b border-slate-100/50 pb-1">
                                <span>Speed: <strong class="text-slate-800">${bus.speed} km/h</strong></span>
                                <span>ETA: <strong class="text-slate-800">${bus.eta} min</strong></span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <i class="ti ti-users text-slate-400"></i>
                                <span>Passengers: <strong class="text-slate-800">${bus.passengers} / ${bus.capacity}</strong></span>
                            </div>
                            <div class="text-[11px] bg-slate-50 border border-slate-100 p-2 rounded-lg text-slate-500">
                                Next: <strong class="text-slate-700">${bus.nextStop}</strong>
                            </div>
                        </div>
                        <div class="mt-3.5 border-t border-slate-100 pt-2 text-center shrink-0">
                            <button onclick="alert('Secure message dispatched to driver ${bus.driver}.')" class="text-xs font-black text-[#003F87] hover:underline cursor-pointer flex items-center justify-center gap-1 w-full">
                                <i class="ti ti-message-2 text-sm"></i>
                                Message driver
                            </button>
                        </div>
                    </div>
                `;

                const marker = L.marker([bus.lat, bus.lng], { icon: divIcon })
                    .bindPopup(popupContent, { closeButton: false })
                    .addTo(liveMap);

                mapMarkersMap[bus.id] = marker;
            }
        });
    }

    // Helper: HTML format for status badges
    function getStatusChipHtml(status) {
        const badgeClass = statusBadgeColors[status] || "bg-slate-50 text-slate-500 border border-slate-200";
        return `<span class="inline-flex rounded-full ${badgeClass} px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider shrink-0">${status}</span>`;
    }

    // Update Fleet Summary stat counters (2x2 grid)
    function updateFleetSummaryStats() {
        let active = 0, delayed = 0, breakdown = 0, idle = 0;
        
        fleetData.forEach(bus => {
            if (bus.status === 'Active') active++;
            else if (bus.status === 'Delayed') delayed++;
            else if (bus.status === 'Breakdown') breakdown++;
            else if (bus.status === 'Idle') idle++;
        });

        document.getElementById('stats-active').textContent = active;
        document.getElementById('stats-delayed').textContent = delayed;
        document.getElementById('stats-alerts').textContent = breakdown;
        document.getElementById('stats-idle').textContent = idle;
    }

    // Update Fleet scrollable Sidebar panel lists
    function updateFleetSidebarList() {
        const container = document.getElementById('fleet-sidebar-list');
        container.innerHTML = '';

        fleetData.forEach(bus => {
            // Apply filtering conditions
            const matchesRoute = activeRouteFilter === 'all' || bus.route === activeRouteFilter;
            const matchesStatus = activeStatusFilter === 'all' || bus.status === activeStatusFilter;

            if (matchesRoute && matchesStatus) {
                const card = document.createElement('div');
                card.className = "rounded-xl border border-slate-200 bg-white p-3 hover:border-[#003F87]/25 transition duration-200 space-y-2.5";
                
                const statusChip = getStatusChipHtml(bus.status);
                const routeDotColor = routeColors[bus.route] || '#888780';
                
                // Capacity Progress Calculation (>80% turns Red)
                const percent = Math.min(100, Math.round((bus.passengers / bus.capacity) * 100));
                const isOverloaded = percent > 80;
                const barColor = isOverloaded ? 'bg-[#E24B4A]' : 'bg-[#003F87]';
                const capacityWarning = isOverloaded ? `
                    <span class="flex items-center text-[#E24B4A] gap-0.5 text-[10px]" title="Bus capacity critical">
                        <i class="ti ti-alert-triangle text-xs animate-pulse"></i>
                    </span>
                ` : '';

                card.innerHTML = `
                    <!-- Top Row -->
                    <div class="flex items-center justify-between border-b border-slate-50 pb-1.5 shrink-0">
                        <span class="font-mono text-xs font-extrabold text-slate-800 uppercase tracking-widest">${bus.plate}</span>
                        ${statusChip}
                    </div>

                    <!-- Row 2: Driver -->
                    <div class="flex items-center gap-1.5 text-slate-500 leading-none">
                        <i class="ti ti-id text-slate-400 text-sm"></i>
                        <span class="text-[11px] font-semibold text-slate-600">${bus.driver}</span>
                    </div>

                    <!-- Row 3: Route badge -->
                    <div class="flex items-center gap-1.5 leading-none">
                        <span class="h-2 w-2 rounded-full" style="background-color: ${routeDotColor};"></span>
                        <span class="text-[11px] font-extrabold text-slate-500 uppercase tracking-wider">${routeNames[bus.route]}</span>
                    </div>

                    <!-- Row 4: Capacity Bar -->
                    <div class="space-y-1 shrink-0">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">${bus.passengers} / ${bus.capacity} Passengers</span>
                            ${capacityWarning}
                        </div>
                        <div class="h-1 w-full bg-[#E6F1FB] rounded-full overflow-hidden">
                            <div class="${barColor} h-full rounded-full transition-all duration-500" style="width: ${percent}%;"></div>
                        </div>
                    </div>

                    <!-- Row 5: Next stop eta -->
                    <div class="text-[10px] bg-slate-50 border border-slate-100 px-2 py-1.5 rounded-lg text-slate-500 shrink-0">
                        Next: <strong class="text-slate-700">${bus.nextStop}</strong> — ${bus.eta} min
                    </div>

                    <!-- Row 6: Locate button -->
                    <button onclick="locateBusOnMap(${bus.id})" 
                            class="flex w-full items-center justify-center gap-1.5 rounded-lg border border-slate-200 bg-white py-1.8 text-[10px] font-extrabold text-[#003F87] transition hover:bg-slate-50 cursor-pointer">
                        <i class="ti ti-map-pin text-xs"></i>
                        Locate on map
                    </button>
                `;

                container.appendChild(card);
            }
        });

        // Show blank state empty indicator
        if (container.children.length === 0) {
            container.innerHTML = `
                <div class="py-12 text-center space-y-2 shrink-0">
                    <div class="h-10 w-10 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                        <i class="ti ti-search text-base"></i>
                    </div>
                    <p class="text-xs font-bold text-slate-400">No active vehicles match filters.</p>
                </div>
            `;
        }
    }

    // Toggle Route Filter chips
    function toggleRouteFilter(routeLetter) {
        activeRouteFilter = routeLetter;

        // Reset and highlight route filter buttons styling
        const filterBtns = document.querySelectorAll('[data-route-filter]');
        filterBtns.forEach(btn => {
            btn.className = "rounded-full bg-slate-50 border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer";
        });

        const activeBtn = document.querySelector(`[data-route-filter="${routeLetter}"]`);
        if (activeBtn) {
            activeBtn.className = "rounded-full bg-[#003F87] px-3.5 py-1.5 text-xs font-bold text-white transition cursor-pointer";
        }

        // Show/Hide Leaflet Polylines dynamically
        for (let route in mapPolylinesMap) {
            if (routeLetter === 'all' || route === routeLetter) {
                mapPolylinesMap[route].addTo(liveMap);
            } else {
                liveMap.removeLayer(mapPolylinesMap[route]);
            }
        }

        // Re-render markers and list
        renderMapMarkers();
        updateFleetSidebarList();
    }

    // Toggle Status Filter dropdown
    function filterMapByStatus() {
        const filterSelect = document.getElementById('map-status-filter');
        activeStatusFilter = filterSelect.value;

        // Re-render markers and list
        renderMapMarkers();
        updateFleetSidebarList();
    }

    // Locate bus on map (zoom, pan, open popup tooltip)
    function locateBusOnMap(busId) {
        const bus = fleetData.find(b => b.id === busId);
        const marker = mapMarkersMap[busId];

        if (bus && marker) {
            // Pan and zoom
            liveMap.setView([bus.lat, bus.lng], 15.5, { animate: true, duration: 1 });
            // Open popup
            setTimeout(() => {
                marker.openPopup();
            }, 800);
        }
    }

    // Auto-Refresh positions and coordinates periodically
    async function triggerManualRefresh() {
        if (liveMap === null) return;
        const refreshIcon = document.getElementById('map-refresh-icon');
        const lastUpdatedText = document.getElementById('map-last-updated');

        // Spin refresh icon animation
        refreshIcon.classList.add('animate-spin');

        // Apply visual load fade / pulse transition to Leaflet markers
        fleetData.forEach(bus => {
            const markerPin = document.getElementById(`map-marker-pin-${bus.id}`);
            if (markerPin) {
                markerPin.classList.add('opacity-40', 'scale-90');
            }
        });

        // Load the actual latest database fleet data (coordinates posted by active drivers)
        if (typeof loadDatabaseFleetData === 'function') {
            try {
                await loadDatabaseFleetData();
            } catch (err) {
                console.error('Failed to sync live coordinates:', err);
            }
        }

        // Set refresh delay simulation
        setTimeout(() => {
            refreshIcon.classList.remove('animate-spin');
            
            // Re-render elements
            renderMapMarkers();
            updateFleetSidebarList();
            updateFleetSummaryStats();

            // Reset counter
            mapUpdateSeconds = 0;
            if (lastUpdatedText) {
                lastUpdatedText.textContent = "Last updated just now";
            }
        }, 800);
    }

    // Dynamic timestamp count interval loop
    setInterval(() => {
        if (liveMap === null) return;
        mapUpdateSeconds++;
        const lastUpdatedText = document.getElementById('map-last-updated');
        if (lastUpdatedText) {
            lastUpdatedText.textContent = `Last updated ${mapUpdateSeconds}s ago`;
        }

        // Trigger auto-refresh every 10 seconds silently
        if (mapUpdateSeconds >= 10) {
            triggerManualRefresh();
        }
    }, 1000);

    // ==================== END LIVE FLEET MAP MODULE ====================
