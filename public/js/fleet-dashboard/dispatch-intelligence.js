/**
 * GoPasig Fleet Ops - Dispatch Intelligence Javascript Controller
 * Handles ECharts, live simulation tick requests, threshold updates, ML phase selection, and dynamic UI updates.
 */

// Window Configuration Setup
window.FleetDispatchConfig = {
    dataUrl: '/fleet/api/dispatch-data',
    saveThresholdUrl: '/fleet/api/dispatch-save-threshold',
    addCommuterUrl: '/fleet/api/dispatch-add-commuter',
    addManualUrl: '/fleet/api/dispatch-add-manual',
    simulateSpurtUrl: '/fleet/api/dispatch-simulate-spurt',
    clearUrl: '/fleet/api/dispatch-clear-simulator',
    dispatchUrl: '/fleet/api/dispatch-now',
    csrfToken: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
};

// Global State
let selectedPhase = 1;
let simulatedDay = 'Monday';
let simulatedTimeSlot = '06:00-08:00';
let selectedRouteId = 1;
let notifiedRoutes = {};
const dispatchVariantSelections = new Map();

async function fetchDispatchData() {
    selectedPhase = document.querySelector('[data-active-phase]')?.getAttribute('data-active-phase') || 1;
    simulatedDay = document.getElementById('simulatedDay')?.value || 'Monday';
    simulatedTimeSlot = document.getElementById('simulatedTimeSlot')?.value || '06:00-08:00';
    selectedRouteId = document.getElementById('selectedRouteId')?.value || 1;

    try {
        const queryParams = new URLSearchParams({
            phase: selectedPhase,
            day: simulatedDay,
            time_slot: simulatedTimeSlot,
            route_id: selectedRouteId
        });

        const response = await fetch(`${window.FleetDispatchConfig.dataUrl}?${queryParams.toString()}`);
        if (!response.ok) throw new Error('Failed to fetch dispatch intelligence data');
        const data = await response.json();

        updateAlertsFeedDOM(data.activeAlerts);
        updateDemandBoardDOM(data.routesData);
        updateRecentDispatchesDOM(data.recentDispatches);
        updateMlAccuracyTrackerDOM(data.historicalPatterns);

        // Update custom threshold override value if route settings matched
        const customInput = document.getElementById('customThreshold');
        if (customInput && !customInput.matches(':focus')) {
            customInput.value = data.customThreshold;
        }
    } catch (error) {
        console.error('Error loading dispatch intelligence data:', error);
    }
}

function updateAlertsFeedDOM(alerts) {
    const feed = document.getElementById('dispatch-alerts-feed');
    const countBadge = document.getElementById('dispatch-alerts-count');
    if (!feed) return;

    countBadge.innerText = `${alerts.length} Alerts`;
    feed.innerHTML = '';

    if (alerts.length === 0) {
        feed.innerHTML = `
            <div class="h-[200px] flex flex-col items-center justify-center text-center space-y-3">
                <div class="h-12 w-12 rounded-full bg-[#F3F9EA] text-[#639922] flex items-center justify-center shadow-inner">
                    <i class="ti ti-circle-check text-2xl"></i>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-800">Operational Stability Achieved</p>
                    <p class="text-[11.5px] text-slate-400 font-semibold mt-0.5">No critical passenger surges or threshold overrides active.</p>
                </div>
            </div>
        `;
        return;
    }

    alerts.forEach(alert => {
        const isHigh = alert.severity === 'High';
        const alertBg = isHigh ? 'bg-[#FCEBEB]/60 border-[#F5C2C2]' : 'bg-[#FAEEDA]/60 border-[#F5E1C2]';
        const alertText = isHigh ? 'text-[#A32D2D]' : 'text-[#854F0B]';
        const iconColor = isHigh ? 'text-[#E24B4A]' : 'text-[#BA7517]';
        const badgeColor = isHigh ? 'bg-[#FCEBEB] text-[#A32D2D]' : 'bg-[#FAEEDA] text-[#854F0B]';
        
        const icon = alert.type === 'reactive' 
            ? `<i class="ti ti-alert-triangle text-xl ${iconColor} animate-pulse"></i>` 
            : `<i class="ti ti-bolt text-xl ${iconColor}"></i>`;

        const div = document.createElement('div');
        div.className = `p-4 rounded-xl border flex gap-3.5 ${alertBg} transition-all duration-200 shadow-sm`;
        div.innerHTML = `
            <div class="shrink-0 pt-0.5">${icon}</div>
            <div class="flex-grow space-y-1">
                <div class="flex items-center justify-between">
                    <h4 class="text-xs font-black uppercase tracking-wider ${alertText}">${alert.title}</h4>
                    <span class="inline-flex rounded px-1.5 py-0.2 text-[8px] font-black uppercase tracking-wide ${badgeColor}">
                        ${alert.severity}
                    </span>
                </div>
                <p class="text-[12.5px] text-slate-700 font-semibold leading-relaxed">${alert.message}</p>
                <div class="pt-2">
                    <button onclick="dispatchNowAction(${alert.route_id}, ${alert.route_variant_id || 'null'})" class="h-7 px-3 text-[10px] font-black text-white bg-[#003F87] hover:bg-[#002D62] rounded-lg transition uppercase tracking-wider">
                        Dispatch Bus Now
                    </button>
                </div>
            </div>
        `;
        feed.appendChild(div);
    });
}

function dispatchVariantSelectMarkup(route) {
    const variants = Array.isArray(route.variants) ? route.variants : [];
    if (variants.length === 0) return '';

    const selectedVariantId = dispatchVariantSelections.get(String(route.id)) || '';
    const usableCount = variants.filter(v => v.usable_for_dispatch).length;
    const options = [`<option value="">${usableCount > 1 ? 'Choose direction...' : 'Use default direction'}</option>`]
        .concat(variants.map(v => {
            const selected = String(v.id) === String(selectedVariantId) ? 'selected' : '';
            const disabled = v.usable_for_dispatch ? '' : 'disabled';

            return `<option value="${v.id}" ${selected} ${disabled}>${v.label} - ${v.waiting_count || 0} waiting${v.usable_for_dispatch ? '' : ' (' + v.geometry_status + ')'}</option>`;
        }));

    return `<select id="dispatch-variant-${route.id}" data-dispatch-variant-route="${route.id}" class="w-full h-8 rounded-lg border border-slate-200 bg-slate-50 px-2 text-[10px] font-bold text-slate-700 outline-none focus:border-[#003F87]">${options.join('')}</select>`;
}

function directionDemandMarkup(route) {
    const variants = Array.isArray(route.variants) ? route.variants : [];
    const directionCells = variants.map(variant => `
        <div class="min-w-0">
            <span class="text-[9px] text-slate-400 font-bold block uppercase tracking-wider truncate">${variant.direction}</span>
            <span class="text-sm font-bold text-slate-800 font-mono">${variant.waiting_count || 0}</span>
        </div>
    `).join('');
    const unresolved = Number(route.unresolved_waiting_count || 0);
    const unresolvedMarkup = unresolved > 0 ? `
        <div class="mt-2 rounded-lg border border-amber-200 bg-amber-50 px-2 py-1.5 text-[10px] font-bold text-amber-800">
            ${unresolved} legacy waiting ${unresolved === 1 ? 'journey is' : 'journeys are'} unresolved and excluded.
        </div>
    ` : '';

    return `
        <div class="bg-slate-50 border border-slate-100 p-2 rounded-xl">
            <div class="grid gap-2 text-center" style="grid-template-columns: repeat(${Math.max(1, variants.length)}, minmax(0, 1fr));">
                ${directionCells || '<span class="text-[10px] text-slate-400 font-bold">No route directions configured</span>'}
            </div>
            <div class="mt-2 pt-2 border-t border-slate-200 flex justify-between text-[9px] font-bold text-slate-500 uppercase tracking-wide">
                <span>Resolved total: ${route.total || 0}</span>
                <span>Simulator: ${route.manual_count || 0}</span>
            </div>
            ${unresolvedMarkup}
        </div>
    `;
}

function selectedDispatchVariantId(routeId, explicitVariantId = null) {
    if (explicitVariantId) return explicitVariantId;
    const select = document.getElementById(`dispatch-variant-${routeId}`);
    const selectedVariantId = select && select.value
        ? select.value
        : dispatchVariantSelections.get(String(routeId));

    return selectedVariantId || null;
}

function captureDispatchVariantSelections(container) {
    container.querySelectorAll('[data-dispatch-variant-route], select[id^="dispatch-variant-"]').forEach(select => {
        const routeId = select.dataset.dispatchVariantRoute || select.id.replace('dispatch-variant-', '');

        if (select.value) {
            dispatchVariantSelections.set(String(routeId), select.value);
        } else {
            dispatchVariantSelections.delete(String(routeId));
        }
    });
}
function updateDemandBoardDOM(routesData) {
    const container = document.getElementById('demand-board-grid');
    if (!container) return;

    captureDispatchVariantSelections(container);
    container.innerHTML = '';
    routesData.forEach(r => {
        let borderClass = 'border-slate-200 border-t-[4px] border-t-[#003F87]';
        let badgeClass = 'bg-[#EAF3DE] text-[#3B6D11]';
        let badgeText = 'Normal';
        let progressBarColor = 'bg-[#003F87]';

        if (r.status === 'red') {
            borderClass = 'border-[#E24B4A] border-t-[4px]';
            badgeClass = 'bg-[#FCEBEB] text-[#A32D2D]';
            badgeText = 'Dispatch Now';
            progressBarColor = 'bg-[#E24B4A]';

            // Trigger push notification once
            if ('Notification' in window && Notification.permission === 'granted' && !notifiedRoutes[r.id]) {
                const criticalVariant = r.critical_variant || {};
                let msg = `URGENT: ${criticalVariant.waiting_count || 0} commuters waiting sa ${r.name} ${criticalVariant.direction || ''}.`;
                if (r.suggested_bus) {
                    msg += ` Suggested Bus: ${r.suggested_bus.plate_number} (${r.suggested_bus.distance_km} km away).`;
                }
                new Notification("Critical Passenger Surge", {
                    body: msg,
                });
                notifiedRoutes[r.id] = true;
            }
        } else if (r.status === 'yellow') {
            borderClass = 'border-[#BA7517] border-t-[4px]';
            badgeClass = 'bg-[#FAEEDA] text-[#854F0B]';
            badgeText = 'Standby (High)';
            progressBarColor = 'bg-[#BA7517]';
            delete notifiedRoutes[r.id];
        } else {
            delete notifiedRoutes[r.id];
        }

        const loadPercent = r.threshold > 0 ? Math.min(100, Math.round(((r.max_direction_waiting_count || 0) / r.threshold) * 100)) : 0;
        
        let predictionMarkup = '';
        if (selectedPhase >= 2) {
            predictionMarkup = `
                <div class="p-2 bg-[#E6F1FB] border border-[#003F87]/15 rounded-xl text-[11px] text-[#0C447C] font-semibold flex items-center justify-between">
                    <span>Expected Peak:</span>
                    <strong class="font-mono">${r.historical_avg} pax</strong>
                </div>
            `;
        }

        let suggestedBusMarkup = '';
        const variantSelectMarkup = dispatchVariantSelectMarkup(r);
        const demandMarkup = directionDemandMarkup(r);
        if (r.suggested_bus) {
            suggestedBusMarkup = `
                <div class="p-2 bg-[#FAEEDA] border border-[#BA7517]/20 rounded-xl text-[11px] text-[#854F0B] font-semibold flex flex-col gap-1">
                    <span class="text-[9.5px] font-extrabold text-[#BA7517] uppercase tracking-wide">Suggested Rescue Bus:</span>
                    <div class="flex justify-between items-center">
                        <span class="font-mono font-bold">${r.suggested_bus.plate_number}</span>
                        <span class="text-[10px] opacity-90">${r.suggested_bus.distance_km} km away</span>
                    </div>
                </div>
            `;
        }

        const card = document.createElement('div');
        card.className = `bg-white border rounded-2xl p-4 shadow-sm flex flex-col justify-between space-y-4 transition hover:shadow-md ${borderClass}`;
        card.innerHTML = `
            <div class="space-y-1">
                <div class="flex justify-between items-start">
                    <h4 class="text-sm font-extrabold text-[#001F44]">${r.name || `Route ${r.id}`}</h4>
                    <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider ${badgeClass}">${badgeText}</span>
                </div>
                <p class="text-[11px] text-slate-400 font-semibold leading-tight line-clamp-2 h-[32px]">${r.description}</p>
            </div>

            ${demandMarkup}

            <div class="space-y-1">
                <div class="flex justify-between text-[11px] font-semibold text-slate-500">
                    <span>Waiting Load: ${loadPercent}%</span>
                    <span>Threshold: ${r.threshold} pax</span>
                </div>
                <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden border border-black/5 shadow-inner">
                    <div class="h-full rounded-full ${progressBarColor} transition-all" style="width: ${loadPercent}%"></div>
                </div>
            </div>

            ${predictionMarkup}
            ${suggestedBusMarkup}
            ${variantSelectMarkup}

            <button onclick="dispatchNowAction(${r.id})" class="w-full h-9 flex items-center justify-center gap-1 bg-[#003F87] hover:bg-[#002D62] text-white text-xs font-extrabold uppercase tracking-wider rounded-xl transition shadow-sm cursor-pointer">
                <i class="ti ti-bus-stop text-base"></i>
                <span>Dispatch Bus</span>
            </button>
        `;
        container.appendChild(card);
    });
}

function updateRecentDispatchesDOM(recentDispatches) {
    const list = document.getElementById('recent-dispatches-list');
    if (!list) return;

    list.innerHTML = '';
    if (recentDispatches.length === 0) {
        list.innerHTML = `
            <div class="py-12 text-center text-slate-400 text-xs font-bold">
                No dispatch actions recorded in this session.
            </div>
        `;
        return;
    }

    // ISSUE-035 FIX: Use the DB-sourced route_color from the API response instead of
    // a hardcoded local palette. Color is now set in the controller (DispatchIntelligenceController).
    recentDispatches.forEach(log => {
        const routeColor = log.route_color || '#888780';
        const div = document.createElement('div');
        div.className = 'relative py-1 group flex flex-col gap-1 transition-all rounded hover:bg-slate-50 p-2 border border-transparent hover:border-slate-100';
        div.innerHTML = `
            <span class="absolute h-2.5 w-2.5 rounded-full border-2 border-white shadow-sm -left-[22px] top-4 z-10" style="background-color: ${routeColor}"></span>
            <div class="flex items-center justify-between">
                <span class="text-xs font-black text-[#001F44] truncate">Route ${log.route_id} (${log.route_name})</span>
                <span class="text-[10px] text-slate-400 font-bold font-mono">${log.time_diff}</span>
            </div>
            <div class="text-[11px] text-slate-500 font-semibold space-y-0.5">
                <div>Bus: <strong class="text-slate-700 font-mono">${log.bus_plate}</strong> Ãƒâ€šÃ‚Â· Driver: <strong class="text-slate-700">${log.driver_name}</strong></div>
                <div class="italic text-[10px] opacity-90 mt-0.5">${log.notes}</div>
            </div>
        `;
        list.appendChild(div);
    });
}

function updateMlAccuracyTrackerDOM(patterns) {
    const container = document.getElementById('accuracy-or-patterns-container');
    if (!container) return;

    if (selectedPhase == 3) {
        // ISSUE-034 FIX: The previous implementation used Math.random() to fabricate ML accuracy
        // data and showed a hardcoded '96.4% Acc' badge ÃƒÂ¢Ã¢â€šÂ¬Ã¢â‚¬Â all fake. Until a real ML model is
        // integrated, show an honest empty state instead of misleading mock data.
        container.innerHTML = `
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-chart-bar text-lg text-[#003F87]"></i>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-tight">ML Model Accuracy Tracker</h3>
                    </div>
                    <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase bg-slate-100 text-slate-500">Not Available</span>
                </div>
                <div class="py-10 flex flex-col items-center justify-center text-center space-y-3">
                    <div class="h-12 w-12 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center">
                        <i class="ti ti-brain text-2xl"></i>
                    </div>
                    <div>
                        <p class="text-xs font-bold text-slate-700">ML Model Not Yet Integrated</p>
                        <p class="text-[11px] text-slate-400 font-semibold mt-1 max-w-xs">Real-time accuracy tracking will appear here once a trained model is connected to the dispatch pipeline.</p>
                    </div>
                </div>
            </div>
        `;
    } else {
        container.innerHTML = `
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-history text-lg text-[#003F87]"></i>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Recorded Peak Demand Patterns</h3>
                    </div>
                </div>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3" id="peak-demand-boxes"></div>
            </div>
        `;

        const boxesContainer = document.getElementById('peak-demand-boxes');
        patterns.slice(0, 4).forEach(p => {
            const box = document.createElement('div');
            box.className = 'p-3 bg-slate-50 border border-slate-100 rounded-xl text-center space-y-1';
            box.innerHTML = `
                <span class="text-[10px] text-slate-400 font-extrabold uppercase block tracking-wider">${p.day_of_week}</span>
                <span class="text-[11px] font-bold text-slate-700 block truncate">Route ${p.route_id}</span>
                <strong class="text-lg font-black text-[#003F87] font-mono block">${p.total_commuters} pax</strong>
                <span class="text-[9px] text-slate-400 font-bold block">${p.time_slot}</span>
            `;
            boxesContainer.appendChild(box);
        });
    }
}

// SIMULATOR ACTIONS
async function addCommuterAction(routeId) {
    try {
        const response = await fetch(window.FleetDispatchConfig.addCommuterUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetDispatchConfig.csrfToken
            },
            body: JSON.stringify({ route_id: routeId })
        });
        if (response.ok) fetchDispatchData();
    } catch (e) {
        console.error('Simulator app commuter error:', e);
    }
}

async function addManualTickerAction(routeId) {
    try {
        const response = await fetch(window.FleetDispatchConfig.addManualUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetDispatchConfig.csrfToken
            },
            body: JSON.stringify({ route_id: routeId })
        });
        if (response.ok) fetchDispatchData();
    } catch (e) {
        console.error('Simulator manual ticker error:', e);
    }
}

async function simulateRushSpurtAction(routeId) {
    try {
        const response = await fetch(window.FleetDispatchConfig.simulateSpurtUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetDispatchConfig.csrfToken
            },
            body: JSON.stringify({
                route_id: routeId,
                day: simulatedDay,
                time_slot: simulatedTimeSlot
            })
        });
        if (response.ok) fetchDispatchData();
    } catch (e) {
        console.error('Simulator spurt error:', e);
    }
}

async function clearSimulatorDataAction() {
    try {
        const response = await fetch(window.FleetDispatchConfig.clearUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetDispatchConfig.csrfToken
            }
        });
        const data = await response.json();
        if (response.ok && data.success) {
            showDispatchNotification(data.message);
            fetchDispatchData();
        }
    } catch (e) {
        console.error('Clear simulator error:', e);
    }
}

// SAVE THRESHOLD
async function saveThresholdAction(event) {
    event.preventDefault();
    const routeId = document.getElementById('selectedRouteId').value;
    const thresholdVal = document.getElementById('customThreshold').value;

    try {
        const response = await fetch(window.FleetDispatchConfig.saveThresholdUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetDispatchConfig.csrfToken
            },
            body: JSON.stringify({
                route_id: routeId,
                day: simulatedDay,
                time_slot: simulatedTimeSlot,
                threshold: thresholdVal
            })
        });

        const data = await response.json();
        if (response.ok && data.success) {
            showDispatchNotification(data.message);
            fetchDispatchData();
        } else {
            showDispatchNotification(data.message || 'Failed to update threshold.', true);
        }
    } catch (e) {
        console.error('Save threshold override error:', e);
    }
}

// DISPATCH NOW ACTION
async function dispatchNowAction(routeId, routeVariantId = null) {
    try {
        const selectedRouteVariantId = selectedDispatchVariantId(routeId, routeVariantId);
        if (!selectedRouteVariantId) {
            showDispatchNotification('Select a direction for this route before dispatching.', true);
            return;
        }

        const payload = { route_id: routeId, phase: selectedPhase };
        payload.route_variant_id = selectedRouteVariantId;

        const response = await fetch(window.FleetDispatchConfig.dispatchUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': window.FleetDispatchConfig.csrfToken
            },
            body: JSON.stringify(payload)
        });

        const data = await response.json();
        if (response.ok && data.success) {
            dispatchVariantSelections.delete(String(routeId));
            showDispatchNotification(data.message);
            fetchDispatchData();
        } else {
            showDispatchNotification(data.message || 'Dispatch error.', true);
        }
    } catch (e) {
        console.error('Dispatch error:', e);
        showDispatchNotification('Failed to dispatch unit.', true);
    }
}

// Alerts helper
function showDispatchNotification(message, isError = false) {
    const alertSuccess = document.getElementById('dispatch-alert-success');
    const alertError = document.getElementById('dispatch-alert-error');
    if (!alertSuccess || !alertError) return;

    if (isError) {
        alertError.querySelector('span').innerText = message;
        alertError.classList.remove('hidden');
        alertSuccess.classList.add('hidden');
        setTimeout(() => alertError.classList.add('hidden'), 5000);
    } else {
        alertSuccess.querySelector('span').innerText = message;
        alertSuccess.classList.remove('hidden');
        alertError.classList.add('hidden');
        setTimeout(() => alertSuccess.classList.add('hidden'), 5000);
    }
}

// Document ready and events
let fleetDispatchModuleInitialized = false;
let fleetDispatchPollingId = null;

function initFleetDispatchModule() {
    if (fleetDispatchModuleInitialized || !document.getElementById('demand-board-grid')) return;
    fleetDispatchModuleInitialized = true;


        document.getElementById('demand-board-grid')?.addEventListener('change', event => {
            const select = event.target.closest('[data-dispatch-variant-route], select[id^="dispatch-variant-"]');
            if (!select) return;

            const routeId = select.dataset.dispatchVariantRoute || select.id.replace('dispatch-variant-', '');
            if (select.value) {
                dispatchVariantSelections.set(String(routeId), select.value);
            } else {
                dispatchVariantSelections.delete(String(routeId));
            }
        });

        // Phase selectors clicks
        document.querySelectorAll('.flex.items-center.bg-slate-100 button').forEach((btn, idx) => {
            btn.addEventListener('click', (e) => {
                btn.parentNode.querySelectorAll('button').forEach(b => {
                    b.className = 'px-4 py-2 rounded-lg text-xs font-extrabold uppercase tracking-wider transition-all text-slate-500 hover:text-slate-800';
                });
                btn.className = 'px-4 py-2 rounded-lg text-xs font-extrabold uppercase tracking-wider transition-all bg-[#003F87] text-white shadow-sm';
                
                const activePhase = idx + 1;
                btn.parentNode.setAttribute('data-active-phase', activePhase);
                
                fetchDispatchData();
            });
        });

        // Dropdown changes trigger fetch
        ['simulatedDay', 'simulatedTimeSlot', 'selectedRouteId'].forEach(id => {
            document.getElementById(id)?.addEventListener('change', fetchDispatchData);
        });

        // Threshold Form
        document.getElementById('threshold-override-form')?.addEventListener('submit', saveThresholdAction);

        // Request browser push notification permission
        if ('Notification' in window && Notification.permission === 'default') {
            Notification.requestPermission();
        }

        // Fetch initially
        fetchDispatchData();

        // Polling loop
        if (!fleetDispatchPollingId) {
            fleetDispatchPollingId = setInterval(fetchDispatchData, 10000);
        }
}

window.initFleetDispatchModule = initFleetDispatchModule;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initFleetDispatchModule, { once: true });
} else {
    initFleetDispatchModule();
}
