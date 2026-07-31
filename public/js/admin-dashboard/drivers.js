/* ============================================================
   GoPasig Admin — Driver Management
   drivers.js
   ============================================================ */

// ── SAMPLE DATA CONTAINER (POPULATED VIA DYNAMIC DATABASE FETCH) ──
const DRIVERS_DATA = [];
const DRIVERS_PAGE_SIZE = 8;
let currentDriversPage = 1;
let isDriversDataLoaded = false;


// Helper: Retrieve CSRF Token from Head Meta tag or Config
function getCsrfToken() {
    if (window.GoPasigConfig && window.GoPasigConfig.csrfToken) {
        return window.GoPasigConfig.csrfToken;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// ── DYNAMIC LOADER FROM MYSQL API ─────────────────────────────
async function loadDatabaseDriversData() {
    const refreshIcon = document.querySelector('.ti-refresh');
    if (refreshIcon) refreshIcon.classList.add('animate-spin');

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.driversBaseUrl) ? window.GoPasigConfig.driversBaseUrl : '/admin/api/drivers';
        const response = await fetch(baseUrl);
        const data = await response.json();
        
        if (response.ok && data.success) {
            DRIVERS_DATA.length = 0; // clear existing
            data.drivers.forEach(d => {
                // Formatting helper for license expiry string
                let labelStr = '—';
                if (d.license_expiry) {
                    const dateObj = new Date(d.license_expiry);
                    labelStr = dateObj.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
                }

                DRIVERS_DATA.push({
                    id: d.id,
                    firstName: d.first_name || '',
                    lastName: d.last_name || '',
                    initials: (d.first_name ? d.first_name.charAt(0).toUpperCase() : '') + (d.last_name ? d.last_name.charAt(0).toUpperCase() : ''),
                    empId: d.emp_id || '',
                    license: d.license_number || '',
                    expiryDate: (d.license_expiry && typeof d.license_expiry === 'string') ? d.license_expiry.split('T')[0] : (d.license_expiry || ''),
                    expiryLabel: labelStr,
                    expiryStatus: d.license_expiry ? computeExpiryStatus(d.license_expiry).status : 'ok',
                    bus: d.assigned_bus,
                    route: d.assigned_route,
                    status: d.status === 'active' ? 'On Duty' : (d.status === 'suspended' ? 'Suspended' : 'Off Duty'),
                    tripsToday: d.trips_today || 0,
                    paxToday: d.pax_today || 0,
                    address: d.address || '',
                    contact: d.contact_number || '',
                    emergency: d.emergency_contact || '',
                    perfScore: d.performance_score || 100,
                    tripHistory: d.trip_history || [],
                    incidents30: d.incidents_30 || 0
                });
            });

            // Update registered subtitle count
            const subtitleEl = document.getElementById('dm-registered-drivers-subtitle');
            if (subtitleEl) {
                subtitleEl.textContent = `${DRIVERS_DATA.length} registered drivers · Pasig City Libreng Sakay Program`;
            }

            // Update dynamic dashboard calculations & DOM stats
            updateDriversStats();

            // Set last updated timer
            const lastUpdatedEl = document.getElementById('dm-last-updated');
            if (lastUpdatedEl) {
                lastUpdatedEl.textContent = 'Just now';
            }

            // Triggers filter & rendering updates (preserve active filters)
            isDriversDataLoaded = true;
            filterDriversTable(false);
        } else {
            console.error("Backend error during drivers fetch:", data);
            isDriversDataLoaded = true;
            filterDriversTable(false);
        }
    } catch (error) {
        console.error("Failed to load dynamic database drivers data:", error);
        isDriversDataLoaded = true;
        filterDriversTable(false);
    } finally {
        if (refreshIcon) {
            setTimeout(() => {
                refreshIcon.classList.remove('animate-spin');
            }, 500);
        }
    }
}

// ── HELPERS ──────────────────────────────────────────────────

/**
 * Compute expiry status relative to today
 * Status: 'ok' | 'warn' (31–60d) | 'urgent' (≤30d) | 'expired'
 */
function computeExpiryStatus(dateStr) {
    if (!dateStr) return { status: 'ok', days: 999 };
    const today = new Date();
    today.setHours(0,0,0,0);
    const exp = new Date(dateStr);
    exp.setHours(0,0,0,0);
    const diff = Math.floor((exp - today) / 86400000);
    if (diff < 0) return { status:'expired', days: diff };
    if (diff <= 30) return { status:'urgent', days: diff };
    if (diff <= 60) return { status:'warn', days: diff };
    return { status:'ok', days: diff };
}

/** Pax bar fill color based on volume */
function paxBarColor(pax) {
    if (pax >= 250) return '#A32D2D';
    if (pax >= 175) return '#003F87';
    if (pax >= 100) return '#378ADD';
    return '#85B7EB';
}

/** Build license expiry cell HTML with relative indicators */
function buildExpiryCell(driver) {
    const { status, days } = computeExpiryStatus(driver.expiryDate);
    let html = '';
    if (status === 'expired') {
        const absDays = Math.abs(days);
        html = `<div class="flex flex-col gap-0.5">
            <span class="dm-expiry-expired text-rose-700 font-bold">${driver.expiryLabel}</span>
            <span class="inline-flex items-center rounded bg-rose-50 border border-rose-200 text-rose-700 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider shrink-0 w-fit">Expired • ${absDays} day${absDays !== 1 ? 's' : ''}</span>
        </div>`;
    } else if (status === 'urgent') {
        html = `<div class="flex flex-col gap-0.5">
            <span class="dm-expiry-urgent text-amber-700 font-bold">${driver.expiryLabel}</span>
            <span class="inline-flex items-center rounded bg-amber-50 border border-amber-200 text-amber-700 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider shrink-0 w-fit">Expires in ${days} day${days !== 1 ? 's' : ''}</span>
        </div>`;
    } else if (status === 'warn') {
        html = `<div class="flex flex-col gap-0.5">
            <span class="dm-expiry-warn text-blue-700 font-bold">${driver.expiryLabel}</span>
            <span class="inline-flex items-center rounded bg-blue-50 border border-blue-200 text-blue-700 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider shrink-0 w-fit">Expires in ${days} days</span>
        </div>`;
    } else {
        html = `<span class="dm-expiry-ok text-emerald-700 font-bold">${driver.expiryLabel}</span>`;
    }
    return html;
}

/** Build status chip HTML */
function buildStatusChip(status) {
    if (status === 'On Duty') {
        return `<span class="inline-flex items-center gap-1 rounded-full bg-[#E8F4E0] text-[#639922] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider shrink-0">
            <span class="h-1.5 w-1.5 rounded-full bg-[#639922] inline-block"></span>
            On Duty
        </span>`;
    }
    if (status === 'Suspended') {
        return `<span class="inline-flex items-center gap-1 rounded-full bg-[#FDF2F2] text-[#E24B4A] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider shrink-0">
            <span class="h-1.5 w-1.5 rounded-full bg-[#E24B4A] inline-block"></span>
            Suspended
        </span>`;
    }
    // Standby is represented as "Off Duty" in dataset
    return `<span class="inline-flex items-center gap-1 rounded-full bg-[#E6F1FB] text-[#003F87] px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider shrink-0">
        <span class="h-1.5 w-1.5 rounded-full bg-[#003F87] inline-block"></span>
        Standby
    </span>`;
}

/** Build route pill HTML */
function buildRoutePill(route) {
    if (!route || route === 'None') return `<span class="text-slate-400 text-xs italic">No Route Assigned</span>`;
    let colorClass = 'dm-route-a';
    if (route == '2') colorClass = 'dm-route-b';
    else if (route == '3') colorClass = 'dm-route-c';
    return `<span class="dm-route-chip ${colorClass}">Route ${route}</span>`;
}

/** Build pax mini-bar cell HTML */
function buildPaxCell(pax) {
    if (!pax) return `<span class="text-slate-400 text-xs italic">No Passengers</span>`;
    const pct = Math.min((pax / 250) * 100, 100);
    const color = paxBarColor(pax);
    return `<div class="dm-pax-cell">
        <div class="dm-pax-track"><div class="dm-pax-fill" style="width:${pct}%;background:${color};"></div></div>
        <span class="dm-pax-count">${pax}</span>
    </div>`;
}

/** Build actions vertical three-dot menu dropdown cell */
function buildActionsCell(driver) {
    const isSuspended = driver.status === 'Suspended';
    const suspendActionLabel = isSuspended ? 'Reinstate' : 'Suspend';
    const suspendIcon = isSuspended ? 'ti-circle-check text-emerald-500' : 'ti-ban text-rose-500';
    const suspendClass = isSuspended ? 'text-emerald-700 hover:bg-emerald-50' : 'text-rose-700 hover:bg-rose-50';

    const isAssigned = driver.bus && driver.bus !== '—' && driver.bus !== 'None';
    const hasActiveTrip = driver.tripsToday > 0;
    const cannotDelete = isAssigned || hasActiveTrip;

    let deleteBtnAttr = '';
    let deleteBtnClass = 'text-rose-700 hover:bg-rose-50';
    let deleteTooltip = '';

    if (cannotDelete) {
        deleteBtnAttr = 'disabled';
        deleteBtnClass = 'text-slate-300 cursor-not-allowed opacity-50';
        deleteTooltip = 'title="Driver cannot be deleted while assigned to an active dispatch."';
    } else {
        deleteBtnAttr = `onclick="deleteDriverFromTable(${driver.id}); return false;"`;
        deleteTooltip = 'title="Delete Driver"';
    }

    const suspendActionItem = isSuspended
        ? `<button onclick="toggleSuspendDriver(${driver.id}); return false;" class="dm-dropdown-item text-emerald-700 hover:bg-emerald-50 cursor-pointer w-full">
            <i class="ti ti-circle-check text-emerald-500"></i> Reinstate
           </button>`
        : `<button onclick="toggleSuspendDriver(${driver.id}); return false;" class="dm-dropdown-item text-rose-700 hover:bg-rose-50 cursor-pointer w-full">
            <i class="ti ti-ban text-rose-500"></i> Suspend
           </button>`;

    return `
        <div class="relative inline-block text-left">
            <button onclick="toggleDriverRowMenu(${driver.id}, event)" class="dm-action-trigger flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-500 hover:bg-slate-50 hover:border-slate-300 transition-all cursor-pointer shadow-sm" title="Actions">
                <i class="ti ti-dots-vertical text-base"></i>
            </button>
            <div id="driver-row-menu-${driver.id}" class="dm-dropdown-menu hidden">
                <button onclick="openDriversShowScreen(${driver.id}); switchScreen('drivers-show'); return false;" class="dm-dropdown-item cursor-pointer w-full">
                    <i class="ti ti-eye text-slate-450"></i> View Profile
                </button>
                <button onclick="openDriversEditScreen(${driver.id}); switchScreen('drivers-edit'); return false;" class="dm-dropdown-item cursor-pointer w-full">
                    <i class="ti ti-edit text-slate-450"></i> Edit Driver
                </button>
                <div class="dm-dropdown-divider"></div>
                ${suspendActionItem}
                <div class="dm-dropdown-divider"></div>
                <button ${deleteBtnAttr} ${deleteTooltip} class="dm-dropdown-item ${deleteBtnClass} w-full">
                    <i class="ti ti-trash ${cannotDelete ? 'text-slate-300' : 'text-rose-500'}"></i> Delete Driver
                </button>
            </div>
        </div>
    `;
}

/** Build trip history status chip */
function buildTripStatusChip(status) {
    if (status === 'Completed') return `<span class="dm-trip-status-done">Completed</span>`;
    if (status.includes('delay')) return `<span class="dm-trip-status-delay">${status}</span>`;
    return `<span class="dm-trip-status-incident">${status}</span>`;
}

/** Build route chip (small, for trip table) */
function buildTripRoutePill(route) {
    let colorClass = 'dm-route-a';
    if (route == '2') colorClass = 'dm-route-b';
    else if (route == '3') colorClass = 'dm-route-c';
    return `<span class="dm-route-chip ${colorClass}" style="font-size:10px;padding:2px 7px;">Route ${route}</span>`;
}

// ── RENDER TABLE ──────────────────────────────────────────────
function renderDriversTable(data) {
    const tbody = document.getElementById('drivers-tbody');
    if (!tbody) return;
    if (!data || !data.length) {
        if (!isDriversDataLoaded) {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:48px;color:var(--color-text-secondary);font-size:13px;"><div class="flex items-center justify-center gap-2 text-slate-500 font-semibold"><i class="ti ti-refresh animate-spin text-lg text-[#003F87]"></i> Loading drivers database...</div></td></tr>`;
        } else {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:48px;color:var(--color-text-secondary);font-size:13px;">
                <div class="flex flex-col items-center justify-center gap-3">
                    <div class="h-10 w-10 rounded-full bg-slate-100 flex items-center justify-center text-slate-400">
                        <i class="ti ti-search-off text-lg"></i>
                    </div>
                    <div class="text-slate-700 font-bold">No drivers match your filters.</div>
                    <button onclick="resetDriversFilters()" class="px-4 py-2 text-xs font-bold bg-[#003F87] text-white rounded-lg hover:bg-[#002d62] transition cursor-pointer">Reset Filters</button>
                </div>
            </td></tr>`;
        }
        const pagRow = document.querySelector('.dm-pagination-row');
        if (pagRow) pagRow.style.display = 'none';
        updateShowingCount(0);
        return;
    }

    const totalRecords = data.length;
    const totalPages = Math.ceil(totalRecords / DRIVERS_PAGE_SIZE);

    if (currentDriversPage > totalPages) {
        currentDriversPage = Math.max(1, totalPages);
    }

    const startIndex = (currentDriversPage - 1) * DRIVERS_PAGE_SIZE;
    const endIndex = Math.min(startIndex + DRIVERS_PAGE_SIZE, totalRecords);
    const pageData = data.slice(startIndex, endIndex);

    tbody.innerHTML = pageData.map(driver => {
        const { status: expStatus } = computeExpiryStatus(driver.expiryDate);
        const rowClass = expStatus === 'expired' ? 'dm-tbody-row dm-row-expired' : 'dm-tbody-row';
        
        // Better driver information metadata & operational dots
        const nameDotColor = driver.status === 'On Duty' ? 'bg-[#639922]' : (driver.status === 'Suspended' ? 'bg-[#E24B4A]' : 'bg-[#003F87]');
        const assignText = driver.bus ? `Assigned: ${driver.bus}` : 'Standby';
        const assignTextClass = driver.bus ? 'text-[#639922]' : 'text-[#003F87]';
        
        const busValue = driver.bus ? `<span class="font-bold">${driver.bus}</span>` : '<span class="text-slate-400 text-xs italic">No Bus Assigned</span>';
        const tripsValue = driver.tripsToday ? driver.tripsToday : '<span class="text-slate-400 text-xs italic">No Trips Today</span>';

        return `<tr class="${rowClass}" data-driver-id="${driver.id}" data-status="${driver.status}" data-license-status="${expStatus}">
            <td class="dm-td">
                <div class="dm-driver-cell items-start">
                    <div class="dm-avatar mt-0.5">${driver.initials}</div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <span class="h-2 w-2 rounded-full ${nameDotColor} inline-block shrink-0" title="${driver.status}"></span>
                            <button onclick="openDriversShowScreen(${driver.id}); switchScreen('drivers-show');" class="dm-driver-name hover:underline text-left font-bold text-[#003F87]" style="background: none; border: none; padding: 0; cursor: pointer;">${driver.firstName} ${driver.lastName}</button>
                        </div>
                        <div class="dm-driver-empid mt-1 text-[11px] text-slate-500 font-semibold flex flex-col gap-0.5 leading-tight">
                            <span>ID: ${driver.empId}</span>
                            <span class="text-[10px] text-slate-400">License: ${driver.license}</span>
                            <span class="text-[10px] font-bold ${assignTextClass}">${assignText}</span>
                        </div>
                    </div>
                </div>
            </td>
            <td class="dm-td dm-mono">${driver.license}</td>
            <td class="dm-td">${buildExpiryCell(driver)}</td>
            <td class="dm-td dm-mono" style="font-size:12px;">${busValue}</td>
            <td class="dm-td">${buildRoutePill(driver.route)}</td>
            <td class="dm-td">${buildStatusChip(driver.status)}</td>
            <td class="dm-td" style="text-align:center;">${tripsValue}</td>
            <td class="dm-td">${buildPaxCell(driver.paxToday)}</td>
            <td class="dm-td dm-td-actions text-right pr-6">${buildActionsCell(driver)}</td>
        </tr>`;
    }).join('');

    updateShowingCount(totalRecords);
    renderPaginationRow(totalRecords, totalPages);
}

function updateShowingCount(count) {
    const el = document.getElementById('driver-showing-count');
    if (el) el.textContent = `Showing ${count} of ${DRIVERS_DATA.length} drivers`;
}

function renderPaginationRow(totalRecords, totalPages) {
    const row = document.querySelector('.dm-pagination-row');
    if (!row) return;

    if (totalRecords === 0) {
        row.style.display = 'none';
        return;
    }
    row.style.display = 'flex';

    const startIndex = (currentDriversPage - 1) * DRIVERS_PAGE_SIZE + 1;
    const endIndex = Math.min(startIndex + DRIVERS_PAGE_SIZE - 1, totalRecords);

    let countLabel = `${startIndex}–${endIndex} of ${totalRecords} drivers`;
    if (totalRecords === 1) {
        countLabel = `1 of 1 driver`;
    }

    let buttonsHtml = '';
    
    // Previous button
    const prevDisabled = currentDriversPage === 1 ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '';
    buttonsHtml += `<button class="dm-page-btn" ${prevDisabled} onclick="changeDriversPage(${currentDriversPage - 1})">‹</button>`;

    // Page numbers
    for (let i = 1; i <= totalPages; i++) {
        const activeClass = i === currentDriversPage ? 'dm-page-btn--active' : '';
        buttonsHtml += `<button class="dm-page-btn ${activeClass}" onclick="changeDriversPage(${i})">${i}</button>`;
    }

    // Next button
    const nextDisabled = currentDriversPage === totalPages ? 'disabled style="opacity: 0.5; cursor: not-allowed;"' : '';
    buttonsHtml += `<button class="dm-page-btn" ${nextDisabled} onclick="changeDriversPage(${currentDriversPage + 1})">›</button>`;

    row.innerHTML = `
        <span class="dm-count-label">${countLabel}</span>
        <div class="dm-page-btns">
            ${buttonsHtml}
        </div>
    `;
}

function changeDriversPage(page) {
    currentDriversPage = page;
    filterDriversTable(false);
}

// ── FILTERING ──────────────────────────────────────────────────
function filterDriversTable(resetPage) {
    if (resetPage === undefined || typeof resetPage !== 'boolean') {
        resetPage = true;
    }
    if (resetPage) {
        currentDriversPage = 1;
    }
    const query  = (document.getElementById('driver-search')?.value || '').toLowerCase().trim();
    const status = document.getElementById('driver-status-filter')?.value || '';
    const licFilter = document.getElementById('driver-license-filter')?.value || '';

    const filtered = DRIVERS_DATA.filter(driver => {
        const fullName = `${driver.firstName} ${driver.lastName}`.toLowerCase();
        const matchSearch = !query 
            || fullName.includes(query) 
            || (driver.empId && driver.empId.toLowerCase().includes(query))
            || (driver.license && driver.license.toLowerCase().includes(query));

        const matchStatus = !status || driver.status === status;

        const { status: expStatus } = computeExpiryStatus(driver.expiryDate);
        let matchLicense = true;
        if (licFilter === 'ok') matchLicense = expStatus === 'ok';
        else if (licFilter === 'warn') matchLicense = expStatus === 'warn' || expStatus === 'urgent';
        else if (licFilter === 'expired') matchLicense = expStatus === 'expired';

        // Filter by primary dashboard cards selection
        let matchCard = true;
        if (activeDriverCardFilter === 'on-duty') matchCard = driver.status === 'On Duty';
        else if (activeDriverCardFilter === 'standby') matchCard = driver.status === 'Off Duty';
        else if (activeDriverCardFilter === 'suspended') matchCard = driver.status === 'Suspended';

        return matchSearch && matchStatus && matchLicense && matchCard;
    });

    renderDriversTable(filtered);
}

// ── SECTION INITIALIZATION & POPULATION ─────────────────────────
function openDriversCreateScreen() {
    // Clear inputs and errors first
    ['df-firstname','df-lastname','df-contact','df-license','df-expiry','df-address','df-emergency'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const statusEl = document.getElementById('df-status');
    if (statusEl) statusEl.value = 'inactive';
    const warnEl = document.getElementById('df-expiry-warn');
    if (warnEl) warnEl.classList.add('hidden');
    
    // Clear validation errors
    clearCreateErrors();

    // Compute unique EMP ID
    const nextNum = DRIVERS_DATA.length ? Math.max(...DRIVERS_DATA.map(d => {
        const num = parseInt(d.empId.replace('EMP-', ''));
        return isNaN(num) ? 0 : num;
    })) + 1 : 29;
    const empIdEl = document.getElementById('df-empid');
    if (empIdEl) empIdEl.value = `EMP-${nextNum.toString().padStart(4, '0')}`;
}

function openDriversEditScreen(driverId) {
    clearEditErrors();

    const driver = DRIVERS_DATA.find(d => d.id === driverId);
    if (!driver) {
        // If data is not yet loaded, wait and try again
        setTimeout(() => {
            const retryDriver = DRIVERS_DATA.find(d => d.id === driverId);
            if (retryDriver) fillEditForm(retryDriver);
        }, 300);
        return;
    }
    fillEditForm(driver);
}

function fillEditForm(driver) {
    document.getElementById('df-edit-driver-id').value = driver.id;
    document.getElementById('df-edit-firstname').value = driver.firstName;
    document.getElementById('df-edit-lastname').value = driver.lastName;
    document.getElementById('df-edit-empid').value = driver.empId;
    document.getElementById('df-edit-contact').value = driver.contact || '';
    document.getElementById('df-edit-license').value = driver.license;
    document.getElementById('df-edit-expiry').value = driver.expiryDate;
    document.getElementById('df-edit-address').value = driver.address || '';
    document.getElementById('df-edit-status').value = driver.status === 'Suspended' ? 'suspended' : (driver.status === 'On Duty' ? 'active' : 'inactive');
    document.getElementById('df-edit-emergency').value = driver.emergency || '';

    // Expiry Warning checking
    const warnEl = document.getElementById('df-edit-expiry-warn');
    const warnText = document.getElementById('df-edit-expiry-warn-text');
    if (warnEl && warnText) {
        const { status, days } = computeExpiryStatus(driver.expiryDate);
        if (status === 'urgent' || status === 'warn') {
            warnText.textContent = days <= 30
                ? `License expiring in ${days} day${days !== 1 ? 's' : ''} — notify driver to renew`
                : `License expiring soon (${days} days) — notify driver to renew`;
            warnEl.classList.remove('hidden');
        } else if (status === 'expired') {
            warnText.textContent = `License expired! The driver must renew immediately.`;
            warnEl.classList.remove('hidden');
        } else {
            warnEl.classList.add('hidden');
        }
    }

    // Status badge
    const badgeEl = document.getElementById('df-edit-status-badge');
    if (badgeEl) {
        badgeEl.className = 'inline-flex rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wider ' +
            (driver.status === 'On Duty' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' :
             (driver.status === 'Suspended' ? 'bg-rose-50 text-rose-700 border border-rose-200' :
              'bg-slate-100 text-slate-600 border border-slate-200'));
        badgeEl.textContent = driver.status;
    }
}

function openDriversShowScreen(driverId) {
    window.currentDriversShowId = driverId;
    const driver = DRIVERS_DATA.find(d => d.id === driverId);
    if (!driver) {
        setTimeout(() => {
            const retryDriver = DRIVERS_DATA.find(d => d.id === driverId);
            if (retryDriver) fillShowScreen(retryDriver);
        }, 300);
        return;
    }
    fillShowScreen(driver);
}

function fillShowScreen(driver) {
    const { status: expStatus, days } = computeExpiryStatus(driver.expiryDate);

    // Identity Banner Data Bindings
    document.getElementById('dp-show-breadcrumb-name').textContent = `${driver.firstName} ${driver.lastName}`;
    document.getElementById('dp-show-avatar').textContent = driver.initials;
    document.getElementById('dp-show-name').textContent = `${driver.firstName} ${driver.lastName}`;
    document.getElementById('dp-show-empid').textContent = driver.empId;

    // Badges Row Bindings
    const statusBadge = document.getElementById('dp-show-status-badge');
    if (statusBadge) {
        statusBadge.textContent = driver.status;
        if (driver.status === 'On Duty') {
            statusBadge.className = 'inline-flex items-center gap-1 rounded-full bg-[#E8F4E0] text-[#639922] border border-[#E8F4E0] px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider';
        } else if (driver.status === 'Suspended') {
            statusBadge.className = 'inline-flex items-center gap-1 rounded-full bg-[#FDF2F2] text-[#E24B4A] border border-[#FDF2F2] px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider';
        } else {
            statusBadge.className = 'inline-flex items-center gap-1 rounded-full bg-[#E6F1FB] text-[#003F87] border border-[#003F87]/15 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider';
        }
    }

    const complianceBadge = document.getElementById('dp-show-compliance-badge');
    if (complianceBadge) {
        if (expStatus === 'expired') {
            complianceBadge.textContent = 'LICENSE EXPIRED';
            complianceBadge.className = 'inline-flex items-center gap-1 rounded-full bg-[#FDF2F2] text-[#E24B4A] border border-[#FDF2F2] px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider';
        } else if (expStatus === 'urgent' || expStatus === 'warn') {
            complianceBadge.textContent = 'LICENSE EXPIRING';
            complianceBadge.className = 'inline-flex items-center gap-1 rounded-full bg-amber-50 text-amber-700 border border-amber-200 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider';
        } else {
            complianceBadge.textContent = 'VALID LICENSE';
            complianceBadge.className = 'inline-flex items-center gap-1 rounded-full bg-emerald-50 text-[#639922] border border-[#E8F4E0] px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider';
        }
    }

    const ratingBadge = document.getElementById('dp-show-rating-badge');
    if (ratingBadge) {
        if (driver.perfScore >= 90) {
            ratingBadge.textContent = 'EXCELLENT';
            ratingBadge.className = 'inline-flex items-center gap-1 rounded-full bg-emerald-50 text-[#639922] border border-[#E8F4E0] px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider';
        } else if (driver.perfScore >= 80) {
            ratingBadge.textContent = 'GOOD';
            ratingBadge.className = 'inline-flex items-center gap-1 rounded-full bg-blue-50 text-[#003F87] border border-[#E6F1FB] px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider';
        } else {
            ratingBadge.textContent = 'NEEDS ATTENTION';
            ratingBadge.className = 'inline-flex items-center gap-1 rounded-full bg-rose-50 text-[#E24B4A] border border-rose-200 px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider';
        }
    }

    // Header Status Strip Summary
    const busStrip = document.getElementById('dp-show-bus-strip');
    if (busStrip) busStrip.textContent = driver.bus || 'No Bus Assigned';

    const routeStrip = document.getElementById('dp-show-route-strip');
    if (routeStrip) routeStrip.textContent = (driver.route && driver.route !== 'None') ? `Route ${driver.route}` : 'No Active Route';

    const shiftStrip = document.getElementById('dp-show-shift-strip');
    if (shiftStrip) shiftStrip.textContent = driver.status === 'On Duty' ? 'Morning Shift' : 'Not Scheduled';

    const dispatchStrip = document.getElementById('dp-show-dispatch-strip');
    if (dispatchStrip) dispatchStrip.textContent = driver.status === 'On Duty' ? `Trip #${driver.id + 120}` : 'No Active Dispatch';


    // 3 KPI Cards
    const scoreKpi = document.getElementById('dp-show-stat-score-kpi');
    if (scoreKpi) scoreKpi.textContent = `${driver.perfScore}%`;

    const tripsKpi = document.getElementById('dp-show-stat-trips-kpi');
    if (tripsKpi) tripsKpi.textContent = driver.tripHistory ? driver.tripHistory.length : 0;

    const incidentsKpi = document.getElementById('dp-show-stat-incidents-kpi');
    if (incidentsKpi) incidentsKpi.textContent = driver.incidents30 || 0;


    // Component 3: Conditional Operational Status Panel
    const opIndicator = document.getElementById('dp-show-active-indicator');
    const opContent = document.getElementById('dp-show-operational-content');
    if (opIndicator && opContent) {
        if (driver.status === 'On Duty') {
            opIndicator.textContent = 'ACTIVE DISPATCH';
            opIndicator.className = 'inline-flex items-center gap-1 rounded bg-[#E8F4E0] text-[#639922] px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider';
            
            const commuters = driver.paxToday ? Math.round(driver.paxToday / (driver.tripsToday || 1)) : 18;
            opContent.innerHTML = `
                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-xs">
                    <div class="flex flex-col bg-slate-50 p-3 rounded-lg border border-slate-100">
                        <span class="text-slate-400 font-bold uppercase text-[9px]">Active Bus</span>
                        <span class="text-slate-800 font-mono font-black mt-1 text-sm">${driver.bus}</span>
                    </div>
                    <div class="flex flex-col bg-slate-50 p-3 rounded-lg border border-slate-100">
                        <span class="text-slate-400 font-bold uppercase text-[9px]">Current Route</span>
                        <span class="text-slate-850 font-black mt-1 text-sm">Route ${driver.route}</span>
                    </div>
                    <div class="flex flex-col bg-slate-50 p-3 rounded-lg border border-slate-100">
                        <span class="text-slate-400 font-bold uppercase text-[9px]">Commuters Onboard</span>
                        <span class="text-[#003F87] font-black mt-1 text-sm">${commuters} pax</span>
                    </div>
                    <div class="flex flex-col bg-slate-50 p-3 rounded-lg border border-slate-100">
                        <span class="text-slate-400 font-bold uppercase text-[9px]">Current Speed</span>
                        <span class="text-emerald-700 font-black mt-1 text-sm flex items-center gap-1">
                            <span class="h-2 w-2 rounded-full bg-[#639922] animate-pulse"></span> 24 km/h
                        </span>
                    </div>
                    <div class="flex flex-col bg-slate-50 p-3 rounded-lg border border-slate-100">
                        <span class="text-slate-400 font-bold uppercase text-[9px]">Estimated ETA</span>
                        <span class="text-slate-800 font-black mt-1 text-sm">09:12 AM</span>
                    </div>
                </div>
            `;
        } else if (driver.status === 'Suspended') {
            opIndicator.textContent = 'UNAVAILABLE';
            opIndicator.className = 'inline-flex items-center gap-1 rounded bg-[#FDF2F2] text-[#E24B4A] px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider';
            
            opContent.innerHTML = `
                <div class="rounded-xl border border-rose-100 bg-rose-50/50 p-4 flex items-center gap-4 text-xs">
                    <div class="h-10 w-10 rounded-full bg-rose-100 flex items-center justify-center text-rose-700 shrink-0">
                        <i class="ti ti-ban text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <div class="font-black text-rose-800">Operational Blocked: Suspended</div>
                        <div class="text-rose-600 font-medium mt-0.5">Reason: Account Suspended. The driver has been suspended by dispatch administrative settings and cannot be assigned to any bus or route schedule.</div>
                    </div>
                </div>
            `;
        } else {
            opIndicator.textContent = 'READY FOR DISPATCH';
            opIndicator.className = 'inline-flex items-center gap-1 rounded bg-[#E6F1FB] text-[#003F87] px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider';
            
            opContent.innerHTML = `
                <div class="rounded-xl border border-blue-100 bg-[#E6F1FB]/30 p-4 flex items-center gap-4 text-xs">
                    <div class="h-10 w-10 rounded-full bg-[#E6F1FB] flex items-center justify-center text-[#003F87] shrink-0">
                        <i class="ti ti-circle-check text-lg"></i>
                    </div>
                    <div class="flex-1">
                        <div class="font-black text-slate-800">Standby Status: Ready for Dispatch</div>
                        <div class="text-slate-500 font-medium mt-0.5">No active bus assignments or dispatches registered for today. Driver is eligible and available to be scheduled for active route coverage.</div>
                    </div>
                </div>
            `;
        }
    }


    // Component 7: Quick Actions Categorization Trigger Bindings
    const editBtn = document.getElementById('dp-show-edit-btn');
    if (editBtn) {
        editBtn.setAttribute('onclick', `openDriversEditScreen(${driver.id}); switchScreen('drivers-edit'); return false;`);
    }
    const suspBtn = document.getElementById('dp-show-suspend-btn');
    if (suspBtn) {
        if (driver.status === 'Suspended') {
            suspBtn.innerHTML = '<i class="ti ti-circle-check text-sm"></i> Reinstate Driver';
            suspBtn.className = 'flex w-full items-center justify-start gap-2.5 rounded-lg border px-3.5 py-2.5 text-xs font-bold transition shadow-sm cursor-pointer select-none border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100';
        } else {
            suspBtn.innerHTML = '<i class="ti ti-ban text-sm"></i> Suspend Driver';
            suspBtn.className = 'flex w-full items-center justify-start gap-2.5 rounded-lg border px-3.5 py-2.5 text-xs font-bold transition shadow-sm cursor-pointer select-none border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100';
        }
        suspBtn.setAttribute('onclick', `toggleSuspendDriver(${driver.id})`);
    }

    // Stats
    document.getElementById('dp-show-stat-trips').textContent = driver.tripsToday;
    document.getElementById('dp-show-stat-pax').textContent = driver.paxToday;
    const avg = driver.tripsToday > 0 ? (driver.paxToday / driver.tripsToday).toFixed(1) : '0.0';
    document.getElementById('dp-show-stat-avg').textContent = avg;

    // Perf Index Breakdown with color indicators
    let ratingText = 'Needs Attention';
    let ratingColorClass = 'bg-rose-50 text-rose-700 border border-rose-200';
    let barColor = '#E24B4A';
    if (driver.perfScore >= 90) {
        ratingText = 'Excellent';
        ratingColorClass = 'bg-emerald-50 text-[#639922] border border-[#E8F4E0]';
        barColor = '#639922';
    } else if (driver.perfScore >= 80) {
        ratingText = 'Good';
        ratingColorClass = 'bg-blue-50 text-[#003F87] border border-[#E6F1FB]';
        barColor = '#003F87';
    }
    document.getElementById('dp-show-perf-label').innerHTML = `
        <span class="font-extrabold">${driver.perfScore}%</span> 
        <span class="ml-1.5 px-2 py-0.5 rounded-full text-[9px] font-bold uppercase tracking-wider ${ratingColorClass}">${ratingText}</span>
    `;
    document.getElementById('dp-show-perf-bar').style.width = `${driver.perfScore}%`;
    document.getElementById('dp-show-perf-bar').style.backgroundColor = barColor;

    // Derived values for schedule compliance and commuter feedback bars
    const complianceVal = Math.min(100, Math.max(70, driver.perfScore + 2));
    const feedbackVal = (driver.perfScore / 20).toFixed(1);
    const feedbackPct = Math.min(100, Math.max(60, driver.perfScore - 4));

    document.getElementById('dp-show-perf-adherence').textContent = `${complianceVal}%`;
    document.getElementById('dp-show-adherence-bar').style.width = `${complianceVal}%`;
    document.getElementById('dp-show-feedback-label').textContent = `${feedbackVal} / 5.0`;
    document.getElementById('dp-show-feedback-bar').style.width = `${feedbackPct}%`;


    // Component 5: Recent Driver Activity Timeline Date headers
    const timelineWrapper = document.getElementById('dp-show-timeline-wrapper');
    if (timelineWrapper) {
        if (driver.status === 'On Duty') {
            const activeBus = driver.bus || 'PAS-003';
            timelineWrapper.innerHTML = `
                <!-- TODAY -->
                <div class="relative pl-4 mb-4 select-none">
                    <span class="absolute left-[-29px] top-1 px-1 py-0.5 rounded text-[8px] font-black uppercase tracking-widest text-[#003F87] bg-[#E6F1FB] border border-[#003F87]/10">Today</span>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="h-2 w-2 rounded-full bg-[#003F87] mt-1 shrink-0"></span>
                            <div>
                                <span class="text-[10px] font-mono text-slate-400 font-bold">08:15</span>
                                <p class="text-xs text-slate-700 font-bold mt-0.5">Assigned to Bus ${activeBus}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="h-2 w-2 rounded-full bg-[#003F87] mt-1 shrink-0"></span>
                            <div>
                                <span class="text-[10px] font-mono text-slate-400 font-bold">08:18</span>
                                <p class="text-xs text-slate-700 font-bold mt-0.5">Dispatch Created</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="h-2 w-2 rounded-full bg-[#003F87] mt-1 shrink-0"></span>
                            <div>
                                <span class="text-[10px] font-mono text-slate-400 font-bold">08:30</span>
                                <p class="text-xs text-slate-700 font-bold mt-0.5">Trip Started</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="h-2 w-2 rounded-full bg-[#003F87] mt-1 shrink-0"></span>
                            <div>
                                <span class="text-[10px] font-mono text-slate-400 font-bold">09:05</span>
                                <p class="text-xs text-slate-700 font-bold mt-0.5">Reached Stop 4</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="h-2 w-2 rounded-full bg-[#639922] mt-1 shrink-0"></span>
                            <div>
                                <span class="text-[10px] font-mono text-slate-400 font-bold">09:42</span>
                                <p class="text-xs text-[#639922] font-black mt-0.5">Trip Completed</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="h-2 w-2 rounded-full bg-slate-400 mt-1 shrink-0"></span>
                            <div>
                                <span class="text-[10px] font-mono text-slate-400 font-bold">11:10</span>
                                <p class="text-xs text-slate-500 font-semibold mt-0.5">Returned to Standby</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- YESTERDAY -->
                <div class="relative pl-4 mt-6 select-none">
                    <span class="absolute left-[-29px] top-1 px-1 py-0.5 rounded text-[8px] font-black uppercase tracking-widest text-slate-500 bg-slate-100 border border-slate-200">Yest</span>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="h-2 w-2 rounded-full bg-slate-400 mt-1 shrink-0"></span>
                            <div>
                                <span class="text-[10px] font-mono text-slate-400 font-semibold">16:00</span>
                                <p class="text-xs text-slate-600 font-medium mt-0.5">Completed Route 2 dispatch shift</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        } else {
            timelineWrapper.innerHTML = `
                <!-- TODAY -->
                <div class="relative pl-4 mb-4 select-none">
                    <span class="absolute left-[-29px] top-1 px-1 py-0.5 rounded text-[8px] font-black uppercase tracking-widest text-[#003F87] bg-[#E6F1FB] border border-[#003F87]/10">Today</span>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="h-2 w-2 rounded-full bg-slate-400 mt-1 shrink-0"></span>
                            <div>
                                <span class="text-[10px] font-mono text-slate-400 font-semibold">08:00</span>
                                <p class="text-xs text-slate-500 font-semibold mt-0.5">Clocked In (Standby Duty)</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- YESTERDAY -->
                <div class="relative pl-4 mt-6 select-none">
                    <span class="absolute left-[-29px] top-1 px-1 py-0.5 rounded text-[8px] font-black uppercase tracking-widest text-slate-500 bg-slate-100 border border-slate-200">Yest</span>
                    <div class="space-y-4">
                        <div class="flex items-start gap-3">
                            <span class="h-2 w-2 rounded-full bg-slate-400 mt-1 shrink-0"></span>
                            <div>
                                <span class="text-[10px] font-mono text-slate-400 font-semibold">17:15</span>
                                <p class="text-xs text-slate-500 font-semibold mt-0.5">Shift Closed</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="h-2 w-2 rounded-full bg-slate-400 mt-1 shrink-0"></span>
                            <div>
                                <span class="text-[10px] font-mono text-slate-400 font-semibold">16:20</span>
                                <p class="text-xs text-slate-600 font-medium mt-0.5">Completed Route coverage</p>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
    }


    // Component 6: Priority-Grouped Alerts Panel Bindings
    const criticalSec = document.getElementById('dp-show-alert-section-critical');
    const warningSec = document.getElementById('dp-show-alert-section-warning');
    const criticalBody = document.getElementById('dp-show-alert-critical-body');
    const warningBody = document.getElementById('dp-show-alert-warning-body');
    const eligibilityLabel = document.getElementById('dp-show-dispatch-eligibility');

    if (criticalSec && warningSec && criticalBody && warningBody) {
        // Evaluate License Expiry
        if (expStatus === 'expired') {
            criticalSec.style.display = 'block';
            criticalBody.innerHTML = `
                <i class="ti ti-alert-circle text-rose-600 text-base shrink-0 mt-0.5"></i>
                <div class="flex-1">
                    <div class="font-extrabold">License Expired</div>
                    <div class="text-[10px] text-rose-500 font-medium mt-0.5">Driver license expired ${Math.abs(days)} day(s) ago. Renew immediately before dispatching.</div>
                </div>
            `;
        } else {
            criticalSec.style.display = 'none';
        }

        // Evaluate Warning triggers
        let warnHtml = '';
        if (expStatus === 'urgent' || expStatus === 'warn') {
            warnHtml += `
                <div class="flex items-start gap-2.5">
                    <i class="ti ti-alert-triangle text-amber-600 text-base shrink-0 mt-0.5"></i>
                    <div class="flex-1">
                        <div class="font-extrabold">License Expiring Soon</div>
                        <div class="text-[10px] text-amber-600 font-medium mt-0.5">License expires in ${days} days. Notify driver to renew.</div>
                    </div>
                </div>
            `;
        }
        if (driver.perfScore < 80) {
            warnHtml += `
                <div class="flex items-start gap-2.5 mt-2">
                    <i class="ti ti-alert-triangle text-amber-600 text-base shrink-0 mt-0.5"></i>
                    <div class="flex-1">
                        <div class="font-extrabold">Under Observation</div>
                        <div class="text-[10px] text-amber-600 font-medium mt-0.5">Performance Score index has dropped below 80%. Review trip compliance checks.</div>
                    </div>
                </div>
            `;
        }

        if (warnHtml) {
            warningSec.style.display = 'block';
            warningBody.innerHTML = warnHtml;
        } else {
            warningSec.style.display = 'none';
        }

        // Eligibility Check in Alerts Panel
        if (eligibilityLabel) {
            if (driver.status === 'Suspended' || expStatus === 'expired') {
                eligibilityLabel.className = 'flex items-center gap-2 text-xs text-slate-700 font-semibold bg-rose-50 border border-rose-100 rounded-lg p-2.5';
                eligibilityLabel.innerHTML = `
                    <i class="ti ti-x text-rose-600 text-sm"></i>
                    <span>Blocked from active dispatches</span>
                `;
            } else {
                eligibilityLabel.className = 'flex items-center gap-2 text-xs text-slate-700 font-semibold bg-[#E6F1FB] border border-[#003F87]/10 rounded-lg p-2.5';
                eligibilityLabel.innerHTML = `
                    <i class="ti ti-circle-check text-[#003F87] text-sm"></i>
                    <span>Eligible for Dispatch</span>
                `;
            }
        }
    }


    // Component 8: Compliance Checklist Card indicators checkmarks
    const complianceLicWrapper = document.getElementById('dp-show-compliance-license-check-wrapper');
    const complianceLicValue = document.getElementById('dp-show-compliance-license-check-value');
    if (complianceLicWrapper && complianceLicValue) {
        if (expStatus === 'expired') {
            complianceLicWrapper.className = 'flex items-center justify-between text-xs p-2 rounded-lg bg-rose-50 text-rose-700';
            complianceLicValue.innerHTML = `<i class="ti ti-x text-rose-600 text-sm"></i> Expired`;
        } else if (expStatus === 'urgent' || expStatus === 'warn') {
            complianceLicWrapper.className = 'flex items-center justify-between text-xs p-2 rounded-lg bg-amber-50 text-amber-800';
            complianceLicValue.innerHTML = `<i class="ti ti-alert-triangle text-amber-600 text-sm"></i> Expiring`;
        } else {
            complianceLicWrapper.className = 'flex items-center justify-between text-xs p-2 rounded-lg bg-emerald-50 text-[#639922]';
            complianceLicValue.innerHTML = `<i class="ti ti-circle-check text-emerald-500 text-sm"></i> Valid`;
        }
    }

    const complianceDispWrapper = document.getElementById('dp-show-compliance-dispatch-check-wrapper');
    const complianceDispValue = document.getElementById('dp-show-compliance-dispatch-check-value');
    if (complianceDispWrapper && complianceDispValue) {
        if (driver.status === 'Suspended' || expStatus === 'expired') {
            complianceDispWrapper.className = 'flex items-center justify-between text-xs p-2 rounded-lg bg-rose-50 text-rose-700';
            complianceDispValue.innerHTML = `<i class="ti ti-x text-rose-600 text-sm"></i> Ineligible`;
        } else {
            complianceDispWrapper.className = 'flex items-center justify-between text-xs p-2 rounded-lg bg-emerald-50 text-[#639922]';
            complianceDispValue.innerHTML = `<i class="ti ti-circle-check text-emerald-500 text-sm"></i> Eligible`;
        }
    }

    document.getElementById('dp-show-license').textContent = driver.license;
    document.getElementById('dp-show-expiry').textContent = driver.expiryLabel;


    // Component 9: Trip History list and count displays
    const tripTbody = document.getElementById('dp-show-trip-tbody');
    const countLabel = document.getElementById('dp-show-trip-count');
    if (driver.tripHistory && driver.tripHistory.length) {
        countLabel.textContent = `Showing ${driver.tripHistory.length} of ${driver.tripHistory.length} Trips`;
        tripTbody.innerHTML = driver.tripHistory.map(trip => `
            <tr class="hover:bg-slate-50/40 transition">
                <td class="px-6 py-4 font-semibold text-slate-700">${trip.date}</td>
                <td class="px-6 py-4 font-mono font-bold text-slate-650">${trip.bus}</td>
                <td class="px-6 py-4">${buildTripRoutePill(trip.route)}</td>
                <td class="px-6 py-4 text-center font-bold text-slate-650">${trip.trips}</td>
                <td class="px-6 py-4 text-center font-bold text-[#003F87]">${trip.pax}</td>
                <td class="px-6 py-4">${buildTripStatusChip(trip.status)}</td>
            </tr>
        `).join('');
    } else {
        countLabel.textContent = 'Showing 0 of 0 Trips';
        tripTbody.innerHTML = `<tr><td colspan="6" class="px-6 py-12 text-center text-slate-400 font-semibold">No trip logs recorded in the system.</td></tr>`;
    }

    document.getElementById('dp-show-contact').textContent = driver.contact || 'No contact number registered.';
    document.getElementById('dp-show-address').textContent = driver.address || 'No address registered.';
    document.getElementById('dp-show-emergency').textContent = driver.emergency || 'No emergency contact registered.';
}

// ── DYNAMIC SUSPEND TOGGLE AJAX ───────────────────────────────
async function toggleSuspendDriver(driverId) {
    const driver = DRIVERS_DATA.find(d => d.id === driverId);
    if (!driver) return;
    const willSuspend = driver.status !== 'Suspended';
    const action = willSuspend ? 'suspend' : 'unsuspend';
    if (!(await GoPasigUI.confirm(`Are you sure you want to ${action} driver ${driver.firstName} ${driver.lastName}?`))) return;

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.driversBaseUrl) ? window.GoPasigConfig.driversBaseUrl : '/admin/api/drivers';
        const response = await fetch(`${baseUrl}/${driverId}/suspend`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.success) {
            GoPasigUI.alert(data.message);
            await loadDatabaseDriversData();

            // Refresh Profile screen if currently opened
            if (window.currentDriversShowId === driverId && !document.getElementById('screen-drivers-show').classList.contains('hidden')) {
                openDriversShowScreen(driverId);
            }
        } else {
            GoPasigUI.alert(data.message || 'Failed to toggle suspend status.');
        }
    } catch (error) {
        GoPasigUI.alert('Server connection error. Failed to toggle suspend status.');
        console.error('AJAX suspend toggle error:', error);
    }
}

// ── VALIDATION & AJAX HANDLERS ─────────────────────────────────
function clearCreateErrors() {
    ['df-firstname-err', 'df-lastname-err', 'df-contact-err', 'df-license-err', 'df-expiry-err'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    });
    ['df-firstname', 'df-lastname', 'df-contact', 'df-license', 'df-expiry'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
    });
}

function showCreateFieldError(fieldId, errId) {
    const field = document.getElementById(fieldId);
    if (field) field.classList.add('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
    const err = document.getElementById(errId);
    if (err) err.classList.remove('hidden');
}

function checkExpiryWarn() {
    const val = document.getElementById('df-expiry')?.value;
    const warnEl = document.getElementById('df-expiry-warn');
    const warnText = document.getElementById('df-expiry-warn-text');
    if (!val || !warnEl) return;
    const { status, days } = computeExpiryStatus(val);
    if (status === 'urgent' || status === 'warn') {
        warnText.textContent = days <= 30
            ? `License expiring in ${days} day${days !== 1 ? 's' : ''} — notify driver to renew`
            : `License expiring soon (${days} days) — notify driver to renew`;
        warnEl.classList.remove('hidden');
    } else {
        warnEl.classList.add('hidden');
    }
}

async function handleDriverCreateSubmit(event) {
    event.preventDefault();
    clearCreateErrors();

    const submitBtn = document.getElementById('driver-submit-btn');
    const firstName = document.getElementById('df-firstname').value.trim();
    const lastName = document.getElementById('df-lastname').value.trim();
    const empId = document.getElementById('df-empid').value;
    const contact = document.getElementById('df-contact').value.trim().replace(/\s/g, '');
    const license = document.getElementById('df-license').value.trim();
    const expiry = document.getElementById('df-expiry').value;
    const address = document.getElementById('df-address').value.trim();
    const status = document.getElementById('df-status').value;
    const emergency = document.getElementById('df-emergency').value.trim();

    let valid = true;

    if (firstName.length < 2) {
        showCreateFieldError('df-firstname', 'df-firstname-err');
        valid = false;
    }
    if (lastName.length < 2) {
        showCreateFieldError('df-lastname', 'df-lastname-err');
        valid = false;
    }

    const contactRe = /^09\d{9}$/;
    if (!contactRe.test(contact)) {
        showCreateFieldError('df-contact', 'df-contact-err');
        valid = false;
    }

    const licenseRe = /^N\d{2}-\d{2}-\d{6}$/;
    if (!licenseRe.test(license)) {
        showCreateFieldError('df-license', 'df-license-err');
        valid = false;
    }

    if (!expiry) {
        showCreateFieldError('df-expiry', 'df-expiry-err');
        valid = false;
    }

    // Duplicate license check
    if (valid) {
        const duplicate = DRIVERS_DATA.find(d => d.license === license);
        if (duplicate) {
            const errEl = document.getElementById('df-license-err');
            errEl.textContent = `License number already registered to ${duplicate.firstName} ${duplicate.lastName}`;
            showCreateFieldError('df-license','df-license-err');
            valid = false;
        }
    }

    if (!valid) return;

    const payload = {
        first_name: firstName,
        last_name: lastName,
        emp_id: empId,
        license_number: license,
        license_expiry: expiry,
        status: status,
        contact_number: contact,
        address: address,
        emergency_contact: emergency
    };

    try {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Registering...';
        }

        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.driversBaseUrl) ? window.GoPasigConfig.driversBaseUrl : '/admin/api/drivers';
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
            switchScreen('drivers');
            await loadDatabaseDriversData();
        } else {
            GoPasigUI.alert(data.message || 'Validation error. Please verify input data.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Register Driver';
            }
        }
    } catch (error) {
        GoPasigUI.alert('Server connection error. Failed to register driver.');
        console.error('AJAX Driver submit error:', error);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Register Driver';
        }
    }
}

function clearEditErrors() {
    ['df-edit-firstname-err', 'df-edit-lastname-err', 'df-edit-contact-err', 'df-edit-license-err', 'df-edit-expiry-err'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    });
    ['df-edit-firstname', 'df-edit-lastname', 'df-edit-contact', 'df-edit-license', 'df-edit-expiry'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
    });
}

function showEditFieldError(fieldId, errId) {
    const field = document.getElementById(fieldId);
    if (field) field.classList.add('border-rose-500', 'focus:border-rose-500', 'focus:ring-rose-500');
    const err = document.getElementById(errId);
    if (err) err.classList.remove('hidden');
}

function checkEditExpiryWarn() {
    const val = document.getElementById('df-edit-expiry')?.value;
    const warnEl = document.getElementById('df-edit-expiry-warn');
    const warnText = document.getElementById('df-edit-expiry-warn-text');
    if (!val || !warnEl) return;
    const { status, days } = computeExpiryStatus(val);
    if (status === 'urgent' || status === 'warn') {
        warnText.textContent = days <= 30
            ? `License expiring in ${days} day${days !== 1 ? 's' : ''} — notify driver to renew`
            : `License expiring soon (${days} days) — notify driver to renew`;
        warnEl.classList.remove('hidden');
    } else if (status === 'expired') {
        warnText.textContent = `License expired! The driver must renew immediately.`;
        warnEl.classList.remove('hidden');
    } else {
        warnEl.classList.add('hidden');
    }
}

async function handleDriverEditSubmit(event) {
    event.preventDefault();
    clearEditErrors();

    const submitBtn = document.getElementById('driver-edit-submit-btn');
    const driverId = document.getElementById('df-edit-driver-id').value;
    const firstName = document.getElementById('df-edit-firstname').value.trim();
    const lastName = document.getElementById('df-edit-lastname').value.trim();
    const contact = document.getElementById('df-edit-contact').value.trim().replace(/\s/g, '');
    const license = document.getElementById('df-edit-license').value.trim();
    const expiry = document.getElementById('df-edit-expiry').value;
    const address = document.getElementById('df-edit-address').value.trim();
    const status = document.getElementById('df-edit-status').value;
    const emergency = document.getElementById('df-edit-emergency').value.trim();

    let valid = true;

    if (firstName.length < 2) {
        showEditFieldError('df-edit-firstname', 'df-edit-firstname-err');
        valid = false;
    }
    if (lastName.length < 2) {
        showEditFieldError('df-edit-lastname', 'df-edit-lastname-err');
        valid = false;
    }

    const contactRe = /^09\d{9}$/;
    if (!contactRe.test(contact)) {
        showEditFieldError('df-edit-contact', 'df-edit-contact-err');
        valid = false;
    }

    const licenseRe = /^N\d{2}-\d{2}-\d{6}$/;
    if (!licenseRe.test(license)) {
        showEditFieldError('df-edit-license', 'df-edit-license-err');
        valid = false;
    }

    if (!expiry) {
        showEditFieldError('df-edit-expiry', 'df-edit-expiry-err');
        valid = false;
    }

    // Duplicate license check (excluding self in edit)
    if (valid) {
        const duplicate = DRIVERS_DATA.find(d => d.license === license && d.id !== parseInt(driverId));
        if (duplicate) {
            const errEl = document.getElementById('df-edit-license-err');
            errEl.textContent = `License number already registered to ${duplicate.firstName} ${duplicate.lastName}`;
            showEditFieldError('df-edit-license','df-edit-license-err');
            valid = false;
        }
    }

    if (!valid) return;

    const payload = {
        first_name: firstName,
        last_name: lastName,
        license_number: license,
        license_expiry: expiry,
        status: status,
        contact_number: contact,
        address: address,
        emergency_contact: emergency
    };

    try {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Saving...';
        }

        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.driversBaseUrl) ? window.GoPasigConfig.driversBaseUrl : '/admin/api/drivers';
        const response = await fetch(`${baseUrl}/${driverId}`, {
            method: 'PUT',
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
            switchScreen('drivers');
            await loadDatabaseDriversData();
        } else {
            GoPasigUI.alert(data.message || 'Validation error. Please verify input data.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            }
        }
    } catch (error) {
        GoPasigUI.alert('Server connection error. Failed to save driver details.');
        console.error('AJAX Driver edit error:', error);
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Save Changes';
        }
    }
}

async function handleEditDeleteDriver() {
    const driverId = document.getElementById('df-edit-driver-id').value;
    if (!driverId) return;
    const driver = DRIVERS_DATA.find(d => d.id === parseInt(driverId));
    if (!driver) return;

    if (!(await GoPasigUI.confirm(`Are you absolutely sure you want to delete driver record ${driver.firstName} ${driver.lastName}?\nThis action cannot be undone.`))) {
        return;
    }

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.driversBaseUrl) ? window.GoPasigConfig.driversBaseUrl : '/admin/api/drivers';
        const response = await fetch(`${baseUrl}/${driverId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.success) {
            GoPasigUI.alert(data.message);
            switchScreen('drivers');
            await loadDatabaseDriversData();
        } else {
            GoPasigUI.alert(data.message || 'Failed to delete driver.');
        }
    } catch (error) {
        GoPasigUI.alert('Server connection error. Failed to delete driver.');
        console.error('AJAX driver delete error:', error);
    }
}

async function deleteDriverFromTable(driverId) {
    const driver = DRIVERS_DATA.find(d => d.id === driverId);
    if (!driver) return;

    if (!(await GoPasigUI.confirm(`Are you absolutely sure you want to delete driver record ${driver.firstName} ${driver.lastName}?\nThis action cannot be undone.`))) {
        return;
    }

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.driversBaseUrl) ? window.GoPasigConfig.driversBaseUrl : '/admin/api/drivers';
        const response = await fetch(`${baseUrl}/${driverId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.success) {
            GoPasigUI.alert(data.message);
            await loadDatabaseDriversData();
        } else {
            GoPasigUI.alert(data.message || 'Failed to delete driver.');
        }
    } catch (error) {
        GoPasigUI.alert('Server connection error. Failed to delete driver.');
        console.error('AJAX driver delete error:', error);
    }
}

function formatDateLabel(dateStr) {
    if (!dateStr) return '—';
    const d = new Date(dateStr);
    return d.toLocaleDateString('en-US', { month:'short', day:'numeric', year:'numeric' });
}

// ── EXPORT CSV ─────────────────────────────────────────────────
function exportDriversCSV() {
    const headers = ['Employee ID','Name','License No','Expiry','Bus','Route','Status','Trips Today','Pax Today'];
    const rows = DRIVERS_DATA.map(d => [
        d.empId,
        `${d.firstName} ${d.lastName}`,
        d.license,
        d.expiryLabel,
        d.bus || '',
        d.route ? `Route ${d.route}` : '',
        d.status,
        d.tripsToday,
        d.paxToday,
    ]);
    const csv = [headers, ...rows].map(r => r.map(v => `"${v}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'gopasig-drivers.csv'; a.click();
    URL.revokeObjectURL(url);
}

// ── ATTACH TRIGGERS & AUTO LOADER ─────────────────────────────
function initDriversModule() {
    const expiryInput = document.getElementById('df-expiry');
    if (expiryInput) {
        expiryInput.removeEventListener('change', checkExpiryWarn);
        expiryInput.addEventListener('change', checkExpiryWarn);
    }
    const editExpiryInput = document.getElementById('df-edit-expiry');
    if (editExpiryInput) {
        editExpiryInput.removeEventListener('change', checkEditExpiryWarn);
        editExpiryInput.addEventListener('change', checkEditExpiryWarn);
    }
    
    // Fetch database drivers instantly
    loadDatabaseDriversData();
}

// Ensure execution timing runs immediately if DOM is already fully interactive
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initDriversModule();
} else {
    document.addEventListener('DOMContentLoaded', initDriversModule);
}

// Close driver sub-screens on Escape key press
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        const createScreen = document.getElementById('screen-drivers-create');
        const editScreen = document.getElementById('screen-drivers-edit');
        const showScreen = document.getElementById('screen-drivers-show');
        
        const isCreateOpen = createScreen && !createScreen.classList.contains('hidden');
        const isEditOpen = editScreen && !editScreen.classList.contains('hidden');
        const isShowOpen = showScreen && !showScreen.classList.contains('hidden');

        if (isCreateOpen || isEditOpen || isShowOpen) {
            switchScreen('drivers');
        }
    }
});

// ── ADDITIONAL DYNAMIC STATS CARD FILTER & RECALCULATOR HELPERS ──
let activeDriverCardFilter = 'all';

function toggleDriverCardFilter(statusType, cardElement) {
    // Remove active styling classes
    document.querySelectorAll('[data-driver-card-filter]').forEach(el => {
        el.classList.remove('ring-2', 'ring-[#003F87]', 'shadow-md', 'bg-blue-50/10');
    });

    if (activeDriverCardFilter === statusType) {
        activeDriverCardFilter = 'all';
    } else {
        activeDriverCardFilter = statusType;
        cardElement.classList.add('ring-2', 'ring-[#003F87]', 'shadow-md', 'bg-blue-50/10');
    }

    filterDriversTable();
}

function resetDriversFilters() {
    const searchEl = document.getElementById('driver-search');
    if (searchEl) searchEl.value = '';
    const statusEl = document.getElementById('driver-status-filter');
    if (statusEl) statusEl.value = '';
    const licenseEl = document.getElementById('driver-license-filter');
    if (licenseEl) licenseEl.value = '';

    activeDriverCardFilter = 'all';
    document.querySelectorAll('[data-driver-card-filter]').forEach(el => {
        el.classList.remove('ring-2', 'ring-[#003F87]', 'shadow-md', 'bg-blue-50/10');
    });

    filterDriversTable();
}

function updateDriversStats() {
    const onDuty = DRIVERS_DATA.filter(d => d.status === 'On Duty').length;
    const standby = DRIVERS_DATA.filter(d => d.status === 'Off Duty').length;
    const suspended = DRIVERS_DATA.filter(d => d.status === 'Suspended').length;

    const attention = DRIVERS_DATA.filter(d => {
        const { status } = computeExpiryStatus(d.expiryDate);
        return status === 'expired' || status === 'urgent' || status === 'warn';
    }).length;

    const assigned = DRIVERS_DATA.filter(d => d.bus && d.bus !== '—' && d.bus !== 'None').length;
    const highPerformers = DRIVERS_DATA.filter(d => d.perfScore >= 85).length;
    const expired = DRIVERS_DATA.filter(d => {
        const { status } = computeExpiryStatus(d.expiryDate);
        return status === 'expired';
    }).length;
    const noTrips = DRIVERS_DATA.filter(d => d.status === 'Off Duty' && d.tripsToday === 0).length;

    const dutyEl = document.getElementById('dm-stat-on-duty');
    if (dutyEl) dutyEl.textContent = onDuty;

    const standbyEl = document.getElementById('dm-stat-standby');
    if (standbyEl) standbyEl.textContent = standby;

    const suspendedEl = document.getElementById('dm-stat-suspended');
    if (suspendedEl) suspendedEl.textContent = suspended;

    const attentionEl = document.getElementById('dm-stat-attention');
    if (attentionEl) attentionEl.textContent = attention;

    const assignedEl = document.getElementById('dm-health-assigned');
    if (assignedEl) assignedEl.textContent = assigned;

    const highEl = document.getElementById('dm-health-high-performers');
    if (highEl) highEl.textContent = highPerformers;

    const expiredEl = document.getElementById('dm-health-expired');
    if (expiredEl) expiredEl.textContent = expired;

    const noTripsEl = document.getElementById('dm-health-no-trips');
    if (noTripsEl) noTripsEl.textContent = noTrips;
}

// ── ROW ACTION CONTEXT OVERFLOW MENU CONTROLLER ──
let activeDriverRowMenuId = null;

function toggleDriverRowMenu(id, event) {
    event.stopPropagation();

    // Close any other open actions menus
    if (activeDriverRowMenuId && activeDriverRowMenuId !== id) {
        const otherMenu = document.getElementById(`driver-row-menu-${activeDriverRowMenuId}`);
        if (otherMenu) otherMenu.classList.add('hidden');
        const otherTrigger = document.querySelector(`tr[data-driver-id="${activeDriverRowMenuId}"] .dm-action-trigger`);
        if (otherTrigger) otherTrigger.classList.remove('active');
    }

    const menu = document.getElementById(`driver-row-menu-${id}`);
    const trigger = event.currentTarget;

    if (menu) {
        menu.classList.toggle('hidden');
        if (!menu.classList.contains('hidden')) {
            activeDriverRowMenuId = id;
            if (trigger) trigger.classList.add('active');

            // --- Smart positioning check ---
            const triggerRect = trigger.getBoundingClientRect();
            const viewportHeight = window.innerHeight;
            const dropdownHeight = 220; // 190px menu height + some padding safety
            const spaceBelow = viewportHeight - triggerRect.bottom;

            if (spaceBelow < dropdownHeight) {
                // Not enough room below trigger -> render above
                menu.style.bottom = 'calc(100% + 6px)';
                menu.style.top = 'auto';
            } else {
                // Renders normally below trigger
                menu.style.top = 'calc(100% + 6px)';
                menu.style.bottom = 'auto';
            }

            window.addEventListener('click', closeDriverRowMenuOutside);
        } else {
            activeDriverRowMenuId = null;
            if (trigger) trigger.classList.remove('active');
            window.removeEventListener('click', closeDriverRowMenuOutside);
        }
    }
}

function closeDriverRowMenuOutside() {
    if (activeDriverRowMenuId) {
        const menu = document.getElementById(`driver-row-menu-${activeDriverRowMenuId}`);
        if (menu) menu.classList.add('hidden');
        const trigger = document.querySelector(`tr[data-driver-id="${activeDriverRowMenuId}"] .dm-action-trigger`);
        if (trigger) trigger.classList.remove('active');
        activeDriverRowMenuId = null;
        window.removeEventListener('click', closeDriverRowMenuOutside);
    }
}



