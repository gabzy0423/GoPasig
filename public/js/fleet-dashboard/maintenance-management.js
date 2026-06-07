/**
 * GoPasig Fleet Ops - Maintenance Management Javascript Controller
 * Handles Vanilla JS / AJAX CRUD, filters, drawer views, validation, and status updates.
 */

window.FleetMaintenanceConfig = {
    dataUrl: '/fleet/api/maintenance-data',
    recordUrl: '/fleet/api/maintenance-record',
    busUrl: '/fleet/api/maintenance-bus',
    storeUrl: '/fleet/api/maintenance-store',
    updateStatusUrl: '/fleet/api/maintenance-update-status',
    deleteUrl: '/fleet/api/maintenance-delete',
    exportUrl: '/fleet/api/maintenance-export',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

// Global variables to track selected states
let selectedRecordId = null;
let selectedBusPlate = null;
let currentLogsPage = 1;

// Document Ready Setup
document.addEventListener('DOMContentLoaded', () => {
    // Only execute if on the maintenance page
    if (document.getElementById('log-type-filter') || document.getElementById('log-status-filter')) {
        setupEventListeners();
        initializeInitialData();
    }
});

// Setup DOM Event Listeners
function setupEventListeners() {
    // 1. Filter dropdown changes
    const typeFilter = document.getElementById('log-type-filter');
    const statusFilter = document.getElementById('log-status-filter');

    if (typeFilter) {
        typeFilter.addEventListener('change', () => {
            currentLogsPage = 1;
            fetchMaintenanceData();
        });
    }

    if (statusFilter) {
        statusFilter.addEventListener('change', () => {
            currentLogsPage = 1;
            fetchMaintenanceData();
        });
    }

    // 2. Schedule maintenance trigger
    const btnAdd = document.getElementById('btn-add-maintenance');
    if (btnAdd) {
        btnAdd.addEventListener('click', () => {
            openScheduleModal();
        });
    }

    // 3. Export CSV button
    const btnExport = document.getElementById('btn-export-maintenance');
    if (btnExport) {
        btnExport.addEventListener('click', () => {
            const typeVal = document.getElementById('log-type-filter')?.value || 'all';
            const statusVal = document.getElementById('log-status-filter')?.value || 'all';
            window.location.href = `${window.FleetMaintenanceConfig.exportUrl}?type=${typeVal}&status=${statusVal}`;
        });
    }

    // 4. Modal validation reset on change
    const fields = ['form-bus-id', 'form-scheduled-at', 'form-technician-name', 'form-cost', 'form-description'];
    fields.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', () => {
                const err = document.getElementById(`error-${id.replace('form-', '')}`);
                if (err) err.classList.add('hidden');
            });
        }
    });

    // 5. Drawer Actions
    const btnStart = document.getElementById('btn-action-start');
    if (btnStart) {
        btnStart.addEventListener('click', () => {
            if (selectedRecordId) updateRecordStatus(selectedRecordId, 'in_progress');
        });
    }

    const btnComplete = document.getElementById('btn-action-complete');
    if (btnComplete) {
        btnComplete.addEventListener('click', () => {
            if (selectedRecordId) updateRecordStatus(selectedRecordId, 'completed');
        });
    }

    const btnCancel = document.getElementById('btn-action-cancel');
    if (btnCancel) {
        btnCancel.addEventListener('click', () => {
            if (selectedRecordId) updateRecordStatus(selectedRecordId, 'cancelled');
        });
    }

    const btnEdit = document.getElementById('btn-action-edit');
    if (btnEdit) {
        btnEdit.addEventListener('click', () => {
            if (selectedRecordId) editRecord(selectedRecordId);
        });
    }

    const btnDelete = document.getElementById('btn-action-delete');
    if (btnDelete) {
        btnDelete.addEventListener('click', () => {
            if (selectedRecordId) deleteRecord(selectedRecordId);
        });
    }

    const btnBusSchedule = document.getElementById('btn-bus-schedule');
    if (btnBusSchedule) {
        btnBusSchedule.addEventListener('click', () => {
            if (selectedBusPlate) {
                // Find bus numeric ID from option elements to open schedule form with it preselected
                const selectEl = document.getElementById('form-bus-id');
                let foundId = '';
                if (selectEl) {
                    for (let i = 0; i < selectEl.options.length; i++) {
                        if (selectEl.options[i].text.includes(selectedBusPlate)) {
                            foundId = selectEl.options[i].value;
                            break;
                        }
                    }
                }
                closeDetailDrawer();
                openScheduleModal(foundId);
            }
        });
    }

    // Escape Key listener
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            closeScheduleModal();
            closeDetailDrawer();
        }
    });
}

// Read and use window initial data if pre-rendered to save network request on load
function initializeInitialData() {
    if (window.GoPasigMaintenanceInitialData) {
        const data = window.GoPasigMaintenanceInitialData;
        currentLogsPage = data.currentPage || 1;
        // The HTML already renders the initial page load, but we can hook pagination listeners
        setupPaginationListeners();
    }
}

// Fetch Maintenance Dashboard Data
async function fetchMaintenanceData(page = currentLogsPage) {
    currentLogsPage = page;
    const typeVal = document.getElementById('log-type-filter')?.value || 'all';
    const statusVal = document.getElementById('log-status-filter')?.value || 'all';

    try {
        const url = `${window.FleetMaintenanceConfig.dataUrl}?type=${typeVal}&status=${statusVal}&page=${page}`;
        const response = await fetch(url);
        if (!response.ok) throw new Error('Failed to fetch maintenance data');
        const data = await response.json();

        updateDashboardDOM(data);
    } catch (err) {
        console.error('Error fetching maintenance data:', err);
    }
}

// Update DOM elements
function updateDashboardDOM(data) {
    if (!data.success) return;

    // 1. Update Metrics Cards
    document.getElementById('summary-total-fleet').innerText = data.summary.total_fleet;
    document.getElementById('summary-active-units').innerText = data.summary.active_units;
    document.getElementById('summary-under-maintenance').innerText = data.summary.under_maintenance;
    document.getElementById('summary-breakdown-count').innerText = data.summary.breakdown_count;
    document.getElementById('summary-due-for-service').innerText = data.summary.due_for_service;

    // 2. Update Bus Health Matrix Grid
    const healthBadge = document.getElementById('bus-health-badge');
    if (healthBadge) healthBadge.innerText = `${data.busHealth.length} units`;

    const healthGrid = document.getElementById('bus-health-grid');
    if (healthGrid) {
        healthGrid.innerHTML = '';
        if (data.busHealth.length === 0) {
            healthGrid.innerHTML = `<p class="col-span-full text-center text-slate-400 py-8">No bus health data.</p>`;
        } else {
            const statusClasses = {
                active: 'border-[#EAF3DE] bg-[#EAF3DE] text-[#3B6D11] hover:bg-[#EAF3DE]/80',
                maintenance: 'border-[#FAEEDA] bg-[#FAEEDA] text-[#854F0B] hover:bg-[#FAEEDA]/80',
                inactive: 'border-[#FCEBEB] bg-[#FCEBEB] text-[#A32D2D] hover:bg-[#FCEBEB]/80'
            };
            const statusLabels = {
                active: 'Active',
                maintenance: 'Maintenance',
                inactive: 'Offline'
            };

            data.busHealth.forEach(bus => {
                const btn = document.createElement('button');
                btn.className = `text-left rounded-xl border px-3 py-3 hover:shadow-xs transition cursor-pointer ${statusClasses[bus.status] || 'border-slate-200 bg-slate-50'}`;
                btn.onclick = () => openBusDrawer(bus.bus_id);
                btn.innerHTML = `
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-[13px] font-bold">${bus.bus_id}</span>
                        <span class="h-2 w-2 rounded-full" style="background-color: ${bus.route_color}"></span>
                    </div>
                    <p class="mt-2 text-[10px] opacity-80 font-medium">${bus.assigned_route ?: 'No Route Assigned'}</p>
                    <p class="mt-2 text-[11px] font-extrabold uppercase tracking-wider">${statusLabels[bus.status] || bus.status}</p>
                `;
                healthGrid.appendChild(btn);
            });
        }
    }

    // 3. Update Upcoming Schedule Panel
    const scheduleWrapper = document.getElementById('upcoming-schedule-wrapper');
    if (scheduleWrapper) {
        if (data.upcomingSchedule.length === 0) {
            scheduleWrapper.innerHTML = `
                <div class="grid min-h-[220px] place-items-center rounded-xl border border-dashed border-black/10 bg-slate-50/70 p-6 text-center">
                    <div class="space-y-2">
                        <i class="ti ti-calendar-off text-[48px] text-slate-300"></i>
                        <p class="text-[14px] text-slate-500 font-semibold">No scheduled maintenance in the next 30 days</p>
                    </div>
                </div>
            `;
        } else {
            let listHtml = `<div class="max-h-[280px] space-y-2 overflow-y-auto pr-1">`;
            data.upcomingSchedule.forEach(entry => {
                listHtml += `
                    <div onclick="openBusDrawer('${entry.bus_id}')" class="rounded-xl border border-black/10 bg-white px-3 py-3 border-l-[3px] border-l-[#185FA5] hover:bg-slate-50 transition cursor-pointer">
                        <p class="text-[13px] font-semibold text-[#001F44]">${entry.scheduled_date}</p>
                        <p class="text-[12px] text-slate-500 mt-0.5">Bus <strong class="text-slate-700 font-mono">${entry.bus_id}</strong> — ${entry.description}</p>
                    </div>
                `;
            });
            listHtml += `</div>`;
            scheduleWrapper.innerHTML = listHtml;
        }
    }

    // 4. Update Logs Table Body
    const tbody = document.getElementById('maintenance-log-tbody');
    if (tbody) {
        tbody.innerHTML = '';
        if (data.logs.data.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="py-12 text-center bg-slate-50/50">
                        <i class="ti ti-calendar-off text-[48px] text-slate-300"></i>
                        <p class="text-[16px] font-bold text-slate-500 mt-2">No maintenance logs found.</p>
                        <p class="text-[13px] text-slate-400 mt-1">Adjust filters or schedule service.</p>
                    </td>
                </tr>
            `;
        } else {
            const statusClasses = {
                scheduled: 'bg-[#E6F1FB] text-[#185FA5] border-[#185FA5]/15',
                in_progress: 'bg-[#FAEEDA] text-[#854F0B] border-[#BA7517]/15',
                completed: 'bg-[#EAF3DE] text-[#3B6D11] border-[#3B6D11]/15',
                cancelled: 'bg-[#FCEBEB] text-[#A32D2D] border-[#A32D2D]/15'
            };
            const statusLabels = {
                scheduled: 'Scheduled',
                in_progress: 'In progress',
                completed: 'Done',
                cancelled: 'Cancelled'
            };

            data.logs.data.forEach(row => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-slate-50 transition-colors';
                tr.innerHTML = `
                    <td class="py-3 px-3 text-slate-600 font-mono text-[12px]">${row.maintenance_date}</td>
                    <td class="py-3 px-3 font-mono text-[#003F87] font-bold">${row.bus_id}</td>
                    <td class="py-3 px-3 text-slate-600">${row.assigned_route}</td>
                    <td class="py-3 px-3"><span class="font-medium text-slate-800">${row.type}</span></td>
                    <td class="py-3 px-3 text-slate-500 truncate" title="${row.description}">${row.description}</td>
                    <td class="py-3 px-3 text-slate-700 font-medium">${row.technician_name}</td>
                    <td class="py-3 px-3 text-right font-mono font-semibold text-slate-700">${row.cost_php}</td>
                    <td class="py-3 px-3">
                        <span class="rounded px-2.5 py-0.5 text-[11px] font-bold border uppercase ${statusClasses[row.status] || 'bg-slate-100 text-slate-600'}">${statusLabels[row.status] || row.status}</span>
                    </td>
                    <td class="py-3 px-3 text-right">
                        <button onclick="openDetailDrawer(${row.id})" class="text-[#003F87] hover:text-[#002d62] font-semibold text-[12px] transition cursor-pointer">View</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }
    }

    // 5. Update Pagination Log Container
    updatePaginationDOM(data.logs);
}

// Build standard tailwind/bootstrap style page links for Vanilla JS
function updatePaginationDOM(logs) {
    const container = document.getElementById('maintenance-log-pagination');
    if (!container) return;

    if (logs.last_page <= 1) {
        container.innerHTML = '';
        return;
    }

    let html = `<nav class="flex items-center justify-between border-t border-slate-200 px-4 py-3 sm:px-6 mt-4" aria-label="Pagination">`;
    html += `<div class="hidden sm:block"><p class="text-sm text-slate-700">Showing <span class="font-medium">${(logs.current_page - 1) * logs.per_page + 1}</span> to <span class="font-medium">${Math.min(logs.current_page * logs.per_page, logs.total)}</span> of <span class="font-medium">${logs.total}</span> logs</p></div>`;
    html += `<div class="flex flex-1 justify-between sm:justify-end gap-2">`;

    if (logs.current_page > 1) {
        html += `<button onclick="fetchMaintenanceData(${logs.current_page - 1})" class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 cursor-pointer">Previous</button>`;
    } else {
        html += `<span class="relative inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-400">Previous</span>`;
    }

    if (logs.current_page < logs.last_page) {
        html += `<button onclick="fetchMaintenanceData(${logs.current_page + 1})" class="relative inline-flex items-center rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50 cursor-pointer">Next</button>`;
    } else {
        html += `<span class="relative inline-flex items-center rounded-md border border-slate-200 bg-slate-50 px-4 py-2 text-sm font-medium text-slate-400">Next</span>`;
    }

    html += `</div></nav>`;
    container.innerHTML = html;
}

// Listen to default blade links if page was not AJAX fetched yet
function setupPaginationListeners() {
    const pagContainer = document.getElementById('maintenance-log-pagination');
    if (!pagContainer) return;

    // Attach click events on standard Laravel pagination links inside the container to make them load via AJAX
    const links = pagContainer.querySelectorAll('a');
    links.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const urlObj = new URL(link.href);
            const pageNum = urlObj.searchParams.get('page') || 1;
            fetchMaintenanceData(Number(pageNum));
        });
    });
}

// Modal Form Scheduling Controls
function openScheduleModal(busId = '') {
    // Reset Validation Errors
    document.querySelectorAll('[id^="error-"]').forEach(el => el.classList.add('hidden'));

    // Reset Form Fields
    document.getElementById('form-record-id').value = '';
    document.getElementById('form-bus-id').value = busId;
    document.getElementById('form-type').value = 'Preventive';
    document.getElementById('form-description').value = '';

    // Set Default DateTime local to current time
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    document.getElementById('form-scheduled-at').value = now.toISOString().slice(0, 16);

    document.getElementById('form-technician-name').value = '';
    document.getElementById('form-cost').value = '';
    document.getElementById('form-status').value = 'scheduled';

    document.getElementById('modal-title-text').innerText = 'Schedule Maintenance';
    const formSubmitBtn = document.querySelector('#maintenance-schedule-form button[type="submit"]');
    if (formSubmitBtn) formSubmitBtn.innerText = 'Schedule Service';

    const modal = document.getElementById('maintenance-schedule-modal');
    if (modal) modal.classList.remove('hidden');
}

function closeScheduleModal() {
    const modal = document.getElementById('maintenance-schedule-modal');
    if (modal) modal.classList.add('hidden');
}

// Save Maintenance Schedule Form Action
async function saveMaintenanceSchedule(event) {
    event.preventDefault();

    const form = document.getElementById('maintenance-schedule-form');
    const formData = new FormData(form);

    const formObj = {};
    formData.forEach((value, key) => {
        formObj[key] = value;
    });

    try {
        const response = await fetch(window.FleetMaintenanceConfig.storeUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetMaintenanceConfig.csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify(formObj)
        });

        const result = await response.json();

        if (response.status === 422) {
            // Display Validation Errors
            if (result.errors) {
                Object.keys(result.errors).forEach(field => {
                    const errorSpan = document.getElementById(`error-${field.replace('_', '-')}`);
                    if (errorSpan) {
                        errorSpan.innerText = result.errors[field][0];
                        errorSpan.classList.remove('hidden');
                    }
                });
            }
            return;
        }

        if (!response.ok) throw new Error(result.message || 'Failed to save schedule');

        // Success Alert
        showSuccessAlert(result.message);
        closeScheduleModal();
        fetchMaintenanceData();

    } catch (error) {
        console.error('Error saving maintenance schedule:', error);
    }
}

// Drawer Controls
function closeDetailDrawer() {
    const drawer = document.getElementById('maintenance-detail-drawer');
    if (drawer) drawer.classList.add('hidden');
    selectedRecordId = null;
    selectedBusPlate = null;
}

// Open Record Details in Drawer
async function openDetailDrawer(id) {
    selectedRecordId = id;
    selectedBusPlate = null;

    try {
        const response = await fetch(`${window.FleetMaintenanceConfig.recordUrl}/${id}`);
        if (!response.ok) throw new Error('Failed to load record details');
        const data = await response.json();

        if (data.success) {
            const rec = data.record;
            document.getElementById('drawer-record-bus-plate').innerText = `Bus ${rec.bus_plate}`;
            document.getElementById('drawer-record-description').innerText = rec.description;
            document.getElementById('drawer-record-type').innerText = rec.type;
            document.getElementById('drawer-record-technician').innerText = rec.technician_name || '—';
            document.getElementById('drawer-record-cost').innerText = `PHP ${rec.cost_formatted}`;
            document.getElementById('drawer-record-date').innerText = rec.scheduled_at_formatted;

            // Setup Badge
            const statusClasses = {
                scheduled: 'bg-[#E6F1FB] text-[#185FA5] border-[#185FA5]/15',
                in_progress: 'bg-[#FAEEDA] text-[#854F0B] border-[#BA7517]/15',
                completed: 'bg-[#EAF3DE] text-[#3B6D11] border-[#3B6D11]/15',
                cancelled: 'bg-[#FCEBEB] text-[#A32D2D] border-[#A32D2D]/15'
            };
            const statusLabels = {
                scheduled: 'Scheduled',
                in_progress: 'In progress',
                completed: 'Done',
                cancelled: 'Cancelled'
            };

            const statusCont = document.getElementById('drawer-record-status-container');
            statusCont.innerHTML = `<span class="inline-flex rounded px-2.5 py-0.5 text-[10px] font-bold uppercase border ${statusClasses[rec.status] || 'bg-slate-100 text-slate-600'}">${statusLabels[rec.status] || rec.status}</span>`;

            // Action buttons configuration based on status
            const btnStart = document.getElementById('btn-action-start');
            const btnComplete = document.getElementById('btn-action-complete');
            const btnCancel = document.getElementById('btn-action-cancel');

            btnStart.classList.add('hidden');
            btnComplete.classList.add('hidden');
            btnCancel.classList.add('hidden');

            if (rec.status === 'scheduled') {
                btnStart.classList.remove('hidden');
                btnCancel.classList.remove('hidden');
            } else if (rec.status === 'in_progress') {
                btnComplete.classList.remove('hidden');
                btnCancel.classList.remove('hidden');
            }

            // Show record drawer content and hide bus profile
            document.getElementById('drawer-record-content').classList.remove('hidden');
            document.getElementById('drawer-bus-content').classList.add('hidden');
            
            // Show Drawer overall container
            document.getElementById('maintenance-detail-drawer').classList.remove('hidden');
        }

    } catch (error) {
        console.error('Error opening detail drawer:', error);
    }
}

// Open Bus Profile in Drawer
async function openBusDrawer(plateNumber) {
    selectedBusPlate = plateNumber;
    selectedRecordId = null;

    try {
        const response = await fetch(`${window.FleetMaintenanceConfig.busUrl}/${plateNumber}`);
        if (!response.ok) throw new Error('Failed to load bus profile');
        const data = await response.json();

        if (data.success) {
            const bus = data.bus;
            document.getElementById('drawer-bus-plate').innerText = bus.plate_number;
            document.getElementById('drawer-bus-route').innerText = bus.assigned_route;
            document.getElementById('drawer-bus-capacity').innerText = `${bus.capacity} passengers`;
            document.getElementById('drawer-bus-passengers').innerText = `${bus.passengers} aboard`;

            // Status Badge
            const statusClasses = {
                active: 'border-[#EAF3DE] bg-[#EAF3DE] text-[#3B6D11]',
                maintenance: 'border-[#FAEEDA] bg-[#FAEEDA] text-[#854F0B]',
                inactive: 'border-[#FCEBEB] bg-[#FCEBEB] text-[#A32D2D]'
            };
            const statusLabels = {
                active: 'Active',
                maintenance: 'Maintenance',
                inactive: 'Offline'
            };

            const statusCont = document.getElementById('drawer-bus-status-container');
            statusCont.innerHTML = `<span class="inline-flex rounded px-2.5 py-0.5 text-[10px] font-bold uppercase border ${statusClasses[bus.status] || 'border-slate-200 bg-slate-50'}">${statusLabels[bus.status] || bus.status}</span>`;

            // Recent Services list
            const servicesList = document.getElementById('drawer-bus-services-list');
            servicesList.innerHTML = '';

            if (bus.recent_services.length === 0) {
                servicesList.innerHTML = `<p class="text-slate-400 text-xs italic py-2">No maintenance history recorded.</p>`;
            } else {
                const recStatusClasses = {
                    scheduled: 'bg-[#E6F1FB] text-[#185FA5] border-[#185FA5]/15',
                    in_progress: 'bg-[#FAEEDA] text-[#854F0B] border-[#BA7517]/15',
                    completed: 'bg-[#EAF3DE] text-[#3B6D11] border-[#3B6D11]/15',
                    cancelled: 'bg-[#FCEBEB] text-[#A32D2D] border-[#A32D2D]/15'
                };
                const recStatusLabels = {
                    scheduled: 'Scheduled',
                    in_progress: 'In progress',
                    completed: 'Done',
                    cancelled: 'Cancelled'
                };

                bus.recent_services.forEach(hist => {
                    const div = document.createElement('div');
                    div.className = 'p-2 bg-slate-50 border border-black/5 rounded text-xs flex justify-between items-center cursor-pointer hover:bg-slate-100/70';
                    div.onclick = () => openDetailDrawer(hist.id);
                    div.innerHTML = `
                        <div>
                            <span class="font-bold text-[#001F44]">${hist.type}</span>
                            <span class="text-[10px] text-slate-400 block font-mono">${hist.date}</span>
                        </div>
                        <span class="rounded px-1.5 py-0.5 text-[9px] font-bold uppercase border ${recStatusClasses[hist.status] || 'bg-slate-100 text-slate-600'}">${recStatusLabels[hist.status] || hist.status}</span>
                    `;
                    servicesList.appendChild(div);
                });
            }

            // Show bus profile content and hide record drawer
            document.getElementById('drawer-bus-content').classList.remove('hidden');
            document.getElementById('drawer-record-content').classList.add('hidden');

            // Show Drawer overall container
            document.getElementById('maintenance-detail-drawer').classList.remove('hidden');
        }

    } catch (error) {
        console.error('Error opening bus profile drawer:', error);
    }
}

// Update Maintenance Record Status AJAX
async function updateRecordStatus(recordId, status) {
    try {
        const response = await fetch(`${window.FleetMaintenanceConfig.updateStatusUrl}/${recordId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetMaintenanceConfig.csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({ status })
        });

        if (!response.ok) throw new Error('Failed to update status');
        const data = await response.json();

        showSuccessAlert(data.message);
        fetchMaintenanceData();
        
        // Re-open detail drawer to display updated status
        openDetailDrawer(recordId);

    } catch (error) {
        console.error('Error updating status:', error);
    }
}

// Load Record Details into Modal to Edit
async function editRecord(id) {
    try {
        const response = await fetch(`${window.FleetMaintenanceConfig.recordUrl}/${id}`);
        if (!response.ok) throw new Error('Failed to fetch record for edit');
        const data = await response.json();

        if (data.success) {
            const rec = data.record;
            document.getElementById('form-record-id').value = rec.id;
            document.getElementById('form-bus-id').value = rec.bus_id;
            document.getElementById('form-type').value = rec.type;
            document.getElementById('form-scheduled-at').value = rec.scheduled_at;
            document.getElementById('form-technician-name').value = rec.technician_name;
            document.getElementById('form-cost').value = rec.cost_php;
            document.getElementById('form-status').value = rec.status;
            document.getElementById('form-description').value = rec.description;

            document.getElementById('modal-title-text').innerText = 'Edit Maintenance Session';
            const formSubmitBtn = document.querySelector('#maintenance-schedule-form button[type="submit"]');
            if (formSubmitBtn) formSubmitBtn.innerText = 'Save Details';

            closeDetailDrawer();
            
            const modal = document.getElementById('maintenance-schedule-modal');
            if (modal) modal.classList.remove('hidden');
        }

    } catch (error) {
        console.error('Error opening edit modal:', error);
    }
}

// Delete Maintenance Record Action
async function deleteRecord(id) {
    if (!confirm('Are you sure you want to permanently delete this maintenance log?')) return;

    try {
        const response = await fetch(`${window.FleetMaintenanceConfig.deleteUrl}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': window.FleetMaintenanceConfig.csrfToken,
                'Accept': 'application/json'
            }
        });

        if (!response.ok) throw new Error('Failed to delete record');
        const data = await response.json();

        showSuccessAlert(data.message);
        closeDetailDrawer();
        fetchMaintenanceData();

    } catch (error) {
        console.error('Error deleting record:', error);
    }
}

// Success Alert Animation Helper
function showSuccessAlert(message) {
    const alertCont = document.getElementById('maintenance-alert');
    const alertMsg = document.getElementById('maintenance-alert-message');

    if (alertCont && alertMsg) {
        alertMsg.innerText = message;
        alertCont.classList.remove('hidden');
        
        // Auto fade out after 5 seconds
        setTimeout(() => {
            alertCont.classList.add('hidden');
        }, 5000);
    }
}
