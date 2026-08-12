let map;
let busMarkers = {};
let stopMarkers = [];
let routePolylines = [];
let activeBuses = [];
let selectedRouteId = null;
let visibleDirections = { outbound: true, inbound: true };
let HTMLMarker;

// Stops & Routes raw JSON passed securely from window.GoPasig object
const stopsData = window.GoPasig?.stopsData || [];
const routesData = window.GoPasig?.routesData || [];

function initMap() {
    // Define the custom HTML Marker class dynamically now that 'google' is fully loaded
    HTMLMarker = class extends google.maps.OverlayView {
        constructor(pos, bus, map) {
            super();
            this.pos = pos;
            this.bus = bus;
            this.setMap(map);
        }
        
        onAdd() {
            this.div = document.createElement('div');
            this.div.style.position = 'absolute';
            this.div.style.cursor = 'pointer';
            this.div.style.zIndex = '100';
            
            const isActive = this.bus.status === 'active';
            let fillColor = '#003F87';
            if (this.bus.status === 'delayed') fillColor = '#BA7517';
            else if (this.bus.status === 'breakdown') fillColor = '#E24B4A';
            else if (this.bus.status === 'idle') fillColor = '#888780';

            this.div.innerHTML = `
                <div class="relative flex items-center justify-center select-none" style="width: 24px; height: 28px; transform: translate(-12px, -28px);">
                    <!-- Pulse animation soft radiating ring -->
                    ${isActive ? `
                        <div class="absolute -inset-2.5 rounded-full pulse-ring" 
                             style="background-color: ${fillColor}; opacity: 0.35;">
                        </div>
                    ` : ''}
                    <!-- Pin Shape: sharp corner at bottom pointing straight down -->
                    <div class="w-6 h-7 flex items-center justify-center rounded-lg shadow-md select-none border-1.5 border-white premium-transition" 
                         id="bus-marker-pin-${this.bus.bus_id}"
                         style="background-color: ${fillColor}; border-radius: 8px 8px 8px 0; transform: rotate(-45deg); border: 1.5px solid white;">
                        <div class="flex items-center justify-center" style="transform: rotate(45deg); width: 100%; height: 100%;">
                            <i class="ti ti-bus text-white text-[12px] leading-none"></i>
                        </div>
                    </div>
                </div>
            `;

            this.div.addEventListener('click', () => {
                openBusPopover(this.bus, this.pos);
            });

            const panes = this.getPanes();
            panes.overlayImage.appendChild(this.div);
        }

        draw() {
            const projection = this.getProjection();
            if (!projection) return;
            const position = projection.fromLatLngToDivPixel(this.pos);
            if (position) {
                this.div.style.left = position.x + 'px';
                this.div.style.top = position.y + 'px';
            }
        }

        onRemove() {
            if (this.div) {
                this.div.parentNode.removeChild(this.div);
                this.div = null;
            }
        }

        setPosition(pos) {
            this.pos = pos;
            this.draw();
            
            // Marker opacity pulse on position refresh
            const pin = document.getElementById(`bus-marker-pin-${this.bus.bus_id}`);
            if (pin) {
                pin.style.opacity = '0.6';
                setTimeout(() => { if (pin) pin.style.opacity = '1.0'; }, 300);
            }
        }
    };

    // Initialise Google Maps centred on Pasig City (14.5764Â° N, 121.0851Â° E), zoom 13
    map = new google.maps.Map(document.getElementById('map'), {
        center: { lat: 14.5764, lng: 121.0851 },
        zoom: 13.2,
        disableDefaultUI: true,
        styles: [
            { "featureType": "all", "elementType": "labels.text.fill", "stylers": [{"color": "#4b5563"}] },
            { "featureType": "water", "elementType": "geometry.fill", "stylers": [{"color": "#E0F2FE"}] },
            { "featureType": "road", "elementType": "geometry.fill", "stylers": [{"color": "#FFFFFF"}] },
            { "featureType": "landscape", "elementType": "geometry.fill", "stylers": [{"color": "#F8FAFC"}] },
            { "featureType": "poi", "stylers": [{"visibility": "off"}] }
        ]
    });

    // Custom Map Control Actions
    document.getElementById('zoom-in').addEventListener('click', () => map.setZoom(map.getZoom() + 1));
    document.getElementById('zoom-out').addEventListener('click', () => map.setZoom(map.getZoom() - 1));

    // Render Routes & Stops
    drawRoutePolylines();
    drawStops();

    // Setup Livewire Event Listeners for Updates (Standard in Livewire v3)
    Livewire.on('buses-updated', event => {
        activeBuses = event.buses;
        updateBusMarkers(event.buses);
        checkRegisteredAlerts(event.buses);
    });
}

function addRouteMapControls() {
    if (document.getElementById('commuter-route-map-controls')) return;
    const panel = document.createElement('div');
    panel.id = 'commuter-route-map-controls';
    panel.style.cssText = 'background:#fff;border:1px solid rgba(15,23,42,.14);border-radius:8px;box-shadow:0 2px 8px rgba(15,23,42,.12);color:#172033;font:12px/1.35 system-ui,sans-serif;margin:10px;padding:9px 10px;max-width:280px;';
    panel.innerHTML = `<strong>Official routes</strong><div style="color:#64748b;font-size:10px;margin:4px 0 7px">Schematic visualization based on official stop coordinates.</div><div style="display:flex;flex-wrap:wrap;gap:4px">${routesData.map(route => `<button type="button" data-route="${route.id}" aria-pressed="true" style="padding:4px 6px;border:1px solid #0284c7;border-radius:5px;background:#e0f2fe;color:#075985">${route.name}</button>`).join('')}</div><div style="display:flex;gap:9px;margin-top:7px"><label><input type="checkbox" data-direction="outbound" checked> OUT solid</label><label><input type="checkbox" data-direction="inbound" checked> IN dashed</label></div>`;
    map.controls[google.maps.ControlPosition.TOP_LEFT].push(panel);
    panel.querySelectorAll('[data-route]').forEach(button => button.addEventListener('click', () => {
        selectedRouteId = selectedRouteId === Number(button.dataset.route) ? null : Number(button.dataset.route);
        button.setAttribute('aria-pressed', selectedRouteId === Number(button.dataset.route));
        drawRoutePolylines();
        drawStops();
    }));
    panel.querySelectorAll('[data-direction]').forEach(input => input.addEventListener('change', () => {
        visibleDirections[input.dataset.direction] = input.checked;
        drawRoutePolylines();
    }));
}
function drawRoutePolylines() {
    routePolylines.forEach(p => p.setMap(null));
    routePolylines = [];

    routesData.forEach(route => {
        const isHidden = selectedRouteId !== null && route.id !== selectedRouteId;
        const opacity = isHidden ? 0.15 : 0.85;
        const variants = (route.variant_geometries || []).filter(item => item.polyline_coordinates?.length > 0);
        const geometries = variants.length ? variants : [{ polyline_coordinates: route.coords, direction: 'outbound' }];

        geometries.forEach(variant => {
            if (!variant.polyline_coordinates?.length) return;
            const path = variant.polyline_coordinates.map(coord => ({ lat: parseFloat(coord[0]), lng: parseFloat(coord[1]) }));
            const direction = String(variant.direction || '').toLowerCase();
            if (direction.includes('in') && !visibleDirections.inbound) return;
            if (direction.includes('out') && !visibleDirections.outbound) return;
            const polyline = new google.maps.Polyline({
                path: path,
                geodesic: true,
                strokeColor: route.color,
                strokeOpacity: opacity,
                strokeWeight: 4.5,
                icons: direction.includes('in') ? [{ icon: { path: 'M 0,-1 0,1', scale: 3, strokeOpacity: 1 }, offset: '0', repeat: '12px' }] : [],
                map: map
            });
            routePolylines.push(polyline);
        });
    });
}

let stopInfoWindow;
function drawStops() {
    stopMarkers.forEach(m => m.setMap(null));
    stopMarkers = [];

    const officialStops = routesData.flatMap(route => (route.variant_geometries || []).flatMap(variant => (variant.stops || []).map(stop => ({ ...stop, route_id: route.id, route_name: route.name, route_color: route.color, direction: variant.direction, variant_total: (variant.stops || []).length }))));
    const displayStops = officialStops.length ? officialStops : stopsData;

    displayStops.forEach(stop => {
        // Hide stops of filtered-out routes
        if (selectedRouteId !== null && stop.route_id !== selectedRouteId) return;

        const pos = { lat: stop.lat, lng: stop.lng };
        
        // Stop Marker: white filled circle, 9px diameter, 1.5px route border
        const stopMarker = new google.maps.Marker({
            position: pos,
            map: map,
            title: `${stop.name} (${stop.direction || 'route stop'})`,
            label: stop.sequence && (stop.sequence === 1 || stop.sequence === stop.variant_total) ? { text: 'â—', color: stop.route_color, fontWeight: '700' } : undefined,
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                scale: 4.5, // 9px diameter
                fillColor: '#FFFFFF',
                fillOpacity: 1,
                strokeColor: stop.route_color,
                strokeWeight: 1.5
            }
        });

        // Hover scale magnification to 12px + tooltip open
        stopMarker.addListener('mouseover', () => {
            stopMarker.setIcon({
                path: google.maps.SymbolPath.CIRCLE,
                scale: 6, // 12px diameter
                fillColor: '#FFFFFF',
                fillOpacity: 1,
                strokeColor: stop.route_color,
                strokeWeight: 2
            });
            showStopTooltip(stopMarker, stop);
        });

        stopMarker.addListener('mouseout', () => {
            stopMarker.setIcon({
                path: google.maps.SymbolPath.CIRCLE,
                scale: 4.5,
                fillColor: '#FFFFFF',
                fillOpacity: 1,
                strokeColor: stop.route_color,
                strokeWeight: 1.5
            });
            hideStopTooltip();
        });

        stopMarkers.push(stopMarker);
    });
}

function showStopTooltip(marker, stop) {
    if (stopInfoWindow) stopInfoWindow.close();

    const approachingCount = activeBuses.filter(b => b.route_name === stop.route_name && typeof b.eta_minutes === "number" && b.eta_minutes <= 10).length;

    stopInfoWindow = new google.maps.InfoWindow({
        content: `
            <div class="px-2.5 py-1.5 text-slate-800 text-[11px] font-sans font-semibold">
                <div class="font-extrabold text-slate-900">${stop.name}</div>
                <div class="text-[#003F87] mt-1">${approachingCount} buses approaching</div>
            </div>
        `,
        disableAutoPan: true
    });
    stopInfoWindow.open(map, marker);
}

function hideStopTooltip() {
    if (stopInfoWindow) stopInfoWindow.close();
}

function updateBusMarkers(buses) {
    const seenPlates = new Set();

    buses.forEach(bus => {
        if (!bus.lat || !bus.lng) return;
        seenPlates.add(bus.plate_number);

        const pos = { lat: bus.lat, lng: bus.lng };

        if (busMarkers[bus.plate_number]) {
            // Update existing marker details
            busMarkers[bus.plate_number].setPosition(pos);
            busMarkers[bus.plate_number].bus = bus;
        } else {
            // Instantiate custom overlay HTML marker
            busMarkers[bus.plate_number] = new HTMLMarker(pos, bus, map);
        }
    });

    // Cleanup obsolete marker records
    for (const plate in busMarkers) {
        if (!seenPlates.has(plate)) {
            busMarkers[plate].setMap(null);
            delete busMarkers[plate];
        }
    }
}

let busInfoWindow;
function openBusPopover(bus, pos) {
    if (busInfoWindow) busInfoWindow.close();

    const fillRatio = bus.capacity > 0 ? bus.passenger_count / bus.capacity : 0;
    const isFull = fillRatio > 0.8;
    const progressPercent = Math.round(fillRatio * 100);

    const freshnessState = bus.gps_freshness_state || 'UNKNOWN';
    const freshnessAge = bus.gps_freshness_age_seconds;

    let gpsBadge = '';
    if (freshnessState === 'LIVE') {
        gpsBadge = `<span class="px-1.5 py-0.5 text-[9px] font-bold bg-emerald-50 text-[#0F6E56] border border-emerald-100 rounded-full flex items-center gap-0.5"><span class="w-1 h-1 rounded-full bg-emerald-500"></span>LIVE</span>`;
    } else if (freshnessState === 'STALE') {
        gpsBadge = `<span class="px-1.5 py-0.5 text-[9px] font-bold bg-amber-50 text-[#854F0B] border border-amber-100 rounded-full flex items-center gap-0.5"><span class="w-1 h-1 rounded-full bg-amber-500"></span>STALE (${freshnessAge}s)</span>`;
    } else if (freshnessState === 'OFFLINE') {
        gpsBadge = `<span class="px-1.5 py-0.5 text-[9px] font-bold bg-rose-50 text-[#A32D2D] border border-rose-100 rounded-full flex items-center gap-0.5"><span class="w-1 h-1 rounded-full bg-rose-500"></span>OFFLINE</span>`;
    } else {
        gpsBadge = `<span class="px-1.5 py-0.5 text-[9px] font-bold bg-slate-50 text-slate-500 border border-slate-200 rounded-full flex items-center gap-0.5"><span class="w-1 h-1 rounded-full bg-slate-400"></span>UNKNOWN</span>`;
    }

    let simBadge = '';
    if (bus.is_simulated) {
        simBadge = `<span class="px-1.5 py-0.5 text-[9px] font-bold bg-blue-50 text-[#1D4ED8] border border-blue-100 rounded-full flex items-center gap-0.5"><span class="w-1 h-1 rounded-full bg-blue-500 animate-pulse"></span>Estimated</span>`;
    }

    const content = `
        <div class="w-[230px] p-3 text-slate-800 flex flex-col gap-2 font-sans select-none relative">
            <div class="flex justify-between items-center pr-4">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[13.5px] font-mono font-bold text-slate-800">${bus.plate_number}</span>
                    ${simBadge}
                </div>
                <div class="flex items-center gap-1 flex-wrap">
                    <span class="px-1.5 py-0.5 text-[9.5px] font-extrabold uppercase rounded-full bg-slate-100 text-slate-600 shadow-sm">
                        ${bus.status.toUpperCase()}
                    </span>
                    ${gpsBadge}
                </div>
            </div>

            <div class="flex items-center gap-1.5 text-xs text-slate-500 font-semibold">
                <i class="ti ti-id text-sm text-slate-400"></i>
                <span>${bus.driver_name}</span>
            </div>

            <div class="flex items-center gap-1.5 text-xs text-slate-500 font-bold">
                <span class="h-2 w-2 rounded-full" style="background-color: ${bus.route_color};"></span>
                <span>${bus.route_name}</span>
            </div>

            <hr class="border-slate-100 my-1" />

            <div class="flex flex-col gap-0.5">
                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider">Next Stop</span>
                <span class="text-[12.5px] font-extrabold text-slate-700 leading-tight">
                    ${bus.next_stop_name} - <strong class="text-[#003F87] font-black">${bus.eta_label || "ETA unavailable"}</strong>
                </span>
            </div>

            <div class="flex flex-col gap-1 mt-1">
                <div class="flex items-center justify-between text-[11px] font-bold ${isFull ? 'text-[#E24B4A]' : 'text-slate-500'}">
                    <span>${bus.passenger_count} / ${bus.capacity} riders</span>
                    <span>${progressPercent}%</span>
                </div>
                <div class="w-full bg-[#E6F1FB] rounded-full h-1 overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-300" 
                         style="width: ${progressPercent}%; background-color: ${isFull ? '#E24B4A' : '#003F87'};">
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-1.5 text-xs text-slate-400 font-bold mt-1">
                <i class="ti ti-speedboat text-sm"></i>
                <span>${bus.speed} km/h</span>
            </div>

            <button onclick="requestAlertPermission('${bus.plate_number}', ${bus.eta_minutes ?? null})"
                    class="w-full text-center text-[12.5px] font-bold text-[#003F87] active:opacity-70 mt-2 flex items-center justify-center gap-1.5 py-1.5 rounded-lg border border-[#003F87]/15 bg-[#003F87]/5 hover:bg-[#003F87]/10 transition-colors">
                <i class="ti ti-bell-plus"></i> Set Arrival Alert
            </button>
        </div>
    `;

    busInfoWindow = new google.maps.InfoWindow({
        content: content,
        pixelOffset: new google.maps.Size(0, -32)
    });
    
    busInfoWindow.open(map);
    busInfoWindow.setPosition(pos);
}

// Triggered when clicking a Bus Card in the List panel
function focusBusOnMap(busId, lat, lng, plateNumber) {
    if (!lat || !lng) return;
    const pos = { lat: parseFloat(lat), lng: parseFloat(lng) };
    
    map.panTo(pos);
    map.setZoom(14.5);

    // Fetch the detailed bus object to open its popup instantly
    const targetBus = activeBuses.find(b => b.plate_number === plateNumber);
    if (targetBus) {
        setTimeout(() => {
            openBusPopover(targetBus, pos);
        }, 300);
    }
}

// Web Notifications Alert System (localStorage registered)
function requestAlertPermission(plateNumber, etaMinutes) {
    if (!("Notification" in window)) {
        GoPasigUI.alert("This browser does not support desktop notifications.");
        return;
    }
    
    Notification.requestPermission().then(permission => {
        if (permission === "granted") {
            let alerts = JSON.parse(localStorage.getItem("gopasig_alerts") || "[]");
            
            alerts = alerts.filter(a => a.plate !== plateNumber);
            alerts.push({
                plate: plateNumber,
                eta: etaMinutes,
                timestamp: Date.now()
            });
            
            localStorage.setItem("gopasig_alerts", JSON.stringify(alerts));
            
            new Notification("GoPasig Arrival Alert Set!", {
                body: `We will notify you when bus ${plateNumber} is 2 minutes away.`,
                icon: "/images/pasig_logo.png"
            });
        } else {
            GoPasigUI.alert("Notification permission denied. Please enable permission to receive arrival alerts.");
        }
    });
}

function checkRegisteredAlerts(buses) {
    let alerts = JSON.parse(localStorage.getItem("gopasig_alerts") || "[]");
    if (alerts.length === 0) return;

    let remainingAlerts = [];

    alerts.forEach(alert => {
        const activeBus = buses.find(b => b.plate_number === alert.plate);
        if (activeBus) {
            if (typeof activeBus.eta_minutes === "number" && activeBus.eta_minutes <= 2) {
                // Fire System notification!
                if (Notification.permission === "granted") {
                    new Notification("GoPasig Bus Approaching!", {
                        body: `Libreng Sakay Bus ${activeBus.plate_number} is approaching your stop (${activeBus.eta_label || `ETA: ${activeBus.eta_minutes} min`})!`,
                        icon: "/images/pasig_logo.png"
                    });
                }
            } else {
                remainingAlerts.push(alert);
            }
        }
    });

    localStorage.setItem("gopasig_alerts", JSON.stringify(remainingAlerts));
}

// Livewire dynamic route filter hooks
document.addEventListener("DOMContentLoaded", () => {
    Livewire.on('route-selected', event => {
        selectedRouteId = event.routeId;
        drawRoutePolylines();
        drawStops();
    });
});





