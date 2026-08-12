    // ==================== LIVE FLEET MAP MODULE ====================

    const LIVE_FLEET_DEFAULT_CENTER = [14.5764, 121.0851];
    const LIVE_FLEET_DEFAULT_ZOOM = 13.2;
    const LIVE_FLEET_ROUTE_CONTROL_GAP = 12;
    let liveFleetRouteControlResizeBound = false;

    function alignOfficialRoutesControl() {
        const mapCanvas = document.getElementById('live-map-canvas');
        const toolbar = document.getElementById('live-map-toolbar');
        const control = mapCanvas?.querySelector('.gopasig-route-map-ux');
        if (!control) return;

        const usesFloatingToolbar = window.matchMedia('(min-width: 1024px)').matches;
        control.style.left = usesFloatingToolbar ? '16px' : '12px';
        control.style.top = usesFloatingToolbar && toolbar?.offsetHeight
            ? `${toolbar.offsetTop + toolbar.offsetHeight + LIVE_FLEET_ROUTE_CONTROL_GAP}px`
            : '12px';
    }

    function bindOfficialRoutesControlAlignment() {
        if (liveFleetRouteControlResizeBound) return;
        window.addEventListener('resize', alignOfficialRoutesControl);
        liveFleetRouteControlResizeBound = true;
    }

    function escapeRouteFilterText(value) {
        return String(value ?? '').replace(/[&<>"']/g, c => ({ '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c]));
    }

    function routeFilterButtonClass(active = false) {
        return active
            ? "rounded-full bg-[#003F87] px-3 py-1 text-xs font-bold text-white transition cursor-pointer shrink-0"
            : "rounded-full bg-slate-50 border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer shrink-0";
    }

    function renderRouteFilterChips() {
        const strips = document.querySelectorAll('.map-chip-strip');
        if (!strips.length || !Array.isArray(routesDataDb)) return;

        const routes = routesDataDb.filter(route => route && route.id && route.name);
        strips.forEach(strip => {
            strip.innerHTML = '<span class="mr-1 shrink-0 text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Routes:</span>' +
                '<button onclick="toggleRouteFilter(\'all\')" data-route-filter="all" class="' + routeFilterButtonClass(activeRouteFilter === 'all') + '">All <span id="route-pill-all-count"></span></button>' +
                routes.map(route => {
                    const id = String(route.id);
                    return '<button onclick="toggleRouteFilter(\'' + escapeRouteFilterText(id) + '\')" data-route-filter="' + escapeRouteFilterText(id) + '" class="' + routeFilterButtonClass(activeRouteFilter === id) + '">' + escapeRouteFilterText(route.name) + ' <span id="route-pill-' + escapeRouteFilterText(id) + '-count"></span></button>';
                }).join('');
        });
    }
    function applyInitialLiveFleetViewport() {
        if (liveMap === null || window.GoPasigRouteMapUX) return;

        const canonicalBounds = L.latLngBounds([]);

        Object.entries(mapPolylinesMap).forEach(([key, polyline]) => {
            const routeId = key.split(':')[0];
            if (Array.isArray(routesDataDb) && routesDataDb.length && !routesDataDb.some(route => String(route.id) === routeId)) return;
            if (!liveMap.hasLayer(polyline)) return;

            const bounds = typeof polyline.getBounds === 'function' ? polyline.getBounds() : null;
            if (bounds && bounds.isValid()) {
                canonicalBounds.extend(bounds);
            }
        });

        if (canonicalBounds.isValid()) {
            liveMap.fitBounds(canonicalBounds, {
                paddingTopLeft: [24, 96],
                paddingBottomRight: [384, 32],
                maxZoom: 14
            });
            return;
        }

        liveMap.setView(LIVE_FLEET_DEFAULT_CENTER, LIVE_FLEET_DEFAULT_ZOOM);
    }

    // Initialize Leaflet Map with a one-time route-aware default viewport.
    function initLiveFleetMap() {
        if (liveMap !== null) {
            liveMap.invalidateSize();
            alignOfficialRoutesControl();
            return;
        }

        // Initialize Map centered on Pasig coords
        liveMap = L.map('live-map-canvas', { 
            zoomControl: false,
            attributionControl: false
        }).setView(LIVE_FLEET_DEFAULT_CENTER, LIVE_FLEET_DEFAULT_ZOOM);

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
        renderRouteFilterChips();
        renderMapPolylines();
        renderMapStops();
        renderMapMarkers();
        updateFleetSidebarList();
        updateFleetSummaryStats();
        updateRoutePillsCounts();
        updateRecentActivityUI();
        applyInitialLiveFleetViewport();

        // Leaflet Invalidate Size trigger
        liveMap.invalidateSize();
    }

    // Render Routes Polylines
    function renderMapPolylines() {
        if (window.GoPasigRouteMapUX) {
            window.GoPasigRouteMapUX.mount({ map: liveMap, routes: routesDataDb, compact: false, fitOnFirstRender: true });
            alignOfficialRoutesControl();
            bindOfficialRoutesControlAlignment();
            return;
        }
        // Clear existing polylines if any
        for (let key in mapPolylinesMap) {
            liveMap.removeLayer(mapPolylinesMap[key]);
        }
        mapPolylinesMap = {};

        routesDataDb.forEach(route => {
            if (route.status === 'Suspended' || route.status === 'suspended' || route.status === 'inactive' || route.status === 'Inactive') return;
            const geometries = route.map_geometry_source === 'route_variant'
                ? (route.map_variant_geometries || []).filter(item => item.polyline_coordinates?.length > 0)
                : [{ polyline_coordinates: route.polyline_coordinates }];
            geometries.forEach(geometry => {
            if (geometry.polyline_coordinates && geometry.polyline_coordinates.length > 0) {
                const color = routeColors[route.id.toString()] || '#003F87';
                const polyline = L.polyline(geometry.polyline_coordinates, {
                    color: color,
                    weight: 3.5,
                    opacity: 0.85
                });

                if (activeRouteFilter === 'all' || route.id.toString() === activeRouteFilter) {
                    polyline.addTo(liveMap);
                }

                const key = route.id + ':' + (geometry.route_variant_id || 'legacy');
                mapPolylinesMap[key] = polyline;
            }
            });
        });
    }

    // Render Designated Stop Circles (White circles, 8px, stroke 1.5px)
    function renderMapStops() {
        if (window.GoPasigRouteMapUX) return;
        // Clear existing stops
        mapStopCircles.forEach(circle => liveMap.removeLayer(circle));
        mapStopCircles = [];

        routesDataDb.forEach(route => {
            if (route.status === 'Suspended' || route.status === 'suspended' || route.status === 'inactive' || route.status === 'Inactive') return;
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
                    });

                    circle.routeId = route.id.toString();

                    if (activeRouteFilter === 'all' || route.id.toString() === activeRouteFilter) {
                        circle.addTo(liveMap);
                    }
                    
                    mapStopCircles.push(circle);
                });
            }
        });
    }

    function getValidDisplayHeading(heading) {
        if (heading === null || heading === undefined || heading === '') return null;
        const value = Number(heading);
        if (!Number.isFinite(value) || value < 0 || value >= 360) return null;
        return value;
    }

    function getDirectionArrowHtml(bus) {
        const displayHeading = getValidDisplayHeading(bus.displayHeading);
        if (displayHeading === null) return '';
        return `<div class="absolute -top-2 left-1/2 h-0 w-0 border-l-[5px] border-r-[5px] border-b-[11px] border-l-transparent border-r-transparent border-b-slate-900 opacity-90 drop-shadow-sm" style="transform: translateX(-50%) rotate(${displayHeading}deg); transform-origin: 50% 24px;"></div>`;
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
            const isVisible = (bus.has_active_trip || bus.status === 'Breakdown') && bus.status !== 'Inactive' && bus.status !== 'Maintenance';
            if (!isVisible) return;

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
                            ${getDirectionArrowHtml(bus)}
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
                            <div class="flex items-center gap-1.5 text-[11px]">
                                <i class="ti ti-satellite text-slate-400"></i>
                                <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${getGpsQualityChipClass(bus.gpsQualityState)}">${getGpsQualityLabel(bus.gpsQualityState)}</span>
                            </div>
                        </div>
                        <div class="mt-3.5 border-t border-slate-100 pt-2 text-center shrink-0">
                            <button onclick="GoPasigUI.alert('Secure message dispatched to driver ${bus.driver}.')" class="text-xs font-black text-[#003F87] hover:underline cursor-pointer flex items-center justify-center gap-1 w-full">
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

    // Global module selection state
    let selectedBusId = null;
    let followVehicleId = null;

    // Helper: HTML format for status badges with circular CSS indicator
    function getStatusChipHtml(status) {
        const badgeClass = statusBadgeColors[status] || "bg-slate-50 text-slate-500 border border-slate-200";
        const dotColor = status === 'Active' ? 'bg-[#639922]' : (status === 'Delayed' ? 'bg-[#BA7517]' : (status === 'Breakdown' ? 'bg-[#E24B4A]' : (status === 'Maintenance' ? 'bg-[#BA7517]' : 'bg-slate-400')));
        return `<span class="inline-flex items-center gap-1.5 rounded-full ${badgeClass} px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider shrink-0">
            <span class="h-1.5 w-1.5 rounded-full ${dotColor} inline-block"></span>
            ${status}
        </span>`;
    }


    function getGpsQualityLabel(state) {
        switch (String(state || 'UNKNOWN').toUpperCase()) {
            case 'GOOD': return 'GPS Good';
            case 'DEGRADED': return 'GPS Degraded';
            case 'STALE': return 'GPS Stale';
            case 'BLOCKED': return 'GPS Blocked';
            default: return 'GPS Unknown';
        }
    }

    function getGpsQualityChipClass(state) {
        switch (String(state || 'UNKNOWN').toUpperCase()) {
            case 'GOOD': return 'bg-emerald-50 text-emerald-700 border border-emerald-100';
            case 'DEGRADED': return 'bg-amber-50 text-amber-700 border border-amber-100';
            case 'STALE': return 'bg-rose-50 text-rose-700 border border-rose-100';
            case 'BLOCKED': return 'bg-slate-100 text-slate-600 border border-slate-200';
            default: return 'bg-slate-50 text-slate-500 border border-slate-200';
        }
    }
    // Calculate active counts per route and update route pills content/styles
    function updateRoutePillsCounts() {
        const getActiveBusesCount = (routeId) => {
            return fleetData.filter(bus => {
                const isVisible = (bus.has_active_trip || bus.status === 'Breakdown') && bus.status !== 'Inactive' && bus.status !== 'Maintenance';
                return isVisible && (routeId === 'all' || bus.route === routeId);
            }).length;
        };

        const routes = ['all', ...routesDataDb.map(route => String(route.id))];
        routes.forEach(routeId => {
            const count = getActiveBusesCount(routeId);
            const badgeEl = document.getElementById(`route-pill-${routeId}-count`);
            if (badgeEl) {
                badgeEl.textContent = `(${count})`;
            }
            // Add faded styling for zero-count pills (except 'all')
            const pillEl = document.querySelector(`[data-route-filter="${routeId}"]`);
            if (pillEl && routeId !== 'all') {
                if (count === 0) {
                    pillEl.classList.add('pill-count-zero');
                } else {
                    pillEl.classList.remove('pill-count-zero');
                }
            }
        });
    }

    // Update Fleet Summary stat counters (sidebar overview grid)
    function updateFleetSummaryStats() {
        const totalCount = fleetData.length;
        const activeCount = fleetData.filter(b => b.status === 'Active' || b.status === 'Delayed').length;
        const standbyCount = fleetData.filter(b => b.status === 'Inactive' || b.status === 'Idle').length;
        const maintenanceCount = fleetData.filter(b => b.status === 'Maintenance').length;
        const breakdownCount = fleetData.filter(b => b.status === 'Breakdown').length;

        const totalEl = document.getElementById('stats-total-fleet');
        const activeEl = document.getElementById('stats-active');
        const standbyEl = document.getElementById('stats-standby');
        const maintenanceEl = document.getElementById('stats-maintenance');
        const breakdownEl = document.getElementById('stats-breakdown');
        const totalTrackedEl = document.getElementById('sidebar-tracked-count');

        if (totalEl) totalEl.textContent = totalCount;
        if (activeEl) activeEl.textContent = activeCount;
        if (standbyEl) standbyEl.textContent = standbyCount;
        if (maintenanceEl) maintenanceEl.textContent = maintenanceCount;
        if (breakdownEl) breakdownEl.textContent = breakdownCount;
        if (totalTrackedEl) totalTrackedEl.textContent = `${totalCount} Buses Tracked`;
    }

    // Update Fleet scrollable Sidebar panel lists
    function updateFleetSidebarList() {
        const container = document.getElementById('fleet-sidebar-list');
        if (!container) return;
        container.innerHTML = '';

        let renderedCount = 0;

        fleetData.forEach(bus => {
            const isVisible = (bus.has_active_trip || bus.status === 'Breakdown') && bus.status !== 'Inactive' && bus.status !== 'Maintenance';
            if (!isVisible) return;

            // Apply filtering conditions
            const matchesRoute = activeRouteFilter === 'all' || bus.route === activeRouteFilter;
            const matchesStatus = activeStatusFilter === 'all' || bus.status === activeStatusFilter;
            
            let matchesSearch = true;
            if (window.currentSearchQuery) {
                const q = window.currentSearchQuery;
                const plateMatch = bus.plate.toLowerCase().includes(q);
                const driverMatch = bus.driver.toLowerCase().includes(q);
                const routeName = routeNames[bus.route] ? routeNames[bus.route].toLowerCase() : '';
                const routeMatch = routeName.includes(q) || `route ${bus.route}`.includes(q);
                matchesSearch = plateMatch || driverMatch || routeMatch;
            }

            if (matchesRoute && matchesStatus && matchesSearch) {
                renderedCount++;
                const card = document.createElement('div');
                card.id = `active-vehicle-card-${bus.id}`;
                
                // Active highlight styling check
                const isSelected = selectedBusId === bus.id;
                const borderBgClass = isSelected ? "border-[#003F87] bg-blue-50/20 shadow-sm" : "border-slate-200 bg-white";
                
                card.className = `active-vehicle-card rounded-xl border p-3 hover:border-[#003F87]/50 hover:shadow-sm transition duration-200 cursor-pointer space-y-2 relative ${borderBgClass}`;
                card.setAttribute('onclick', `selectActiveVehicle(${bus.id})`);

                const statusChip = getStatusChipHtml(bus.status);
                const routeName = routeNames[bus.route] ? routeNames[bus.route].split('|')[0].trim() : `Route ${bus.route}`;

                card.innerHTML = `
                    <div class="flex items-center justify-between border-b border-slate-50 pb-1.5 shrink-0">
                        <span class="font-mono text-xs font-extrabold text-slate-800 uppercase tracking-widest">${bus.plate}</span>
                        ${statusChip}
                    </div>
                    <div class="grid grid-cols-2 gap-x-2 gap-y-1 text-[10px] text-slate-500 font-semibold">
                        <div class="flex items-center gap-1 min-w-0">
                            <i class="ti ti-route text-slate-400"></i>
                            <span class="truncate" title="${routeName}">${routeName}</span>
                        </div>
                        <div class="flex items-center gap-1 min-w-0">
                            <i class="ti ti-user text-slate-400"></i>
                            <span class="truncate" title="${bus.driver}">${bus.driver}</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <i class="ti ti-gauge text-slate-400"></i>
                            <span>${bus.speed || 0} km/h</span>
                        </div>
                        <div class="flex items-center gap-1">
                            <i class="ti ti-clock text-slate-400"></i>
                            <span>ETA ${bus.eta} mins</span>
                        </div>
                        <div class="flex items-center gap-1 col-span-2">
                            <i class="ti ti-satellite text-slate-400"></i>
                            <span class="rounded-full px-1.5 py-0.5 text-[10px] font-semibold ${getGpsQualityChipClass(bus.gpsQualityState)}">${getGpsQualityLabel(bus.gpsQualityState)}</span>
                        </div>
                    </div>
                `;

                container.appendChild(card);
            }
        });

        // Show blank state empty indicator
        if (renderedCount === 0) {
            container.innerHTML = `
                <div class="py-8 px-4 text-center space-y-3 select-none">
                    <div class="h-12 w-12 mx-auto rounded-full bg-slate-55/10 flex items-center justify-center text-slate-400">
                        <i class="ti ti-bus text-xl"></i>
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-xs font-bold text-slate-800">No active vehicles</h3>
                        <p class="text-[10px] text-slate-500">No vehicles currently match your filters.</p>
                    </div>
                    <div class="flex items-center justify-center gap-2 pt-1">
                        <button onclick="resetToolbarFilters()" class="px-2.5 py-1.5 rounded-lg bg-slate-105 bg-slate-100 hover:bg-slate-200 text-[10px] font-bold text-slate-700 transition cursor-pointer border-none">
                            Reset Filters
                        </button>
                        <button onclick="triggerManualRefresh()" class="px-2.5 py-1.5 rounded-lg bg-[#003F87] hover:bg-[#002d62] text-[10px] font-bold text-white transition cursor-pointer border-none">
                            Refresh Tracker
                        </button>
                    </div>
                </div>
            `;
        }
    }

    // Render Recent Activity events directly from tripsData (ongoing/completed)
    function updateRecentActivityUI() {
        const container = document.getElementById('recent-activity-list');
        if (!container) return;

        if (!tripsData || tripsData.length === 0) {
            container.innerHTML = `
                <div class="py-6 text-center text-slate-400 text-xs select-none">
                    <i class="ti ti-activity text-lg mb-1 block"></i>
                    No recent activity events.
                </div>
            `;
            return;
        }

        container.innerHTML = tripsData.map(trip => {
            let eventText = "";
            const plate = trip.busPlate || "Vehicle";
            if (trip.status === 'Completed') {
                eventText = `<span class="text-slate-800 font-bold">${plate}</span> completed trip on <span class="text-slate-750 font-bold">${trip.route}</span>`;
            } else if (trip.status === 'Cancelled') {
                eventText = `<span class="text-slate-800 font-bold">${plate}</span> trip on <span class="text-slate-750 font-bold">${trip.route}</span> cancelled`;
            } else {
                eventText = `<span class="text-slate-800 font-bold">${plate}</span> departed terminal on <span class="text-slate-750 font-bold">${trip.route}</span>`;
            }

            const diffMs = Date.now() - trip.timestamp.getTime();
            const diffMins = Math.floor(diffMs / 60000);
            let timeStr = "Just now";
            if (diffMins > 0) {
                timeStr = `${diffMins} min${diffMins > 1 ? 's' : ''} ago`;
            }

            return `
                <div class="flex items-start gap-2 py-1.5 border-b border-slate-50 last:border-b-0 text-[10px]">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#003F87] mt-1.5 shrink-0"></span>
                    <div class="flex-1 min-w-0">
                        <p class="font-semibold text-slate-650 leading-normal">${eventText}</p>
                        <span class="text-[9px] text-slate-400 font-bold">${timeStr}</span>
                    </div>
                </div>
            `;
        }).join('');
    }

    // Update Section 4: Selected Vehicle Detail Panel
    function updateSelectedVehiclePanel(busId) {
        const container = document.getElementById('selected-vehicle-panel');
        if (!container) return;

        const bus = fleetData.find(b => b.id === busId);
        if (!bus) {
            container.innerHTML = `
                <div class="py-4 text-center text-slate-400 text-xs select-none">
                    <i class="ti ti-bus text-lg mb-1 block"></i>
                    Select a vehicle from the map or list to view details.
                </div>
            `;
            return;
        }

        const statusChip = getStatusChipHtml(bus.status);
        const routeText = routeNames[bus.route] ? routeNames[bus.route] : `Route ${bus.route}`;

        const isFollowing = followVehicleId === busId;
        const followBtnClass = isFollowing 
            ? "bg-emerald-600 hover:bg-emerald-700 text-white border-none font-bold shadow-sm" 
            : "bg-white hover:bg-slate-50 border border-slate-205 border-slate-200 text-slate-700 font-bold";
        const followIcon = isFollowing ? "ti-lock" : "ti-lock-open";
        const followText = isFollowing ? "Following" : "Follow Vehicle";

        const lastGpsAge = mapUpdateSeconds >= 5 ? 0 : mapUpdateSeconds;
        const lastGpsText = lastGpsAge === 0 ? "Just now" : `${lastGpsAge} sec${lastGpsAge > 1 ? 's' : ''} ago`;

        container.innerHTML = `
            <div class="space-y-3">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2">
                    <div class="flex flex-col leading-none">
                        <span class="font-mono text-sm font-black text-slate-900">${bus.plate}</span>
                        <span class="text-[9px] text-slate-400 font-bold uppercase mt-1">Plate Number</span>
                    </div>
                    ${statusChip}
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="space-y-0.5">
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Driver</span>
                        <p class="font-bold text-slate-800 truncate" title="${bus.driver}">${bus.driver}</p>
                    </div>
                    <div class="space-y-0.5">
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Route</span>
                        <p class="font-bold text-slate-800 truncate" title="${routeText}">${routeText.split('|')[0]}</p>
                    </div>
                    <div class="space-y-0.5">
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Passengers</span>
                        <p class="font-bold text-slate-800">${bus.passengers} / ${bus.capacity}</p>
                    </div>
                    <div class="space-y-0.5">
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Current Speed</span>
                        <p class="font-bold text-slate-800">${bus.speed || 0} km/h</p>
                    </div>
                    <div class="space-y-0.5">
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">ETA</span>
                        <p class="font-bold text-slate-800">${bus.eta} mins</p>
                    </div>
                    <div class="space-y-0.5">
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">GPS Signal</span>
                        <p class="font-bold text-emerald-650 text-emerald-600 flex items-center gap-1 leading-none mt-0.5 select-none">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse inline-block"></span>
                            Online
                        </p>
                    </div>
                    <div class="space-y-0.5 col-span-2">
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Next Stop</span>
                        <p class="font-bold text-slate-800 truncate">${bus.nextStop}</p>
                    </div>
                    <div class="space-y-0.5 col-span-2">
                        <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider">Last GPS Update</span>
                        <p class="font-bold text-slate-500" id="selected-gps-update-text">${lastGpsText}</p>
                    </div>
                </div>

                <div class="flex flex-col gap-2 pt-2 border-t border-slate-100">
                    <div class="flex gap-2">
                        <button onclick="locateBusOnMap(${bus.id})" class="bm-btn-primary flex-1 flex items-center justify-center gap-1.5 text-xs">
                            <i class="ti ti-focus-2"></i> Center Map
                        </button>
                        <button onclick="toggleFollowVehicle(${bus.id})" class="px-3 py-2 rounded-lg border text-xs flex-1 flex items-center justify-center gap-1.5 transition cursor-pointer ${followBtnClass}">
                            <i class="ti ${followIcon}"></i> ${followText}
                        </button>
                    </div>
                    <button onclick="viewBusTripDetails(${bus.id})" class="w-full py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-bold text-slate-700 transition cursor-pointer flex items-center justify-center gap-1.5">
                        <i class="ti ti-file-description"></i> View Trip Details
                    </button>
                </div>
            </div>
        `;
    }

    // Select Active Vehicle (highlights list card, centers map, details panel)
    function selectActiveVehicle(busId) {
        selectedBusId = busId;

        // Highlight card inside sidebar list
        document.querySelectorAll('.active-vehicle-card').forEach(card => {
            card.classList.remove('border-[#003F87]', 'bg-blue-50/20', 'shadow-sm');
            card.classList.add('border-slate-200', 'bg-white');
        });
        const activeCard = document.getElementById(`active-vehicle-card-${busId}`);
        if (activeCard) {
            activeCard.classList.remove('border-slate-200', 'bg-white');
            activeCard.classList.add('border-[#003F87]', 'bg-blue-50/20', 'shadow-sm');
        }

        // Disable previous follow mode and transfer it to the newly selected vehicle
        if (followVehicleId !== null) {
            followVehicleId = busId;
        }

        locateBusOnMap(busId);
        updateSelectedVehiclePanel(busId);
    }

    // Toggle Follow Vehicle mode
    function toggleFollowVehicle(busId) {
        if (followVehicleId === busId) {
            followVehicleId = null;
        } else {
            followVehicleId = busId;
            // Center camera immediately
            const bus = fleetData.find(b => b.id === busId);
            if (bus) {
                liveMap.setView([bus.lat, bus.lng], liveMap.getZoom(), { animate: true, duration: 0.5 });
            }
        }
        updateSelectedVehiclePanel(busId);
    }

    // Toggle Route Filter chips
    function toggleRouteFilter(routeLetter) {
        activeRouteFilter = routeLetter;

        // Reset and highlight route filter buttons styling
        const filterBtns = document.querySelectorAll('[data-route-filter]');
        filterBtns.forEach(btn => {
            btn.className = "rounded-full bg-slate-50 border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer shrink-0";
            const rId = btn.getAttribute('data-route-filter');
            const countEl = document.getElementById(`route-pill-${rId}-count`);
            if (countEl && countEl.textContent === '(0)' && rId !== 'all') {
                btn.classList.add('pill-count-zero');
            }
        });

        const activeBtn = document.querySelector(`[data-route-filter="${routeLetter}"]`);
        if (activeBtn) {
            activeBtn.className = "rounded-full bg-[#003F87] px-3 py-1 text-xs font-bold text-white transition cursor-pointer shrink-0";
        }

        if (window.GoPasigRouteMapUX) {
            window.GoPasigRouteMapUX.setRouteFilter(liveMap, routeLetter);
        }

        // Show/Hide Leaflet Polylines dynamically

        for (let route in mapPolylinesMap) {
            if (routeLetter === 'all' || route.split(':')[0] === routeLetter) {
                mapPolylinesMap[route].addTo(liveMap);
            } else {
                liveMap.removeLayer(mapPolylinesMap[route]);
            }
        }

        // Show/Hide Leaflet Stop Circles dynamically
        mapStopCircles.forEach(circle => {
            if (routeLetter === 'all' || circle.routeId === routeLetter) {
                circle.addTo(liveMap);
            } else {
                liveMap.removeLayer(circle);
            }
        });

        // Re-render markers and list
        renderMapMarkers();
        updateFleetSidebarList();
    }

    // Apply Universal Search and dropdown filters
    function applyToolbarFilters() {
        const query = document.getElementById('universal-search').value.toLowerCase().trim();
        const statusFilterVal = document.getElementById('map-status-filter').value;

        activeStatusFilter = statusFilterVal;
        window.currentSearchQuery = query;

        renderMapMarkers();
        updateFleetSidebarList();
    }

    // Reset all filters in controls bar
    function resetToolbarFilters() {
        document.getElementById('universal-search').value = '';
        document.getElementById('map-status-filter').value = 'all';
        activeRouteFilter = 'all';
        window.currentSearchQuery = '';

        toggleRouteFilter('all');
    }

    // Expose controller methods globally
    window.selectActiveVehicle = selectActiveVehicle;
    window.toggleFollowVehicle = toggleFollowVehicle;
    window.viewBusTripDetails = viewBusTripDetails;
    window.applyToolbarFilters = applyToolbarFilters;
    window.resetToolbarFilters = resetToolbarFilters;
    window.filterMapByStatus = applyToolbarFilters; // fallback binding

    // Locate bus on map (zoom, pan, open popup tooltip)
    function locateBusOnMap(busId, openPopup = true) {
        const bus = fleetData.find(b => b.id === busId);
        const marker = mapMarkersMap[busId];

        if (bus && marker) {
            liveMap.setView([bus.lat, bus.lng], 15.5, { animate: true, duration: 1 });
            if (openPopup) {
                setTimeout(() => {
                    marker.openPopup();
                }, 800);
            }
        }
    }

    // Auto-Refresh positions and coordinates periodically
    async function triggerManualRefresh() {
        if (liveMap === null) return;
        const refreshIcon = document.getElementById('map-refresh-icon');
        const lastUpdatedText = document.getElementById('map-last-updated');

        if (refreshIcon) refreshIcon.classList.add('animate-spin');

        // Apply visual load fade transition
        fleetData.forEach(bus => {
            const markerPin = document.getElementById(`map-marker-pin-${bus.id}`);
            if (markerPin) {
                markerPin.classList.add('opacity-40', 'scale-90');
            }
        });

        // Fetch latest coordinates
        if (typeof loadDatabaseFleetData === 'function') {
            try {
                await loadDatabaseFleetData();
            } catch (err) {
                console.error('Failed to sync live coordinates:', err);
            }
        }

        setTimeout(() => {
            if (refreshIcon) refreshIcon.classList.remove('animate-spin');
            
            // Re-render elements
            renderMapMarkers();
            updateFleetSidebarList();
            updateFleetSummaryStats();
            updateRoutePillsCounts();
            updateRecentActivityUI();

            // Persistence update
            if (selectedBusId !== null) {
                updateSelectedVehiclePanel(selectedBusId);
            }

            // Reset counter
            mapUpdateSeconds = 0;
            if (lastUpdatedText) {
                lastUpdatedText.textContent = "Just now";
            }
        }, 800);
    }

    // Dynamic timestamp count interval loop
    setInterval(() => {
        if (liveMap === null) return;
        mapUpdateSeconds++;
        const lastUpdatedText = document.getElementById('map-last-updated');
        if (lastUpdatedText) {
            if (mapUpdateSeconds === 0) {
                lastUpdatedText.textContent = "Just now";
            } else {
                lastUpdatedText.textContent = `${mapUpdateSeconds}s ago`;
            }
        }

        // Live Selected Vehicle GPS updates
        const selectedGpsAgeText = document.getElementById('selected-gps-update-text');
        if (selectedGpsAgeText) {
            const lastGpsAge = mapUpdateSeconds >= 5 ? 0 : mapUpdateSeconds;
            selectedGpsAgeText.textContent = lastGpsAge === 0 ? "Just now" : `${lastGpsAge} sec${lastGpsAge > 1 ? 's' : ''} ago`;
        }

        // Keep relative times ticking in recent activity log
        updateRecentActivityUI();

        // Auto-refresh every 5 seconds
        if (mapUpdateSeconds >= 5) {
            triggerManualRefresh();
        }
    }, 1000);

    // ==================== END LIVE FLEET MAP MODULE ======================
