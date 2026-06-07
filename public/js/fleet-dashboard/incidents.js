/**
 * GoPasig Fleet Ops - Incidents Management Javascript Controller
 * Handles Vanilla JS / AJAX CRUD, filters, drawer views, validation, and confirmation modals.
 */

// Window Configuration Setup
window.FleetIncidentsConfig = {
    dataUrl: '/fleet/api/incidents-data',
    storeUrl: '/fleet/api/incidents-store',
    updateStatusUrl: '/fleet/api/incidents-update-status',
    deleteUrl: '/fleet/api/incidents-delete',
    tripDetailsUrl: '/fleet/api/trips-details',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

// Global DOM State
let selectedIncidentId = null;
let currentActiveIncidents = [];
let currentResolvedIncidents = [];

// Fetch and Update Incidents Page
async function fetchIncidentsData() {
    const dateStart = document.getElementById('filter-incidents-date-start')?.value;
    const dateEnd = document.getElementById('filter-incidents-date-end')?.value;
    const routeFilter = document.getElementById('filter-incidents-route')?.value;
    const typeFilter = document.getElementById('filter-incidents-type')?.value;
    const statusFilter = document.getElementById('filter-incidents-status')?.value;
    const activeSort = document.querySelector('[data-sort-active]')?.getAttribute('data-sort-active') || 'newest';

    try {
        const queryParams = new URLSearchParams({
            date_start: dateStart || '',
            date_end: dateEnd || '',
            route_filter: routeFilter || 'all',
            type_filter: typeFilter || 'all',
            status_filter: statusFilter || 'all',
            active_sort: activeSort
        });

        const response = await fetch(`${window.FleetIncidentsConfig.dataUrl}?${queryParams.toString()}`);
        if (!response.ok) throw new Error('Network response not ok');
        const data = await response.json();
        
        currentActiveIncidents = data.activeIncidents;
        currentResolvedIncidents = data.resolvedIncidents;

        updateIncidentsMetricsDOM(data.incidentMetrics);
        updateActiveIncidentsFeedDOM();
        updateResolvedIncidentsTableDOM();
    } catch (error) {
        console.error('Failed to fetch refreshed incidents stats:', error);
    }
}

// Update KPI Metrics Cards
function updateIncidentsMetricsDOM(metrics) {
    if (!metrics) return;
    const m1 = document.getElementById('metric-incidents-today');
    if (m1) m1.innerText = metrics.total_today;
    const m2 = document.getElementById('metric-incidents-open');
    if (m2) m2.innerText = metrics.open;
    const m3 = document.getElementById('metric-incidents-review');
    if (m3) m3.innerText = metrics.under_investigation;
    const m4 = document.getElementById('metric-incidents-resolved-today');
    if (m4) m4.innerText = metrics.resolved_today;
    const m5 = document.getElementById('metric-incidents-avg-time');
    if (m5) m5.innerText = `${metrics.avg_resolution_minutes} min`;
}

// Update Active Feed
function updateActiveIncidentsFeedDOM() {
    const feed = document.getElementById('active-incidents-list');
    const badge = document.getElementById('active-incidents-count-badge');
    if (!feed) return;

    badge.innerText = `${currentActiveIncidents.length} active`;
    if (currentActiveIncidents.length > 0) {
        badge.className = 'rounded-full px-2.5 py-1 text-[12px] font-medium bg-[#FCEBEB] text-[#A32D2D]';
    } else {
        badge.className = 'rounded-full px-2.5 py-1 text-[12px] font-medium bg-slate-100 text-slate-500';
    }

    feed.innerHTML = '';
    if (currentActiveIncidents.length === 0) {
        feed.innerHTML = `
            <div class="grid min-h-[220px] place-items-center rounded-xl border border-dashed border-black/10 bg-slate-50/70 p-6 text-center">
                <div class="space-y-2">
                    <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-slate-200/80 text-slate-500">
                        <i class="ti ti-shield-check text-[24px]"></i>
                    </div>
                    <p class="text-[14px] font-medium text-slate-500">No active incidents</p>
                    <p class="text-[12px] text-slate-400">All clear</p>
                </div>
            </div>
        `;
        return;
    }

    const statusClasses = {
        'reported': 'bg-[#FAEEDA] text-[#854F0B]',
        'under_review': 'bg-[#E6F1FB] text-[#185FA5]',
        'resolved': 'bg-[#EAF3DE] text-[#3B6D11]'
    };
    const statusLabels = {
        'reported': 'Open',
        'under_review': 'Under Investigation',
        'resolved': 'Resolved'
    };

    currentActiveIncidents.forEach(incident => {
        const timeDiff = getRelativeTime(incident.reported_at);
        const statusClass = statusClasses[incident.status] || 'bg-slate-100 text-slate-600';
        const statusLabel = statusLabels[incident.status] || incident.status;
        const barColor = incident.status === 'reported' ? 'bg-[#BA7517]' : 'bg-[#378ADD]';

        const article = document.createElement('article');
        article.className = 'flex cursor-pointer items-start justify-between gap-3 rounded-xl border border-black/10 bg-white px-4 py-3 transition hover:border-[#003F87]/30 hover:bg-[#F8FBFF] hover:shadow-sm';
        article.onclick = () => openIncidentDrawerAction(incident.id);
        article.innerHTML = `
            <div class="mt-1 h-[46px] w-[3px] shrink-0 rounded-full ${barColor}"></div>
            <div class="min-w-0 flex-1 space-y-1.5">
                <h3 class="truncate text-[14px] font-medium text-[#001F44]">${incident.title}</h3>
                <p class="text-[12px] text-slate-500">${incident.incident_id} • ${incident.bus_plate} • ${incident.driver_name} • ${incident.route_name}</p>
            </div>
            <div class="min-w-[150px] space-y-1 text-right">
                <p class="text-[12px] text-slate-400">Reported ${timeDiff}</p>
                <span class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-medium ${statusClass}">${statusLabel}</span>
            </div>
        `;
        feed.appendChild(article);
    });
}

// Update Resolved Incidents Table
function updateResolvedIncidentsTableDOM() {
    const tableBody = document.getElementById('resolved-incidents-table-body');
    const container = document.getElementById('resolved-incidents-container');
    const badge = document.getElementById('resolved-incidents-count-badge');
    if (!tableBody) return;

    if (badge) badge.innerText = `${currentResolvedIncidents.length} resolved`;

    tableBody.innerHTML = '';
    if (currentResolvedIncidents.length === 0) {
        tableBody.innerHTML = `
            <tr>
                <td colspan="5" class="py-8 text-center text-slate-400">No resolved incidents yet.</td>
            </tr>
        `;
        return;
    }

    const statusClasses = {
        'reported': 'bg-[#FAEEDA] text-[#854F0B]',
        'under_review': 'bg-[#E6F1FB] text-[#185FA5]',
        'resolved': 'bg-[#EAF3DE] text-[#3B6D11]'
    };
    const statusLabels = {
        'reported': 'Open',
        'under_review': 'Under Investigation',
        'resolved': 'Resolved'
    };

    currentResolvedIncidents.forEach(incident => {
        const tr = document.createElement('tr');
        tr.className = 'hover:bg-slate-50 transition-colors cursor-pointer';
        tr.onclick = () => openIncidentDrawerAction(incident.id);

        const statusClass = statusClasses[incident.status] || 'bg-slate-100 text-slate-600';
        const statusLabel = statusLabels[incident.status] || incident.status;
        const resolvedTime = formatDateTime(incident.updated_at);

        tr.innerHTML = `
            <td class="py-3 px-3 font-mono text-[12px] text-slate-600 font-semibold">${incident.incident_id}</td>
            <td class="py-3 px-3">
                <div class="font-medium text-[#001F44]">${incident.title}</div>
                <div class="text-[11px] text-slate-400 truncate">${incident.description}</div>
            </td>
            <td class="py-3 px-3 text-slate-600">${incident.route_name}</td>
            <td class="py-3 px-3 text-slate-500 font-mono text-[12px]">${resolvedTime}</td>
            <td class="py-3 px-3">
                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-medium ${statusClass}">${statusLabel}</span>
            </td>
        `;
        tableBody.appendChild(tr);
    });
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

// Format DateTime nicely
function formatDateTime(isoString) {
    const d = new Date(isoString);
    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const month = months[d.getMonth()];
    const day = String(d.getDate()).padStart(2, '0');
    const year = d.getFullYear();
    
    let hours = d.getHours();
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12;
    const hourStr = String(hours).padStart(2, '0');

    return `${month} ${day}, ${year} ${hourStr}:${minutes} ${ampm}`;
}

// Detail Drawer Actions
function openIncidentDrawerAction(id) {
    selectedIncidentId = id;
    const incident = [...currentActiveIncidents, ...currentResolvedIncidents].find(i => i.id == id);
    if (!incident) return;

    // Fill elements
    document.getElementById('drawer-incident-id').innerText = incident.incident_id;
    document.getElementById('drawer-incident-title').innerText = incident.title;
    document.getElementById('drawer-incident-description').innerText = incident.description;
    document.getElementById('drawer-incident-type').innerText = incident.type;
    
    const statusEl = document.getElementById('drawer-incident-status');
    const statusClasses = {
        'reported': 'bg-[#FAEEDA] text-[#854F0B]',
        'under_review': 'bg-[#E6F1FB] text-[#185FA5]',
        'resolved': 'bg-[#EAF3DE] text-[#3B6D11]'
    };
    const statusLabels = {
        'reported': 'Open',
        'under_review': 'Under Investigation',
        'resolved': 'Resolved'
    };
    statusEl.className = `inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-bold uppercase tracking-wide ${statusClasses[incident.status] || 'bg-slate-100'}`;
    statusEl.innerText = statusLabels[incident.status] || incident.status;

    document.getElementById('drawer-bus-plate').innerText = incident.bus_plate;
    document.getElementById('drawer-route-name').innerText = incident.route_name;
    document.getElementById('drawer-driver-name').innerText = incident.driver_name;
    
    const timeText = formatDateTime(incident.reported_at);
    document.getElementById('drawer-reported-at').innerText = `${timeText} (${getRelativeTime(incident.reported_at)})`;

    // Footer actions visibility
    const footer = document.getElementById('drawer-actions-footer');
    footer.innerHTML = '';

    if (incident.status !== 'resolved') {
        footer.innerHTML += `<button onclick="confirmResolveIncidentModal()" class="w-full rounded-lg bg-[#003F87] hover:bg-[#002D62] text-white px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer shadow-sm">Mark as resolved</button>`;
    }
    
    if (incident.status === 'reported') {
        footer.innerHTML += `<button onclick="updateIncidentStatusAction(${id}, 'under_review')" class="w-full rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer">Move to Under Investigation</button>`;
    }

    if (incident.status === 'under_review') {
        footer.innerHTML += `<button onclick="updateIncidentStatusAction(${id}, 'reported')" class="w-full rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer">Move back to Open</button>`;
    }

    if (incident.status === 'resolved') {
        footer.innerHTML += `<button onclick="updateIncidentStatusAction(${id}, 'under_review')" class="w-full rounded-lg border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer">Reopen Incident</button>`;
    }

    footer.innerHTML += `<button onclick="confirmDeleteIncidentModal()" class="w-full rounded-lg border border-red-200 hover:bg-red-50 text-red-600 px-4 py-2.5 text-[13px] font-semibold transition cursor-pointer">Delete / Cancel Record</button>`;

    // Show drawer
    const drawerContainer = document.getElementById('details-drawer-container');
    if (drawerContainer) {
        drawerContainer.classList.remove('hidden');
    }
}

function closeIncidentDrawerAction() {
    selectedIncidentId = null;
    const drawerContainer = document.getElementById('details-drawer-container');
    if (drawerContainer) {
        drawerContainer.classList.add('hidden');
    }
}

// Confirmation Modals
function confirmResolveIncidentModal() {
    const modal = document.getElementById('confirm-resolve-modal');
    if (modal) modal.classList.remove('hidden');
}

function closeResolveIncidentModal() {
    const modal = document.getElementById('confirm-resolve-modal');
    if (modal) modal.classList.add('hidden');
}

async function executeResolveIncident() {
    if (!selectedIncidentId) return;
    await updateIncidentStatusAction(selectedIncidentId, 'resolved');
    closeResolveIncidentModal();
}

function confirmDeleteIncidentModal() {
    const modal = document.getElementById('confirm-delete-modal');
    if (modal) modal.classList.remove('hidden');
}

function closeDeleteIncidentModal() {
    const modal = document.getElementById('confirm-delete-modal');
    if (modal) modal.classList.add('hidden');
}

async function executeDeleteIncident() {
    if (!selectedIncidentId) return;
    try {
        const response = await fetch(`${window.FleetIncidentsConfig.deleteUrl}/${selectedIncidentId}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetIncidentsConfig.csrfToken
            }
        });
        const data = await response.json();
        if (response.ok && data.success) {
            showIncidentsAlert(data.message);
            closeIncidentDrawerAction();
            fetchIncidentsData();
        } else {
            showIncidentsAlert(data.message || 'Error deleting incident.', true);
        }
    } catch (error) {
        console.error('Error deleting incident:', error);
        showIncidentsAlert('Failed to delete incident.', true);
    }
    closeDeleteIncidentModal();
}

// Update Incident Status AJAX
async function updateIncidentStatusAction(id, status) {
    try {
        const response = await fetch(`${window.FleetIncidentsConfig.updateStatusUrl}/${id}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetIncidentsConfig.csrfToken
            },
            body: JSON.stringify({ status: status })
        });
        const data = await response.json();
        if (response.ok && data.success) {
            showIncidentsAlert(data.message);
            closeIncidentDrawerAction();
            fetchIncidentsData();
        } else {
            showIncidentsAlert(data.message || 'Error updating status.', true);
        }
    } catch (error) {
        console.error('Error updating status:', error);
        showIncidentsAlert('Failed to update status.', true);
    }
}

// Log Incident Modal Controls
function openLogIncidentModal() {
    const modal = document.getElementById('log-incident-modal');
    if (modal) {
        modal.classList.remove('hidden');
        document.getElementById('newTripId').value = '';
        document.getElementById('newType').value = 'Breakdown';
        document.getElementById('newStatus').value = 'reported';
        document.getElementById('newDescription').value = '';
        document.getElementById('form-auto-fields').classList.add('hidden');
        clearIncidentsFormErrors();
    }
}

function closeLogIncidentModal() {
    const modal = document.getElementById('log-incident-modal');
    if (modal) {
        modal.classList.add('hidden');
        clearIncidentsFormErrors();
    }
}

// Fetch ongoing trip details when select dropdown changes
async function handleTripChange(event) {
    const tripId = event.target.value;
    const card = document.getElementById('form-auto-fields');
    if (!tripId) {
        card.classList.add('hidden');
        return;
    }

    try {
        const response = await fetch(`${window.FleetIncidentsConfig.tripDetailsUrl}/${tripId}`);
        if (!response.ok) throw new Error('Trip details response error');
        const data = await response.json();

        if (data.success) {
            document.getElementById('auto-bus-plate').innerText = data.bus_plate;
            document.getElementById('auto-route-name').innerText = data.route_name;
            document.getElementById('auto-driver-name').innerText = data.driver_name;
            card.classList.remove('hidden');
        }
    } catch (error) {
        console.error('Error loading ongoing trip details:', error);
    }
}

// Submit Incident Creation
async function submitIncidentForm(event) {
    event.preventDefault();
    clearIncidentsFormErrors();

    const tripId = document.getElementById('newTripId').value;
    const type = document.getElementById('newType').value;
    const status = document.getElementById('newStatus').value;
    const description = document.getElementById('newDescription').value.trim();

    let hasErrors = false;
    if (!tripId) {
        showIncidentsFieldError('newTripId', 'Affected Ongoing Trip is required.');
        hasErrors = true;
    }
    if (!description || description.length < 5) {
        showIncidentsFieldError('newDescription', 'Description must be at least 5 characters.');
        hasErrors = true;
    }

    if (hasErrors) return;

    try {
        const response = await fetch(window.FleetIncidentsConfig.storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetIncidentsConfig.csrfToken
            },
            body: JSON.stringify({
                trip_id: tripId,
                type: type,
                status: status,
                description: description
            })
        });

        const data = await response.json();
        if (response.ok && data.success) {
            showIncidentsAlert(data.message);
            closeLogIncidentModal();
            fetchIncidentsData();
        } else {
            showIncidentsAlert(data.message || 'Failed to submit incident.', true);
        }
    } catch (error) {
        console.error('Error storing incident:', error);
        showIncidentsAlert('Failed to log incident.', true);
    }
}

// Show alert message
function showIncidentsAlert(message, isError = false) {
    const alertBox = document.getElementById('incidents-alert');
    const alertMsg = document.getElementById('incidents-alert-message');
    if (alertBox && alertMsg) {
        alertMsg.innerText = message;
        if (isError) {
            alertBox.className = 'p-3 bg-red-100 border border-red-500 text-red-700 rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up';
            alertBox.querySelector('i').className = 'ti ti-circle-x text-[16px]';
        } else {
            alertBox.className = 'p-3 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up';
            alertBox.querySelector('i').className = 'ti ti-circle-check text-[16px]';
        }
        alertBox.classList.remove('hidden');
        setTimeout(() => alertBox.classList.add('hidden'), 5000);
    }
}

// Error UI helper
function showIncidentsFieldError(id, msg) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.add('border-red-500', 'bg-red-50');
    
    const err = document.createElement('span');
    err.className = 'text-xs text-red-500 font-medium block mt-1 incident-error';
    err.innerText = msg;
    el.parentNode.appendChild(err);
}

function clearIncidentsFormErrors() {
    document.querySelectorAll('.incident-error').forEach(e => e.remove());
    document.querySelectorAll('.border-red-500').forEach(e => e.classList.remove('border-red-500', 'bg-red-50'));
}

// Document ready entry
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('active-incidents-list')) {
        // Load initial dataset if injected
        if (window.GoPasigIncidentsInitialData) {
            currentActiveIncidents = window.GoPasigIncidentsInitialData.activeIncidents;
            currentResolvedIncidents = window.GoPasigIncidentsInitialData.resolvedIncidents;
            updateIncidentsMetricsDOM(window.GoPasigIncidentsInitialData.incidentMetrics);
            updateActiveIncidentsFeedDOM();
            updateResolvedIncidentsTableDOM();
        }

        // Event Listeners for Filters
        const filters = ['filter-incidents-date-start', 'filter-incidents-date-end', 'filter-incidents-route', 'filter-incidents-type', 'filter-incidents-status'];
        filters.forEach(id => {
            document.getElementById(id)?.addEventListener('change', fetchIncidentsData);
        });

        // Event Listener for Trip Select in Form
        document.getElementById('newTripId')?.addEventListener('change', handleTripChange);

        // Sorting buttons
        document.querySelectorAll('.inline-flex.rounded-full.border button').forEach(btn => {
            btn.addEventListener('click', (e) => {
                // Remove styling of sibling
                btn.parentNode.querySelectorAll('button').forEach(b => {
                    b.className = 'rounded-full px-3 py-1 font-medium transition cursor-pointer text-slate-600 hover:text-slate-900';
                });
                btn.className = 'rounded-full px-3 py-1 font-medium transition cursor-pointer bg-white text-[#003F87] shadow-sm';
                
                const container = btn.parentNode;
                const activeSort = e.target.innerText.toLowerCase().includes('priority') ? 'priority' : 'newest';
                container.setAttribute('data-sort-active', activeSort);
                
                fetchIncidentsData();
            });
        });

        // Export button click
        document.querySelector('[wire\\:click="exportIncidentReport"]')?.addEventListener('click', () => {
            const dateStart = document.getElementById('filter-incidents-date-start')?.value || '';
            const dateEnd = document.getElementById('filter-incidents-date-end')?.value || '';
            const routeFilter = document.getElementById('filter-incidents-route')?.value || 'all';
            const typeFilter = document.getElementById('filter-incidents-type')?.value || 'all';
            const statusFilter = document.getElementById('filter-incidents-status')?.value || 'all';
            
            window.location.href = `/fleet/api/incidents-export?date_start=${dateStart}&date_end=${dateEnd}&route_filter=${routeFilter}&type_filter=${typeFilter}&status_filter=${statusFilter}`;
        });

        // Toggle Resolved incidents button
        document.querySelector('[wire\\:click="toggleResolvedLog"]')?.addEventListener('click', (e) => {
            const container = document.getElementById('resolved-incidents-container');
            const icon = e.currentTarget.querySelector('i');
            const span = e.currentTarget.querySelector('span');

            if (container.classList.contains('hidden')) {
                container.classList.remove('hidden');
                icon.className = 'ti ti-chevron-up text-[14px]';
                span.innerText = 'Hide resolved incidents';
            } else {
                container.classList.add('hidden');
                icon.className = 'ti ti-chevron-down text-[14px]';
                span.innerText = 'Show resolved incidents';
            }
        });

        // Form submission
        document.getElementById('incident-creation-form')?.addEventListener('submit', submitIncidentForm);

        // Polling interval
        setInterval(fetchIncidentsData, 15000);
    }
});
