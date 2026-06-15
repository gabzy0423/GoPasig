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

            // Update DOM Stats Strips
            const dutyEl = document.getElementById('dm-stat-on-duty');
            if (dutyEl) dutyEl.textContent = data.stats.on_duty;

            const offEl = document.getElementById('dm-stat-off-duty');
            if (offEl) offEl.textContent = data.stats.off_duty;

            const suspEl = document.getElementById('dm-stat-suspended');
            if (suspEl) suspEl.textContent = data.stats.suspended;

            const expEl = document.getElementById('dm-stat-expiring');
            if (expEl) expEl.textContent = data.stats.expiring;

            // Update registered subtitle count
            const subtitleEl = document.getElementById('dm-registered-drivers-subtitle');
            if (subtitleEl) {
                subtitleEl.textContent = `${DRIVERS_DATA.length} registered drivers · Pasig City Libreng Sakay Program`;
            }

            // Triggers filter & rendering updates
            isDriversDataLoaded = true;
            filterDriversTable();
        } else {
            console.error("Backend error during drivers fetch:", data);
            isDriversDataLoaded = true;
            filterDriversTable();
        }
    } catch (error) {
        console.error("Failed to load dynamic database drivers data:", error);
        isDriversDataLoaded = true;
        filterDriversTable();
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

/** Build license expiry cell HTML */
function buildExpiryCell(driver) {
    const { status, days } = computeExpiryStatus(driver.expiryDate);
    let html = '';
    if (status === 'expired') {
        html = `<span class="dm-expiry-expired">${driver.expiryLabel}</span>
                <span class="dm-badge dm-badge-expired">Expired</span>`;
    } else if (status === 'urgent') {
        html = `<i class="ti ti-alert-circle" style="color:#A32D2D;font-size:13px;vertical-align:-2px;"></i>
                <span class="dm-expiry-urgent"> ${driver.expiryLabel}</span>
                <span class="dm-badge dm-badge-urgent">Urgent</span>`;
    } else if (status === 'warn') {
        html = `<span class="dm-expiry-warn">${driver.expiryLabel}</span>
                <span class="dm-badge dm-badge-warn">Soon</span>`;
    } else {
        html = `<span class="dm-expiry-ok">${driver.expiryLabel}</span>`;
    }
    return html;
}

/** Build status chip HTML */
function buildStatusChip(status) {
    if (status === 'On Duty')
        return `<span class="dm-status-chip dm-status-on-duty"><i class="ti ti-circle-check"></i> On Duty</span>`;
    if (status === 'Suspended')
        return `<span class="dm-status-chip dm-status-suspended"><i class="ti ti-ban"></i> Suspended</span>`;
    return `<span class="dm-status-chip dm-status-off-duty">Off Duty</span>`;
}

/** Build route pill HTML */
function buildRoutePill(route) {
    if (!route || route === 'None') return `<span style="color:var(--color-text-secondary);">—</span>`;
    let colorClass = 'dm-route-a';
    if (route == '2') colorClass = 'dm-route-b';
    else if (route == '3') colorClass = 'dm-route-c';
    return `<span class="dm-route-chip ${colorClass}">Route ${route}</span>`;
}

/** Build pax mini-bar cell HTML */
function buildPaxCell(pax) {
    if (!pax) return `<span class="dm-pax-none">—</span>`;
    const pct = Math.min((pax / 250) * 100, 100);
    const color = paxBarColor(pax);
    return `<div class="dm-pax-cell">
        <div class="dm-pax-track"><div class="dm-pax-fill" style="width:${pct}%;background:${color};"></div></div>
        <span class="dm-pax-count">${pax}</span>
    </div>`;
}

/** Build action buttons cell */
function buildActionsCell(driver) {
    return `<div class="dm-actions-cell" style="justify-content: flex-end; gap: 8px;">
        <button class="dm-icon-btn" onclick="window.location.hash = 'drivers-show-' + ${driver.id}; event.stopPropagation();" title="View driver profile">
            <i class="ti ti-eye"></i>
        </button>
        <button class="dm-icon-btn" onclick="window.location.hash = 'drivers-edit-' + ${driver.id}; event.stopPropagation();" title="Edit driver">
            <i class="ti ti-edit"></i>
        </button>
        <button class="dm-icon-btn dm-icon-btn--ban" onclick="deleteDriverFromTable(${driver.id}); event.stopPropagation();" title="Delete driver">
            <i class="ti ti-trash"></i>
        </button>
    </div>`;
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
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:32px;color:var(--color-text-secondary);font-size:13px;"><div style="display:flex;align-items:center;justify-content:center;gap:8px;"><i class="ti ti-loader animate-spin" style="font-size:16px;"></i> Loading drivers data...</div></td></tr>`;
        } else {
            tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:32px;color:var(--color-text-secondary);font-size:13px;">No drivers found matching filters.</td></tr>`;
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
        return `<tr class="${rowClass}" data-driver-id="${driver.id}" data-status="${driver.status}" data-license-status="${expStatus}">
            <td class="dm-td">
                <div class="dm-driver-cell">
                    <div class="dm-avatar">${driver.initials}</div>
                    <div>
                        <button onclick="window.location.hash = 'drivers-show-' + ${driver.id};" class="dm-driver-name hover:underline" style="color: #003F87; font-weight: 600; text-decoration: none; background: none; border: none; padding: 0; cursor: pointer; text-align: left;">${driver.firstName} ${driver.lastName}</button>
                        <div class="dm-driver-empid">${driver.empId}</div>
                    </div>
                </div>
            </td>
            <td class="dm-td dm-mono">${driver.license}</td>
            <td class="dm-td">${buildExpiryCell(driver)}</td>
            <td class="dm-td dm-mono" style="font-size:12px;">${driver.bus || '<span style="color:var(--color-text-secondary);">—</span>'}</td>
            <td class="dm-td">${buildRoutePill(driver.route)}</td>
            <td class="dm-td">${buildStatusChip(driver.status)}</td>
            <td class="dm-td" style="text-align:center;">${driver.tripsToday}</td>
            <td class="dm-td">${buildPaxCell(driver.paxToday)}</td>
            <td class="dm-td">${buildActionsCell(driver)}</td>
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
        const matchSearch = !query || fullName.includes(query) || (driver.license && driver.license.toLowerCase().includes(query));

        const matchStatus = !status || driver.status === status;

        const { status: expStatus } = computeExpiryStatus(driver.expiryDate);
        let matchLicense = true;
        if (licFilter === 'ok') matchLicense = expStatus === 'ok';
        else if (licFilter === 'warn') matchLicense = expStatus === 'warn' || expStatus === 'urgent';
        else if (licFilter === 'expired') matchLicense = expStatus === 'expired';

        return matchSearch && matchStatus && matchLicense;
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
    document.getElementById('dp-show-breadcrumb-name').textContent = `${driver.firstName} ${driver.lastName}`;
    document.getElementById('dp-show-avatar').textContent = driver.initials;
    document.getElementById('dp-show-name').textContent = `${driver.firstName} ${driver.lastName}`;
    document.getElementById('dp-show-empid').textContent = driver.empId;
    document.getElementById('dp-show-license').textContent = driver.license;
    document.getElementById('dp-show-expiry').textContent = driver.expiryLabel;
    document.getElementById('dp-show-contact').textContent = driver.contact || '—';
    
    const busEl = document.getElementById('dp-show-bus');
    if (busEl) busEl.textContent = driver.bus || '—';

    const routeEl = document.getElementById('dp-show-route');
    if (routeEl) {
        if (driver.route && driver.route !== 'None') {
            routeEl.textContent = `Route ${driver.route}`;
            let colorClass = 'bg-blue-50 text-blue-700 border border-blue-200';
            if (driver.route == '2') colorClass = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
            else if (driver.route == '3') colorClass = 'bg-amber-50 text-amber-700 border border-amber-200';
            routeEl.className = `inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider ${colorClass}`;
            routeEl.style.display = '';
        } else {
            routeEl.style.display = 'none';
        }
    }

    // Action Buttons Setup
    const editBtn = document.getElementById('dp-show-edit-btn');
    if (editBtn) {
        editBtn.setAttribute('onclick', `window.location.hash = 'drivers-edit-${driver.id}'; return false;`);
    }
    const suspBtn = document.getElementById('dp-show-suspend-btn');
    if (suspBtn) {
        if (driver.status === 'Suspended') {
            suspBtn.innerHTML = '<i class="ti ti-circle-check"></i> Unsuspend';
            suspBtn.className = 'flex-1 rounded-lg border py-2 text-xs font-bold transition shadow-sm cursor-pointer flex items-center justify-center gap-1.5 border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border-none';
        } else {
            suspBtn.innerHTML = '<i class="ti ti-ban"></i> Suspend';
            suspBtn.className = 'flex-1 rounded-lg border py-2 text-xs font-bold transition shadow-sm cursor-pointer flex items-center justify-center gap-1.5 border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100 border-none';
        }
        suspBtn.setAttribute('onclick', `toggleSuspendDriver(${driver.id})`);
    }

    // Stats
    document.getElementById('dp-show-stat-trips').textContent = driver.tripsToday;
    document.getElementById('dp-show-stat-pax').textContent = driver.paxToday;
    const avg = driver.tripsToday > 0 ? (driver.paxToday / driver.tripsToday).toFixed(1) : '0.0';
    document.getElementById('dp-show-stat-avg').textContent = avg;
    document.getElementById('dp-show-stat-incidents').textContent = driver.incidents30;

    // Perf Index
    document.getElementById('dp-show-perf-label').textContent = `${driver.perfScore} / 100`;
    document.getElementById('dp-show-perf-bar').style.width = `${driver.perfScore}%`;

    // Trip History list
    const tripTbody = document.getElementById('dp-show-trip-tbody');
    const countLabel = document.getElementById('dp-show-trip-count');
    if (driver.tripHistory && driver.tripHistory.length) {
        countLabel.textContent = `${driver.tripHistory.length} record${driver.tripHistory.length !== 1 ? 's' : ''}`;
        tripTbody.innerHTML = driver.tripHistory.map(trip => `
            <tr class="hover:bg-slate-50/40 transition">
                <td class="px-6 py-4 font-semibold text-slate-700">${trip.date}</td>
                <td class="px-6 py-4 font-mono font-bold text-slate-600">${trip.bus}</td>
                <td class="px-6 py-4">${buildTripRoutePill(trip.route)}</td>
                <td class="px-6 py-4 text-center font-bold text-slate-600">${trip.trips}</td>
                <td class="px-6 py-4 text-center font-bold text-[#003F87]">${trip.pax}</td>
                <td class="px-6 py-4">${buildTripStatusChip(trip.status)}</td>
            </tr>
        `).join('');
    } else {
        countLabel.textContent = '0 records';
        tripTbody.innerHTML = `<tr><td colspan="6" class="px-6 py-12 text-center text-slate-400 font-semibold">No trip logs recorded in the system.</td></tr>`;
    }

    document.getElementById('dp-show-address').textContent = driver.address || 'No address registered.';
    document.getElementById('dp-show-emergency').textContent = driver.emergency || 'No emergency contact registered.';
}

// ── DYNAMIC SUSPEND TOGGLE AJAX ───────────────────────────────
async function toggleSuspendDriver(driverId) {
    const driver = DRIVERS_DATA.find(d => d.id === driverId);
    if (!driver) return;
    const willSuspend = driver.status !== 'Suspended';
    const action = willSuspend ? 'suspend' : 'unsuspend';
    if (!confirm(`Are you sure you want to ${action} driver ${driver.firstName} ${driver.lastName}?`)) return;

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
            alert(data.message);
            await loadDatabaseDriversData();

            // Refresh Profile screen if currently opened
            const activeHash = window.location.hash;
            if (activeHash === `#drivers-show-${driverId}`) {
                openDriversShowScreen(driverId);
            }
        } else {
            alert(data.message || 'Failed to toggle suspend status.');
        }
    } catch (error) {
        alert('Server connection error. Failed to toggle suspend status.');
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
            alert(data.message);
            window.location.hash = 'drivers';
            await loadDatabaseDriversData();
        } else {
            alert(data.message || 'Validation error. Please verify input data.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Register Driver';
            }
        }
    } catch (error) {
        alert('Server connection error. Failed to register driver.');
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
            alert(data.message);
            window.location.hash = 'drivers';
            await loadDatabaseDriversData();
        } else {
            alert(data.message || 'Validation error. Please verify input data.');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Changes';
            }
        }
    } catch (error) {
        alert('Server connection error. Failed to save driver details.');
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

    if (!confirm(`Are you absolutely sure you want to delete driver record ${driver.firstName} ${driver.lastName}?\nThis action cannot be undone.`)) {
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
            alert(data.message);
            window.location.hash = 'drivers';
            await loadDatabaseDriversData();
        } else {
            alert(data.message || 'Failed to delete driver.');
        }
    } catch (error) {
        alert('Server connection error. Failed to delete driver.');
        console.error('AJAX driver delete error:', error);
    }
}

async function deleteDriverFromTable(driverId) {
    const driver = DRIVERS_DATA.find(d => d.id === driverId);
    if (!driver) return;

    if (!confirm(`Are you absolutely sure you want to delete driver record ${driver.firstName} ${driver.lastName}?\nThis action cannot be undone.`)) {
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
            alert(data.message);
            await loadDatabaseDriversData();
        } else {
            alert(data.message || 'Failed to delete driver.');
        }
    } catch (error) {
        alert('Server connection error. Failed to delete driver.');
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
        const activeHash = window.location.hash;
        if (activeHash === '#drivers-create' || activeHash.startsWith('#drivers-edit-') || activeHash.startsWith('#drivers-show-')) {
            window.location.hash = 'drivers';
        }
    }
});
