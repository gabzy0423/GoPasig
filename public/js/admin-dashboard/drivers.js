/* ============================================================
   GoPasig Admin — Driver Management
   drivers.js
   ============================================================ */

// ── SAMPLE DATA CONTAINER (POPULATED VIA DYNAMIC DATABASE FETCH) ──
const DRIVERS_DATA = [];

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
            filterDriversTable();
        } else {
            console.error("Backend error during drivers fetch:", data);
        }
    } catch (error) {
        console.error("Failed to load dynamic database drivers data:", error);
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
    const isSuspended = driver.status === 'Suspended';
    const banClass = isSuspended ? 'dm-icon-btn dm-icon-btn--ban dm-banned' : 'dm-icon-btn dm-icon-btn--ban';
    const banTitle = isSuspended ? 'Unsuspend driver' : 'Suspend driver';
    return `<div class="dm-actions-cell">
        <button class="dm-icon-btn" onclick="openDriverProfile(${driver.id})" title="View driver profile"><i class="ti ti-eye"></i></button>
        <button class="dm-icon-btn" onclick="openDriverModal('edit', ${driver.id})" title="Edit driver"><i class="ti ti-edit"></i></button>
        <button class="${banClass}" onclick="toggleSuspendDriver(${driver.id})" title="${banTitle}"><i class="ti ti-ban"></i></button>
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
        tbody.innerHTML = `<tr><td colspan="9" style="text-align:center;padding:32px;color:var(--color-text-secondary);font-size:13px;">No drivers found matching filters.</td></tr>`;
        return;
    }

    tbody.innerHTML = data.map(driver => {
        const { status: expStatus } = computeExpiryStatus(driver.expiryDate);
        const rowClass = expStatus === 'expired' ? 'dm-tbody-row dm-row-expired' : 'dm-tbody-row';
        return `<tr class="${rowClass}" data-driver-id="${driver.id}" data-status="${driver.status}" data-license-status="${expStatus}">
            <td class="dm-td">
                <div class="dm-driver-cell">
                    <div class="dm-avatar">${driver.initials}</div>
                    <div>
                        <div class="dm-driver-name">${driver.firstName} ${driver.lastName}</div>
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

    updateShowingCount(data.length);
}

function updateShowingCount(count) {
    const el = document.getElementById('driver-showing-count');
    if (el) el.textContent = `Showing ${count} of ${DRIVERS_DATA.length} drivers`;
}

// ── FILTERING ──────────────────────────────────────────────────
function filterDriversTable() {
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

// ── MODAL ──────────────────────────────────────────────────────
let _modalMode = 'add';
let _editDriverId = null;

function openDriverModal(mode, driverId) {
    _modalMode = mode;
    _editDriverId = driverId || null;

    const modal = document.getElementById('driver-modal');
    const titleEl = document.getElementById('dm-modal-title');
    const infoText = document.getElementById('dm-info-text');
    const saveLabel = document.getElementById('dm-save-label');
    const deleteBtn = document.getElementById('dm-delete-btn');

    clearModalErrors();

    if (mode === 'add') {
        titleEl.textContent = 'Add new driver';
        infoText.textContent = 'Driver will receive login credentials via SMS after registration.';
        saveLabel.textContent = 'Register Driver';
        deleteBtn.classList.add('hidden');
        resetDriverForm();

        // Calculate unique sequential EMP ID
        const nextNum = DRIVERS_DATA.length ? Math.max(...DRIVERS_DATA.map(d => {
            const num = parseInt(d.empId.replace('EMP-', ''));
            return isNaN(num) ? 0 : num;
        })) + 1 : 29;
        document.getElementById('df-empid').value = `EMP-${nextNum.toString().padStart(4, '0')}`;
    } else {
        const driver = DRIVERS_DATA.find(d => d.id === driverId);
        if (!driver) return;
        titleEl.textContent = `Edit driver — ${driver.firstName} ${driver.lastName}`;
        infoText.textContent = 'Changes will be reflected immediately across all active sessions.';
        saveLabel.textContent = 'Update driver';
        deleteBtn.classList.remove('hidden');
        prefillDriverForm(driver);
    }

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeDriverModal() {
    document.getElementById('driver-modal').classList.add('hidden');
    document.body.style.overflow = '';
    clearModalErrors();
}

function resetDriverForm() {
    ['df-firstname','df-lastname','df-contact','df-license','df-expiry','df-address','df-emergency'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    document.getElementById('df-status').value = 'Active';
    document.getElementById('df-expiry-warn').classList.add('hidden');
}

function prefillDriverForm(driver) {
    document.getElementById('df-firstname').value = driver.firstName;
    document.getElementById('df-lastname').value = driver.lastName;
    document.getElementById('df-empid').value = driver.empId;
    document.getElementById('df-contact').value = driver.contact || '';
    document.getElementById('df-license').value = driver.license;
    document.getElementById('df-expiry').value = driver.expiryDate;
    document.getElementById('df-address').value = driver.address || '';
    document.getElementById('df-status').value = driver.status === 'Suspended' ? 'Suspended' : 'Active';
    document.getElementById('df-emergency').value = driver.emergency || '';
    checkExpiryWarn();
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

// ── VALIDATION & DYNAMIC CREATE/UPDATE AJAX ─────────────────────
function clearModalErrors() {
    ['df-firstname-err','df-lastname-err','df-contact-err','df-license-err','df-expiry-err'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.add('hidden');
    });
    ['df-firstname','df-lastname','df-contact','df-license','df-expiry'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.classList.remove('dm-input--error');
    });
}

function showFieldError(fieldId, errId) {
    document.getElementById(fieldId)?.classList.add('dm-input--error');
    document.getElementById(errId)?.classList.remove('hidden');
}

async function handleDriverFormSubmit(event) {
    if (event) event.preventDefault();
    clearModalErrors();

    const firstName = document.getElementById('df-firstname').value.trim();
    const lastName  = document.getElementById('df-lastname').value.trim();
    const contact   = document.getElementById('df-contact').value.trim().replace(/\s/g,'');
    const license   = document.getElementById('df-license').value.trim();
    const expiry    = document.getElementById('df-expiry').value;
    const address   = document.getElementById('df-address').value.trim();
    const status    = document.getElementById('df-status').value;
    const emergency = document.getElementById('df-emergency').value.trim();

    let valid = true;

    if (firstName.length < 2) { showFieldError('df-firstname','df-firstname-err'); valid = false; }
    if (lastName.length < 2)  { showFieldError('df-lastname','df-lastname-err'); valid = false; }

    const contactRe = /^09\d{9}$/;
    if (!contactRe.test(contact)) { showFieldError('df-contact','df-contact-err'); valid = false; }

    const licenseRe = /^N\d{2}-\d{2}-\d{6}$/;
    if (!licenseRe.test(license)) { showFieldError('df-license','df-license-err'); valid = false; }

    if (!expiry) {
        showFieldError('df-expiry','df-expiry-err');
        valid = false;
    }

    // Duplicate license check (excluding self in edit)
    if (valid) {
        const duplicate = DRIVERS_DATA.find(d => d.license === license && d.id !== _editDriverId);
        if (duplicate) {
            const errEl = document.getElementById('df-license-err');
            errEl.textContent = `License number already registered to ${duplicate.firstName} ${duplicate.lastName}`;
            showFieldError('df-license','df-license-err');
            valid = false;
        }
    }

    if (!valid) return;

    const payload = {
        first_name: firstName,
        last_name: lastName,
        emp_id: document.getElementById('df-empid').value,
        license_number: license,
        license_expiry: expiry,
        status: status === 'Suspended' ? 'suspended' : (_modalMode === 'add' ? 'inactive' : 'active'),
        contact_number: contact,
        address: address,
        emergency_contact: emergency
    };

    const isEdit = _modalMode !== 'add';
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.driversBaseUrl) ? window.GoPasigConfig.driversBaseUrl : '/admin/api/drivers';
    const url = isEdit ? `${baseUrl}/${_editDriverId}` : baseUrl;
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
            closeDriverModal();
            
            // Re-fetch all dynamic records to update table, map states and stats cards
            await loadDatabaseDriversData();
        } else {
            alert(data.message || 'Validation error. Please verify input formats.');
            console.error('Driver submit failed:', data.errors || data);
        }
    } catch (error) {
        alert('Server connection error. Failed to save driver registration.');
        console.error('AJAX Driver submit error:', error);
    }
}

// ── DYNAMIC DELETE AJAX ───────────────────────────────────────
async function handleDeleteDriver() {
    if (!_editDriverId) return;
    const driver = DRIVERS_DATA.find(d => d.id === _editDriverId);
    if (!driver) return;

    if (!confirm(`Are you absolutely sure you want to delete driver record ${driver.firstName} ${driver.lastName}?\nThis action cannot be undone.`)) {
        return;
    }

    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.driversBaseUrl) ? window.GoPasigConfig.driversBaseUrl : '/admin/api/drivers';
        const response = await fetch(`${baseUrl}/${_editDriverId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert(data.message);
            closeDriverModal();
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

            // Refresh Profile drawer if currently opened
            if (document.getElementById('driver-profile-drawer').classList.contains('dm-drawer--open')) {
                openDriverProfile(driverId);
            }
        } else {
            alert(data.message || 'Failed to toggle suspend status.');
        }
    } catch (error) {
        alert('Server connection error. Failed to toggle suspend status.');
        console.error('AJAX suspend toggle error:', error);
    }
}

// ── DRIVER PROFILE DRAWER ──────────────────────────────────────
function openDriverProfile(driverId) {
    const driver = DRIVERS_DATA.find(d => d.id === driverId);
    if (!driver) return;

    const { status: expStatus, days } = computeExpiryStatus(driver.expiryDate);

    // Avatar
    document.getElementById('dp-avatar').textContent = driver.initials;

    // Identity
    document.getElementById('dp-name').textContent = `${driver.firstName} ${driver.lastName}`;
    document.getElementById('dp-meta').textContent = `${driver.empId} · License: ${driver.license}`;

    // Chips row
    const chipsEl = document.getElementById('dp-chips');
    let statusChip = '';
    if (driver.status === 'On Duty') statusChip = `<span class="dm-status-chip dm-status-on-duty"><i class="ti ti-circle-check"></i> On Duty</span>`;
    else if (driver.status === 'Suspended') statusChip = `<span class="dm-status-chip dm-status-suspended"><i class="ti ti-ban"></i> Suspended</span>`;
    else statusChip = `<span class="dm-status-chip dm-status-off-duty">Off Duty</span>`;

    let routeChip = (driver.route && driver.route !== 'None') ? `<span class="dm-route-chip dm-route-${driver.route.toLowerCase()}">Route ${driver.route}</span>` : '';

    let licChip = '';
    if (expStatus === 'expired') {
        licChip = `<span class="dm-license-urgent-chip"><i class="ti ti-alert-circle"></i> License expired ${driver.expiryLabel}</span>`;
    } else if (expStatus === 'urgent') {
        licChip = `<span class="dm-license-urgent-chip"><i class="ti ti-alert-circle"></i> License exp. ${driver.expiryLabel}</span>`;
    } else if (expStatus === 'warn') {
        licChip = `<span class="dm-license-warn-chip"><i class="ti ti-alert-circle"></i> License exp. ${driver.expiryLabel}</span>`;
    }

    chipsEl.innerHTML = statusChip + routeChip + licChip;

    // Action buttons
    document.getElementById('dp-edit-btn').setAttribute('onclick', `openDriverModal('edit', ${driver.id})`);
    const suspBtn = document.getElementById('dp-suspend-btn');
    if (driver.status === 'Suspended') {
        suspBtn.innerHTML = '<i class="ti ti-circle-check"></i> Unsuspend';
    } else {
        suspBtn.innerHTML = '<i class="ti ti-ban"></i> Suspend';
    }
    suspBtn.setAttribute('onclick', `toggleSuspendDriver(${driver.id})`);

    // Stats
    document.getElementById('dp-stat-trips').textContent = driver.tripsToday;
    document.getElementById('dp-stat-pax').textContent = driver.paxToday;
    const avg = driver.tripsToday > 0 ? (driver.paxToday / driver.tripsToday).toFixed(1) : '0';
    document.getElementById('dp-stat-avg').textContent = avg;
    
    const incEl = document.getElementById('dp-stat-incidents');
    incEl.textContent = driver.incidents30;
    incEl.style.color = driver.incidents30 > 0 ? '#A32D2D' : '#3B6D11';

    // Performance score bar
    document.getElementById('dp-perf-label').textContent = `${driver.perfScore} / 100`;
    document.getElementById('dp-perf-bar').style.width = `${driver.perfScore}%`;

    // Trip history table
    const tripTbody = document.getElementById('dp-trip-tbody');
    if (driver.tripHistory && driver.tripHistory.length) {
        tripTbody.innerHTML = driver.tripHistory.map(trip => `
            <tr>
                <td>${trip.date}</td>
                <td class="dm-mono" style="font-size:11px;">${trip.bus}</td>
                <td>${buildTripRoutePill(trip.route)}</td>
                <td>${trip.trips}</td>
                <td style="color:#003F87;font-weight:500;">${trip.pax}</td>
                <td>${buildTripStatusChip(trip.status)}</td>
            </tr>
        `).join('');
    } else {
        tripTbody.innerHTML = `<tr><td colspan="6" style="text-align:center;color:var(--color-text-secondary);padding:16px;font-size:12px;">No trips recorded.</td></tr>`;
    }

    // Open drawer overlay
    const overlay = document.getElementById('driver-profile-overlay');
    const drawer  = document.getElementById('driver-profile-drawer');
    overlay.classList.remove('hidden');
    requestAnimationFrame(() => {
        drawer.classList.add('dm-drawer--open');
    });
    document.body.style.overflow = 'hidden';
}

function closeDriverProfile() {
    const overlay = document.getElementById('driver-profile-overlay');
    const drawer  = document.getElementById('driver-profile-drawer');
    drawer.classList.remove('dm-drawer--open');
    setTimeout(() => {
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }, 280);
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
    
    // Fetch database drivers instantly
    loadDatabaseDriversData();
}

// Ensure execution timing runs immediately if DOM is already fully interactive
if (document.readyState === 'complete' || document.readyState === 'interactive') {
    initDriversModule();
} else {
    document.addEventListener('DOMContentLoaded', initDriversModule);
}

// Close drawer / modal on Escape key press
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        if (!document.getElementById('driver-modal').classList.contains('hidden')) {
            closeDriverModal();
        } else if (document.getElementById('driver-profile-drawer').classList.contains('dm-drawer--open')) {
            closeDriverProfile();
        }
    }
});
