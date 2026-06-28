// ==================== LIVE ADMIN OVERVIEW VEHICLE VISUALIZER ====================

let overviewMapInstance = null;
const overviewMarkersMap = {};

// Expose dynamic rendering globally so it can be re-triggered by dashboard-data.js
function renderOverviewBuses() {
    if (overviewMapInstance === null) return;

    // Clear existing markers in overview map
    for (let id in overviewMarkersMap) {
        overviewMapInstance.removeLayer(overviewMarkersMap[id]);
    }

    fleetData.forEach(bus => {
        // Only map active, delayed, or breakdown buses (skip inactive)
        if (bus.status === 'Inactive') return;

        const color = statusColors[bus.status] || '#888780';
        const iconHtml = `
            <div class="relative flex items-center justify-center">
                ${bus.status === 'Active' || bus.status === 'Delayed' ? `
                    <span class="absolute inline-flex h-8 w-8 animate-ping rounded-full opacity-20" style="background-color: ${color};"></span>
                ` : ''}
                <div class="relative flex h-7 w-7 items-center justify-center rounded-full text-white border-2 border-white shadow-md font-semibold" style="background-color: ${color};">
                    <i class="${bus.status === 'Breakdown' ? 'ti ti-alert-triangle' : 'ti ti-bus'} text-[10px]"></i>
                </div>
                <div class="absolute top-[28px] bg-slate-900/90 text-white font-extrabold text-[8px] px-1.5 py-0.5 rounded border border-white/20 uppercase tracking-widest shadow-sm">
                    ${bus.plate}
                </div>
            </div>
        `;

        const marker = L.marker([bus.lat, bus.lng], {
            icon: L.divIcon({
                html: iconHtml,
                className: 'custom-bus-marker-icon',
                iconSize: [28, 28],
                iconAnchor: [14, 14]
            })
        }).addTo(overviewMapInstance);

        overviewMarkersMap[bus.id] = marker;
    });
}

function initOverviewMap() {
    const mapContainer = document.getElementById('overview-map');
    if (!mapContainer || overviewMapInstance !== null) return;


    const centerLat = (typeof mapCenterLat !== 'undefined' && mapCenterLat !== null) ? mapCenterLat : 14.5690;
    const centerLng = (typeof mapCenterLng !== 'undefined' && mapCenterLng !== null) ? mapCenterLng : 121.0680;
    const zoomLevel = (typeof mapZoom !== 'undefined' && mapZoom !== null) ? mapZoom : 13.5;

    // Initialize Map centered on Pasig coords with restricted zoom/pan for visual layout polish
    overviewMapInstance = L.map('overview-map', {
        zoomControl: false,
        attributionControl: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        dragPan: true
    }).setView([centerLat, centerLng], zoomLevel);

    // Load official Google Maps roadmap layer using Google Maps API
    try {
        L.gridLayer.googleMutant({
            type: 'roadmap'
            // opacity fallback is handled internally by L.gridLayer
        }).addTo(overviewMapInstance);
    } catch (error) {
        console.error("Google Maps Mutant failed to load on overview dashboard, falling back to CartoDB:", error);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            maxZoom: 20
        }).addTo(overviewMapInstance);
    }

    // Zoom Control to bottom-right (clean layout)
    L.control.zoom({ position: 'bottomright' }).addTo(overviewMapInstance);

    // 1. Draw all operational routes polylines from database
    routesDataDb.forEach(route => {
        if (route.polyline_coordinates && route.polyline_coordinates.length > 0) {
            const color = routeColors[route.id.toString()] || '#003F87';
            L.polyline(route.polyline_coordinates, {
                color: color,
                weight: 4,
                opacity: 0.85
            }).addTo(overviewMapInstance);
        }
    });

    // 2. Draw all stops dynamically from database
    routesDataDb.forEach(route => {
        if (route.stops && route.stops.length > 0) {
            const strokeColor = routeColors[route.id.toString()] || '#003F87';
            route.stops.forEach(stop => {
                L.circleMarker([parseFloat(stop.lat), parseFloat(stop.lng)], {
                    radius: 4.5,
                    fillColor: '#FFFFFF',
                    fillOpacity: 1,
                    color: strokeColor,
                    weight: 2
                }).bindTooltip(stop.name, {
                    direction: 'top',
                    className: 'font-sans font-bold text-[9px] px-1.5 py-0.5 rounded shadow-sm border border-slate-100'
                }).addTo(overviewMapInstance);
            });
        }
    });

    // Render buses immediately
    renderOverviewBuses();
    if (typeof updateOverviewDashboard === 'function') {
        updateOverviewDashboard();
    }

    // 3. Real-time DB position refresh — polls the fleet API every N milliseconds
    //    Fetches real lat/lng from the database (written by active driver sessions).
    //    No random jitter — all movement reflects genuine GPS telemetry.
    const refreshInterval = (typeof pollingInterval !== 'undefined') ? pollingInterval : 10000;

    setInterval(async () => {
        if (typeof loadDatabaseFleetData === 'function') {
            await loadDatabaseFleetData();
        }

        // Full re-render: clears and redraws all markers from fresh DB data.
        // This handles buses newly dispatched (appear) and buses gone offline (disappear).
        renderOverviewBuses();

        // Refresh stats panel (dispatch queue, donut, system status chip)
        if (typeof updateOverviewDashboard === 'function') {
            updateOverviewDashboard();
        }
    }, refreshInterval); // real-data refresh cycle
}

// Initialise when DOM is fully loaded
document.addEventListener("DOMContentLoaded", function () {
    setTimeout(initOverviewMap, 200);
});

// Expose switchScreen hook so if they navigate back to Overview, map gets invalidated and renders perfectly
const originalSwitchScreen = typeof switchScreen === 'function' ? switchScreen : null;
switchScreen = function (screenName) {
    if (originalSwitchScreen) {
        originalSwitchScreen(screenName);
    }
    if (screenName === 'overview' && overviewMapInstance !== null) {
        setTimeout(() => {
            overviewMapInstance.invalidateSize();
            if (typeof updateOverviewDashboard === 'function') {
                updateOverviewDashboard();
            }
        }, 100);
    }
};

// ==================== DYNAMIC ADMIN OVERVIEW STATS UPDATER ====================

// Expose dynamic metrics and cards updater globally
function updateOverviewDashboard() {
    if (!fleetData || fleetData.length === 0) return;

    // 1. Calculate Metrics
    const totalBuses = fleetData.length;
    const activeBuses = fleetData.filter(b => b.status === 'Active' || b.status === 'Delayed');
    const activeCount = activeBuses.length;

    // Buses in Route are active buses that have an assigned route
    const routeCount = activeBuses.filter(b => b.route !== 'None' && b.route !== '').length;

    // Maintenance count
    const maintCount = fleetData.filter(b => b.status === 'Maintenance').length;

    // Alert count (Breakdown)
    const alertCount = fleetData.filter(b => b.status === 'Breakdown').length;

    // Update DOM Metrics
    const activeEl = document.getElementById('metric-active-buses');
    if (activeEl) activeEl.textContent = activeCount;

    const activeSubEl = document.getElementById('metric-active-buses-sub');
    if (activeSubEl) {
        if (activeCount === totalBuses && totalBuses > 0) {
            activeSubEl.innerHTML = `<span class="h-1.5 w-1.5 rounded-full bg-[#639922] animate-pulse mr-0.5"></span><span>Full fleet active</span>`;
            activeSubEl.className = "text-[11px] text-[#639922] font-semibold mt-0.5 flex items-center gap-0.5";
        } else if (activeCount > 0) {
            activeSubEl.innerHTML = `<span class="h-1.5 w-1.5 rounded-full bg-[#639922] animate-pulse mr-0.5"></span><span>Normal fleet ops</span>`;
            activeSubEl.className = "text-[11px] text-[#639922] font-semibold mt-0.5 flex items-center gap-0.5";
        } else {
            activeSubEl.innerHTML = `<span class="h-1.5 w-1.5 rounded-full bg-slate-400 mr-0.5"></span><span>No active buses</span>`;
            activeSubEl.className = "text-[11px] text-slate-500 font-semibold mt-0.5 flex items-center gap-0.5";
        }
    }

    const routeEl = document.getElementById('metric-buses-in-route');
    if (routeEl) routeEl.textContent = routeCount;

    const maintEl = document.getElementById('metric-under-maintenance');
    if (maintEl) maintEl.textContent = maintCount;

    const alertEl = document.getElementById('metric-service-alerts');
    if (alertEl) alertEl.textContent = alertCount;

    const alertSubEl = document.getElementById('metric-service-alerts-sub');
    if (alertSubEl) {
        if (alertCount > 0) {
            alertSubEl.innerHTML = `<i class="ti ti-alert-triangle"></i><span>Action required</span>`;
            alertSubEl.className = "text-[11px] text-[#E24B4A] font-bold mt-0.5 flex items-center gap-0.5";
        } else {
            alertSubEl.innerHTML = `<i class="ti ti-circle-check"></i><span>No issues reported</span>`;
            alertSubEl.className = "text-[11px] text-[#639922] font-bold mt-0.5 flex items-center gap-0.5";
        }
    }

    // Dynamic System Status Chip
    const statusContainer = document.getElementById('system-status-container');
    if (statusContainer) {
        if (alertCount > 0) {
            statusContainer.innerHTML = `
                <span class="inline-flex items-center gap-1 bg-[#FDF2F2] text-[#E24B4A] font-bold px-3 py-1.5 rounded-lg uppercase text-[11px] tracking-wider shadow-sm border border-red-100">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#E24B4A] animate-pulse"></span>
                    Service Disruption
                </span>
            `;
        } else {
            statusContainer.innerHTML = `
                <span class="inline-flex items-center gap-1 bg-[#E8F4E0] text-[#639922] font-bold px-3 py-1.5 rounded-lg uppercase text-[11px] tracking-wider shadow-sm">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#639922] animate-pulse"></span>
                    Systems Nominal
                </span>
            `;
        }
    }

    // 2. Update Donut Chart
    const donutTotal = document.getElementById('donut-total-buses');
    if (donutTotal) donutTotal.textContent = totalBuses;

    const activeCircle = document.getElementById('donut-circle-active');
    const maintCircle = document.getElementById('donut-circle-maintenance');
    const alertCircle = document.getElementById('donut-circle-alert');

    const legendActive = document.getElementById('donut-legend-active');
    const legendMaint = document.getElementById('donut-legend-maintenance');
    const legendAlert = document.getElementById('donut-legend-alert');

    if (legendActive) legendActive.innerHTML = `<span class="h-2.5 w-2.5 rounded-full bg-[#639922]"></span> Active (${activeCount})`;
    if (legendMaint) legendMaint.innerHTML = `<span class="h-2.5 w-2.5 rounded-full bg-[#BA7517]"></span> Maint (${maintCount})`;
    if (legendAlert) legendAlert.innerHTML = `<span class="h-2.5 w-2.5 rounded-full bg-[#E24B4A]"></span> Alert (${alertCount})`;

    if (totalBuses > 0) {
        const circ = 238.76; // 2 * pi * r (r=38)

        // Count alert as total - active - maint to encompass inactive buses and breakdowns
        const totalAlerts = totalBuses - activeCount - maintCount;
        if (legendAlert) legendAlert.innerHTML = `<span class="h-2.5 w-2.5 rounded-full bg-[#E24B4A]"></span> Alert/Inactive (${totalAlerts})`;

        const activeLen = (activeCount / totalBuses) * circ;
        const maintLen = (maintCount / totalBuses) * circ;
        const alertLen = (totalAlerts / totalBuses) * circ;

        if (activeCircle) {
            activeCircle.setAttribute('stroke-dasharray', `${activeLen.toFixed(1)} ${circ.toFixed(0)}`);
            activeCircle.setAttribute('stroke-dashoffset', '0');
        }
        if (maintCircle) {
            maintCircle.setAttribute('stroke-dasharray', `${maintLen.toFixed(1)} ${circ.toFixed(0)}`);
            maintCircle.setAttribute('stroke-dashoffset', `-${activeLen.toFixed(1)}`);
        }
        if (alertCircle) {
            alertCircle.setAttribute('stroke-dasharray', `${alertLen.toFixed(1)} ${circ.toFixed(0)}`);
            alertCircle.setAttribute('stroke-dashoffset', `-${(activeLen + maintLen).toFixed(1)}`);
        }
    } else {
        if (activeCircle) activeCircle.setAttribute('stroke-dasharray', '0 239');
        if (maintCircle) maintCircle.setAttribute('stroke-dasharray', '0 239');
        if (alertCircle) alertCircle.setAttribute('stroke-dasharray', '0 239');
    }

    // 3. Render Today's Dispatch Queue
    const dispatchList = document.getElementById('dispatch-queue-list');
    if (dispatchList) {
        dispatchList.innerHTML = '';

        const dispatchedBuses = typeof dispatchQueueData !== 'undefined' ? [...dispatchQueueData] : [];

        // Sort so delayed trips appear at top, then by departure time
        dispatchedBuses.sort((a, b) => {
            if (a.status === 'Delayed' && b.status !== 'Delayed') return -1;
            if (a.status !== 'Delayed' && b.status === 'Delayed') return 1;
            return a.departureTime.localeCompare(b.departureTime);
        });

        if (dispatchedBuses.length === 0) {
            dispatchList.innerHTML = `
                <div class="py-16 text-center text-xs font-semibold text-slate-400">
                    No dispatches scheduled for today.
                </div>
            `;
        } else {
            dispatchedBuses.forEach(dispatch => {
                const card = document.createElement('div');
                card.className = "rounded-lg border border-slate-100 bg-slate-50/50 p-3 hover:border-slate-200 hover:bg-slate-50 transition-all duration-200";

                const dispatchStatus = dispatch.status === 'Delayed' || dispatch.status === 'delayed' ? 'Delayed' : 'On Time';
                const badgeClass = dispatchStatus === 'Delayed' ? 'bg-[#FAEEDA] text-[#854F0B]' : 'bg-[#EAF3DE] text-[#3B6D11]';

                card.innerHTML = `
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-bold text-[#003F87]">${dispatch.busPlate}</span>
                        <span class="inline-flex rounded-full ${badgeClass} px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider">${dispatchStatus}</span>
                    </div>
                    <div class="mt-2 text-[11px] text-slate-500 font-semibold space-y-1">
                        <div>Route: <span class="text-slate-800 font-bold">${dispatch.routeName}</span></div>
                        <div>Driver: <span class="text-slate-800 font-bold">${dispatch.driverName}</span></div>
                    </div>
                    <div class="mt-2.5 flex items-center justify-between border-t border-slate-100 pt-2 shrink-0">
                        <span class="text-[10px] font-bold text-slate-400">Departure: ${dispatch.departureTime}</span>
                        <button onclick="viewTripDetails(${dispatch.busId})" class="text-[10px] font-extrabold text-[#003F87] hover:text-[#002D62] transition uppercase tracking-wider cursor-pointer">View Details</button>
                    </div>
                `;
                dispatchList.appendChild(card);
            });
        }
    }

    // 4. Render Recent Trip Logs
    const tripLogsTbody = document.getElementById('trip-logs-tbody');
    if (tripLogsTbody) {
        tripLogsTbody.innerHTML = '';

        if (tripsData.length === 0) {
            tripLogsTbody.innerHTML = `
                <tr>
                    <td colspan="4" class="py-12 text-center text-xs text-slate-400 font-semibold">No recent logs available.</td>
                </tr>
            `;
        } else {
            tripsData.forEach(trip => {
                const tr = document.createElement('tr');
                tr.className = "hover:bg-slate-50/50 transition";

                // Status badge styling
                let badgeClass = 'bg-[#E6F1FB] text-[#0C447C]';
                if (trip.status === 'Active') {
                    badgeClass = 'bg-[#EAF3DE] text-[#3B6D11]';
                } else if (trip.status === 'Delayed') {
                    badgeClass = 'bg-[#FEF7ED] text-[#BA7517]';
                } else if (trip.status === 'Cancelled') {
                    badgeClass = 'bg-[#FCEBEB] text-[#A32D2D]';
                }

                tr.innerHTML = `
                    <td class="py-2.5 font-bold font-mono">${trip.time}</td>
                    <td class="py-2.5">${trip.route}</td>
                    <td class="py-2.5">${trip.driver}</td>
                    <td class="py-2.5 text-right"><span class="inline-flex rounded-full ${badgeClass} px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider">${trip.status}</span></td>
                `;
                tripLogsTbody.appendChild(tr);
            });
        }
    }

    // 5. Render Maintenance Alerts
    const maintList = document.getElementById('overview-maintenance-list');
    if (maintList) {
        maintList.innerHTML = '<div class="py-12 text-center text-slate-400 font-semibold text-xs">Loading maintenance schedules...</div>';

        (async function () {
            try {
                const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
                const response = await fetch(baseUrl);
                const records = await response.json();

                maintList.innerHTML = '';

                // Show scheduled and in progress records
                const activeRecords = records.filter(r => r.status === 'scheduled' || r.status === 'in_progress');

                if (activeRecords.length === 0) {
                    maintList.innerHTML = `
                        <div class="py-16 text-center text-xs font-semibold text-slate-400">
                            No active maintenance schedules pending.
                        </div>
                    `;
                } else {
                    activeRecords.slice(0, 5).forEach(record => {
                        const card = document.createElement('div');
                        card.className = "flex items-center justify-between border-b border-slate-100/50 pb-2 bg-slate-50/20 hover:bg-slate-50/50 p-1.5 rounded transition";

                        const type = record.type.includes('Corrective') ? 'Corrective' : 'Preventive';
                        const badgeClass = type === 'Corrective' ? 'bg-[#FCEBEB] text-[#A32D2D]' : 'bg-[#FAEEDA] text-[#854F0B]';

                        const busLabel = record.bus ? record.bus.plate_number : `Bus #${record.bus_id}`;
                        const formattedDate = new Date(record.scheduled_at).toLocaleDateString([], { month: 'short', day: '2-digit', year: 'numeric' });

                        card.innerHTML = `
                            <div>
                                <p class="text-xs font-extrabold text-slate-900">${busLabel}</p>
                                <p class="text-[10px] font-bold text-slate-400 mt-0.5">Due: ${formattedDate} (${record.status.replace('_', ' ')})</p>
                            </div>
                            <span class="inline-flex rounded ${badgeClass} px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider">${type}</span>
                        `;
                        maintList.appendChild(card);
                    });
                }
            } catch (error) {
                console.error("Overview failed to load maintenance schedule:", error);
                maintList.innerHTML = `<div class="py-12 text-center text-rose-500 font-semibold text-xs">Error loading schedules.</div>`;
            }
        })();
    }
}

// Navigation bridge from dispatch queue view buttons
function viewTripDetails(busId) {
    switchScreen('map');

    // Auto pan/zoom mapping locator integration
    if (typeof initLiveFleetMap === 'function') {
        initLiveFleetMap();
    }

    if (typeof locateBusOnMap === 'function') {
        setTimeout(() => {
            locateBusOnMap(busId);
        }, 400);
    }
}
