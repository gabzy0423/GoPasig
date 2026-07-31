// Dynamic Database-Driven Fleet Maintenance CRUD Controller
let activeMaintenanceStatusFilter = 'all';
let maintenanceSearchQuery = '';
let globalMaintenanceRecords = [];
let maintenanceCurrentPage = 1;
let maintenanceLastPage = 1;
let globalMaintenancePaginationMeta = null;

// Helper: Retrieve CSRF Token from Config or Meta tag
function getCsrfToken() {
    if (window.GoPasigConfig && window.GoPasigConfig.csrfToken) {
        return window.GoPasigConfig.csrfToken;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

async function refreshMaintenanceBusRuntimeState() {
    if (typeof refreshBusManagementState === 'function') {
        await refreshBusManagementState();
        return;
    }

    if (typeof loadDatabaseFleetData === 'function') {
        await loadDatabaseFleetData();
    }
}

// Format date for display (e.g., "May 26, 2026, 09:30 AM")
function formatMaintenanceDate(dateString) {
    if (!dateString) return '—';
    // Clean ISO representation to treat it as local time, preventing UTC shift
    const cleanedString = dateString.replace('T', ' ').replace(/\.\d+Z$/, '').replace('Z', '');
    const date = new Date(cleanedString);
    const options = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true };
    return date.toLocaleDateString('en-US', options);
}

// Parse technician and notes from the combined description field
function parseDescription(descText) {
    const result = { technician: 'Unassigned', notes: 'No notes provided' };
    if (!descText) return result;

    const parts = descText.split(' | ');
    parts.forEach(part => {
        if (part.startsWith('Technician: ')) {
            result.technician = part.replace('Technician: ', '');
        } else if (part.startsWith('Notes: ')) {
            result.notes = part.replace('Notes: ', '');
        } else {
            // Fallback for simple description values
            result.notes = descText;
        }
    });
    return result;
}

// Open inline form and populate bus dropdown dynamically
function openScheduleMaintenanceModal() {
    const listContainer = document.getElementById('maintenance-list-container');
    const formContainer = document.getElementById('maintenance-form-container');
    const busSelect = document.getElementById('maintenance-bus-id');
    const form = document.getElementById('schedule-maintenance-form');
    
    if (form) form.reset();

    // Set default date-time to current local time
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const dateInput = document.getElementById('maintenance-date');
    if (dateInput) {
        dateInput.value = now.toISOString().slice(0, 16);
    }

    // Populate registered buses dynamically from global fleetData
    if (busSelect) {
        busSelect.innerHTML = '<option value="">Select a Bus...</option>';
        if (typeof fleetData !== 'undefined' && fleetData.length > 0) {
            fleetData.forEach(bus => {
                const opt = document.createElement('option');
                opt.value = bus.id;
                opt.textContent = `${bus.plate} (${bus.driver || 'No Assigned Driver'})`;
                busSelect.appendChild(opt);
            });
        }
    }

    if (listContainer) listContainer.classList.add('hidden');
    if (formContainer) formContainer.classList.remove('hidden');

    // Update breadcrumb
    const breadcrumbCurrent = document.getElementById('maintenance-breadcrumb-current');
    if (breadcrumbCurrent) {
        breadcrumbCurrent.textContent = 'Schedule Session';
    }
}

// Close inline form and return to logs list
function closeScheduleMaintenanceModal() {
    const listContainer = document.getElementById('maintenance-list-container');
    const formContainer = document.getElementById('maintenance-form-container');
    
    if (listContainer) listContainer.classList.remove('hidden');
    if (formContainer) formContainer.classList.add('hidden');

    // Reset breadcrumb
    const breadcrumbCurrent = document.getElementById('maintenance-breadcrumb-current');
    if (breadcrumbCurrent) {
        breadcrumbCurrent.textContent = 'Maintenance Logs';
    }
}

// Open inspection modal and load record details
function openInspectionModal(recordId) {
    const listContainer = document.getElementById('maintenance-list-container');
    const inspectionContainer = document.getElementById('maintenance-inspection-container');
    const form = document.getElementById('inspection-form');
    
    if (form) {
        form.reset();
        form.dataset.recordId = recordId;
    }

    // Fetch record details to populate the form
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
    fetch(`${baseUrl}/${recordId}`)
        .then(res => res.json())
        .then(record => {
            const busLabel = record.bus ? record.bus.plate_number : `Bus #${record.bus_id}`;
            const parsedDesc = parseDescription(record.description);
            
            document.getElementById('inspection-bus-label').textContent = busLabel;
            document.getElementById('inspection-record-status').textContent = record.status.toUpperCase();
            document.getElementById('inspection-technician-label').textContent = parsedDesc.technician;
            document.getElementById('inspection-date-label').textContent = formatMaintenanceDate(record.scheduled_at);
        })
        .catch(err => console.error('Failed to load record details:', err));

    if (listContainer) listContainer.classList.add('hidden');
    if (inspectionContainer) inspectionContainer.classList.remove('hidden');
}

// Close inspection modal and return to logs list
function closeInspectionModal() {
    const listContainer = document.getElementById('maintenance-list-container');
    const inspectionContainer = document.getElementById('maintenance-inspection-container');
    
    if (listContainer) listContainer.classList.remove('hidden');
    if (inspectionContainer) inspectionContainer.classList.add('hidden');
}

// Submit inspection form
async function handleInspectionSubmit(event) {
    event.preventDefault();

    const form = event.target;
    const recordId = form.dataset.recordId;
    const inspectionPassed = form.querySelector('input[name="inspection_passed"]:checked').value === 'true';
    const inspectedBy = document.getElementById('inspection-by').value.trim();
    const inspectionNotes = document.getElementById('inspection-notes').value.trim();

    if (!recordId || !inspectedBy) {
        GoPasigUI.alert('Please fill in all required fields.');
        return;
    }

    const payload = {
        inspection_passed: inspectionPassed,
        inspected_by: inspectedBy,
        inspection_notes: inspectionNotes || ''
    };

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
        
        const response = await fetch(`${baseUrl}/${recordId}/perform-inspection`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            GoPasigUI.alert(data.message);
            closeInspectionModal();
            
            // Reload logs
            await fetchMaintenanceLogs();
            await refreshMaintenanceBusRuntimeState();
        } else {
            GoPasigUI.alert(data.message || 'Failed to submit inspection.');
            console.error('Inspection failed:', data);
        }
    } catch (error) {
        GoPasigUI.alert('Server connection error. Failed to submit inspection.');
        console.error('AJAX inspection error:', error);
    }
}

// Submit new maintenance ticket via AJAX Fetch
async function handleMaintenanceSubmit(event) {
    event.preventDefault();

    const busId = document.getElementById('maintenance-bus-id').value;
    const type = document.getElementById('maintenance-type').value;
    const technician = document.getElementById('maintenance-technician').value.trim();
    const notes = document.getElementById('maintenance-desc').value.trim();
    const scheduledAt = document.getElementById('maintenance-date').value;

    if (!busId || !scheduledAt) {
        GoPasigUI.alert('Please select a bus unit and scheduled date/time.');
        return;
    }

    // Combine technician and notes into a single description field to respect DB schema
    const combinedDescription = `Technician: ${technician} | Notes: ${notes || 'Regular maintenance check'}`;

    const payload = {
        bus_id: parseInt(busId),
        type: type,
        description: combinedDescription,
        scheduled_at: scheduledAt,
        status: 'scheduled'
    };

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
        
        const response = await fetch(baseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            GoPasigUI.alert(data.message);
            closeScheduleMaintenanceModal();
            
            // Reload logs and refresh the global fleet tables/maps
            await fetchMaintenanceLogs();
            await refreshMaintenanceBusRuntimeState();
        } else {
            GoPasigUI.alert(data.message || 'Validation error. Please verify input data.');
            console.error('Maintenance schedule failed:', data.errors || data);
        }
    } catch (error) {
        GoPasigUI.alert('Server connection error. Failed to schedule maintenance.');
        console.error('AJAX maintenance submit error:', error);
    }
}

/// Fetch all maintenance tickets from the database and render the table
async function fetchMaintenanceLogs() {
    const container = document.getElementById('maintenance-table-body');
    if (!container) return;

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
        
        const url = `${baseUrl}?page=${maintenanceCurrentPage}&status=${activeMaintenanceStatusFilter}&search=${encodeURIComponent(maintenanceSearchQuery)}`;
        
        // Fetch both logs and stats in parallel to prevent desynchronization
        const [recordsResponse, statsResponse] = await Promise.all([
            fetch(url),
            fetch('/admin/api/maintenance/stats')
        ]);

        if (recordsResponse.ok) {
            const paginated = await recordsResponse.json();
            globalMaintenanceRecords = paginated.data || [];
            maintenanceCurrentPage = paginated.current_page || 1;
            maintenanceLastPage = paginated.last_page || 1;
            globalMaintenancePaginationMeta = paginated;
            
            renderMaintenanceTable();
            renderMaintenancePagination(paginated);
        }

        if (statsResponse.ok) {
            const stats = await statsResponse.json();
            updateStatsCards(stats);
        }
    } catch (error) {
        console.error('Failed to load maintenance records:', error);
        container.innerHTML = `
            <tr>
                <td colspan="8" class="py-12 text-center text-rose-500 font-semibold">
                    Error loading maintenance logs from system server.
                </td>
            </tr>
        `;
    }
}

// Render filtered logs
function renderMaintenanceTable() {
    const container = document.getElementById('maintenance-table-body');
    if (!container) return;

    const records = globalMaintenanceRecords;

    container.innerHTML = ''; // Clear container

    if (records.length === 0) {
        container.innerHTML = `
            <tr>
                <td colspan="7" class="px-5 py-8 text-center text-xs font-semibold text-slate-400">
                    No maintenance records match your current search or filter.
                </td>
            </tr>
        `;
        const showingEl = document.getElementById('maint-showing-count');
        if (showingEl) {
            showingEl.textContent = 'Showing 0 of 0 logs';
        }
        return;
    }

    records.forEach(record => {
        const busLabel = record.bus ? record.bus.plate_number : `Bus #${record.bus_id}`;
        const formattedDate = formatMaintenanceDate(record.scheduled_at);

        // Determine status badge classes
        let statusBadgeClass = 'bg-slate-50 text-slate-600 border border-slate-200';
        if (record.status === 'scheduled') {
            statusBadgeClass = 'bg-blue-50 text-blue-700 border border-blue-100';
        } else if (record.status === 'in_progress') {
            statusBadgeClass = 'bg-amber-50 text-amber-700 border border-amber-105';
        } else if (record.status === 'completed') {
            statusBadgeClass = 'bg-emerald-50 text-emerald-700 border border-emerald-100';
        } else if (record.status === 'cancelled') {
            statusBadgeClass = 'bg-rose-50 text-rose-700 border border-rose-100';
        }

        const tr = document.createElement('tr');
        tr.className = 'bm-tbody-row';

        // Actions columns (overflow menu dropdown)
        let actionsHtml = `
            <div class="relative inline-block text-left pr-4">
                <button onclick="toggleRowMenu(${record.id}, event)" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-slate-800 cursor-pointer shadow-sm hover:shadow transition-all" title="Actions">
                    <i class="ti ti-dots-vertical"></i>
                </button>
                <div id="row-menu-${record.id}" class="bm-dropdown-menu hidden">
                    <a href="/admin/maintenance/${record.id}" class="bm-dropdown-item no-underline">
                        <i class="ti ti-eye text-slate-450"></i> View
                    </a>
                    
                    ${record.status === 'scheduled' ? `
                        <a href="/admin/maintenance/${record.id}/edit" class="bm-dropdown-item no-underline">
                            <i class="ti ti-edit text-slate-450"></i> Edit
                        </a>
                        <button onclick="cancelMaintenanceRecord(${record.id})" class="bm-dropdown-item text-rose-700 hover:bg-rose-50 border-none bg-transparent cursor-pointer text-left w-full">
                            <i class="ti ti-ban text-rose-500"></i> Cancel
                        </button>
                    ` : `
                        <span class="bm-dropdown-item text-slate-350 cursor-not-allowed select-none">
                            <i class="ti ti-edit text-slate-300"></i> Edit
                        </span>
                        <span class="bm-dropdown-item text-slate-350 cursor-not-allowed select-none">
                            <i class="ti ti-ban text-slate-300"></i> Cancel
                        </span>
                    `}
                    
                    ${record.status !== 'completed' ? `
                        <div class="bm-dropdown-divider"></div>
                        <button onclick="deleteMaintenanceRecord(${record.id})" class="bm-dropdown-item text-rose-750 hover:bg-rose-50 border-none bg-transparent cursor-pointer text-left w-full">
                            <i class="ti ti-trash text-rose-500"></i> Delete
                        </button>
                    ` : `
                        <div class="bm-dropdown-divider"></div>
                        <span class="bm-dropdown-item text-slate-350 cursor-not-allowed select-none">
                            <i class="ti ti-trash text-slate-300"></i> Delete
                        </span>
                    `}
                </div>
            </div>
        `;

        tr.innerHTML = `
            <td class="bm-td font-bold text-slate-900">${record.ticket_number || ('MT-2026-' + String(record.id).padStart(6, '0'))}</td>
            <td class="bm-td font-bold text-[#003F87]">${busLabel}</td>
            <td class="bm-td">${record.type}</td>
            <td class="bm-td">${formattedDate}</td>
            <td class="bm-td font-medium text-slate-700">${record.technician_name || '—'}</td>
            <td class="bm-td">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider ${statusBadgeClass}">
                    ${record.status}
                </span>
            </td>
            <td class="bm-td text-right">${actionsHtml}</td>
        `;

        container.appendChild(tr);
    });

    const showingEl = document.getElementById('maint-showing-count');
    if (showingEl && globalMaintenancePaginationMeta) {
        const from = globalMaintenancePaginationMeta.from || 0;
        const to = globalMaintenancePaginationMeta.to || 0;
        const total = globalMaintenancePaginationMeta.total || 0;
        showingEl.textContent = `Showing ${from} - ${to} of ${total} logs`;
    }
}

// Filter maintenance list click triggers
async function filterMaintenance(status) {
    activeMaintenanceStatusFilter = status;
    maintenanceCurrentPage = 1;

    // Reset buttons active class
    const filterBtns = document.querySelectorAll('[data-maint-filter]');
    filterBtns.forEach(btn => {
        btn.classList.remove('active');
    });

    // Highlight active
    const activeBtn = document.querySelector(`[data-maint-filter="${status}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }

    // Reset cards active styling
    const filterCards = document.querySelectorAll('[data-card-filter]');
    filterCards.forEach(card => {
        card.classList.remove('border-[#003F87]', 'bg-slate-50', 'shadow-md');
        card.classList.add('border-slate-200');
    });

    // Highlight active card
    const activeCard = document.querySelector(`[data-card-filter="${status}"]`);
    if (activeCard) {
        activeCard.classList.remove('border-slate-200');
        activeCard.classList.add('border-[#003F87]', 'bg-slate-50', 'shadow-md');
    }

    await fetchMaintenanceLogs();
}

// Handle clicking status cards to toggle filters on the table
function toggleMaintenanceFilter(filterName, cardElement) {
    if (activeMaintenanceStatusFilter === filterName) {
        filterMaintenance('all');
    } else {
        filterMaintenance(filterName);
    }
}

// Active row action dropdown menu controller
let activeMaintenanceRowMenuId = null;
function toggleRowMenu(id, event) {
    event.stopPropagation();
    
    if (activeMaintenanceRowMenuId && activeMaintenanceRowMenuId !== id) {
        const otherMenu = document.getElementById(`row-menu-${activeMaintenanceRowMenuId}`);
        if (otherMenu) otherMenu.classList.add('hidden');
    }

    const menu = document.getElementById(`row-menu-${id}`);
    if (menu) {
        menu.classList.toggle('hidden');
        if (!menu.classList.contains('hidden')) {
            activeMaintenanceRowMenuId = id;
            window.addEventListener('click', closeRowMenuOutside);
        } else {
            activeMaintenanceRowMenuId = null;
            window.removeEventListener('click', closeRowMenuOutside);
        }
    }
}

function closeRowMenuOutside() {
    if (activeMaintenanceRowMenuId) {
        const menu = document.getElementById(`row-menu-${activeMaintenanceRowMenuId}`);
        if (menu) menu.classList.add('hidden');
        activeMaintenanceRowMenuId = null;
        window.removeEventListener('click', closeRowMenuOutside);
    }
}

// Export loaded maintenance records to CSV client-side
function exportMaintenanceCSV() {
    if (!globalMaintenanceRecords || globalMaintenanceRecords.length === 0) {
        GoPasigUI.alert('No records available to export.');
        return;
    }
    
    const headers = ['Ticket Number', 'Bus Plate', 'Maintenance Type', 'Scheduled Date', 'Technician/Service Provider', 'Status', 'Description'];
    const rows = globalMaintenanceRecords.map(record => {
        const busLabel = record.bus ? record.bus.plate_number : `Bus #${record.bus_id}`;
        const formattedDate = formatMaintenanceDate(record.scheduled_at);
        return [
            record.ticket_number || ('MT-2026-' + String(record.id).padStart(6, '0')),
            busLabel,
            record.type,
            formattedDate,
            record.technician_name || 'Unassigned',
            record.status,
            record.description || ''
        ];
    });
    
    const csvContent = [headers, ...rows].map(row => 
        row.map(value => `"${String(value).replace(/"/g, '""')}"`).join(',')
    ).join('\n');
    
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `GoPasig_Maintenance_Records_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Fetch maintenance statistics from the server and update cards
async function fetchMaintenanceStats() {
    try {
        const response = await fetch('/admin/api/maintenance/stats');
        if (response.ok) {
            const stats = await response.json();
            updateStatsCards(stats);
        }
    } catch (error) {
        console.error('Failed to load maintenance stats:', error);
    }
}

// Update the DOM elements of the statistics cards and set Last Updated timestamp
function updateStatsCards(stats) {
    if (!stats) return;
    const mappings = {
        'maint-stat-total': stats.totalRecords,
        'maint-stat-scheduled': stats.scheduledCount,
        'maint-stat-in-progress': stats.inProgressCount,
        'maint-stat-completed': stats.completedCount,
        'maint-stat-cancelled': stats.cancelledCount,
        'maint-stat-observation': stats.observationCount,
        'maint-stat-overdue': stats.overdueCount,
        'maint-stat-observation-failed': stats.requiringRepairCount,
        'maint-stat-duration': stats.averageDuration
    };
    for (const [id, value] of Object.entries(mappings)) {
        const el = document.getElementById(id);
        if (el) {
            el.textContent = value;
            if (id === 'maint-stat-duration') {
                el.title = value;
            }
        }
    }
    
    // Update timestamp
    const now = new Date();
    const timeString = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    const lastUpdatedEl = document.getElementById('maint-last-updated');
    if (lastUpdatedEl) {
        lastUpdatedEl.textContent = timeString;
    }
}

// Search text trigger
async function searchMaintenanceTable() {
    const input = document.getElementById('maintenance-search');
    if (input) {
        maintenanceSearchQuery = input.value.toLowerCase().trim();
    }
    maintenanceCurrentPage = 1;
    await fetchMaintenanceLogs();
}

// Render pagination buttons dynamically
function renderMaintenancePagination(meta) {
    const container = document.getElementById('maintenance-pagination');
    if (!container) return;

    container.innerHTML = '';

    if (!meta || meta.last_page <= 1) {
        return;
    }

    const currentPage = meta.current_page;
    const lastPage = meta.last_page;

    // Previous Button
    const prevBtn = document.createElement('button');
    prevBtn.className = 'bm-page-btn';
    prevBtn.innerHTML = '<i class="ti ti-chevron-left"></i>';
    prevBtn.disabled = currentPage === 1;
    prevBtn.onclick = () => changeMaintenancePage(currentPage - 1);
    container.appendChild(prevBtn);

    // Page Number Buttons
    for (let i = 1; i <= lastPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `bm-page-btn ${i === currentPage ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.onclick = () => changeMaintenancePage(i);
        container.appendChild(pageBtn);
    }

    // Next Button
    const nextBtn = document.createElement('button');
    nextBtn.className = 'bm-page-btn';
    nextBtn.innerHTML = '<i class="ti ti-chevron-right"></i>';
    nextBtn.disabled = currentPage === lastPage;
    nextBtn.onclick = () => changeMaintenancePage(currentPage + 1);
    container.appendChild(nextBtn);
}

// Change page asynchronously
async function changeMaintenancePage(page) {
    if (page < 1 || page > maintenanceLastPage) return;
    maintenanceCurrentPage = page;
    await fetchMaintenanceLogs();
}

// Cancel Maintenance Record
async function cancelMaintenanceRecord(id) {
    if (!(await GoPasigUI.confirm('Are you sure you want to cancel this scheduled maintenance session?\nThis will return the bus to Standby (Inactive).'))) {
        return;
    }

    try {
        const response = await fetch(`/admin/api/maintenance/${id}/cancel`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.success) {
            GoPasigUI.alert(data.message);
            // Refresh logs
            await fetchMaintenanceLogs();
            await refreshMaintenanceBusRuntimeState();
        } else {
            GoPasigUI.alert(data.message || 'Failed to cancel maintenance.');
        }
    } catch (error) {
        GoPasigUI.alert('Server connection error. Failed to cancel maintenance.');
        console.error('AJAX cancel error:', error);
    }
}

// Complete Maintenance Task
async function completeMaintenanceTask(id) {
    if (!(await GoPasigUI.confirm('Are you sure you want to mark this maintenance task as COMPLETED?\nThis will restore the bus operational status back to ACTIVE.'))) {
        return;
    }

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
        
        const response = await fetch(`${baseUrl}/${id}/complete`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.success) {
            GoPasigUI.alert(data.message);
            // Refresh local logs and global fleet state
            await fetchMaintenanceLogs();
            await refreshMaintenanceBusRuntimeState();
        } else {
            GoPasigUI.alert(data.message || 'Failed to update maintenance task status.');
        }
    } catch (error) {
        GoPasigUI.alert('Server connection error. Failed to complete maintenance task.');
        console.error('AJAX complete error:', error);
    }
}

// Delete Maintenance Record
async function deleteMaintenanceRecord(id) {
    if (!(await GoPasigUI.confirm('Are you sure you want to delete this maintenance record?\nIf the task is not completed, this will unlock the bus back to active.'))) {
        return;
    }

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.maintenanceBaseUrl) ? window.GoPasigConfig.maintenanceBaseUrl : '/admin/api/maintenance';
        
        const response = await fetch(`${baseUrl}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        })

        const data = await response.json();

        if (response.ok && data.success) {
            GoPasigUI.alert(data.message);
            // Refresh local logs and global fleet state
            await fetchMaintenanceLogs();
            await refreshMaintenanceBusRuntimeState();
        } else {
            GoPasigUI.alert(data.message || 'Failed to delete maintenance record.');
        }
    } catch (error) {
        GoPasigUI.alert('Server connection error. Failed to delete maintenance record.');
        console.error('AJAX delete error:', error);
    }
}

// Auto-run on document loading and view switcher bindings
document.addEventListener('DOMContentLoaded', () => {
    // Initial fetch of logs on dashboard load
    fetchMaintenanceLogs();

    // Intercept navigation switcher triggers to reload maintenance logs whenever switching to maintenance screen
    const originalSwitchScreen = window.switchScreen;
    if (typeof originalSwitchScreen === 'function') {
        window.switchScreen = function(screenId) {
            originalSwitchScreen(screenId);
            if (screenId === 'maintenance') {
                fetchMaintenanceLogs();
            }
        };
    }
});



