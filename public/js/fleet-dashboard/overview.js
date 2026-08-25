/**
 * GoPasig Fleet Ops - Overview Panel Javascript Controller
 * Handles Vanilla JS / AJAX operations, dynamic polling, map rendering, and modals.
 */

// Window Configuration Setup
window.FleetOverviewConfig = {
    overviewDataUrl: '/fleet/api/overview-data',
    incidentsUrl: '/fleet/api/incidents',
    announcementsUrl: '/fleet/api/announcements',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

// Global DOM State
let previewMapInstance = null;
let previewBusesMarkers = [];

function escapeOverviewHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

// Clock updates
(function() {
    function updateClock() {
        const el = document.getElementById('live-clock');
        if (!el) return;
        const now = new Date();
        let hours = now.getHours();
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12;
        const hoursStr = String(hours).padStart(2, '0');
        el.textContent = `${hoursStr}:${minutes}:${seconds} ${ampm}`;
    }
    updateClock();

    if (window.GoPasigFleetModules?.registerPoller) {
        window.GoPasigFleetModules.registerPoller('overview', 'clock', updateClock, 1000);
    } else {
        setInterval(updateClock, 1000);
    }
})();

// Fetch and Update Overview Dashboard
async function fetchOverviewDashboardData() {
    try {
        const response = await fetch(window.FleetOverviewConfig.overviewDataUrl);
        if (!response.ok) throw new Error('Network response not ok');
        const data = await response.json();
        
        updateOverviewDOM(data);
        updatePreviewMapMarkers(data.buses);
    } catch (error) {
        console.error('Failed to fetch refreshed overview stats:', error);
    }
}

// Update DOM elements dynamically
function updateOverviewDOM(data) {
    // 1. KPI Counts
    document.getElementById('kpi-active-buses').innerText = data.overviewKpi.active_buses;
    document.getElementById('kpi-active-buses-delta').innerText = data.overviewKpi.deltas.active_buses_yesterday;

    document.getElementById('kpi-delayed-buses').innerText = data.overviewKpi.delayed_buses;
    document.getElementById('kpi-delayed-buses-delta').innerText = data.overviewKpi.deltas.delayed_buses_yesterday;
    const delayedKpiCard = document.getElementById('kpi-container-delayed-buses');
    if (data.overviewKpi.delayed_buses > 0) {
        delayedKpiCard.classList.add('border-l-[3px]', 'border-l-[#BA7517]');
    } else {
        delayedKpiCard.classList.remove('border-l-[3px]', 'border-l-[#BA7517]');
    }

    document.getElementById('kpi-offline-buses').innerText = data.overviewKpi.offline_buses;
    document.getElementById('kpi-offline-buses-delta').innerText = data.overviewKpi.deltas.offline_buses_yesterday;
    const offlineKpiCard = document.getElementById('kpi-container-offline-buses');
    if (data.overviewKpi.offline_buses > 0) {
        offlineKpiCard.classList.add('border-l-[3px]', 'border-l-[#E24B4A]');
    } else {
        offlineKpiCard.classList.remove('border-l-[3px]', 'border-l-[#E24B4A]');
    }

    document.getElementById('kpi-idle-buses').innerText = data.overviewKpi.idle_buses;
    document.getElementById('kpi-idle-buses-delta').innerText = data.overviewKpi.deltas.idle_buses_yesterday;

    document.getElementById('kpi-trips-completed').innerText = data.overviewKpi.trips_completed;
    document.getElementById('kpi-trips-completed-delta').innerText = data.overviewKpi.deltas.trips_completed_yesterday;

    document.getElementById('kpi-total-passengers').innerText = Number(data.overviewKpi.total_passengers).toLocaleString();
    document.getElementById('kpi-total-passengers-delta').innerText = data.overviewKpi.deltas.total_passengers_yesterday;

    document.getElementById('kpi-avg-utilization').innerText = data.overviewKpi.avg_utilization + '%';
    document.getElementById('kpi-avg-utilization-delta').innerText = data.overviewKpi.deltas.avg_utilization_yesterday;

    document.getElementById('kpi-open-incidents').innerText = data.overviewKpi.open_incidents;
    document.getElementById('kpi-open-incidents-delta').innerText = data.overviewKpi.deltas.open_incidents_yesterday;
    const incidentsKpiCard = document.getElementById('kpi-container-open-incidents');
    if (data.overviewKpi.open_incidents > 0) {
        incidentsKpiCard.classList.add('border-l-[3px]', 'border-l-[#E24B4A]');
    } else {
        incidentsKpiCard.classList.remove('border-l-[3px]', 'border-l-[#E24B4A]');
    }

    // 2. Active Incidents Feed
    const incidentsBadge = document.getElementById('active-incidents-badge');
    incidentsBadge.innerText = data.openIncidents;
    if (data.openIncidents > 0) {
        incidentsBadge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase bg-[#FCEBEB] text-[#A32D2D]';
    } else {
        incidentsBadge.className = 'px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase bg-slate-100 text-slate-500';
    }

    const incidentsFeed = document.getElementById('active-incidents-feed');
    incidentsFeed.innerHTML = '';
    
    if (data.activeIncidents && data.activeIncidents.length > 0) {
        data.activeIncidents.forEach(incident => {
            let severityBadge = '';
            if (incident.severity === 'High') {
                severityBadge = '<span class="inline-flex rounded px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wide bg-[#FCEBEB] text-[#A32D2D]">High</span>';
            } else if (incident.severity === 'Medium') {
                severityBadge = '<span class="inline-flex rounded px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wide bg-[#FAEEDA] text-[#854F0B]">Medium</span>';
            } else {
                severityBadge = '<span class="inline-flex rounded px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wide bg-[#EAF3DE] text-[#3B6D11]">Low</span>';
            }

            // Simple diffForHumans fallback on client side
            const timeDiff = getRelativeTime(incident.reported_at);

            const item = document.createElement('div');
            item.className = 'p-3 bg-slate-50 rounded-lg border border-slate-100 hover:border-slate-200 transition-colors flex flex-col space-y-2 relative';
            item.innerHTML = `
                <div class="flex items-center justify-between">
                    ${severityBadge}
                    <span class="text-[11px] text-slate-400 font-semibold">${escapeOverviewHtml(timeDiff)}</span>
                </div>
                <div>
                    <h4 class="text-[13px] font-bold text-slate-800 leading-snug">${escapeOverviewHtml(incident.title)}</h4>
                    <p class="text-[11.5px] text-slate-500 font-medium mt-0.5 flex items-center gap-1">
                        <i class="ti ti-map-pin text-[13px] text-slate-400"></i>
                        <span>${escapeOverviewHtml(incident.location)} | ${escapeOverviewHtml(incident.affected_route)}</span>
                    </p>
                </div>
                <div class="pt-1">
                    <button type="button" data-resolve-incident class="w-full h-7 border border-slate-200 text-[11px] font-extrabold text-slate-700 hover:bg-white bg-slate-100/50 hover:border-slate-300 rounded transition cursor-pointer text-center flex items-center justify-center gap-1 uppercase tracking-wider">
                        <span>Resolve Incident</span>
                    </button>
                </div>
            `;
            item.querySelector('[data-resolve-incident]')?.addEventListener('click', () => resolveIncidentAction(incident.id));
            incidentsFeed.appendChild(item);
        });
    } else {
        incidentsFeed.innerHTML = `
            <div class="h-[180px] flex flex-col items-center justify-center text-center space-y-2">
                <div class="h-12 w-12 rounded-full bg-[#F3F9EA] text-[#639922] flex items-center justify-center">
                    <i class="ti ti-circle-check text-2xl"></i>
                </div>
                <div>
                    <p class="text-[13px] font-bold text-slate-800">No active incidents</p>
                    <p class="text-[11.5px] text-slate-400 font-medium mt-0.5">All routes are operating within normal tolerances.</p>
                </div>
            </div>
        `;
    }

    // 3. Map status update
    document.getElementById('map-status-bus-count').innerText = `${data.activeCount} buses on-route | Updated just now`;

    // 4. Route Health cards
    const routeHealthContainer = document.getElementById('route-health-container');
    routeHealthContainer.innerHTML = '';
    data.routeHealth.forEach(route => {
        let healthBadge = '';
        if (route.health_status === 'On Track') {
            healthBadge = `<span class="inline-flex items-center gap-1 rounded bg-[#EAF3DE] text-[#3B6D11] text-[10px] font-bold px-2 py-0.5 uppercase tracking-wide">
                            <i class="ti ti-circle-check text-[11px]"></i>
                            <span>On Track</span>
                           </span>`;
        } else if (route.health_status === 'Minor Delay') {
            healthBadge = `<span class="inline-flex items-center gap-1 rounded bg-[#FAEEDA] text-[#854F0B] text-[10px] font-bold px-2 py-0.5 uppercase tracking-wide">
                            <i class="ti ti-clock text-[11px]"></i>
                            <span>Minor Delay</span>
                           </span>`;
        } else {
            healthBadge = `<span class="inline-flex items-center gap-1 rounded bg-[#FCEBEB] text-[#A32D2D] text-[10px] font-bold px-2 py-0.5 uppercase tracking-wide">
                            <i class="ti ti-alert-triangle text-[11px]"></i>
                            <span>Disrupted</span>
                           </span>`;
        }

        const progressPct = route.started_trips > 0 ? (route.completed_trips / route.started_trips) * 100 : 0;
        const headwayLabel = route.avg_headway_label || 'No data';

        const routeDiv = document.createElement('div');
        routeDiv.className = 'p-3 bg-white border border-slate-100 rounded-lg hover:border-slate-200 transition-colors space-y-2';
        routeDiv.innerHTML = `
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="h-2.5 w-2.5 rounded-full shrink-0" style="background-color: ${route.route_color}"></span>
                    <h4 class="text-[13px] font-bold text-slate-800 leading-snug">${route.route_name}</h4>
                </div>
                <div>
                    ${healthBadge}
                </div>
            </div>
            <div class="grid grid-cols-3 gap-2 text-[11.5px] text-slate-500 font-semibold">
                <div>Active: <strong class="text-slate-700 font-bold font-mono">${route.buses_on_route} buses</strong></div>
                <div>Trips done: <strong class="text-slate-700 font-bold font-mono">${route.completed_trips}</strong></div>
                <div class="text-right">Actual headway: <strong class="text-slate-700 font-bold font-mono">${escapeOverviewHtml(headwayLabel)}</strong></div>
            </div>
            <div class="w-full bg-[#E6E5E0] h-1 rounded-full overflow-hidden">
                <div class="h-full rounded-full" style="width: ${progressPct}%; background-color: ${route.route_color}"></div>
            </div>
        `;
        routeHealthContainer.appendChild(routeDiv);
    });

    // 5. Actual Trip outcomes today
    const outcomes = data.tripOutcomes || {};
    document.getElementById('trip-outcomes-run').innerText = outcomes.trips_run ?? 0;
    document.getElementById('trip-outcomes-ongoing').innerText = outcomes.ongoing ?? 0;
    document.getElementById('trip-outcomes-completed').innerText = outcomes.completed ?? 0;
    document.getElementById('trip-outcomes-dispatched').innerText = outcomes.dispatched ?? 0;
    document.getElementById('trip-outcomes-cancelled').innerText = outcomes.cancelled ?? 0;
    document.getElementById('trip-outcomes-latest').innerText = outcomes.latest_activity || 'No trip activity today';
    document.getElementById('trip-outcomes-as-of').innerText = outcomes.as_of || '--';

    // 6. Recent Activities
    const activityContainer = document.getElementById('recent-activity-container');
    activityContainer.innerHTML = '';
    
    if (data.recentActivity && data.recentActivity.length > 0) {
        data.recentActivity.forEach(activity => {
            let nodeColor = '#888780';
            if (activity.type === 'Dispatch') nodeColor = '#003F87';
            else if (activity.type === 'Incident') nodeColor = '#E24B4A';
            else if (activity.type === 'Maintenance') nodeColor = '#BA7517';
            else if (activity.type === 'Trip end') nodeColor = '#639922';
            else if (activity.type === 'Announcement') nodeColor = '#378ADD';

            const activityRow = document.createElement('div');
            activityRow.className = 'relative py-0.5 group flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 transition-all rounded p-1 hover:bg-slate-50';
            activityRow.innerHTML = `
                <span class="absolute h-2 w-2 rounded-full border-2 border-white shadow-sm -left-[20.5px] z-10" style="background-color: ${nodeColor}"></span>
                <div class="flex-1 min-w-0">
                    <span class="text-[13px] text-slate-700 font-semibold">${escapeOverviewHtml(activity.description)}</span>
                </div>
                <div class="shrink-0 text-right sm:pl-4">
                    <span class="text-[11.5px] text-slate-400 font-bold font-mono">${escapeOverviewHtml(activity.timestamp)}</span>
                </div>
            `;
            activityContainer.appendChild(activityRow);
        });
    } else {
        activityContainer.innerHTML = `
            <div class="py-8 text-center text-slate-400 text-[13px] font-bold">
                No recent activities recorded today
            </div>
        `;
    }

    // 7. Update incident modal dynamic trip choices if ongoingTrips changed
    const tripSelect = document.getElementById('incident-trip-id');
    if (tripSelect) {
        const currentVal = tripSelect.value;
        tripSelect.innerHTML = '<option value="">Select an ongoing trip...</option>';
        (data.ongoingTrips || []).forEach(trip => {
            const opt = document.createElement('option');
            opt.value = trip.id;
            opt.innerText = `${trip.plate_number} | ${trip.driver_name} | ${trip.route_name}`;
            tripSelect.appendChild(opt);
        });
        tripSelect.value = currentVal;
    }
}

// Convert absolute ISO date to relative timestamp
function getRelativeTime(isoString) {
    const date = new Date(isoString);
    const now = new Date();
    const seconds = Math.floor((now - date) / 1000);
    
    if (seconds < 60) return 'Just now';
    const minutes = Math.floor(seconds / 60);
    if (minutes < 60) return `${minutes}m ago`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return `${hours}h ago`;
    return date.toLocaleDateString();
}

// Leaflet Preview Map
function initOverviewPreviewMap(routes, buses) {
    const mapContainer = document.getElementById('previewMap');
    if (!mapContainer || previewMapInstance) return;

    previewMapInstance = L.map('previewMap', {
        zoomControl: false,
        attributionControl: false,
        scrollWheelZoom: false,
        doubleClickZoom: false,
        boxZoom: false,
        dragPan: false,
        zoomDelta: 0
    }).setView([14.5764, 121.0851], 12.2);

    try {
        L.gridLayer.googleMutant({
            type: 'roadmap'
        }).addTo(previewMapInstance);
    } catch (error) {
        console.error("Google Maps Mutant failed overlay load in preview map:", error);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            maxZoom: 18
        }).addTo(previewMapInstance);
    }

    if (window.GoPasigRouteMapUX) {
        window.GoPasigRouteMapUX.mount({ map: previewMapInstance, routes: routes, compact: true, fitOnFirstRender: true });
    }

    updatePreviewMapMarkers(buses);
}

// Update map markers
function updatePreviewMapMarkers(buses) {
    if (!previewMapInstance) return;

    // Clear old markers
    previewBusesMarkers.forEach(m => previewMapInstance.removeLayer(m));
    previewBusesMarkers = [];

    // Draw new ones
    buses.forEach(bus => {
        const isBusActive = bus.status === 'active';
        const isDelayed = bus.eta >= 10;
        
        let color = '#888780'; // Idle
        if (isBusActive) {
            color = isDelayed ? '#BA7517' : '#003F87'; // Delayed vs Active
        } else if (bus.status === 'maintenance') {
            color = '#E24B4A'; // Breakdown/Offline
        }

        const pulseHtml = isBusActive && !isDelayed ? `<div class="absolute w-full h-full rounded-full animate-ping opacity-60" style="background-color: ${color}"></div>` : '';
        const markerHtml = `
            <div class="relative w-3.5 h-3.5 rounded-full border-2 border-white shadow-md flex items-center justify-center" style="background-color: ${color}">
                ${pulseHtml}
            </div>
        `;

        const icon = L.divIcon({
            html: markerHtml,
            className: 'preview-bus-marker',
            iconSize: [16, 16],
            iconAnchor: [8, 8]
        });

        const marker = L.marker([bus.lat || 14.5593, bus.lng || 121.0805], { icon: icon }).addTo(previewMapInstance);
        previewBusesMarkers.push(marker);
    });
}

// Toast Notification Helper
function showToastNotification(message, isError = false) {
    const toast = document.getElementById('overview-toast');
    const toastMessage = document.getElementById('overview-toast-message');
    const toastIcon = document.getElementById('overview-toast-icon');
    
    if (!toast || !toastMessage) return;

    toastMessage.innerText = message;
    if (isError) {
        toast.classList.add('bg-red-800');
        toast.classList.remove('bg-slate-900');
        if (toastIcon) toastIcon.className = 'ti ti-circle-x text-red-200';
    } else {
        toast.classList.add('bg-slate-900');
        toast.classList.remove('bg-red-800');
        if (toastIcon) toastIcon.className = 'ti ti-circle-check text-emerald-400';
    }

    toast.classList.add('show');
    setTimeout(() => {
        toast.classList.remove('show');
    }, 4000);
}

// Resolve Incident Action (AJAX POST)
async function resolveIncidentAction(id) {
    if (!(await GoPasigUI.confirm('Are you sure you want to mark this incident as resolved?'))) return;
    
    try {
        const url = `/fleet/api/incidents/${id}/resolve`;
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetOverviewConfig.csrfToken
            }
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToastNotification(data.message);
            // Refresh stats immediately
            fetchOverviewDashboardData();
        } else {
            showToastNotification(data.message || 'Glitched resolving incident.', true);
        }
    } catch (error) {
        console.error('Error resolving incident:', error);
        showToastNotification('Failed to resolve incident.', true);
    }
}

// Log Incident modal functions
function openLogIncidentModal() {
    const modal = document.getElementById('log-incident-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Reset form
        document.getElementById('incident-trip-id').value = '';
        document.getElementById('incident-type-input').selectedIndex = 0;
        document.getElementById('incident-description-input').value = '';
        clearFormErrors('incident-form');
    }
}

function closeLogIncidentModal() {
    const modal = document.getElementById('log-incident-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Submit Incident Form (AJAX POST)
async function submitIncidentForm(event) {
    event.preventDefault();
    clearFormErrors('incident-form');

    const tripId = document.getElementById('incident-trip-id').value;
    const type = document.getElementById('incident-type-input').value;
    const description = document.getElementById('incident-description-input').value.trim();

    let hasErrors = false;
    if (!tripId) {
        showFieldError('incident-trip-id', 'Select an official ongoing trip.');
        hasErrors = true;
    }
    if (description.length < 5) {
        showFieldError('incident-description-input', 'Description must be at least 5 characters.');
        hasErrors = true;
    }

    if (hasErrors) return;

    try {
        const response = await fetch(window.FleetOverviewConfig.incidentsUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetOverviewConfig.csrfToken
            },
            body: JSON.stringify({
                trip_id: tripId,
                type: type,
                description: description
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            showToastNotification(data.message);
            closeLogIncidentModal();
            fetchOverviewDashboardData();
        } else {
            showToastNotification(data.message || 'May error sa pag-log ng incident.', true);
            if (data.errors) {
                // Show laravel validation errors
                for (let key in data.errors) {
                    showToastNotification(data.errors[key][0], true);
                }
            }
        }
    } catch (error) {
        console.error('Error logging incident:', error);
        showToastNotification('Failed to submit incident.', true);
    }
}

// Announcement modal functions
function openPostAnnouncementModal() {
    const modal = document.getElementById('post-announcement-modal');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        
        // Reset form
        document.getElementById('announcement-title-input').value = '';
        document.getElementById('announcement-message-input').value = '';
        clearFormErrors('announcement-form');
    }
}

function closePostAnnouncementModal() {
    const modal = document.getElementById('post-announcement-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Submit Announcement Form (AJAX POST)
async function submitAnnouncementForm(event) {
    event.preventDefault();
    clearFormErrors('announcement-form');

    const title = document.getElementById('announcement-title-input').value.trim();
    const message = document.getElementById('announcement-message-input').value.trim();

    let hasErrors = false;
    if (title.length < 3) {
        showFieldError('announcement-title-input', 'Ang pamagat ay dapat kahit 3 characters man lang.');
        hasErrors = true;
    }
    if (!message) {
        showFieldError('announcement-message-input', 'Pakisulat ang detalye ng anunsyo.');
        hasErrors = true;
    }

    if (hasErrors) return;

    try {
        const response = await fetch(window.FleetOverviewConfig.announcementsUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetOverviewConfig.csrfToken
            },
            body: JSON.stringify({
                title: title,
                message: message
            })
        });

        const data = await response.json();

        if (response.ok && data.success) {
            showToastNotification(data.message);
            closePostAnnouncementModal();
            fetchOverviewDashboardData();
        } else {
            showToastNotification(data.message || 'May error sa pag-post ng anunsyo.', true);
        }
    } catch (error) {
        console.error('Error posting announcement:', error);
        showToastNotification('Failed to submit announcement.', true);
    }
}

// Validation UI Helpers
function showFieldError(inputId, errorMsg) {
    const input = document.getElementById(inputId);
    if (!input) return;
    
    input.classList.add('border-red-500', 'bg-red-50');
    
    const errSpan = document.createElement('span');
    errSpan.className = 'text-[11px] text-[#A32D2D] font-bold block mt-1 field-error-msg';
    errSpan.innerText = errorMsg;
    input.parentNode.appendChild(errSpan);
}

function clearFormErrors(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.querySelectorAll('.border-red-500').forEach(el => {
        el.classList.remove('border-red-500', 'bg-red-50');
    });

    form.querySelectorAll('.field-error-msg').forEach(el => el.remove());
}

// Initialize Overview logic on load
document.addEventListener('DOMContentLoaded', () => {
    // Render initial charts/maps if database is loaded
    if (window.GoPasigOverviewInitialData) {
        const initData = window.GoPasigOverviewInitialData;
        setTimeout(() => {
            initOverviewPreviewMap(initData.routes, initData.buses);
        }, 150);
    }

    if (window.GoPasigFleetModules?.registerPoller) {
        window.GoPasigFleetModules.registerPoller('overview', 'operational-data', fetchOverviewDashboardData, 30000);
    } else {
        setInterval(fetchOverviewDashboardData, 30000);
    }
});


