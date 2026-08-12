// ==================== LIVE ADMIN OVERVIEW VEHICLE VISUALIZER ====================

let overviewMapInstance = null;
const overviewMarkersMap = {};
let overviewPolylines = [];
let overviewStops = [];

// Expose dynamic rendering globally so it can be re-triggered by dashboard-data.js
function renderOverviewBuses() {
    if (overviewMapInstance === null) return;

    // Clear existing markers in overview map
    for (let id in overviewMarkersMap) {
        overviewMapInstance.removeLayer(overviewMarkersMap[id]);
    }

    fleetData.forEach(bus => {
        if (!bus.has_active_trip) return;
        // Only map active, delayed, or breakdown buses (skip inactive)
        if (bus.status === 'Inactive') return;

        const color = statusColors[bus.status] || '#888780';
        const iconHtml = `
            <div class="relative flex items-center justify-center">
                ${bus.status === 'Active' || bus.status === 'Operating' || bus.status === 'Delayed' ? `
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

function renderOverviewPolylines() {
    if (overviewMapInstance === null) return;
    overviewPolylines.forEach(layer => overviewMapInstance.removeLayer(layer));
    overviewPolylines = [];

    routesDataDb.forEach(route => {
        const geometries = route.map_geometry_source === 'route_variant'
            ? (route.map_variant_geometries || []).filter(geometry => geometry.polyline_coordinates?.length >= 2)
            : [{ polyline_coordinates: route.polyline_coordinates }];

        geometries.forEach(geometry => {
            if (!geometry.polyline_coordinates || geometry.polyline_coordinates.length < 2) return;

            const color = routeColors[route.id.toString()] || '#003F87';
            const polyline = L.polyline(geometry.polyline_coordinates, {
                color: color,
                weight: 4,
                opacity: 0.85
            }).addTo(overviewMapInstance);
            overviewPolylines.push(polyline);
        });
    });
}

function renderOverviewStops() {
    if (overviewMapInstance === null) return;
    overviewStops.forEach(layer => overviewMapInstance.removeLayer(layer));
    overviewStops = [];

    routesDataDb.forEach(route => {
        if (route.stops && route.stops.length > 0) {
            const strokeColor = routeColors[route.id.toString()] || '#003F87';
            route.stops.forEach(stop => {
                const marker = L.circleMarker([parseFloat(stop.lat), parseFloat(stop.lng)], {
                    radius: 4.5,
                    fillColor: '#FFFFFF',
                    fillOpacity: 1,
                    color: strokeColor,
                    weight: 2
                }).bindTooltip(stop.name, {
                    direction: 'top',
                    className: 'font-sans font-bold text-[9px] px-1.5 py-0.5 rounded shadow-sm border border-slate-100'
                }).addTo(overviewMapInstance);
                overviewStops.push(marker);
            });
        }
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
    renderOverviewPolylines();

    // 2. Draw all stops dynamically from database
    renderOverviewStops();

    // Render buses immediately
    renderOverviewBuses();
    if (typeof updateOverviewDashboard === 'function') {
        updateOverviewDashboard();
    }

    // 3. Real-time DB position refresh — polls the fleet API every N milliseconds
    //    Fetches real lat/lng from the database (written by active driver sessions).
    //    No random jitter — all movement reflects genuine GPS telemetry.
    const refreshInterval = (typeof pollingInterval !== 'undefined') ? pollingInterval : 5000;

    setInterval(async () => {
        if (typeof loadDatabaseFleetData === 'function') {
            await loadDatabaseFleetData();
        }

        // Full re-render: clears and redraws all markers from fresh DB data.
        // This handles buses newly dispatched (appear) and buses gone offline (disappear).
        renderOverviewBuses();

        // Refresh the existing overview cards, schedule panel, and status chart.
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
    if (!overviewOperationsData) return;

    const metrics = overviewOperationsData.metrics || {};
    const fleetStatus = overviewOperationsData.fleet_status || {};
    const disruptionBreakdown = overviewOperationsData.disruption_breakdown || {};
    const inServiceCount = Number(metrics.buses_in_service) || 0;
    const completedToday = Number(metrics.completed_today) || 0;
    const maintenanceCount = Number(metrics.under_maintenance) || 0;
    const disruptionCount = Number(metrics.open_disruptions) || 0;

    // 1. Update the four existing metric cards from actual operations.
    const activeEl = document.getElementById('metric-active-buses');
    if (activeEl) activeEl.textContent = inServiceCount;

    const activeSubEl = document.getElementById('metric-active-buses-sub');
    if (activeSubEl) {
        if (inServiceCount > 0) {
            activeSubEl.innerHTML = `<span class="h-1.5 w-1.5 rounded-full bg-[#639922] animate-pulse mr-0.5"></span><span>Based on ongoing trips</span>`;
            activeSubEl.className = "text-[11px] text-[#639922] font-semibold mt-0.5 flex items-center gap-0.5";
        } else {
            activeSubEl.innerHTML = `<span class="h-1.5 w-1.5 rounded-full bg-slate-400 mr-0.5"></span><span>No ongoing trips</span>`;
            activeSubEl.className = "text-[11px] text-slate-500 font-semibold mt-0.5 flex items-center gap-0.5";
        }
    }

    const routeEl = document.getElementById('metric-buses-in-route');
    if (routeEl) routeEl.textContent = completedToday;

    const maintEl = document.getElementById('metric-under-maintenance');
    if (maintEl) maintEl.textContent = maintenanceCount;

    const alertEl = document.getElementById('metric-service-alerts');
    if (alertEl) alertEl.textContent = disruptionCount;

    const alertSubEl = document.getElementById('metric-service-alerts-sub');
    if (alertSubEl) {
        if (disruptionCount > 0) {
            const details = [
                `${Number(disruptionBreakdown.incidents) || 0} incidents`,
                `${Number(disruptionBreakdown.service_alerts) || 0} alerts`,
                `${Number(disruptionBreakdown.breakdowns) || 0} breakdowns`,
            ].join(' / ');
            alertSubEl.innerHTML = `<i class="ti ti-alert-triangle"></i><span title="${escapeOverviewHtml(details)}">Action required</span>`;
            alertSubEl.className = "text-[11px] text-[#E24B4A] font-bold mt-0.5 flex items-center gap-0.5";
        } else {
            alertSubEl.innerHTML = `<i class="ti ti-circle-check"></i><span>No open disruptions</span>`;
            alertSubEl.className = "text-[11px] text-[#639922] font-bold mt-0.5 flex items-center gap-0.5";
        }
    }

    renderOverviewSystemHealth(overviewOperationsData.system_health || {});
    renderOverviewFleetDonut(fleetStatus);
    renderOverviewOfficialSchedules(overviewOperationsData.official_schedules || []);

    // 2. Render Recent Trip Activity in the existing table.
    const tripLogsTbody = document.getElementById('trip-logs-tbody');
    if (tripLogsTbody) {
        tripLogsTbody.innerHTML = '';

        if (tripsData.length === 0) {
            tripLogsTbody.innerHTML = `
                <tr>
                    <td colspan="4" class="py-12 text-center text-xs text-slate-400 font-semibold">No recent trip activity.</td>
                </tr>
            `;
        } else {
            tripsData.forEach(trip => {
                const tr = document.createElement('tr');
                tr.className = "hover:bg-slate-50/50 transition";

                // Status badge styling
                let badgeClass = 'bg-[#E6F1FB] text-[#0C447C]';
                if (trip.status === 'Ongoing') {
                    badgeClass = 'bg-[#EAF3DE] text-[#3B6D11]';
                } else if (trip.status === 'Awaiting Start') {
                    badgeClass = 'bg-[#E6F1FB] text-[#0C447C]';
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

    // 3. Keep the existing Maintenance Schedule panel and behavior.
    const maintList = document.getElementById('overview-maintenance-list');
    if (maintList) {
        maintList.innerHTML = '<div class="py-12 text-center text-slate-400 font-semibold text-xs">Loading maintenance schedules...</div>';

        (async function () {
            try {
                const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
                const response = await fetch(baseUrl);
                const payload = await response.json();

                if (!response.ok) {
                    throw new Error(payload.message || 'Maintenance request failed.');
                }

                const records = Array.isArray(payload)
                    ? payload
                    : (Array.isArray(payload.data) ? payload.data : []);

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

                        const type = String(record.type || '').includes('Corrective') ? 'Corrective' : 'Preventive';
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
                maintList.innerHTML = `<div class="py-12 text-center text-slate-400 font-semibold text-xs">Maintenance schedules are currently unavailable.</div>`;
            }
        })();
    }
}

function renderOverviewSystemHealth(health) {
    const statusContainer = document.getElementById('system-status-container');
    if (!statusContainer) return;

    const state = health.state || 'nominal';
    const styles = {
        critical: ['bg-[#FDF2F2]', 'text-[#E24B4A]', 'border-red-100', 'bg-[#E24B4A]'],
        degraded: ['bg-[#FEF7ED]', 'text-[#BA7517]', 'border-amber-100', 'bg-[#BA7517]'],
        nominal: ['bg-[#E8F4E0]', 'text-[#639922]', 'border-green-100', 'bg-[#639922]'],
    }[state] || ['bg-[#E8F4E0]', 'text-[#639922]', 'border-green-100', 'bg-[#639922]'];

    statusContainer.innerHTML = `
        <span class="inline-flex items-center gap-1 ${styles[0]} ${styles[1]} font-bold px-3 py-1.5 rounded-lg uppercase text-[11px] tracking-wider shadow-sm border ${styles[2]}">
            <span class="h-1.5 w-1.5 rounded-full ${styles[3]} animate-pulse"></span>
            ${escapeOverviewHtml(health.label || 'Systems Nominal')}
        </span>
    `;
}

function renderOverviewFleetDonut(fleetStatus) {
    const total = Number(fleetStatus.total) || 0;
    const inService = Number(fleetStatus.in_service) || 0;
    const standby = Number(fleetStatus.standby) || 0;
    const unavailable = Number(fleetStatus.unavailable) || 0;
    const categories = [inService, standby, unavailable];
    const circleIds = ['donut-circle-active', 'donut-circle-maintenance', 'donut-circle-alert'];
    const legendData = [
        ['donut-legend-active', 'bg-[#639922]', 'In Service', inService],
        ['donut-legend-maintenance', 'bg-[#BA7517]', 'Standby', standby],
        ['donut-legend-alert', 'bg-[#E24B4A]', 'Unavailable', unavailable],
    ];

    const donutTotal = document.getElementById('donut-total-buses');
    if (donutTotal) donutTotal.textContent = total;

    legendData.forEach(([id, dotClass, label, count]) => {
        const legend = document.getElementById(id);
        if (legend) legend.innerHTML = `<span class="h-2.5 w-2.5 rounded-full ${dotClass}"></span> ${label} (${count})`;
    });

    const circumference = 238.76;
    let offset = 0;
    circleIds.forEach((id, index) => {
        const circle = document.getElementById(id);
        if (!circle) return;

        const length = total > 0 ? (categories[index] / total) * circumference : 0;
        circle.setAttribute('stroke-dasharray', `${length.toFixed(1)} ${circumference.toFixed(0)}`);
        circle.setAttribute('stroke-dashoffset', `-${offset.toFixed(1)}`);
        offset += length;
    });
}

function renderOverviewOfficialSchedules(routes) {
    const scheduleList = document.getElementById('official-schedule-list');
    if (!scheduleList) return;

    if (!routes.length) {
        scheduleList.innerHTML = '<div class="py-16 text-center text-xs font-semibold text-slate-400">No official schedules configured.</div>';
        return;
    }

    scheduleList.innerHTML = routes.map(route => {
        const directions = (route.directions || []).map(direction => {
            const windows = direction.windows && direction.windows.length
                ? direction.windows.join(' | ')
                : 'No operating window';
            const badgeClass = direction.state === 'in_service'
                ? 'bg-[#EAF3DE] text-[#3B6D11]'
                : direction.state === 'suspended'
                    ? 'bg-[#FCEBEB] text-[#A32D2D]'
                    : 'bg-slate-100 text-slate-500';

            return `
                <div class="border-t border-slate-100 pt-2 first:border-t-0 first:pt-0">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500">${escapeOverviewHtml(direction.direction || 'Direction')}</span>
                        <span class="inline-flex rounded-full ${badgeClass} px-2 py-0.5 text-[9px] font-bold">${escapeOverviewHtml(direction.status || '')}</span>
                    </div>
                    <p class="mt-1 text-[11px] font-bold text-slate-700 leading-4">${escapeOverviewHtml(windows)}</p>
                </div>
            `;
        }).join('');

        return `
            <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-3">
                <div class="mb-2 flex items-center gap-2">
                    <span class="h-2 w-2 rounded-full" style="background-color: ${escapeOverviewHtml(route.route_color || '#003F87')}"></span>
                    <span class="text-xs font-extrabold text-slate-900">${escapeOverviewHtml(route.route_name || 'Official Route')}</span>
                </div>
                <div class="space-y-2">${directions}</div>
            </div>
        `;
    }).join('');
}

function escapeOverviewHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
