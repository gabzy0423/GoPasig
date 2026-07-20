// Dynamic Database-Driven Bus Management CRUD Controller

let activeBusStatusFilter = 'all';
let busSearchQuery = '';
let globalBusesRecords = [];
let busCurrentPage = 1;
let busLastPage = 1;
let globalBusPaginationMeta = null;

// Helper: Retrieve CSRF Token from Head Meta tag or Config
function getCsrfToken() {
    if (window.GoPasigConfig && window.GoPasigConfig.csrfToken) {
        return window.GoPasigConfig.csrfToken;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// // Dynamic row action dropdown menu controller
let activeBusRowMenuId = null;
function toggleBusRowMenu(id, event) {
    event.stopPropagation();
    
    if (activeBusRowMenuId && activeBusRowMenuId !== id) {
        const otherMenu = document.getElementById(`bus-row-menu-${activeBusRowMenuId}`);
        if (otherMenu) otherMenu.classList.add('hidden');
    }

    const menu = document.getElementById(`bus-row-menu-${id}`);
    if (menu) {
        menu.classList.toggle('hidden');
        if (!menu.classList.contains('hidden')) {
            activeBusRowMenuId = id;
            window.addEventListener('click', closeBusRowMenuOutside);
        } else {
            activeBusRowMenuId = null;
            window.removeEventListener('click', closeBusRowMenuOutside);
        }
    }
}

function closeBusRowMenuOutside() {
    if (activeBusRowMenuId) {
        const menu = document.getElementById(`bus-row-menu-${activeBusRowMenuId}`);
        if (menu) menu.classList.add('hidden');
        activeBusRowMenuId = null;
        window.removeEventListener('click', closeBusRowMenuOutside);
    }
}

// Fetch buses from the backend API with pagination and filters
async function fetchBuses() {
    const tbody = document.getElementById('buses-tbody');
    if (!tbody) return;

    try {
        const baseUrl = '/admin/api/buses';
        const url = `${baseUrl}?page=${busCurrentPage}&status=${activeBusStatusFilter}&search=${encodeURIComponent(busSearchQuery)}`;
        const response = await fetch(url);
        if (response.ok) {
            const paginated = await response.json();
            globalBusesRecords = paginated.data || [];
            busCurrentPage = paginated.current_page || 1;
            busLastPage = paginated.last_page || 1;
            globalBusPaginationMeta = paginated;
            
            renderBusesTable();
            renderBusesPagination(paginated);
        }
    } catch (error) {
        console.error('Failed to load buses:', error);
    }
}

// Render dynamic Buses Table rows based on paginated records
function renderBusesTable() {
    const tbody = document.getElementById('buses-tbody');
    if (!tbody) return;

    tbody.innerHTML = ''; // clear

    const records = globalBusesRecords;

    if (records.length === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="px-5 py-8 text-center text-xs font-semibold text-slate-400">No buses match the filter criteria.</td></tr>`;
        const showingEl = document.getElementById('bm-showing-count');
        if (showingEl) {
            showingEl.textContent = 'Showing 0 of 0 buses';
        }
        return;
    }

    records.forEach(bus => {
        const row = document.createElement('tr');
        row.className = "hover:bg-slate-50/50 transition bm-tbody-row";

        const normalizedStatus = (bus.status ?? '').toLowerCase();
        
        // Define badge style and display label
        let statusBadgeHtml = '';
        if (normalizedStatus === 'active') {
            statusBadgeHtml = `<span class="inline-flex items-center rounded-full bg-emerald-50 text-emerald-700 border border-emerald-100 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider">Active</span>`;
        } else if (normalizedStatus === 'inactive') {
            statusBadgeHtml = `<span class="inline-flex items-center rounded-full bg-blue-50 text-blue-700 border border-blue-100 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider">Standby</span>`;
        } else if (normalizedStatus === 'maintenance') {
            statusBadgeHtml = `<span class="inline-flex items-center rounded-full bg-amber-50 text-amber-700 border border-amber-100 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider">Maintenance</span>`;
        } else if (normalizedStatus === 'breakdown') {
            statusBadgeHtml = `<span class="inline-flex items-center rounded-full bg-rose-50 text-rose-700 border border-rose-100 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider">Breakdown</span>`;
        } else {
            statusBadgeHtml = `<span class="inline-flex items-center rounded-full bg-slate-50 text-slate-550 border border-slate-200 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider">${normalizedStatus}</span>`;
        }

        // Standardize labels and empty states
        const routeLabel = bus.route ? `Route ${bus.route.name}` : 'No Route Assigned';
        const routeClass = bus.route ? `bm-route-pill bm-route-${bus.route.id}` : 'bm-route-pill bm-route-none';
        
        const driverLabel = (bus.driver_name && bus.driver_name !== 'Unassigned') ? bus.driver_name : 'No Driver Assigned';
        const paxLabel = normalizedStatus === 'active' ? `${bus.passengers || 0}` : 'Not in Service';
        const speedLabel = normalizedStatus === 'active' ? `${bus.speed || 0} km/h` : 'Not in Service';
        
        let nextStopLabel = 'No Active Trip';
        if (normalizedStatus === 'active') {
            nextStopLabel = (bus.next_stop && bus.next_stop !== 'None') ? bus.next_stop : 'No Active Trip';
        }

        // Actions overflow dropdown menu
        const viewUrl = window.GoPasigConfig && window.GoPasigConfig.viewBusRouteTemplate 
            ? window.GoPasigConfig.viewBusRouteTemplate.replace(':id', bus.id) 
            : '/admin/buses/' + bus.id;

        const actionsHtml = `
            <div class="relative inline-block text-left pr-4">
                <button onclick="toggleBusRowMenu(${bus.id}, event)" class="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:text-slate-800 cursor-pointer shadow-sm hover:shadow transition-all" title="Actions">
                    <i class="ti ti-dots-vertical"></i>
                </button>
                <div id="bus-row-menu-${bus.id}" class="bm-dropdown-menu hidden">
                    <a href="${viewUrl}" class="bm-dropdown-item no-underline">
                        <i class="ti ti-eye text-slate-450"></i> View Bus
                    </a>
                    <button onclick="openAddBusModal('edit', ${bus.id}); return false;" class="bm-dropdown-item border-none bg-transparent cursor-pointer text-left w-full">
                        <i class="ti ti-edit text-slate-450"></i> Edit Bus
                    </button>
                    <div class="bm-dropdown-divider"></div>
                    <button onclick="deleteBus(${bus.id}); return false;" class="bm-dropdown-item text-rose-700 hover:bg-rose-50 border-none bg-transparent cursor-pointer text-left w-full">
                        <i class="ti ti-trash text-rose-500"></i> Delete Bus
                    </button>
                </div>
            </div>
        `;

        row.innerHTML = `
            <td class="bm-td font-extrabold text-[#003F87]">${bus.plate_number}</td>
            <td class="bm-td"><span class="${routeClass}">${routeLabel}</span></td>
            <td class="bm-td font-semibold text-slate-700">${driverLabel}</td>
            <td class="bm-td font-bold text-center text-slate-655">${bus.capacity} seats</td>
            <td class="bm-td font-bold text-center text-slate-655">${paxLabel}</td>
            <td class="bm-td font-bold text-center text-slate-655">${speedLabel}</td>
            <td class="bm-td font-semibold text-slate-550">${nextStopLabel}</td>
            <td class="bm-td">${statusBadgeHtml}</td>
            <td class="bm-td text-right shrink-0">${actionsHtml}</td>
        `;
        tbody.appendChild(row);
    });

    const showingEl = document.getElementById('bm-showing-count');
    if (showingEl && globalBusPaginationMeta) {
        const from = globalBusPaginationMeta.from || 0;
        const to = globalBusPaginationMeta.to || 0;
        const total = globalBusPaginationMeta.total || 0;
        showingEl.textContent = `Showing ${from} - ${to} of ${total} buses`;
    }
}

// Update summary stats strips in buses view
function updateBusSummaryStats() {
    let total = fleetData.length;
    let active = 0;
    let inactive = 0; // Standby
    let maintenance = 0;
    let breakdown = 0;
    let underObservation = 0;
    let assignedBuses = 0;

    fleetData.forEach(bus => {
        const normalizedStatus = (bus.status ?? '').toLowerCase();
        
        // Primary Life Cycle Counts
        if (normalizedStatus === 'active') active++;
        else if (normalizedStatus === 'inactive' || normalizedStatus === 'standby') inactive++;
        else if (normalizedStatus === 'maintenance') maintenance++;
        else if (normalizedStatus === 'breakdown') breakdown++;

        // Under Observation Count
        if (bus.has_observation) underObservation++;

        // Assigned Buses Count: (route and driver assigned) OR currently on an active trip
        const hasRouteAndDriver = (bus.route && bus.route !== 'None') && (bus.driver && bus.driver !== 'Unassigned');
        const hasActiveTrip = !!bus.has_active_trip;
        if (hasRouteAndDriver || hasActiveTrip) {
            assignedBuses++;
        }
    });

    // Available for Dispatch = Inactive (Standby) buses
    let availableForDispatch = inactive;

    // Populate Primary Stats Cards
    const totalEl = document.getElementById('bm-stat-total');
    if (totalEl) totalEl.textContent = total;
    
    const activeEl = document.getElementById('bm-stat-active');
    if (activeEl) activeEl.textContent = active;

    const inactiveEl = document.getElementById('bm-stat-inactive');
    if (inactiveEl) inactiveEl.textContent = inactive;

    const maintEl = document.getElementById('bm-stat-maintenance');
    if (maintEl) maintEl.textContent = maintenance;

    // Populate Secondary Fleet Health Indicator Cards
    const breakdownEl = document.getElementById('bm-health-breakdown');
    if (breakdownEl) breakdownEl.textContent = breakdown;

    const observationEl = document.getElementById('bm-health-observation');
    if (observationEl) observationEl.textContent = underObservation;

    const assignedEl = document.getElementById('bm-health-assigned');
    if (assignedEl) assignedEl.textContent = assignedBuses;

    const dispatchEl = document.getElementById('bm-health-dispatch');
    if (dispatchEl) dispatchEl.textContent = availableForDispatch;

    const labelEl = document.getElementById('bm-buses-registered-label');
    if (labelEl) labelEl.textContent = `${total} registered municipal buses in Pasig Libreng Sakay Fleet`;
}

// Toggle card selection: second click on active resets filter to "all"
async function toggleBusCardFilter(status, cardElement) {
    if (activeBusStatusFilter === status) {
        await filterBuses('all');
    } else {
        await filterBuses(status);
    }
}

// Bus status filtering click triggers
async function filterBuses(status) {
    activeBusStatusFilter = status;
    busCurrentPage = 1;

    // Reset cards active styling
    const filterCards = document.querySelectorAll('[data-bus-card-filter]');
    filterCards.forEach(card => {
        card.classList.remove('border-[#003F87]', 'bg-slate-50', 'shadow-md');
        card.classList.add('border-slate-200');
    });

    // Highlight active card
    const activeCard = document.querySelector(`[data-bus-card-filter="${status}"]`);
    if (activeCard) {
        activeCard.classList.remove('border-slate-200');
        activeCard.classList.add('border-[#003F87]', 'bg-slate-50', 'shadow-md');
    }

    await fetchBuses();
}

// Bus search text triggers
async function searchBusesTable() {
    const input = document.getElementById('bus-search');
    if (input) {
        busSearchQuery = input.value.toLowerCase().trim();
    }
    busCurrentPage = 1;
    await fetchBuses();
}

// Render pagination buttons dynamically
function renderBusesPagination(meta) {
    const container = document.getElementById('buses-pagination');
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
    prevBtn.onclick = () => changeBusesPage(currentPage - 1);
    container.appendChild(prevBtn);

    // Page Number Buttons
    for (let i = 1; i <= lastPage; i++) {
        const pageBtn = document.createElement('button');
        pageBtn.className = `bm-page-btn ${i === currentPage ? 'active' : ''}`;
        pageBtn.textContent = i;
        pageBtn.onclick = () => changeBusesPage(i);
        container.appendChild(pageBtn);
    }

    // Next Button
    const nextBtn = document.createElement('button');
    nextBtn.className = 'bm-page-btn';
    nextBtn.innerHTML = '<i class="ti ti-chevron-right"></i>';
    nextBtn.disabled = currentPage === lastPage;
    nextBtn.onclick = () => changeBusesPage(currentPage + 1);
    container.appendChild(nextBtn);
}

// Change page asynchronously
async function changeBusesPage(page) {
    if (page < 1 || page > busLastPage) return;
    busCurrentPage = page;
    await fetchBuses();
}

// Export all bus records to CSV client-side
function exportBusesCSV() {
    if (!fleetData || fleetData.length === 0) {
        alert('No records available to export.');
        return;
    }

    const headers = ['Plate Number', 'Assigned Route', 'Assigned Driver', 'Capacity', 'Pax Boarded', 'Speed', 'Next Stop', 'Status'];
    const rows = fleetData.map(b => {
        const routeLabel = routeNames && routeNames[b.route] ? `Route ${b.route}` : 'No Route Assigned';
        const driverLabel = (b.driver && b.driver !== 'Unassigned') ? b.driver : 'No Driver Assigned';
        const paxLabel = b.status === 'Active' ? `${b.passengers || 0}` : 'Not in Service';
        const speedLabel = b.status === 'Active' ? `${b.speed || 0} km/h` : 'Not in Service';
        const nextStopLabel = b.status === 'Active' ? b.nextStop : 'No Active Trip';

        return [
            b.plate,
            routeLabel,
            driverLabel,
            `${b.capacity} seats`,
            paxLabel,
            speedLabel,
            nextStopLabel,
            b.status
        ];
    });

    const csvContent = [headers, ...rows]
        .map(row => row.map(val => `"${String(val).replace(/"/g, '""')}"`).join(','))
        .join('\n');

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `gopasig-fleet-export-${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
}

// Dynamic driver dropdown population
async function populateDriverDropdown(selectedDriverName, busPlate) {
    const select = document.getElementById('new-bus-driver');
    if (!select) return;

    // Reset dropdown
    select.innerHTML = '<option value="Unassigned">Unassigned (None)</option>';

    try {
        const url = (window.GoPasigConfig && window.GoPasigConfig.driversBaseUrl) ? window.GoPasigConfig.driversBaseUrl : '/admin/api/drivers';
        const response = await fetch(url);
        const data = await response.json();

        if (response.ok && data.success) {
            // Find active buses to identify drivers currently assigned to active buses
            const activeBusDrivers = fleetData
                .filter(b => {
                    const normalizedStatus = (b.status ?? '').toLowerCase();
                    return normalizedStatus === 'active' && b.plate !== busPlate;
                })
                .map(b => b.driver);

            data.drivers.forEach(driver => {
                const fullName = `${driver.first_name} ${driver.last_name}`;

                // Exclude suspended drivers
                if (driver.status === 'suspended') {
                    return;
                }

                // Check if driver is already assigned to an active bus
                const isAssignedToOtherActiveBus = activeBusDrivers.includes(fullName);

                // We allow the driver if:
                // 1. It is the selected driver (the one currently driving the bus)
                // 2. The driver is not assigned to any other active bus
                if (fullName === selectedDriverName || !isAssignedToOtherActiveBus) {
                    const option = document.createElement('option');
                    option.value = fullName;
                    option.textContent = fullName;
                    if (fullName === selectedDriverName) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                }
            });
        }
    } catch (error) {
        console.error('Error populating driver dropdown:', error);
    }
}

// Toggle custom manufacturer input field
window.toggleCustomManufacturer = function(select) {
    const wrapper = document.getElementById('bm-manufacturer-custom-wrapper');
    const customInput = document.getElementById('new-bus-manufacturer-custom');
    if (!wrapper) return;
    if (select.value === 'Others') {
        wrapper.classList.remove('hidden');
        if (customInput) customInput.setAttribute('required', 'required');
    } else {
        wrapper.classList.add('hidden');
        if (customInput) {
            customInput.removeAttribute('required');
            customInput.value = '';
        }
    }
};

// Open inline form for either Add or Edit mode
function openAddBusModal(mode, busId) {
    const title = document.getElementById('add-bus-modal-title');
    const desc = document.getElementById('add-bus-modal-desc');
    const submitBtn = document.getElementById('bus-submit-btn');
    const cancelBtn = document.getElementById('bus-cancel-btn');
    const form = document.getElementById('add-bus-form');
    
    if (form) form.reset();

    // Enable all inputs/selects (except plate & vin which are reset inside mode logic below)
    document.querySelectorAll('#add-bus-form input, #add-bus-form select').forEach(el => {
        el.disabled = false;
    });

    // Show submit button, restore cancel button text
    if (submitBtn) submitBtn.classList.remove('hidden');
    if (cancelBtn) cancelBtn.textContent = "Cancel";

    const statusInput = document.getElementById('new-bus-status');
    const statusSelectWrapper = document.getElementById('bm-status-select-wrapper');
    const statusNoteWrapper = document.getElementById('bm-status-note-wrapper');
    const mfgCustomWrapper = document.getElementById('bm-manufacturer-custom-wrapper');
    const mfgCustomInput = document.getElementById('new-bus-manufacturer-custom');

    if (mfgCustomWrapper) mfgCustomWrapper.classList.add('hidden');
    if (mfgCustomInput) {
        mfgCustomInput.value = '';
        mfgCustomInput.removeAttribute('required');
    }

    if (statusInput) {
        statusInput.innerHTML = '';
        if (mode === 'add') {
            if (statusSelectWrapper) statusSelectWrapper.classList.add('hidden');
            if (statusNoteWrapper) statusNoteWrapper.classList.remove('hidden');
            statusInput.removeAttribute('required');

            const optInactive = new Option('Inactive (Idle / off-duty)', 'inactive');
            optInactive.selected = true;
            statusInput.add(optInactive);
            statusInput.value = 'inactive';
        } else {
            if (statusSelectWrapper) statusSelectWrapper.classList.remove('hidden');
            if (statusNoteWrapper) statusNoteWrapper.classList.add('hidden');
            statusInput.setAttribute('required', 'required');

            const bus = fleetData.find(b => b.id === busId);
            if (bus && bus.status) {
                const currentStatusLower = bus.status.toLowerCase();
                if (currentStatusLower === 'active') {
                    const optActive = new Option('Active (Current Status)', 'active');
                    optActive.disabled = true;
                    optActive.selected = true;
                    statusInput.add(optActive);
                }
                const optInactive = new Option('Inactive (Idle / off-duty)', 'inactive');
                if (currentStatusLower === 'inactive') optInactive.selected = true;
                statusInput.add(optInactive);

                const optMaintenance = new Option('Maintenance (Undergoing repairs)', 'maintenance');
                if (currentStatusLower === 'maintenance') optMaintenance.selected = true;
                statusInput.add(optMaintenance);

                const optBreakdown = new Option('Breakdown (Emergency breakdown)', 'breakdown');
                if (currentStatusLower === 'breakdown') optBreakdown.selected = true;
                statusInput.add(optBreakdown);
            }
        }
    }

    if (mode === 'add') {
        if (title) title.textContent = "Register Electric Bus";
        if (desc) desc.textContent = "Register a new electric municipal bus asset. Operational assignments are configured after registration.";
        if (submitBtn) submitBtn.textContent = "Register Bus";
        const plateInput = document.getElementById('new-bus-plate');
        if (plateInput) {
            plateInput.value = "";
            plateInput.disabled = false;
        }
        const vinInput = document.getElementById('new-bus-vin');
        if (vinInput) {
            vinInput.value = "";
            vinInput.disabled = false;
        }
        const editIdInput = document.getElementById('edit-bus-id');
        if (editIdInput) editIdInput.value = "";
    } else {
        if (title) title.textContent = "Edit Bus Details";
        if (desc) desc.textContent = "Update the operational status, capacity, and technical specifications for this bus.";
        if (submitBtn) submitBtn.textContent = "Update Bus Details";
        
        const bus = fleetData.find(b => b.id === busId);
        if (bus) {
            const editIdInput = document.getElementById('edit-bus-id');
            if (editIdInput) editIdInput.value = bus.id;
            const plateInput = document.getElementById('new-bus-plate');
            if (plateInput) {
                plateInput.value = bus.plate;
                plateInput.disabled = true;
            }
            const vinInput = document.getElementById('new-bus-vin');
            if (vinInput) {
                vinInput.value = bus.vin || "";
                vinInput.disabled = true;
            }
            const fleetInput = document.getElementById('new-bus-fleet-number');
            if (fleetInput) {
                fleetInput.value = bus.fleet_number || "";
            }
            const modelInput = document.getElementById('new-bus-model');
            if (modelInput) {
                modelInput.value = bus.model || "";
            }
            const yearInput = document.getElementById('new-bus-year-model');
            if (yearInput) {
                yearInput.value = bus.year_model || "";
            }
            const capacityInput = document.getElementById('new-bus-capacity');
            if (capacityInput) {
                capacityInput.value = bus.capacity || "";
            }
            const batteryInput = document.getElementById('new-bus-battery-capacity');
            if (batteryInput) {
                batteryInput.value = bus.battery_capacity_kwh || "";
            }
            const powerInput = document.getElementById('new-bus-max-charging-power');
            if (powerInput) {
                powerInput.value = bus.max_charging_power_kw || "";
            }
            const portSelect = document.getElementById('new-bus-charging-port');
            if (portSelect) {
                portSelect.value = bus.charging_port_type || "CCS2";
            }

            const predefined = ['BYD', 'Yutong', 'Higer', 'Golden Dragon', 'Ankai', 'King Long'];
            const mfgSelect = document.getElementById('new-bus-manufacturer-select');
            if (mfgSelect) {
                const isCustom = !predefined.includes(bus.manufacturer);
                if (isCustom) {
                    mfgSelect.value = "Others";
                    if (mfgCustomWrapper) mfgCustomWrapper.classList.remove('hidden');
                    if (mfgCustomInput) {
                        mfgCustomInput.value = bus.manufacturer || "";
                        mfgCustomInput.setAttribute('required', 'required');
                    }
                } else {
                    mfgSelect.value = bus.manufacturer || "BYD";
                    if (mfgCustomWrapper) mfgCustomWrapper.classList.add('hidden');
                    if (mfgCustomInput) {
                        mfgCustomInput.value = "";
                        mfgCustomInput.removeAttribute('required');
                    }
                }
            }
        }
    }

    // Hide list container, show form container
    const listContainer = document.getElementById('buses-list-container');
    const formContainer = document.getElementById('buses-form-container');
    if (listContainer) listContainer.classList.add('hidden');
    if (formContainer) formContainer.classList.remove('hidden');

    // Update breadcrumb
    const breadcrumbCurrent = document.getElementById('buses-breadcrumb-current');
    if (breadcrumbCurrent) {
        breadcrumbCurrent.textContent = mode === 'add' ? 'Register Electric Bus' : 'Edit Bus';
    }
}

// Close Add Bus Form / Go Back to List
function closeAddBusModal() {
    const listContainer = document.getElementById('buses-list-container');
    const formContainer = document.getElementById('buses-form-container');
    
    if (listContainer) listContainer.classList.remove('hidden');
    if (formContainer) formContainer.classList.add('hidden');

    // Reset breadcrumb
    const breadcrumbCurrent = document.getElementById('buses-breadcrumb-current');
    if (breadcrumbCurrent) {
        breadcrumbCurrent.textContent = 'Bus Management';
    }
}

// Form Submit (AJAX Create or Update)
async function handleBusSubmit(event) {
    event.preventDefault();

    // Check if the form is in view-only mode
    const submitBtn = document.getElementById('bus-submit-btn');
    if (submitBtn && submitBtn.classList.contains('hidden')) {
        return;
    }

    const editIdInput = document.getElementById('edit-bus-id');
    const editId = editIdInput ? editIdInput.value : "";
    const isEdit = editId !== "";

    const mfgSelect = document.getElementById('new-bus-manufacturer-select').value.trim();
    const manufacturerCustom = mfgSelect === 'Others' ? document.getElementById('new-bus-manufacturer-custom').value.trim() : '';

    const payload = {
        fleet_number: document.getElementById('new-bus-fleet-number').value.trim().toUpperCase(),
        manufacturer: mfgSelect,
        manufacturer_custom: manufacturerCustom,
        model: document.getElementById('new-bus-model').value.trim(),
        year_model: parseInt(document.getElementById('new-bus-year-model').value),
        battery_capacity_kwh: parseFloat(document.getElementById('new-bus-battery-capacity').value),
        max_charging_power_kw: parseFloat(document.getElementById('new-bus-max-charging-power').value),
        charging_port_type: document.getElementById('new-bus-charging-port').value,
        capacity: parseInt(document.getElementById('new-bus-capacity').value)
    };

    if (!isEdit) {
        payload.plate_number = document.getElementById('new-bus-plate').value.trim();
        payload.vin = document.getElementById('new-bus-vin').value.trim().toUpperCase();
    } else {
        const statusInput = document.getElementById('new-bus-status');
        if (statusInput) {
            payload.status = statusInput.value;
        }
    }

    // Log the payload before submission
    console.log('Register Bus Payload:', payload);

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
            // Log the complete validation response
            console.error('Validation Response:', data);

            let alertMsg = 'Registration failed:\n';
            if (data.errors) {
                const errorsList = Object.values(data.errors)
                    .flat()
                    .map(msg => `• ${msg}`)
                    .join('\n');
                alertMsg += '\n' + errorsList;
            } else {
                alertMsg += `\n• ${data.message || 'Validation error. Please verify input data.'}`;
            }
            alert(alertMsg);
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
    fetchBuses();
    if (typeof isDatabaseDataLoaded !== 'undefined' && isDatabaseDataLoaded) {
        updateBusSummaryStats();
    }
});
