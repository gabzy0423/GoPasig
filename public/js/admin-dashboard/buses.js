// Dynamic Database-Driven Bus Management CRUD Controller

let activeBusStatusFilter = 'all';
let busSearchQuery = '';

// Helper: Retrieve CSRF Token from Head Meta tag or Config
function getCsrfToken() {
    if (window.GoPasigConfig && window.GoPasigConfig.csrfToken) {
        return window.GoPasigConfig.csrfToken;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Render dynamic Buses Table rows based on search and status filters
function renderBusesTable() {
    const tbody = document.getElementById('buses-tbody');
    if (!tbody) return;

    tbody.innerHTML = ''; // clear

    if (fleetData.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="px-5 py-8 text-center text-xs font-semibold text-slate-400">No buses registered in the system database.</td></tr>`;
        return;
    }

    const filtered = fleetData.filter(bus => {
        const matchesStatus = activeBusStatusFilter === 'all' || bus.status === activeBusStatusFilter;
        const matchesSearch = bus.plate.toLowerCase().includes(busSearchQuery) || 
                              bus.driver.toLowerCase().includes(busSearchQuery) ||
                              bus.nextStop.toLowerCase().includes(busSearchQuery);
        return matchesStatus && matchesSearch;
    });

    if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="px-5 py-8 text-center text-xs font-semibold text-slate-400">No buses match the filter criteria.</td></tr>`;
        return;
    }

    filtered.forEach(bus => {
        const row = document.createElement('tr');
        row.className = "hover:bg-slate-50/50 transition bm-tbody-row";

        const badgeClass = statusBadgeColors[bus.status] || "bg-slate-50 text-slate-500 border border-slate-200";
        const routeLabel = routeNames[bus.route] ? `Route ${bus.route}` : 'Unassigned';
        const routeClass = bus.route !== 'None' ? `bm-route-pill bm-route-${bus.route}` : 'bm-route-pill bm-route-none';

        row.innerHTML = `
            <td class="bm-td font-extrabold text-[#003F87]">${bus.plate}</td>
            <td class="bm-td"><span class="${routeClass}">${routeLabel}</span></td>
            <td class="bm-td font-semibold text-slate-700">${bus.driver}</td>
            <td class="bm-td font-bold text-center text-slate-600">${bus.capacity} seats</td>
            <td class="bm-td font-bold text-center text-slate-600">${bus.status === 'Active' ? bus.passengers : '—'}</td>
            <td class="bm-td font-bold text-center text-slate-600">${bus.status === 'Active' ? `${bus.speed} km/h` : '—'}</td>
            <td class="bm-td font-semibold text-slate-500">${bus.status === 'Active' ? bus.nextStop : '—'}</td>
            <td class="bm-td"><span class="inline-flex rounded-full ${badgeClass} px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider">${bus.status}</span></td>
            <td class="bm-td text-right font-extrabold text-[#003F87] space-x-2 shrink-0">
                <button onclick="openAddBusModal('edit', ${bus.id})" class="bm-btn-outline hover:text-[#002d62]">
                    <i class="ti ti-edit"></i> Edit
                </button>
                <button onclick="deleteBus(${bus.id})" class="bm-btn-outline bm-btn-danger-text">
                    <i class="ti ti-trash"></i> Delete
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });

    document.getElementById('bm-showing-count').textContent = `Showing ${filtered.length} of ${fleetData.length} buses`;
}

// Update summary stats strips in buses view
function updateBusSummaryStats() {
    let total = fleetData.length;
    let active = 0;
    let inactive = 0;
    let maintenance = 0;

    fleetData.forEach(bus => {
        if (bus.status === 'Active') active++;
        else if (bus.status === 'Inactive') inactive++;
        else if (bus.status === 'Maintenance') maintenance++;
    });

    const totalEl = document.getElementById('bm-stat-total');
    if (totalEl) totalEl.textContent = total;
    
    const activeEl = document.getElementById('bm-stat-active');
    if (activeEl) activeEl.textContent = active;

    const inactiveEl = document.getElementById('bm-stat-inactive');
    if (inactiveEl) inactiveEl.textContent = inactive;

    const maintEl = document.getElementById('bm-stat-maintenance');
    if (maintEl) maintEl.textContent = maintenance;

    const labelEl = document.getElementById('bm-buses-registered-label');
    if (labelEl) labelEl.textContent = `${total} registered municipal buses · Pasig Libreng Sakay Fleet`;
}

// Bus status filtering click triggers
function filterBuses(status) {
    activeBusStatusFilter = status;

    // Reset buttons
    const filterBtns = document.querySelectorAll('[data-bus-filter]');
    filterBtns.forEach(btn => {
        btn.classList.remove('active');
    });

    // Highlight active
    const activeBtn = document.querySelector(`[data-bus-filter="${status}"]`);
    if (activeBtn) {
        activeBtn.classList.add('active');
    }

    renderBusesTable();
}

// Bus search text triggers
function searchBusesTable() {
    const input = document.getElementById('bus-search');
    if (input) {
        busSearchQuery = input.value.toLowerCase().trim();
    }
    renderBusesTable();
}

// Open modal for either Add or Edit mode
function openAddBusModal(mode, busId) {
    const modal = document.getElementById('add-bus-modal');
    const title = document.getElementById('add-bus-modal-title');
    const submitBtn = document.getElementById('bus-submit-btn');
    const form = document.getElementById('add-bus-form');
    
    if (form) form.reset();

    if (mode === 'add') {
        title.textContent = "Register Municipal Bus";
        submitBtn.textContent = "Register Bus";
        document.getElementById('edit-bus-id').value = "";
        document.getElementById('new-bus-plate').disabled = false;
    } else {
        title.textContent = "Edit Bus Details";
        submitBtn.textContent = "Update Bus Details";
        
        const bus = fleetData.find(b => b.id === busId);
        if (bus) {
            document.getElementById('edit-bus-id').value = bus.id;
            document.getElementById('new-bus-plate').value = bus.plate;
            document.getElementById('new-bus-driver').value = bus.driver === 'Unassigned' ? '' : bus.driver;
            document.getElementById('new-bus-capacity').value = bus.capacity;
            document.getElementById('new-bus-status').value = bus.status;
            
            // Map route string ID
            document.getElementById('new-bus-route').value = bus.route === 'None' ? 'None' : bus.route;
        }
    }

    modal.classList.remove('hidden');
}

// Close Add Bus modal
function closeAddBusModal() {
    document.getElementById('add-bus-modal').classList.add('hidden');
}

// Form Submit (AJAX Create or Update)
async function handleBusSubmit(event) {
    event.preventDefault();

    const plate = document.getElementById('new-bus-plate').value.trim();
    const route = document.getElementById('new-bus-route').value;
    const driver = document.getElementById('new-bus-driver').value.trim();
    const capacity = document.getElementById('new-bus-capacity').value;
    const status = document.getElementById('new-bus-status').value;
    const editId = document.getElementById('edit-bus-id').value;

    const payload = {
        plate_number: plate,
        driver_name: driver,
        capacity: parseInt(capacity),
        route_id: route === 'None' ? null : parseInt(route),
        status: status.toLowerCase()
    };

    const isEdit = editId !== "";
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.busesBaseUrl) ? window.GoPasigConfig.busesBaseUrl : '/admin/api/buses';
    const url = isEdit ? `${baseUrl}/${editId}` : baseUrl;
    const method = isEdit ? 'PUT' : 'POST';

    try {
        const response = await fetch(url, {
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert(data.message);
            closeAddBusModal();
            
            // Reload all dynamic records (re-draws maps and tables automatically)
            await loadDatabaseFleetData();
        } else {
            alert(data.message || 'Validation error. Please verify input data.');
            console.error('Bus operation failed:', data.errors || data);
        }
    } catch (error) {
        alert('Server connection error. Failed to save bus details.');
        console.error('AJAX Bus submit error:', error);
    }
}

// AJAX Bus Delete
async function deleteBus(busId) {
    const bus = fleetData.find(b => b.id === busId);
    if (!bus) return;

    if (!confirm(`Are you absolutely sure you want to delete bus registration ${bus.plate}?\nThis action cannot be undone.`)) {
        return;
    }

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.busesBaseUrl) ? window.GoPasigConfig.busesBaseUrl : '/admin/api/buses';
        const response = await fetch(`${baseUrl}/${busId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert(data.message);
            // Reload dynamic records
            await loadDatabaseFleetData();
        } else {
            alert(data.message || 'Failed to delete bus registration.');
        }
    } catch (error) {
        alert('Server connection error. Failed to delete bus registration.');
        console.error('AJAX Bus delete error:', error);
    }
}

// Automatically render on DOM load if data is already fetched by dashboard-data.js
document.addEventListener('DOMContentLoaded', () => {
    if (typeof isDatabaseDataLoaded !== 'undefined' && isDatabaseDataLoaded) {
        renderBusesTable();
        updateBusSummaryStats();
    }
});
