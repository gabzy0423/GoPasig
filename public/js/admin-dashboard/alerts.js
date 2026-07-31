/* ============================================================
   GoPasig Admin — Service Alert Management
   alerts.js
   ============================================================ */

// ── MOCK DATABASE ─────────────────────────────────────────────
let activeAlerts = [];
let resolvedAlerts = [];
let scheduledAlerts = [];
let historyAlerts = [];
let broadcastInFlight = false;
let broadcastRefreshPromise = null;
let broadcastRefreshFailed = false;
let lastBroadcastAlertId = null;

// ── COMPOSER STATE ───────────────────────────────────────────
// ISSUE-045 FIX: Available routes are loaded dynamically from the DB.
// 'Route A/B/C' hardcoding is removed throughout this file.
const ALL_OFFICIAL_ROUTES = 'All official routes';
let availableRoutes = []; // Populated by loadRoutesIntoComposer()
let routeTargetsLoadFailed = false;

let composerState = {
    editingId: null,
    type: 'Delay',
    severity: 'Medium',
    title: '',
    message: '',
    affects: [],       // Will be set to the first DB route after loadRoutesIntoComposer()
    notifyCommuters: true,
    notifyDrivers: true,
    notifyAdminOnly: false,
    timing: 'now',
    scheduleTime: '',
    suspendRoute: false
};

/**
 * ISSUE-045 FIX: Fetch real route names from the DB API and render dynamic
 * pills in the composer. Replaces the old hardcoded Route A / B / C buttons.
 */
async function loadRoutesIntoComposer() {
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.alertTargetRoutesUrl)
        ? window.GoPasigConfig.alertTargetRoutesUrl
        : '/admin/api/service-alert-target-routes';

    try {
        const resp = await fetch(baseUrl, {
            headers: { 'Accept': 'application/json' }
        });
        if (!resp.ok) throw new Error('Routes fetch failed');
        const data = await resp.json();

        // Support both {routes: [...]} and direct array responses
        const routes = Array.isArray(data) ? data : (data.routes || []);
        availableRoutes = routes.map(r => r.name || r).filter(Boolean);
        routeTargetsLoadFailed = availableRoutes.length === 0;
    } catch (e) {
        console.warn('Could not load official routes for composer.', e);
        availableRoutes = [];
        routeTargetsLoadFailed = true;
    }

    // Inject dynamic pills before the ALL_OFFICIAL_ROUTES button
    const pillRow = document.getElementById('composer-route-pills-row');
    if (pillRow) {
        // Remove any previously injected dynamic pills
        pillRow.querySelectorAll('.am-route-pill[data-dynamic]').forEach(b => b.remove());

        const allBtn = pillRow.querySelector('[data-route="' + ALL_OFFICIAL_ROUTES + '"]') || pillRow.querySelector('[data-route="All routes"]');
        if (allBtn) {
            allBtn.setAttribute('data-route', ALL_OFFICIAL_ROUTES);
            allBtn.textContent = ALL_OFFICIAL_ROUTES;
            allBtn.onclick = () => toggleComposerRoute(ALL_OFFICIAL_ROUTES);
            allBtn.disabled = routeTargetsLoadFailed;
            allBtn.title = routeTargetsLoadFailed ? 'Official route targets are unavailable. Please refresh or contact support.' : '';
        }
        availableRoutes.forEach(name => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'am-route-pill';
            btn.setAttribute('data-route', name);
            btn.setAttribute('data-dynamic', '1');
            btn.textContent = name;
            btn.onclick = () => toggleComposerRoute(name);
            if (allBtn) {
                pillRow.insertBefore(btn, allBtn);
            } else {
                pillRow.appendChild(btn);
            }
        });
    }

    renderRouteTargetLoadState();

    // Default selection: first available route (or empty)
    if (composerState.affects.length === 0 && availableRoutes.length > 0) {
        composerState.affects = [availableRoutes[0]];
    }
    syncComposerUI();
}

/**
 * Get route pill style class based on index-based color cycling.
 */
function renderRouteTargetLoadState() {
    const pillRow = document.getElementById('composer-route-pills-row');
    if (!pillRow) return;

    const existing = document.getElementById('composer-route-load-error');
    if (existing) existing.remove();

    if (!routeTargetsLoadFailed) return;

    const error = document.createElement('div');
    error.id = 'composer-route-load-error';
    error.className = 'mt-2 rounded-lg border border-rose-100 bg-rose-50 px-3 py-2 text-[11px] font-semibold text-rose-700';
    error.textContent = 'Official route targets are unavailable. Refresh before creating a public route alert.';
    pillRow.insertAdjacentElement('afterend', error);
}
function getRoutePillClass(route) {
    if (route === ALL_OFFICIAL_ROUTES) return 'selected-all';
    const idx = availableRoutes.indexOf(route);
    const colors = ['selected-a', 'selected-b', 'selected-c'];
    return colors[idx % colors.length] || 'selected-a';
}

// ── FILTER FEED STATE ─────────────────────────────────────────
let currentFeedStatusTab = 'All'; // 'All', 'Active', 'Resolved', 'Scheduled'
let currentFeedTypeFilter = 'All';
let currentFeedSearchQuery = '';

const deletingAlertIds = new Set();

let databaseStats = {
    total_commuters: 1000,
    total_drivers: 8,
    route_stats: {
        'Route A': { commuters: 335, drivers: 5 },
        'Route B': { commuters: 268, drivers: 1 },
        'Route C': { commuters: 253, drivers: 1 },
        [ALL_OFFICIAL_ROUTES]: { commuters: 1000, drivers: 8 }
    }
};

// ── HISTORY FILTER STATE ──────────────────────────────────────
let historyFilterSeverity = 'All';
let historyFilterType = 'All';
let historyFilterRoute = 'All';
let historyCurrentPage = 1;
const historyRowsPerPage = 8;

// Helper: Retrieve CSRF Token from Meta tag or Config
function getCsrfToken() {
    if (window.GoPasigConfig && window.GoPasigConfig.csrfToken) {
        return window.GoPasigConfig.csrfToken;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

function getAlertsBaseUrl() {
    if (window.GoPasigConfig && window.GoPasigConfig.alertsBaseUrl) {
        return window.GoPasigConfig.alertsBaseUrl;
    }
    return '/admin/api/alerts';
}

function getAlertsHistoryUrl() {
    if (window.GoPasigConfig && window.GoPasigConfig.alertsHistoryUrl) {
        return window.GoPasigConfig.alertsHistoryUrl;
    }
    return '/admin/api/alerts/history';
}

function mapDbSeverityToDisplay(severity) {
    if (severity === 'info' || severity === 'low') return 'Low';
    if (severity === 'warning' || severity === 'medium') return 'Medium';
    if (severity === 'high') return 'High';
    if (severity === 'critical' || severity === 'emergency') return 'Emergency';
    return severity ? String(severity).charAt(0).toUpperCase() + String(severity).slice(1) : 'Low';
}

function formatHistoryDate(value) {
    if (!value) return '-';
    const dateObj = new Date(value);
    if (Number.isNaN(dateObj.getTime())) return '-';
    const options = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
    return dateObj.toLocaleDateString('en-US', options).replace(',', ' �');
}

function normalizeComposerAlertType(type) {
    return String(type || '').trim().toLowerCase().replace(/[\s-]+/g, '_');
}

function normalizeComposerSeverity(severity) {
    return String(severity || '').trim().toLowerCase();
}

function getDefaultSeverityForType(type) {
    return {
        delay: 'Medium',
        route_change: 'Medium',
        route_changes: 'Medium',
        weather: 'Medium',
        breakdown: 'High',
        suspension: 'High',
        emergency: 'Emergency'
    }[normalizeComposerAlertType(type)] || 'Medium';
}

function canSelectedTypeSuspendRoute() {
    const type = normalizeComposerAlertType(composerState.type);
    const severity = normalizeComposerSeverity(composerState.severity);

    if (type === 'suspension') return true;
    if (['weather', 'breakdown', 'delay'].includes(type)) return severity === 'emergency';
    if (type === 'emergency') return ['high', 'emergency'].includes(severity);

    return false;
}

function isOperationalSuspensionAlert(alert) {
    return Boolean(alert && alert.suspendRoute);
}

function renderAlertDeleteMenuItem(alert) {
    if (isOperationalSuspensionAlert(alert)) {
        return `<button class="am-dropdown-item font-red is-disabled" disabled title="Resolve this operational suspension before archiving it."><i class="ti ti-archive"></i> Archive</button>`;
    }

    return `<button class="am-dropdown-item font-red" data-archive-alert-id="${alert.id}" onclick="deleteAlert(${alert.id})"><i class="ti ti-archive"></i> Archive</button>`;
}

function getDefaultSuspendRouteForType(type) {
    return normalizeComposerAlertType(type) === 'suspension';
}

function getSuspensionPolicyMessage() {
    if (composerState.suspendRoute && canSelectedTypeSuspendRoute()) {
        return 'New dispatches on the selected route(s) will be blocked immediately. Existing trips will continue until completed.';
    }

    if (normalizeComposerAlertType(composerState.type) === 'suspension') {
        return 'This is an advance suspension advisory. Drivers and commuters will be notified. The selected route(s) will remain active until Route Suspension is activated.';
    }

    return 'This alert is informational only. No operational changes will be made.';
}

// Dynamic loader from MySQL Database API
async function loadDatabaseAlertsData() {
    try {
        const response = await fetch(getAlertsBaseUrl());
        const data = await response.json();
        
        if (response.ok && data.success) {
            activeAlerts = [];
            resolvedAlerts = [];
            scheduledAlerts = [];
            historyAlerts = [];

            if (data.stats) {
                databaseStats = data.stats;
            }

            const now = new Date();

            data.alerts.forEach(alert => {
                // ISSUE-045 FIX: No longer remap Route 1/2/3 to Route A/B/C.
                // The DB now stores real route names (e.g. 'Route 1') which are
                // displayed directly without aliasing.
                let affectsArr = alert.affected_routes
                    ? alert.affected_routes.split(',').map(r => r.trim())
                    : [];

                // Parse severity
                let severityStr = 'Medium';
                if (alert.severity === 'info') severityStr = 'Low';
                else if (alert.severity === 'warning') severityStr = 'Medium';
                else if (alert.severity === 'high') severityStr = 'High';
                else if (alert.severity === 'critical') severityStr = 'Emergency';

                // Parse date/time
                const createdTime = new Date(alert.created_at);
                const diffMs = now - createdTime;
                const diffMin = Math.floor(diffMs / 60000);
                
                let timeStr = '';
                if (diffMin < 1) {
                    timeStr = 'Just now';
                } else if (diffMin < 60) {
                    timeStr = `${diffMin} min ago`;
                } else {
                    const options = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
                    timeStr = createdTime.toLocaleDateString('en-US', options).replace(',', ' ·');
                }

                const optionsDate = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
                const formattedDate = createdTime.toLocaleDateString('en-US', optionsDate).replace(',', ' ·');

                // Map reached commuters count
                const reachedCnt = alert.reads_count || 0;

                if (alert.status === 'resolved') {
                    resolvedAlerts.push({
                        id: alert.id,
                        type: alert.type,
                        title: alert.title,
                        body: alert.message,
                        affects: affectsArr,
                        resolvedBy: 'Admin',
                        resolvedTime: new Date(alert.updated_at).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' }),
                        severity: severityStr,
                        suspendRoute: Boolean(alert.suspend_route)
                    });


                } else {
                    if (createdTime > now) {
                        scheduledAlerts.push({
                            id: alert.id,
                            severity: severityStr,
                            type: alert.type,
                            title: alert.title,
                            body: alert.message,
                            affects: affectsArr,
                            publishTime: formattedDate,
                            suspendRoute: Boolean(alert.suspend_route)
                        });
                    } else {
                        activeAlerts.push({
                            id: alert.id,
                            severity: severityStr,
                            type: alert.type,
                            time: timeStr,
                            timestamp: alert.created_at,
                            title: alert.title,
                            body: alert.message,
                            affects: affectsArr,
                            reached: reachedCnt,
                            status: 'Active',
                            suspendRoute: Boolean(alert.suspend_route)
                        });
                    }
                }
            });

            console.log("MySQL Database alerts loaded dynamically!");
        }
    } catch (error) {
        console.error("Failed to load alerts from database:", error);
    }
}

// ── INITIALIZER ──────────────────────────────────────────────
async function loadHistoryAlertsData() {
    try {
        const response = await fetch(getAlertsHistoryUrl());
        const data = await response.json();

        if (response.ok && data.success) {
            historyAlerts = (data.history || []).map(log => ({
                id: log.id,
                serviceAlertId: log.service_alert_id,
                dateTime: formatHistoryDate(log.alert_created_at),
                archivedDate: formatHistoryDate(log.archived_at),
                type: log.type || 'Alert',
                severity: mapDbSeverityToDisplay(log.severity),
                title: log.title,
                affects: Array.isArray(log.affected_routes) ? log.affected_routes : [],
                suspendRoute: Boolean(log.suspend_route),
                status: log.status ? String(log.status).charAt(0).toUpperCase() + String(log.status).slice(1) : 'Archived'
            }));
        } else {
            historyAlerts = [];
            GoPasigUI.alert(data.message || 'Failed to load alert history.');
        }
    } catch (error) {
        console.error('Failed to load alert history:', error);
        historyAlerts = [];
    }
}
async function initAlertsDashboard() {
    await loadRoutesIntoComposer();
    await loadDatabaseAlertsData();
    renderAlertsFeed();
    renderResolvedAlerts();
    renderScheduledAlerts();
    syncComposerUI();
    updateDashboardHeaderStats();
}

function updateDashboardHeaderStats() {
    const activeCount = activeAlerts.length;
    
    // Update red badge count in header
    const activeBadge = document.getElementById('active-alerts-count');
    if (activeBadge) {
        activeBadge.textContent = `${activeCount} active alert${activeCount !== 1 ? 's' : ''}`;
    }

    // Update last broadcast time dynamically
    const lastBroadcastElement = document.querySelector('.am-last-broadcast');
    if (lastBroadcastElement) {
        let newestTime = null;
        activeAlerts.forEach(alert => {
            if (alert.timestamp) {
                const time = new Date(alert.timestamp);
                if (!newestTime || time > newestTime) {
                    newestTime = time;
                }
            }
        });
        if (newestTime) {
            const diffMs = new Date() - newestTime;
            const diffMin = Math.max(0, Math.floor(diffMs / 60000));
            if (diffMin < 1) {
                lastBroadcastElement.textContent = 'Last broadcast: Just now';
            } else if (diffMin < 60) {
                lastBroadcastElement.textContent = `Last broadcast: ${diffMin} min ago`;
            } else {
                const diffHr = Math.floor(diffMin / 60);
                lastBroadcastElement.textContent = `Last broadcast: ${diffHr} hour${diffHr !== 1 ? 's' : ''} ago`;
            }
        } else {
            lastBroadcastElement.textContent = 'Last broadcast: None today';
        }
    }

    // Update status pills counts in filter bar
    const tabActiveCount = document.getElementById('tab-active-count');
    if (tabActiveCount) tabActiveCount.textContent = activeCount;

    const tabResolvedCount = document.getElementById('tab-resolved-count');
    if (tabResolvedCount) tabResolvedCount.textContent = resolvedAlerts.length;

    const tabScheduledCount = document.getElementById('tab-scheduled-count');
    if (tabScheduledCount) tabScheduledCount.textContent = scheduledAlerts.length;

    // Update red dot visibility in All tab
    const redDot = document.getElementById('filter-all-dot');
    if (redDot) {
        if (currentFeedStatusTab === 'All' && activeCount > 0) {
            redDot.style.display = 'inline-block';
        } else {
            redDot.style.display = 'none';
        }
    }
}

// ── COMPOSER ACTIONS & EVENTS ─────────────────────────────────
function selectComposerType(type) {
    composerState.type = type;
    composerState.severity = getDefaultSeverityForType(type);
    composerState.suspendRoute = getDefaultSuspendRouteForType(type) && canSelectedTypeSuspendRoute();

    syncComposerUI();
}

function selectComposerSeverity(severity) {
    composerState.severity = severity;

    if (!canSelectedTypeSuspendRoute()) {
        composerState.suspendRoute = false;
    }

    syncComposerUI();
}

function toggleComposerRoute(route) {
    if (route === ALL_OFFICIAL_ROUTES) {
        composerState.affects = [ALL_OFFICIAL_ROUTES];
    } else {
        // If All routes was selected, clear it
        if (composerState.affects.includes(ALL_OFFICIAL_ROUTES)) {
            composerState.affects = [];
        }
        
        const idx = composerState.affects.indexOf(route);
        if (idx > -1) {
            composerState.affects.splice(idx, 1);
        } else {
            composerState.affects.push(route);
        }
        
        // If nothing is selected, fall back to first available DB route (not hardcoded 'Route A')
        if (composerState.affects.length === 0) {
            composerState.affects = availableRoutes.length > 0 ? [availableRoutes[0]] : [];
        }
    }
    syncComposerUI();
}

function onComposerTitleInput(val) {
    composerState.title = val;
    const counter = document.getElementById('composer-title-counter');
    if (counter) {
        counter.textContent = `${val.length} / 80`;
        counter.className = 'am-char-counter';
        if (val.length >= 75) {
            counter.classList.add('counter-red');
        } else if (val.length >= 60) {
            counter.classList.add('counter-amber');
        }
    }
}

// Global hook for title input in Blade template
function handleComposerTitleInput(event) {
    onComposerTitleInput(event.target.value);
}

function onComposerMessageInput(val) {
    composerState.message = val;
    const counter = document.getElementById('composer-message-counter');
    if (counter) {
        counter.textContent = `${val.length} / 280`;
        counter.className = 'am-char-counter';
        if (val.length >= 260) {
            counter.classList.add('counter-red');
        } else if (val.length >= 210) {
            counter.classList.add('counter-amber');
        }
    }
}

// Global hook for message input in Blade template
function handleComposerMessageInput(event) {
    onComposerMessageInput(event.target.value);
}

function setComposerTiming(timing) {
    composerState.timing = timing;
    syncComposerUI();
}

function onComposerScheduleTimeChange(val) {
    composerState.scheduleTime = val;
    syncComposerUI();
}

function toggleComposerSuspension() {
    if (!canSelectedTypeSuspendRoute()) {
        composerState.suspendRoute = false;
        syncComposerUI();
        return;
    }

    composerState.suspendRoute = !composerState.suspendRoute;
    syncComposerUI();
}

function toggleNotificationTarget(target) {
    if (target === 'commuters') {
        if (composerState.notifyCommuters && !composerState.notifyDrivers) {
            return;
        }
        composerState.notifyCommuters = !composerState.notifyCommuters;
    } else if (target === 'drivers') {
        if (composerState.notifyDrivers && !composerState.notifyCommuters) {
            return;
        }
        composerState.notifyDrivers = !composerState.notifyDrivers;
    } else if (target === 'admin') {
        composerState.notifyAdminOnly = !composerState.notifyAdminOnly;
        if (composerState.notifyAdminOnly) {
            composerState.notifyCommuters = false;
            composerState.notifyDrivers = false;
        } else {
            composerState.notifyCommuters = true;
            composerState.notifyDrivers = true;
        }
    }
    syncComposerUI();
}

function clearComposerForm() {
    composerState = {
        editingId: null,
        type: 'Delay',
        severity: 'Medium',
        title: '',
        message: '',
        affects: availableRoutes.length > 0 ? [availableRoutes[0]] : [],
        notifyCommuters: true,
        notifyDrivers: true,
        notifyAdminOnly: false,
        timing: 'now',
        scheduleTime: '',
        suspendRoute: false
    };

    // Reset fields in DOM
    const titleInput = document.getElementById('composer-title');
    const msgTextarea = document.getElementById('composer-message');
    if (titleInput) titleInput.value = '';
    if (msgTextarea) msgTextarea.value = '';
    onComposerTitleInput('');
    onComposerMessageInput('');

    // Update section label to New Alert
    const composerH2 = document.getElementById('composer-header-title');
    if (composerH2) composerH2.textContent = 'New alert';

    syncComposerUI();
}

function syncComposerUI() {
    // 1. Alert Type Selector grid items
    const typeCards = document.querySelectorAll('.am-type-card');
    typeCards.forEach(card => {
        const type = card.getAttribute('data-type');
        card.className = 'am-type-card';
        if (type === composerState.type) {
            if (composerState.type === 'Emergency') {
                card.classList.add('selected-emergency');
            } else {
                card.classList.add('selected');
            }
        }
    });

    // 2. Severity pills
    const severityPills = document.querySelectorAll('.am-sev-pill');
    severityPills.forEach(pill => {
        const sev = pill.getAttribute('data-severity');
        pill.className = `am-sev-pill pill-${sev.toLowerCase()}`;
        if (sev === composerState.severity) {
            pill.classList.add('active');
        }
    });

    // 3. Emergency state overrides
    const composerCard = document.getElementById('composer-card');
    const emergencyBanner = document.getElementById('composer-emergency-banner');
    const broadcastBtn = document.getElementById('btn-broadcast-alert');

    if (composerState.severity === 'Emergency') {
        if (composerCard) {
            composerCard.style.backgroundColor = '#FFFBFB';
            composerCard.style.borderColor = '#F09595';
        }
        if (emergencyBanner) emergencyBanner.classList.remove('hidden');
        if (broadcastBtn) {
            broadcastBtn.style.backgroundColor = '#E24B4A';
            broadcastBtn.innerHTML = (composerState.editingId ? '<i class="ti ti-send"></i> Update & Broadcast Emergency' : '<i class="ti ti-send"></i> Broadcast Emergency');
        }
    } else {
        if (composerCard) {
            composerCard.style.backgroundColor = '';
            composerCard.style.borderColor = '';
        }
        if (emergencyBanner) emergencyBanner.classList.add('hidden');
        if (broadcastBtn) {
            broadcastBtn.style.backgroundColor = '';
            broadcastBtn.innerHTML = (composerState.editingId ? '<i class="ti ti-send"></i> Update & Broadcast Alert' : '<i class="ti ti-send"></i> Broadcast Alert');
        }
    }

    // 4. Affected routes pills — highlight using index-based color cycling
    // (selected-a/b/c/all are cycle colors, not tied to specific route names)
    const routePills = document.querySelectorAll('.am-route-pill');
    routePills.forEach(pill => {
        const route = pill.getAttribute('data-route');
        pill.className = 'am-route-pill';
        if (composerState.affects.includes(route)) {
            pill.classList.add(getRoutePillClass(route));
        }
    });

    // 5. Notify targets
    const chkCommuters = document.getElementById('chk-commuters');
    const chkDrivers = document.getElementById('chk-drivers');
    const chkAdmin = document.getElementById('chk-admin');

    if (chkCommuters) chkCommuters.checked = composerState.notifyCommuters;
    if (chkDrivers) chkDrivers.checked = composerState.notifyDrivers;
    if (chkAdmin) chkAdmin.checked = composerState.notifyAdminOnly;

    // 6. Timing radios & inputs
    const radNow = document.getElementById('rad-send-now');
    const radLater = document.getElementById('rad-send-later');
    const scheduleTimeContainer = document.getElementById('schedule-time-container');
    const scheduleTimeInput = document.getElementById('schedule-time-input');
    const previewChip = document.getElementById('schedule-preview-chip');

    if (radNow) radNow.checked = (composerState.timing === 'now');
    if (radLater) radLater.checked = (composerState.timing === 'later');

    if (composerState.timing === 'later') {
        if (scheduleTimeContainer) scheduleTimeContainer.classList.remove('hidden');
        if (scheduleTimeInput) scheduleTimeInput.value = composerState.scheduleTime;
        if (previewChip) {
            if (composerState.scheduleTime) {
                const dateObj = new Date(composerState.scheduleTime);
                const options = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
                previewChip.textContent = `Scheduled for ${dateObj.toLocaleDateString('en-US', options).replace(',', ' ·')}`;
                previewChip.classList.remove('hidden');
            } else {
                previewChip.classList.add('hidden');
            }
        }
    } else {
        if (scheduleTimeContainer) scheduleTimeContainer.classList.add('hidden');
        if (previewChip) previewChip.classList.add('hidden');
    }

    // 7. Route Suspension
    const toggleSuspensionTrack = document.getElementById('toggle-suspension-track');
    const toggleSuspensionLabel = document.getElementById('suspension-label');
    const suspensionWarning = document.getElementById('suspension-warning-card');
    const suspensionWarningText = document.getElementById('suspension-warning-text');
    const suspensionRow = document.getElementById('suspension-toggle-row');
    const suspensionPolicyHelper = document.getElementById('suspension-policy-helper');
    const canSuspendRoute = canSelectedTypeSuspendRoute();

    if (suspensionPolicyHelper) {
        suspensionPolicyHelper.textContent = getSuspensionPolicyMessage();
        suspensionPolicyHelper.classList.remove('hidden');
    }

    if (!canSuspendRoute) {
        composerState.suspendRoute = false;
        if (suspensionRow) suspensionRow.className = 'am-toggle-row hidden';
        if (suspensionWarning) suspensionWarning.classList.add('hidden');
        return;
    }

    if (suspensionRow) suspensionRow.className = 'am-toggle-row';

    if (composerState.suspendRoute) {
        if (toggleSuspensionTrack) toggleSuspensionTrack.className = 'am-toggle-track am-toggle-on';
        if (toggleSuspensionLabel) toggleSuspensionLabel.style.color = '#A32D2D';
        if (suspensionRow) suspensionRow.className = 'am-toggle-row suspension-active';
        

        if (suspensionWarningText) {
            suspensionWarningText.textContent = 'New dispatches on the selected route(s) will be blocked immediately. Existing trips will continue until completed.';
        }
        if (suspensionWarning) suspensionWarning.classList.remove('hidden');
    } else {
        if (toggleSuspensionTrack) toggleSuspensionTrack.className = 'am-toggle-track';
        if (toggleSuspensionLabel) toggleSuspensionLabel.style.color = '';
        if (suspensionRow) suspensionRow.className = 'am-toggle-row';
        if (suspensionWarning) suspensionWarning.classList.add('hidden');
    }
}

// --- ALERTS FEED FILTERING & RENDER ─────────────────────────────
function setFeedStatusTab(tab) {
    currentFeedStatusTab = tab;
    
    const tabs = document.querySelectorAll('.am-status-tab-btn');
    tabs.forEach(btn => {
        if (btn.getAttribute('data-tab') === tab) {
            btn.classList.add('active');
        } else {
            btn.classList.remove('active');
        }
    });

    renderAlertsFeed();
    updateDashboardHeaderStats();
}

function onFeedTypeFilterChange(val) {
    currentFeedTypeFilter = val;
    renderAlertsFeed();
}

function onFeedSearchInput(val) {
    currentFeedSearchQuery = val.trim().toLowerCase();
    renderAlertsFeed();
}

function renderAlertsFeed() {
    const activeFeed = document.getElementById('active-alerts-feed');
    if (!activeFeed) return;

    let filteredActive = [];
    let showActiveSection = (currentFeedStatusTab === 'All' || currentFeedStatusTab === 'Active');
    let showResolvedSection = (currentFeedStatusTab === 'All' || currentFeedStatusTab === 'Resolved');
    let showScheduledSection = (currentFeedStatusTab === 'All' || currentFeedStatusTab === 'Scheduled');

    if (showActiveSection) {
        filteredActive = activeAlerts.filter(alert => {
            if (currentFeedTypeFilter !== 'All' && alert.type !== currentFeedTypeFilter) return false;
            if (currentFeedSearchQuery) {
                const inTitle = alert.title.toLowerCase().includes(currentFeedSearchQuery);
                const inBody = alert.body.toLowerCase().includes(currentFeedSearchQuery);
                const inType = alert.type.toLowerCase().includes(currentFeedSearchQuery);
                if (!inTitle && !inBody && !inType) return false;
            }
            return true;
        });
    }

    const activeSectionContainer = document.getElementById('active-section-container');
    const resolvedSectionContainer = document.getElementById('resolved-section-container');
    const scheduledSectionContainer = document.getElementById('scheduled-section-container');

    if (activeSectionContainer) {
        if (showActiveSection && (filteredActive.length > 0 || currentFeedStatusTab === 'Active')) {
            activeSectionContainer.classList.remove('hidden');
        } else {
            activeSectionContainer.classList.add('hidden');
        }
    }

    if (resolvedSectionContainer) {
        if (showResolvedSection) {
            resolvedSectionContainer.classList.remove('hidden');
        } else {
            resolvedSectionContainer.classList.add('hidden');
        }
    }

    if (scheduledSectionContainer) {
        if (showScheduledSection && (scheduledAlerts.length > 0 || currentFeedStatusTab === 'Scheduled')) {
            scheduledSectionContainer.classList.remove('hidden');
        } else {
            scheduledSectionContainer.classList.add('hidden');
        }
    }

    if (filteredActive.length === 0) {
        if (currentFeedStatusTab === 'Active' || currentFeedStatusTab === 'All') {
            activeFeed.innerHTML = `
                <div class="am-empty-state">
                    <i class="ti ti-bell-off"></i>
                    <p>No active service alerts match the filters.</p>
                </div>
            `;
        } else {
            activeFeed.innerHTML = '';
        }
    } else {
        activeFeed.innerHTML = filteredActive.map(alert => {
            const isEmergency = alert.severity === 'Emergency';
            const severityClass = alert.severity.toLowerCase();
            const bgClass = isEmergency ? 'bg-emergency-tint' : '';
            
            const routePillsHtml = alert.affects.includes(ALL_OFFICIAL_ROUTES)
                ? `<span class="am-route-pill-display selected-all">All official routes</span>`
                : alert.affects.map(route => {
                    return `<span class="am-route-pill-display ${getRoutePillClass(route)}">${route}</span>`;
                  }).join('');

            let sevIcon = 'ti-info-circle';
            if (alert.severity === 'Emergency') sevIcon = 'ti-alert-octagon';
            else if (alert.severity === 'High') sevIcon = 'ti-alert-triangle';
            else if (alert.severity === 'Medium') sevIcon = 'ti-alert-circle';

            return `
                <div class="am-alert-card border-${severityClass} ${bgClass}" id="alert-card-${alert.id}">
                    <div class="am-card-inner">
                        <div class="am-card-top-row">
                            <div class="am-card-left-group">
                                <span class="am-severity-chip badge-${severityClass}">
                                    <i class="ti ${sevIcon}"></i> ${alert.severity}
                                </span>
                                <span class="am-card-type-label">${alert.type}</span>
                                <span class="am-dot-sep">·</span>
                                <span class="am-card-time">${alert.time}</span>
                            </div>
                            
                            <div class="am-card-right-group" style="position: relative;">
                                <button class="am-icon-btn-action" onclick="toggleCardMenu(${alert.id}, event)" title="Actions">
                                    <i class="ti ti-dots-vertical"></i>
                                </button>
                                <div id="card-menu-${alert.id}" class="am-dropdown-menu hidden">
                                    <button class="am-dropdown-item" onclick="editAlert(${alert.id})"><i class="ti ti-edit"></i> Edit</button>
                                    <button class="am-dropdown-item" onclick="broadcastAgain(${alert.id})"><i class="ti ti-send"></i> Broadcast again</button>
                                    <button class="am-dropdown-item font-green" onclick="markResolved(${alert.id})"><i class="ti ti-check"></i> Mark resolved</button>
                                    <div class="am-dropdown-divider"></div>
                                    ${renderAlertDeleteMenuItem(alert)}
                                </div>
                            </div>
                        </div>

                        <div class="am-card-content-row">
                            <h3 class="am-card-title ${isEmergency ? 'title-emergency' : ''}">${alert.title}</h3>
                            <p class="am-card-body" id="card-body-text-${alert.id}">${alert.body}</p>
                            <button id="card-body-show-more-${alert.id}" class="am-link-show-more hidden" onclick="toggleCardBodyText(${alert.id})">Show more</button>
                        </div>

                        <div class="am-card-routes-row">
                            <i class="ti ti-route"></i>
                            <span class="am-card-routes-label">Affects:</span>
                            <div class="am-card-route-pills">
                                ${routePillsHtml}
                            </div>
                        </div>

                        <div class="am-card-action-row">
                            <button class="am-card-btn-resolve" onclick="markResolved(${alert.id})">
                                <i class="ti ti-check"></i> Mark resolved
                            </button>
                            <button class="am-card-btn-edit" onclick="editAlert(${alert.id})">
                                <i class="ti ti-edit"></i> Edit alert
                            </button>
                            <button class="am-card-btn-broadcast" onclick="broadcastAgain(${alert.id})">
                                <i class="ti ti-send"></i> Broadcast again
                            </button>
                            
                            <span class="am-card-reached-stats">
                                <i class="ti ti-eye"></i> ${alert.reached} commuters reached
                            </span>
                        </div>
                    </div>
                </div>
            `;
        }).join('');

        filteredActive.forEach(alert => {
            const bodyElem = document.getElementById(`card-body-text-${alert.id}`);
            const btnMore = document.getElementById(`card-body-show-more-${alert.id}`);
            if (bodyElem && btnMore) {
                if (alert.body.length > 180) {
                    bodyElem.classList.add('clamp-lines');
                    btnMore.classList.remove('hidden');
                }
            }
        });
    }
}

// ── EXPAND/COLLAPSE & RENDER RESOLVED SECTION ──────────────────
let resolvedExpanded = false;
function toggleResolvedSection() {
    resolvedExpanded = !resolvedExpanded;
    
    const chevron = document.getElementById('resolved-chevron');
    const container = document.getElementById('resolved-rows-container');

    if (resolvedExpanded) {
        if (chevron) chevron.className = 'ti ti-chevron-down';
        if (container) container.classList.remove('hidden');
        renderResolvedList();
    } else {
        if (chevron) chevron.className = 'ti ti-chevron-right';
        if (container) container.classList.add('hidden');
    }
}

function renderResolvedAlerts() {
    const resolvedCountLabel = document.getElementById('resolved-count-label');
    if (resolvedCountLabel) {
        resolvedCountLabel.textContent = `Resolved today (${resolvedAlerts.length})`;
    }
    if (resolvedExpanded) {
        renderResolvedList();
    }
}

function renderResolvedList() {
    const list = document.getElementById('resolved-rows-list');
    if (!list) return;

    if (resolvedAlerts.length === 0) {
        list.innerHTML = `<div style="padding: 10px 12px; font-size:12px; color:var(--color-text-secondary);">No alerts resolved today.</div>`;
        return;
    }

    list.innerHTML = resolvedAlerts.map(alert => `
        <div class="am-resolved-row">
            <span class="am-resolved-dot"></span>
            <span class="am-resolved-type">${alert.type}</span>
            <span class="am-resolved-title">${alert.title}</span>
            <div class="am-resolved-right">
                <span class="am-resolved-meta">Resolved by ${alert.resolvedBy} · ${alert.resolvedTime}</span>
                <button class="am-resolved-delete" data-archive-alert-id="${alert.id}" onclick="deleteAlert(${alert.id})" title="Archive resolved alert"><i class="ti ti-archive"></i></button>
                <i class="ti ti-circle-check"></i>
            </div>
        </div>
    `).join('');
}

// ── EXPAND/COLLAPSE & RENDER SCHEDULED SECTION ─────────────────
let scheduledExpanded = false;
function toggleScheduledSection() {
    scheduledExpanded = !scheduledExpanded;
    
    const chevron = document.getElementById('scheduled-chevron');
    const container = document.getElementById('scheduled-cards-container');

    if (scheduledExpanded) {
        if (chevron) chevron.className = 'ti ti-chevron-down';
        if (container) container.classList.remove('hidden');
        renderScheduledList();
    } else {
        if (chevron) chevron.className = 'ti ti-chevron-right';
        if (container) container.classList.add('hidden');
    }
}

function renderScheduledAlerts() {
    const scheduledCountLabel = document.getElementById('scheduled-count-label');
    if (scheduledCountLabel) {
        scheduledCountLabel.textContent = `Scheduled (${scheduledAlerts.length})`;
    }
    if (scheduledExpanded) {
        renderScheduledList();
    }
}

function renderScheduledList() {
    const container = document.getElementById('scheduled-cards-list');
    if (!container) return;

    if (scheduledAlerts.length === 0) {
        container.innerHTML = `<div style="padding: 10px 12px; font-size:12px; color:var(--color-text-secondary);">No scheduled alerts queued.</div>`;
        return;
    }

    container.innerHTML = scheduledAlerts.map(alert => {
        const routePillsHtml = alert.affects.includes(ALL_OFFICIAL_ROUTES)
            ? `<span class="am-route-pill-display selected-all">All official routes</span>`
            : alert.affects.map(route => {
                return `<span class="am-route-pill-display ${getRoutePillClass(route)}">${route}</span>`;
              }).join('');

        return `
            <div class="am-scheduled-card">
                <div class="am-sched-top">
                    <span class="am-sched-chip"><i class="ti ti-calendar-time"></i> Scheduled</span>
                    <span class="am-sched-publish-time">Publishes ${alert.publishTime}</span>
                </div>
                <h4 class="am-sched-title">${alert.title}</h4>
                <p class="am-sched-body">${alert.body}</p>
                <div class="am-sched-footer">
                    <div class="am-sched-routes">
                        <i class="ti ti-route"></i>
                        <span class="am-sched-routes-lbl">Affects:</span>
                        <div class="am-card-route-pills">${routePillsHtml}</div>
                    </div>
                    <div class="am-sched-actions">
                        <button class="am-sched-btn-edit" onclick="editScheduledAlert(${alert.id})">Edit</button>
                        <button class="am-sched-btn-cancel" onclick="cancelScheduledAlert(${alert.id})">Cancel scheduled alert</button>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// ── ACTIVE ALERTS CARD MENUS ──────────────────────────────────
let activeOpenCardMenuId = null;
function toggleCardMenu(id, event) {
    event.stopPropagation();
    
    if (activeOpenCardMenuId && activeOpenCardMenuId !== id) {
        const otherMenu = document.getElementById(`card-menu-${activeOpenCardMenuId}`);
        if (otherMenu) otherMenu.classList.add('hidden');
    }

    const menu = document.getElementById(`card-menu-${id}`);
    if (menu) {
        menu.classList.toggle('hidden');
        if (!menu.classList.contains('hidden')) {
            activeOpenCardMenuId = id;
            window.addEventListener('click', closeCardMenuOutside);
        } else {
            activeOpenCardMenuId = null;
            window.removeEventListener('click', closeCardMenuOutside);
        }
    }
}

function closeCardMenuOutside() {
    if (activeOpenCardMenuId) {
        const menu = document.getElementById(`card-menu-${activeOpenCardMenuId}`);
        if (menu) menu.classList.add('hidden');
        activeOpenCardMenuId = null;
    }
    window.removeEventListener('click', closeCardMenuOutside);
}

function toggleCardBodyText(id) {
    const elem = document.getElementById(`card-body-text-${id}`);
    const btn = document.getElementById(`card-body-show-more-${id}`);
    if (elem && btn) {
        elem.classList.toggle('clamp-lines');
        if (elem.classList.contains('clamp-lines')) {
            btn.textContent = 'Show more';
        } else {
            btn.textContent = 'Show less';
        }
    }
}

// ── BROADCAST CONFIRMATION OVERLAYS ───────────────────────────
function triggerComposerBroadcast() {
    if (!composerState.title.trim()) {
        GoPasigUI.alert('Please specify an alert title.');
        return;
    }
    if (!composerState.message.trim()) {
        GoPasigUI.alert('Please describe the situation in the message.');
        return;
    }
    if (composerState.timing === 'later' && !composerState.scheduleTime) {
        GoPasigUI.alert('Please choose a publish date and time.');
        return;
    }
    if (routeTargetsLoadFailed || availableRoutes.length === 0) {
        GoPasigUI.alert('Official route targets are unavailable. Refresh before creating a public route alert.');
        return;
    }
    if (!composerState.affects.length) {
        GoPasigUI.alert('Please select at least one official route target.');
        return;
    }

    showBroadcastConfirmation();
}

function showBroadcastConfirmation() {
    const overlay = document.getElementById('broadcast-overlay');
    if (!overlay) return;

    const headerIconCircle = document.getElementById('confirm-icon-circle');
    const headerTitle = document.getElementById('confirm-title');
    const headerSub = document.getElementById('confirm-sub');
    
    const sumType = document.getElementById('confirm-sum-type');
    const sumSeverity = document.getElementById('confirm-sum-severity');
    const sumRoutes = document.getElementById('confirm-sum-routes');
    const sumNotifying = document.getElementById('confirm-sum-notifying');
    const sumSuspension = document.getElementById('confirm-sum-suspension');
    
    const emergencyWarning = document.getElementById('confirm-emergency-warning-card');
    const confirmBtn = document.getElementById('btn-confirm-broadcast');

    if (composerState.severity === 'Emergency') {
        headerIconCircle.className = 'am-confirm-icon-circle confirm-circle-emergency';
        headerIconCircle.innerHTML = '<i class="ti ti-alert-octagon text-2xl"></i>';
        if (emergencyWarning) emergencyWarning.classList.remove('hidden');
        if (confirmBtn) {
            confirmBtn.style.backgroundColor = '#E24B4A';
        }
    } else if (composerState.severity === 'High' || composerState.severity === 'Medium') {
        headerIconCircle.className = 'am-confirm-icon-circle confirm-circle-warning';
        headerIconCircle.innerHTML = '<i class="ti ti-alert-triangle text-2xl"></i>';
        if (emergencyWarning) emergencyWarning.classList.add('hidden');
        if (confirmBtn) {
            confirmBtn.style.backgroundColor = (composerState.severity === 'High') ? '#D85A30' : '#BA7517';
        }
    } else {
        headerIconCircle.className = 'am-confirm-icon-circle confirm-circle-info';
        headerIconCircle.innerHTML = '<i class="ti ti-bell text-2xl"></i>';
        if (emergencyWarning) emergencyWarning.classList.add('hidden');
        if (confirmBtn) {
            confirmBtn.style.backgroundColor = '#003F87';
        }
    }

    if (composerState.timing === 'later') {
        headerTitle.textContent = 'Schedule this alert?';
        const dateObj = new Date(composerState.scheduleTime);
        const options = { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' };
        const timeFormatted = dateObj.toLocaleDateString('en-US', options).replace(',', ' ·');
        headerSub.textContent = `This will queue the alert to broadcast automatically on ${timeFormatted}.`;
        confirmBtn.innerHTML = `<i class="ti ti-calendar-time"></i> Confirm scheduling`;
    } else {
        headerTitle.textContent = 'Broadcast this alert?';
        headerSub.textContent = 'This will notify the following recipients immediately.';
        confirmBtn.innerHTML = `<i class="ti ti-send"></i> Confirm broadcast`;
    }

    if (sumType) {
        sumType.innerHTML = `<span class="chip-type-display"><i class="ti ti-tag"></i> ${composerState.type}</span>`;
    }
    if (sumSeverity) {
        sumSeverity.innerHTML = `<span class="am-severity-chip badge-${composerState.severity.toLowerCase()}">${composerState.severity}</span>`;
    }

    if (sumRoutes) {
        if (composerState.affects.includes(ALL_OFFICIAL_ROUTES)) {
            sumRoutes.innerHTML = '<span class="am-route-pill-display selected-all">All official routes</span>';
        } else {
            sumRoutes.innerHTML = composerState.affects.map(route => {
                return `<span class="am-route-pill-display ${getRoutePillClass(route)}">${route}</span>`;
            }).join('');
        }
    }

    if (sumNotifying) {
        if (composerState.notifyAdminOnly) {
            sumNotifying.textContent = 'Admin team only (internal)';
        } else {
            let commuterCnt = 0;
            let driverCnt = 0;
            if (composerState.affects.includes(ALL_OFFICIAL_ROUTES) || composerState.affects.length === 0) {
                commuterCnt = databaseStats.total_commuters;
                driverCnt = databaseStats.total_drivers;
            } else {
                composerState.affects.forEach(route => {
                    const rStats = databaseStats.route_stats[route];
                    if (rStats) {
                        commuterCnt += rStats.commuters;
                        driverCnt += rStats.drivers;
                    }
                });
            }
            sumNotifying.textContent = `${commuterCnt} commuters + ${driverCnt} drivers`;
        }
    }

    if (sumSuspension) {
        if (composerState.suspendRoute) {
            let affectedText = composerState.affects.join(' and ');
            if (composerState.affects.includes(ALL_OFFICIAL_ROUTES)) affectedText = ALL_OFFICIAL_ROUTES;
            sumSuspension.innerHTML = `<span class="font-red">Yes — ${affectedText} will be suspended</span>`;
        } else {
            sumSuspension.textContent = 'No';
        }
    }

    overlay.classList.remove('hidden');
    
    const receiptCard = document.getElementById('receipt-confirmation-card');
    if (receiptCard) receiptCard.classList.add('hidden');

    const overlayCard = document.getElementById('broadcast-overlay-card');
    if (overlayCard) overlayCard.classList.remove('hidden');
}

function hideBroadcastConfirmation() {
    const overlay = document.getElementById('broadcast-overlay');
    if (overlay) overlay.classList.add('hidden');
}

function setConfirmBroadcastLoading(isLoading) {
    const confirmBtn = document.getElementById('btn-confirm-broadcast');
    if (!confirmBtn) return;

    confirmBtn.disabled = isLoading;
    confirmBtn.classList.toggle('is-loading', isLoading);

    if (isLoading) {
        confirmBtn.innerHTML = '<i class="ti ti-loader-2 am-spin"></i> Broadcasting...';
        return;
    }

    confirmBtn.innerHTML = composerState.timing === 'later'
        ? '<i class="ti ti-calendar-time"></i> Confirm scheduling'
        : '<i class="ti ti-send"></i> Confirm broadcast';
}

function refreshBroadcastFeedsInBackground() {
    broadcastRefreshFailed = false;
    broadcastRefreshPromise = loadDatabaseAlertsData()
        .then(() => {
            renderAlertsFeed();
            renderResolvedAlerts();
            renderScheduledAlerts();
            updateDashboardHeaderStats();
        })
        .catch(error => {
            broadcastRefreshFailed = true;
            console.error('Background alert refresh failed:', error);
        });

    return broadcastRefreshPromise;
}

async function ensureBroadcastRefreshComplete() {
    if (broadcastRefreshPromise) {
        await broadcastRefreshPromise;
    }

    if (broadcastRefreshFailed) {
        broadcastRefreshFailed = false;
        await loadDatabaseAlertsData();
        renderAlertsFeed();
        renderResolvedAlerts();
        renderScheduledAlerts();
        updateDashboardHeaderStats();
    }
}

function focusLatestBroadcastAlert() {
    if (!lastBroadcastAlertId) return;

    setFeedStatusTab('Active');
    currentFeedTypeFilter = 'All';
    currentFeedSearchQuery = '';

    const typeFilter = document.getElementById('feed-type-filter');
    if (typeFilter) typeFilter.value = 'All';

    const searchInput = document.getElementById('feed-search-input');
    if (searchInput) searchInput.value = '';

    renderAlertsFeed();
    updateDashboardHeaderStats();

    const card = document.getElementById(`alert-card-${lastBroadcastAlertId}`);
    if (!card) return;

    card.scrollIntoView({ behavior: 'smooth', block: 'center' });
    card.classList.add('am-alert-card-highlight');
    window.setTimeout(() => card.classList.remove('am-alert-card-highlight'), 1800);
}

async function confirmBroadcast() {
    if (broadcastInFlight) return;

    const csrfToken = getCsrfToken();

    const payload = {
        title: composerState.title,
        message: composerState.message,
        severity: composerState.severity,
        type: composerState.type,
        affects: composerState.affects,
        timing: composerState.timing,
        schedule_time: composerState.scheduleTime,
        suspend_route: canSelectedTypeSuspendRoute() && composerState.suspendRoute
    };

    broadcastInFlight = true;
    setConfirmBroadcastLoading(true);

    try {
        let response;
        if (composerState.editingId) {
            response = await fetch(`${getAlertsBaseUrl()}/${composerState.editingId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
        } else {
            response = await fetch(getAlertsBaseUrl(), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });
        }

        const data = await response.json();
        if (response.ok && data.success) {
            lastBroadcastAlertId = data.alert && data.alert.id ? data.alert.id : null;
            setConfirmBroadcastLoading(false);
            broadcastInFlight = false;

            const overlayCard = document.getElementById('broadcast-overlay-card');
            if (overlayCard) overlayCard.classList.add('hidden');

            showBroadcastReceipt();
            refreshBroadcastFeedsInBackground();
        } else {
            GoPasigUI.alert(data.message || 'Failed to save alert.');
        }
    } catch (error) {
        console.error("AJAX confirm broadcast error:", error);
        GoPasigUI.alert('Server connection error. Failed to save alert.');
    } finally {
        if (broadcastInFlight) {
            broadcastInFlight = false;
            setConfirmBroadcastLoading(false);
        }
    }
}

function showBroadcastReceipt() {
    const receiptCard = document.getElementById('receipt-confirmation-card');
    if (!receiptCard) return;

    const title = document.getElementById('receipt-title');
    const timeLabel = document.getElementById('receipt-time-label');
    const statsRow = document.getElementById('receipt-stats-row');
    const primaryAction = document.getElementById('receipt-primary-action');
    const secondaryAction = document.getElementById('receipt-secondary-action');

    if (primaryAction) {
        primaryAction.style.display = '';
        primaryAction.className = 'am-btn-outline flex-1';
        primaryAction.setAttribute('onclick', 'viewBroadcastAlertInFeed()');
        primaryAction.innerHTML = '<i class="ti ti-arrow-right"></i> View alert in feed';
    }
    if (secondaryAction) {
        secondaryAction.style.display = '';
        secondaryAction.className = 'am-btn-primary flex-1 text-white';
        secondaryAction.setAttribute('onclick', 'closeBroadcastReceipt()');
        secondaryAction.innerHTML = '<i class="ti ti-bell-plus"></i> Create another alert';
    }

    const dateStr = new Date().toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    const timeStr = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });

    if (composerState.timing === 'later') {
        title.textContent = 'Alert scheduled successfully';
        title.style.color = '#003F87';
        timeLabel.textContent = `Scheduled on ${dateStr} · ${timeStr}`;
        statsRow.style.display = 'none';
    } else {
        title.textContent = 'Alert broadcast successfully';
        title.style.color = '#3B6D11';
        timeLabel.textContent = `${dateStr} · ${timeStr}`;

        statsRow.style.display = 'flex';
        const statsCommuters = document.getElementById('receipt-stat-commuters');
        const statsDrivers = document.getElementById('receipt-stat-drivers');
        const statsSuspended = document.getElementById('receipt-stat-suspended');

        let commuterCnt = 0;
        let driverCnt = 0;
        if (composerState.affects.includes(ALL_OFFICIAL_ROUTES) || composerState.affects.length === 0) {
            commuterCnt = databaseStats.total_commuters;
            driverCnt = databaseStats.total_drivers;
        } else {
            composerState.affects.forEach(route => {
                const rStats = databaseStats.route_stats[route];
                if (rStats) {
                    commuterCnt += rStats.commuters;
                    driverCnt += rStats.drivers;
                }
            });
        }

        statsCommuters.textContent = `${commuterCnt} commuters notified`;
        statsDrivers.textContent = `${driverCnt} drivers notified`;

        if (composerState.suspendRoute) {
            let affectedText = composerState.affects.join('/');
            if (composerState.affects.includes(ALL_OFFICIAL_ROUTES)) affectedText = ALL_OFFICIAL_ROUTES;
            statsSuspended.textContent = `${affectedText} suspended`;
            statsSuspended.style.display = 'inline-flex';
        } else {
            statsSuspended.style.display = 'none';
        }
    }

    receiptCard.classList.remove('hidden');
}

async function viewBroadcastAlertInFeed() {
    await ensureBroadcastRefreshComplete();
    hideBroadcastConfirmation();
    clearComposerForm();
    focusLatestBroadcastAlert();
}

function closeBroadcastReceipt() {
    hideBroadcastConfirmation();
    clearComposerForm();
}

// ── ACTION BUTTON WORKFLOWS ───────────────────────────────────
function isAlertHistoryVaultVisible() {
    const historyScreen = document.getElementById('screen-alerts-history');
    return Boolean(historyScreen && !historyScreen.classList.contains('hidden'));
}

function showArchiveStatusModal(titleText, messageText, isSuccess = true) {
    const overlay = document.getElementById('broadcast-overlay');
    const confirmCard = document.getElementById('broadcast-confirmation-card');
    const receiptCard = document.getElementById('receipt-confirmation-card');
    if (!overlay || !receiptCard) return;

    const title = document.getElementById('receipt-title');
    const timeLabel = document.getElementById('receipt-time-label');
    const statsRow = document.getElementById('receipt-stats-row');
    const primaryAction = document.getElementById('receipt-primary-action');
    const secondaryAction = document.getElementById('receipt-secondary-action');

    if (confirmCard) confirmCard.classList.add('hidden');
    if (title) {
        title.textContent = titleText;
        title.style.color = isSuccess ? '#3B6D11' : '#B42318';
    }
    if (timeLabel) timeLabel.textContent = messageText;
    if (statsRow) statsRow.style.display = 'none';
    if (primaryAction) {
        primaryAction.style.display = '';
        primaryAction.className = isSuccess ? 'am-btn-primary flex-1 text-white' : 'am-btn-outline flex-1';
        primaryAction.setAttribute('onclick', 'closeArchiveStatusModal()');
        primaryAction.innerHTML = isSuccess
            ? '<i class="ti ti-check"></i> Done'
            : '<i class="ti ti-x"></i> Close';
    }
    if (secondaryAction) secondaryAction.style.display = 'none';

    overlay.classList.remove('hidden');
    receiptCard.classList.remove('hidden');
}

function closeArchiveStatusModal() {
    hideBroadcastConfirmation();
}

function setArchiveButtonLoading(id, isLoading) {
    document.querySelectorAll(`[data-archive-alert-id="${id}"]`).forEach(button => {
        button.disabled = isLoading;
        button.classList.toggle('is-loading', isLoading);

        if (button.classList.contains('am-resolved-delete')) {
            button.innerHTML = isLoading ? '<i class="ti ti-loader-2 am-spin"></i>' : '<i class="ti ti-archive"></i>';
        } else {
            button.innerHTML = isLoading ? '<i class="ti ti-loader-2 am-spin"></i> Archiving...' : '<i class="ti ti-archive"></i> Archive';
        }
    });
}

function refreshArchiveViewsInBackground() {
    return loadDatabaseAlertsData()
        .then(() => {
            renderAlertsFeed();
            renderResolvedAlerts();
            renderScheduledAlerts();
            updateDashboardHeaderStats();
        })
        .then(() => {
            if (!isAlertHistoryVaultVisible()) return;

            return loadHistoryAlertsData().then(renderHistoryTable);
        })
        .catch(error => {
            console.error('Background archive refresh failed:', error);
        });
}
function markResolved(id) {
    const card = document.getElementById(`alert-card-${id}`);
    if (card) {
        card.style.opacity = '0';
        card.style.transform = 'translateY(10px)';
    }

    setTimeout(async () => {
        try {
            const response = await fetch(`${getAlertsBaseUrl()}/${id}/resolve`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                }
            });
            let data = await response.json();

            if (data.requiresConfirmation) {
                if (await GoPasigUI.confirm(data.message)) {
                    const confirmResponse = await fetch(`${getAlertsBaseUrl()}/${id}/resolve`, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': getCsrfToken(),
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ confirm: true })
                    });
                    data = await confirmResponse.json();
                } else {
                    return;
                }
            }

            if (data.success) {
                await loadDatabaseAlertsData();
                renderAlertsFeed();
                renderResolvedAlerts();
                updateDashboardHeaderStats();
            } else if (!data.requiresConfirmation) {
                GoPasigUI.alert(data.message || 'Failed to resolve alert.');
            }
        } catch (error) {
            console.error("AJAX resolve error:", error);
            GoPasigUI.alert('Server error resolving alert.');
        }
    }, 300);
}

function editAlert(id) {
    const alert = activeAlerts.find(a => a.id === id);
    if (!alert) return;

    composerState = {
        editingId: alert.id,
        type: alert.type,
        severity: alert.severity,
        title: alert.title,
        message: alert.body,
        affects: [...alert.affects],
        notifyCommuters: true,
        notifyDrivers: true,
        notifyAdminOnly: false,
        timing: 'now',
        scheduleTime: '',
        suspendRoute: Boolean(alert.suspendRoute)
    };

    const titleInput = document.getElementById('composer-title');
    const msgTextarea = document.getElementById('composer-message');
    if (titleInput) titleInput.value = alert.title;
    if (msgTextarea) msgTextarea.value = alert.body;
    
    onComposerTitleInput(alert.title);
    onComposerMessageInput(alert.body);

    const composerH2 = document.getElementById('composer-header-title');
    if (composerH2) composerH2.textContent = 'Edit alert';

    syncComposerUI();

    const composerCard = document.getElementById('composer-card');
    if (composerCard) {
        composerCard.scrollIntoView({ behavior: 'smooth' });
    }
}

function broadcastAgain(id) {
    const alert = activeAlerts.find(a => a.id === id) || resolvedAlerts.find(a => a.id === id);
    if (!alert) return;

    composerState = {
        editingId: null,
        type: alert.type,
        severity: alert.severity || 'Medium',
        title: alert.title.replace(' (Broadcasted)', ''),
        message: alert.body || alert.title,
        affects: alert.affects ? [...alert.affects] : ['Route A'],
        notifyCommuters: true,
        notifyDrivers: true,
        notifyAdminOnly: false,
        timing: 'now',
        scheduleTime: '',
        suspendRoute: false
    };

    showBroadcastConfirmation();
}

async function deleteAlert(id) {
    const activeAlert = activeAlerts.find(a => a.id === id);
    if (isOperationalSuspensionAlert(activeAlert)) {
        showArchiveStatusModal('Archive Blocked', 'Resolve this operational suspension before archiving it.', false);
        return;
    }

    if (deletingAlertIds.has(id)) return;
    if (!(await GoPasigUI.confirm('Archive this Service Alert? It will be removed from the Service Alerts list and retained in the Alert History Vault.'))) return;

    deletingAlertIds.add(id);
    setArchiveButtonLoading(id, true);

    try {
        const response = await fetch(`${getAlertsBaseUrl()}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });
        const data = await response.json().catch(() => ({}));
        if (response.ok && data.success) {
            showArchiveStatusModal('Alert Archived Successfully', data.message || 'Alert successfully archived.');
            refreshArchiveViewsInBackground();
        } else {
            refreshArchiveViewsInBackground();
            showArchiveStatusModal('Archive Failed', data.message || 'Failed to archive alert.', false);
        }
    } catch (error) {
        console.error("AJAX delete error:", error);
        showArchiveStatusModal('Archive Failed', 'Server error archiving alert.', false);
    } finally {
        deletingAlertIds.delete(id);
        setArchiveButtonLoading(id, false);
    }
}
// ── SCHEDULED ALERTS ACTIONS ──────────────────────────────────
function editScheduledAlert(id) {
    const alert = scheduledAlerts.find(s => s.id === id);
    if (!alert) return;

    composerState = {
        editingId: alert.id,
        type: alert.type,
        severity: alert.severity,
        title: alert.title,
        message: alert.body,
        affects: [...alert.affects],
        notifyCommuters: true,
        notifyDrivers: true,
        notifyAdminOnly: false,
        timing: 'later',
        scheduleTime: '',
        suspendRoute: Boolean(alert.suspendRoute)
    };

    const titleInput = document.getElementById('composer-title');
    const msgTextarea = document.getElementById('composer-message');
    if (titleInput) titleInput.value = alert.title;
    if (msgTextarea) msgTextarea.value = alert.body;
    onComposerTitleInput(alert.title);
    onComposerMessageInput(alert.body);

    const composerH2 = document.getElementById('composer-header-title');
    if (composerH2) composerH2.textContent = 'Edit scheduled alert';

    syncComposerUI();
}

async function cancelScheduledAlert(id) {
    if (!(await GoPasigUI.confirm('Are you sure you want to cancel this scheduled alert?'))) return;

    try {
        const response = await fetch(`${getAlertsBaseUrl()}/${id}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        if (response.ok && data.success) {
            await loadDatabaseAlertsData();
            renderScheduledAlerts();
            updateDashboardHeaderStats();
        } else {
            GoPasigUI.alert(data.message || 'Failed to cancel scheduled alert.');
        }
    } catch (error) {
        console.error("AJAX cancel scheduled alert error:", error);
        GoPasigUI.alert('Server error cancelling scheduled alert.');
    }
}

// ── MARK ALL RESOLVED LINK ───────────────────────────────────
async function markAllAlertsResolved(event) {
    if (event) event.preventDefault();
    if (activeAlerts.length === 0) return;

    if (!(await GoPasigUI.confirm('Resolve all active service alerts?'))) return;

    try {
        const response = await fetch(`${getAlertsBaseUrl()}/resolve-all`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });
        const data = await response.json();
        if (response.ok && data.success) {
            await loadDatabaseAlertsData();
            renderAlertsFeed();
            renderResolvedAlerts();
            updateDashboardHeaderStats();
        } else {
            GoPasigUI.alert(data.message || 'Failed to resolve all alerts.');
        }
    } catch (error) {
        console.error("AJAX resolve all error:", error);
        GoPasigUI.alert('Server error resolving all alerts.');
    }
}

// ── ALERT HISTORY FULL VIEW MODAL ──────────────────────────────
function toggleHistoryView(show) {
    const historyView = document.getElementById('history-full-view');
    if (!historyView) return;

    if (show) {
        historyView.classList.remove('hidden');
        renderHistoryTable();
    } else {
        historyView.classList.add('hidden');
    }
}

function setHistoryFilterSeverity(val) {
    historyFilterSeverity = val;
    historyCurrentPage = 1;
    renderHistoryTable();
}

// Global hooks for History filters in select dropdowns
function handleHistoryFilterSeverityChange(event) {
    setHistoryFilterSeverity(event.target.value);
}

function setHistoryFilterType(val) {
    historyFilterType = val;
    historyCurrentPage = 1;
    renderHistoryTable();
}

function handleHistoryFilterTypeChange(event) {
    setHistoryFilterType(event.target.value);
}

function setHistoryFilterRoute(val) {
    historyFilterRoute = val;
    historyCurrentPage = 1;
    renderHistoryTable();
}

function handleHistoryFilterRouteChange(event) {
    setHistoryFilterRoute(event.target.value);
}

function renderHistoryTable() {
    const tbody = document.getElementById('history-table-body');
    const showingCount = document.getElementById('history-showing-count');
    const paginationRow = document.getElementById('history-pagination');

    if (!tbody) return;

    let filtered = historyAlerts.filter(row => {
        if (historyFilterSeverity !== 'All' && row.severity !== historyFilterSeverity) return false;
        if (historyFilterType !== 'All' && row.type !== historyFilterType) return false;
        if (historyFilterRoute !== 'All') {
            if (historyFilterRoute === ALL_OFFICIAL_ROUTES) {
                if (!row.affects.includes(ALL_OFFICIAL_ROUTES)) return false;
            } else {
                if (!row.affects.includes(historyFilterRoute) && !row.affects.includes(ALL_OFFICIAL_ROUTES)) return false;
            }
        }
        return true;
    });

    const totalAlerts = filtered.length;
    const totalPages = Math.ceil(totalAlerts / historyRowsPerPage) || 1;
    if (historyCurrentPage > totalPages) historyCurrentPage = totalPages;

    const startIdx = (historyCurrentPage - 1) * historyRowsPerPage;
    const endIdx = Math.min(startIdx + historyRowsPerPage, totalAlerts);
    const paginatedRows = filtered.slice(startIdx, endIdx);

    if (showingCount) {
        if (totalAlerts === 0) {
            showingCount.textContent = 'Showing 0 of 0 alerts';
        } else {
            showingCount.textContent = `Showing ${startIdx + 1}–${endIdx} of ${totalAlerts} alerts`;
        }
    }

    if (paginatedRows.length === 0) {
        tbody.innerHTML = `
            <tr>
                <td colspan="8" style="text-align:center; padding: 24px; color: var(--color-text-secondary); font-size:13px;">
                    No historical logs found for the selected filters.
                </td>
            </tr>
        `;
    } else {
        tbody.innerHTML = paginatedRows.map(row => {
            const sevLower = row.severity.toLowerCase();
            
            const routePillsHtml = row.affects.includes(ALL_OFFICIAL_ROUTES)
                ? `<span class="am-route-pill-display selected-all">All official routes</span>`
                : row.affects.map(route => {
                    return `<span class="am-route-pill-display ${getRoutePillClass(route)}">${route}</span>`;
                  }).join('');

            const statusClass = row.status === 'Active' ? 'badge-emergency' : 'badge-low';
            const reachedClass = row.suspendRoute ? 'font-blue font-bold' : '';

            let sevIcon = 'ti-info-circle';
            if (row.severity === 'Emergency') sevIcon = 'ti-alert-octagon';
            else if (row.severity === 'High') sevIcon = 'ti-alert-triangle';
            else if (row.severity === 'Medium') sevIcon = 'ti-alert-circle';

            return `
                <tr class="am-table-row">
                    <td class="am-table-cell mono font-bold" style="padding: 12px 16px;">${row.dateTime}</td>
                    <td class="am-table-cell" style="padding: 12px 16px;">
                        <span class="chip-type-display"><i class="ti ti-tag"></i> ${row.type}</span>
                    </td>
                    <td class="am-table-cell" style="padding: 12px 16px;">
                        <span class="am-severity-chip badge-${sevLower}">
                            <i class="ti ${sevIcon}"></i> ${row.severity}
                        </span>
                    </td>
                    <td class="am-table-cell font-medium" style="padding: 12px 16px; max-width: 200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="${row.title}">${row.title}</td>
                    <td class="am-table-cell" style="padding: 12px 16px;">
                        <div class="am-card-route-pills" style="margin-top:0;">${routePillsHtml}</div>
                    </td>
                    <td class="am-table-cell" style="padding: 12px 16px;">${row.archivedDate}</td>
                    <td class="am-table-cell ${reachedClass}" style="padding: 12px 16px;">
                        ${row.suspendRoute ? 'Yes' : 'No'}
                    </td>
                    <td class="am-table-cell" style="padding: 12px 16px;">
                        <span class="am-severity-chip ${statusClass}">${row.status}</span>
                    </td>
                </tr>
            `;
        }).join('');
    }

    if (paginationRow) {
        let pagButtonsHtml = '';
        for (let p = 1; p <= totalPages; p++) {
            const activeClass = p === historyCurrentPage ? 'am-page-btn--active' : '';
            pagButtonsHtml += `<button class="am-page-btn ${activeClass}" onclick="setHistoryPage(${p})">${p}</button>`;
        }
        
        paginationRow.innerHTML = `
            <span class="am-count-label" style="font-size:12px; color:var(--color-text-secondary);">
                ${startIdx + 1}–${endIdx} of ${totalAlerts} alerts
            </span>
            <div class="am-page-btns">
                <button class="am-page-btn" ${historyCurrentPage === 1 ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : ''} onclick="setHistoryPage(${historyCurrentPage - 1})">‹</button>
                ${pagButtonsHtml}
                <button class="am-page-btn" ${historyCurrentPage === totalPages ? 'disabled style="opacity:0.4; cursor:not-allowed;"' : ''} onclick="setHistoryPage(${historyCurrentPage + 1})">›</button>
            </div>
        `;
    }
}

function setHistoryPage(page) {
    historyCurrentPage = page;
    renderHistoryTable();
}

function exportHistoryCSV() {
    if (!historyAlerts || historyAlerts.length === 0) {
        GoPasigUI.alert('No service alert records available to export.');
        return;
    }
    const headers = ['Original Alert Date', 'Type', 'Severity', 'Title', 'Affected Routes', 'Archived Date', 'Operational Suspension', 'Final Status'];
    const rows = historyAlerts.map(a => [
        a.dateTime,
        a.type,
        a.severity,
        a.title,
        Array.isArray(a.affects) ? a.affects.join(', ') : (a.affects || ALL_OFFICIAL_ROUTES),
        a.sentBy,
        a.reached,
        a.status
    ]);
    const csv = [headers, ...rows].map(r => r.map(v => `"${String(v).replace(/"/g, '""')}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = 'gopasig-service-alerts-history.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// ── UTILS ─────────────────────────────────────────────────────
function format12Hour(time24) {
    if (!time24) return '';
    const parts = time24.split(':');
    let hour = parseInt(parts[0]);
    const min = parts[1];
    const ampm = hour >= 12 ? 'PM' : 'AM';
    hour = hour % 12;
    hour = hour ? hour : 12;
    return `${hour}:${min} ${ampm}`;
}





