/* ============================================================
   GoPasig Admin — Schedule & Routes Management
   routes.js
   ============================================================ */

// ── SAMPLE DATASETS ──────────────────────────────────────────

// Active date initialized dynamically to today
let currentActiveDate = new Date();

// Grid configuration
const GRID_START_HOUR = 5;  // 5 AM
const GRID_END_HOUR = 22;   // 10 PM

// Average route durations (in minutes)
const ROUTE_DURATIONS = {
    1: 25, // SPED to Temp Pasig City Hall (P2P)
    2: 45, // SPED to Ligaya via PCGH
    3: 35, // SPED to One San Miguel Ave via Shaw
    4: 40  // SPED to Nagpayong via Urbano Velasco
};

// Route structures (synced dynamically with database)
let routesData = [];
let stopsData = {};



// Helper: Retrieve CSRF Token from Meta tag or Config
function getCsrfToken() {
    if (window.GoPasigConfig && window.GoPasigConfig.csrfToken) {
        return window.GoPasigConfig.csrfToken;
    }
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

// Dynamic loader from MySQL Database API
async function loadDatabaseSchedulesData() {
    try {
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.schedulesBaseUrl) ? window.GoPasigConfig.schedulesBaseUrl : '/admin/api/schedules';
        const response = await fetch(baseUrl);
        const data = await response.json();
        
        if (response.ok && data.success) {
            schedulesData = data.schedules;
            console.log("MySQL Database schedules records loaded dynamically!", schedulesData.length);
        } else {
            console.error("Backend error during schedules fetch:", data);
        }
    } catch (error) {
        console.error("Failed to load dynamic database schedules data:", error);
    }
}

// Schedules database (aligned to operational hours!)
let schedulesData = [];

// Conflicts List
let conflictsList = [];

// Global State
let activeRoutesTab = 'schedule';
let selectedRouteId = '1';
let isWeekViewActive = true;
let isSuspendedRoutesShown = false;
let currentEditingScheduleId = null;
let currentResolvingConflictId = null;

// Extends global DRIVERS_DATA if initialized. Adding Mario Gomez if not exists.
let driversList = [];

function normalizeRouteStatus(status) {
    if (!status) return 'Active';
    return status.toString().toLowerCase() === 'suspended' ? 'Suspended' : 'Active';
}

// ── SYNC WITH DATABASE ───────────────────────────────────────
function syncRoutesWithDatabase() {
    if (typeof routesDataDb === 'undefined' || routesDataDb.length === 0) return;

    routesData = routesDataDb.map(route => {
        const idStr = route.id.toString();
        const color = routeColors[idStr] || '#003F87';
        const status = normalizeRouteStatus(route.status);
        
        // Find assigned buses from fleetData
        const assigned = fleetData.filter(b => b.route === idStr).map(b => b.plate);
        
        // Calculate some statistics or keep seeded values
        let avgPax = 120;
        let distance = '8.0 km';
        let busiestStop = 'SPED Terminal';
        let peakHours = 'Rush Hours (05:30-09:00 AM, 03:00-06:30 PM)';
        
        if (route.id == 1) {
            avgPax = 145;
            distance = '6.2 km';
            busiestStop = 'SPED Terminal';
            peakHours = 'All Day (Mon-Fri)';
        } else if (route.id == 2) {
            avgPax = 165;
            distance = '10.5 km';
            busiestStop = 'PCGH (Maybunga)';
        } else if (route.id == 3) {
            avgPax = 110;
            distance = '8.4 km';
            busiestStop = 'Shaw Blvd.';
        } else if (route.id == 4) {
            avgPax = 125;
            distance = '9.8 km';
            busiestStop = 'Nagpayong';
        }

        // Generate matching styled bg, text colors based on color
        return {
            id: idStr,
            name: route.name,
            endpoints: route.description || route.name,
            status: status,
            stopsCount: route.stops ? route.stops.length : 0,
            distance: distance,
            busesCount: assigned.length,
            avgPax: avgPax,
            peakHours: peakHours,
            busiestStop: busiestStop,
            color: color,
            bg: route.id == 1 ? '#E6F1FB' : (route.id == 2 ? '#EAF3DE' : (route.id == 3 ? '#FAEEDA' : '#FCEBEB')),
            textColor: route.id == 1 ? '#0C447C' : (route.id == 2 ? '#3B6D11' : (route.id == 3 ? '#854F0B' : '#A32D2D')),
            opacityBg: route.id == 1 ? 'rgba(0, 63, 135, 0.10)' : (route.id == 2 ? 'rgba(59, 109, 17, 0.10)' : (route.id == 3 ? 'rgba(133, 79, 11, 0.10)' : 'rgba(226, 75, 74, 0.10)')),
            assignedBuses: assigned
        };
    });

    // Populate stopsData from the database stop arrays
    stopsData = {};
    routesDataDb.forEach(route => {
        const idStr = route.id.toString();
        if (route.stops && route.stops.length > 0) {
            stopsData[idStr] = route.stops.map((stop, index) => {
                let landmark = 'stop';
                if (index === 0) landmark = 'origin';
                else if (index === route.stops.length - 1) landmark = 'terminus';

                return {
                    id: stop.id,
                    name: stop.name,
                    landmark: landmark,
                    boarding: 15 + index * 5,
                    alighting: 10 + index * 5,
                    dwell: '45s',
                    status: index === 0 ? 'served' : (index === route.stops.length - 1 ? 'terminus' : 'current'),
                    lat: parseFloat(stop.lat),
                    lng: parseFloat(stop.lng)
                };
            });
        } else {
            stopsData[idStr] = [];
        }
    });
}

// ── INITIALIZER ──────────────────────────────────────────────
async function initRoutesDashboard() {
    // Set initial date label dynamically
    const dateLabel = document.getElementById('rm-schedule-date-label');
    if (dateLabel) {
        const options = { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' };
        dateLabel.textContent = currentActiveDate.toLocaleDateString('en-US', options);
    }

    if (!isDatabaseDataLoaded && typeof loadDatabaseFleetData === 'function') {
        await loadDatabaseFleetData();
    }
    syncRoutesWithDatabase();
    setupDriversPool();
    await loadDatabaseSchedulesData();
    reScanConflicts();
    switchRoutesTab(activeRoutesTab);
    renderRoutesTab();
}

function setupDriversPool() {
    if (typeof DRIVERS_DATA !== 'undefined' && DRIVERS_DATA.length > 0) {
        driversList = JSON.parse(JSON.stringify(DRIVERS_DATA));
    } else {
        driversList = [];
    }
}

// ── TAB MANAGEMENT ───────────────────────────────────────────
function switchRoutesTab(tab) {
    activeRoutesTab = tab;
    
    const scheduleBtn = document.getElementById('rm-tab-btn-schedule');
    const stopsBtn = document.getElementById('rm-tab-btn-stops');
    const schedulePanel = document.getElementById('rm-panel-schedule');
    const stopsPanel = document.getElementById('rm-panel-stops');

    if (tab === 'schedule') {
        scheduleBtn.classList.add('active');
        stopsBtn.classList.remove('active');
        schedulePanel.classList.remove('hidden');
        stopsPanel.classList.add('hidden');
        renderScheduleGrid();
        renderScheduleRouteMap();
        renderUpcomingTrips();
    } else {
        scheduleBtn.classList.remove('active');
        stopsBtn.classList.add('active');
        schedulePanel.classList.add('hidden');
        stopsPanel.classList.remove('hidden');
        renderRoutesTab();

        // Invalidate size of route preview map to avoid Leaflet rendering bugs in unhidden div
        if (routePreviewMapInstance !== null) {
            setTimeout(() => {
                routePreviewMapInstance.invalidateSize();
            }, 100);
        }
    }
}

// ── DATE CALCULATIONS ─────────────────────────────────────────
function adjustDate(days) {
    currentActiveDate.setDate(currentActiveDate.getDate() + days);
    const dateLabel = document.getElementById('rm-schedule-date-label');
    if (dateLabel) {
        const options = { weekday: 'long', month: 'short', day: 'numeric', year: 'numeric' };
        dateLabel.textContent = currentActiveDate.toLocaleDateString('en-US', options);
    }
    // Re-render
    renderScheduleGrid();
    renderUpcomingTrips();
}

function toggleWeekView() {
    isWeekViewActive = !isWeekViewActive;
    const btn = document.getElementById('btn-week-view');
    if (btn) {
        btn.classList.toggle('active', isWeekViewActive);
    }
}

// ── SCHEDULE GRID RENDERING ──────────────────────────────────
function renderScheduleGrid() {
    const gridContainer = document.getElementById('rm-schedule-grid-container');
    if (!gridContainer) return;

    // Clear old layout
    gridContainer.innerHTML = '';

    // Calculate current hour column
    const now = new Date();
    const currentHour = now.getHours(); // dynamic current hour

    // Draw Header Row
    // First cell (top-left empty corner)
    const emptyCorner = document.createElement('div');
    emptyCorner.className = 'rm-grid-hdr-empty';
    gridContainer.appendChild(emptyCorner);

    // 18 time header label cells
    for (let hour = GRID_START_HOUR; hour <= GRID_END_HOUR; hour++) {
        const cell = document.createElement('div');
        cell.className = 'rm-grid-hdr';
        
        // Label format (e.g. 5 AM, 12 PM, 1 PM)
        let label = '';
        if (hour < 12) label = `${hour} AM`;
        else if (hour === 12) label = '12 PM';
        else label = `${hour - 12} PM`;
        
        cell.textContent = label;

        // Peak hour highlights (7 AM, 8 AM, 5 PM, 6 PM)
        if (hour === 7 || hour === 8 || hour === 17 || hour === 18) {
            cell.classList.add('peak');
        }

        // Current hour highlight
        if (hour === currentHour) {
            cell.classList.add('current-col');
        }

        gridContainer.appendChild(cell);
    }

    // Draw Route rows (A, B, C)
    routesData.forEach(route => {
        // Left column route label cell
        const labelCell = document.createElement('div');
        labelCell.className = `rm-grid-label-cell rm-grid-label-${route.id.toLowerCase()}`;
        labelCell.innerHTML = `<strong>${route.name}</strong>`;
        gridContainer.appendChild(labelCell);

        // 18 time slot cells
        for (let hour = GRID_START_HOUR; hour <= GRID_END_HOUR; hour++) {
            const cell = document.createElement('div');
            cell.className = 'rm-grid-cell';

            if (hour === currentHour) {
                cell.classList.add('current-col');
            }

            // Click empty cell to trigger Create Schedule Modal pre-filled
            cell.onclick = (e) => {
                // If clicking an existing block inside the cell, prevent opening a new create schedule
                if (e.target.closest('.rm-trip-block')) return;
                const timeStr = `${hour.toString().padStart(2, '0')}:00`;
                openScheduleModal('create', route.id, timeStr);
            };

            // Find trips for this route starting during this hour (e.g. 07:00 to 07:59)
            const hourTrips = schedulesData.filter(s => {
                if (s.routeId !== route.id) return false;
                const sHour = parseInt(s.time.split(':')[0]);
                return sHour === hour;
            });

            // Sort trips by minute
            hourTrips.sort((x, y) => {
                const minX = parseInt(x.time.split(':')[1]);
                const minY = parseInt(y.time.split(':')[1]);
                return minX - minY;
            });

            // Render trips in the cell
            hourTrips.forEach(trip => {
                const tripBlock = document.createElement('div');
                const routeClass = `rm-trip-${route.id.toLowerCase()}`;
                tripBlock.className = `rm-trip-block ${routeClass}`;
                
                // Add conflict styling if there is a conflict
                const hasConflict = conflictsList.some(c => c.affectedIds.includes(trip.id) && c.severity === 'High');
                if (hasConflict) {
                    tripBlock.classList.add('rm-trip-conflict');
                }

                // Initials and initials prefix
                let prefixIcon = '';
                if (hasConflict) {
                    prefixIcon = '<i class="ti ti-alert-circle" style="color:#A32D2D;font-size:11px;margin-right:2px;"></i>';
                }

                // Capacity Chip
                let capChipHtml = '';
                if (trip.pax >= 45) {
                    capChipHtml = '<span class="rm-cap-tiny full">Full</span>';
                } else if (trip.pax >= 36) {
                    capChipHtml = '<span class="rm-cap-tiny near-full">Near full</span>';
                }

                tripBlock.innerHTML = `
                    <div class="rm-trip-line1">${prefixIcon}${trip.driver} · ${trip.bus}</div>
                    <div class="rm-trip-line2">
                        <span>${trip.pax} pax</span>
                        ${capChipHtml}
                    </div>
                `;

                // Set up edit details on click
                tripBlock.onclick = (e) => {
                    e.stopPropagation();
                    openScheduleModal('edit', route.id, trip.time, trip.id);
                };

                // Add Hover Tooltip
                const tooltip = document.createElement('div');
                tooltip.className = 'rm-tooltip';

                // Find full conflict details if any
                const tripConflict = conflictsList.find(c => c.affectedIds.includes(trip.id));
                let conflictMsgHtml = '';
                if (tripConflict) {
                    conflictMsgHtml = `<div class="rm-tt-conflict-msg">Conflict: ${tripConflict.description}</div>`;
                }

                // Compute arrival time based on avg route duration
                const duration = ROUTE_DURATIONS[route.id];
                const depParts = trip.time.split(':');
                const depMin = parseInt(depParts[0]) * 60 + parseInt(depParts[1]);
                const arrMin = depMin + duration;
                const arrHour = Math.floor(arrMin / 60) % 24;
                const arrMinute = arrMin % 60;
                const arrTimeStr = `${arrHour.toString().padStart(2, '0')}:${arrMinute.toString().padStart(2, '0')}`;

                const depFormatted = format12Hour(trip.time);
                const arrFormatted = format12Hour(arrTimeStr);

                tooltip.innerHTML = `
                    <div class="rm-tt-row"><span class="rm-tt-lbl">Driver:</span><span class="rm-tt-val">${trip.driverName}</span></div>
                    <div class="rm-tt-row"><span class="rm-tt-lbl">Bus Plate:</span><span class="rm-tt-val mono">${trip.bus}</span></div>
                    <div class="rm-tt-row"><span class="rm-tt-lbl">Departure:</span><span class="rm-tt-val">${depFormatted}</span></div>
                    <div class="rm-tt-row"><span class="rm-tt-lbl">Arrival:</span><span class="rm-tt-val">${arrFormatted}</span></div>
                    <div class="rm-tt-row"><span class="rm-tt-lbl">Passengers:</span><span class="rm-tt-val">${trip.pax} pax</span></div>
                    <div class="rm-tt-row"><span class="rm-tt-lbl">Status:</span><span class="rm-tt-val">${trip.status}</span></div>
                    ${conflictMsgHtml}
                `;

                tripBlock.appendChild(tooltip);
                cell.appendChild(tripBlock);
            });

            gridContainer.appendChild(cell);
        }
    });
}

// ── UPCOMING TRIPS TODAY RENDERING ────────────────────────────
function renderUpcomingTrips() {
    const listContainer = document.getElementById('rm-upcoming-trips-list');
    if (!listContainer) return;

    // Filter, sort by time to find the next departures
    const upcomingTrips = [...schedulesData];
    upcomingTrips.sort((x, y) => {
        const timeX = x.time.split(':').map(Number);
        const timeY = y.time.split(':').map(Number);
        return (timeX[0] * 60 + timeX[1]) - (timeY[0] * 60 + timeY[1]);
    });

    // Take next 5 departures
    const nextFive = upcomingTrips.slice(0, 5);

    if (nextFive.length === 0) {
        listContainer.innerHTML = `<div style="text-align:center;padding:20px;color:var(--color-text-secondary);font-size:13px;">No scheduled trips today.</div>`;
        return;
    }

    listContainer.innerHTML = nextFive.map(trip => {
        const routeLetter = trip.routeId;
        const depTimeFormatted = format12Hour(trip.time);
        
        // Capacity pill
        let capChipHtml = '<span class="rm-status-chip rm-chip-gray">Normal</span>';
        if (trip.pax >= 45) {
            capChipHtml = '<span class="rm-status-chip rm-chip-red"><i class="ti ti-alert-circle"></i> Full</span>';
        } else if (trip.pax >= 36) {
            capChipHtml = '<span class="rm-status-chip rm-chip-amber"><i class="ti ti-alert-circle"></i> Near Full</span>';
        }

        // Status pill
        let statusChipHtml = '<span class="rm-status-chip rm-chip-gray">Not started</span>';
        if (trip.status === 'On time') {
            statusChipHtml = '<span class="rm-status-chip rm-chip-green"><i class="ti ti-circle-check"></i> On time</span>';
        } else if (trip.status.includes('Delayed')) {
            statusChipHtml = `<span class="rm-status-chip rm-chip-amber"><i class="ti ti-clock-exclamation"></i> ${trip.status}</span>`;
        } else if (trip.status === 'Cancelled') {
            statusChipHtml = '<span class="rm-status-chip rm-chip-red"><i class="ti ti-x"></i> Cancelled</span>';
        }

        return `
            <div class="rm-upcoming-row">
                <span class="rm-badge-pill ${routeLetter.toLowerCase()}">Route ${routeLetter}</span>
                <span class="rm-time-txt">${depTimeFormatted}</span>
                <span class="rm-driver-txt">${trip.driverName}</span>
                <span class="rm-bus-txt">${trip.bus}</span>
                ${capChipHtml}
                ${statusChipHtml}
                <button class="rm-btn-link-view" onclick="openScheduleModal('edit', '${trip.routeId}', '${trip.time}', ${trip.id})">
                    <i class="ti ti-eye"></i> View
                </button>
            </div>
        `;
    }).join('');
}

// ── ROUTE CONFIGURATION & STOP EDITOR ────────────────────────
function renderRoutesTab() {
    renderRouteList();
    renderRouteDetailPanel();
}

function renderRouteList() {
    const routeCardsContainer = document.getElementById('rm-route-cards-container');
    if (!routeCardsContainer) return;

    // Filter inactive routes if hidden
    const visibleRoutes = isSuspendedRoutesShown ? routesData : routesData.filter(r => r.status === 'Active');

    document.getElementById('rm-active-routes-count').textContent = `${routesData.filter(r => r.status === 'Active').length} active routes`;

    routeCardsContainer.innerHTML = visibleRoutes.map(route => {
        const isSelected = selectedRouteId === route.id;
        const selectedClass = isSelected ? 'selected' : '';
        const dotBg = route.color;
        const statusBadge = route.status === 'Active' ? '<span class="rm-badge-green" style="font-size:10px;">Active</span>' : '<span class="rm-badge-red" style="font-size:10px;">Suspended</span>';

        return `
            <div class="rm-route-card ${selectedClass}" onclick="selectRoute('${route.id}')">
                <div class="rm-rc-top">
                    <div class="rm-rc-title">
                        <span class="rm-rc-dot" style="background:${dotBg};"></span>
                        <span>${route.name}</span>
                    </div>
                    ${statusBadge}
                </div>
                <div class="rm-rc-mid">${route.endpoints}</div>
                <div class="rm-rc-stats">
                    <span class="rm-rc-stat-item"><i class="ti ti-map-pin"></i> ${route.stopsCount} stops</span>
                    <span class="rm-rc-stat-item"><i class="ti ti-road"></i> ${route.distance}</span>
                    <span class="rm-rc-stat-item"><i class="ti ti-bus"></i> ${route.busesCount} buses</span>
                </div>
                <div class="rm-rc-btm" style="color:${route.textColor};">Avg ${route.avgPax} pax/trip · Peak: ${route.peakHours}</div>
            </div>
        `;
    }).join('');
}

function selectRoute(routeId) {
    selectedRouteId = routeId;
    renderRouteList();
    renderRouteDetailPanel();
}

function toggleSuspendedRoutes() {
    isSuspendedRoutesShown = !isSuspendedRoutesShown;
    const label = document.getElementById('suspended-toggle-label');
    if (label) {
        label.textContent = isSuspendedRoutesShown ? 'Hide suspended routes' : 'Show suspended routes';
    }
    renderRouteList();
}

function renderRouteDetailPanel() {
    const route = routesData.find(r => r.id === selectedRouteId);
    if (!route) return;

    // Header updates
    document.getElementById('rm-detail-route-title').textContent = `${route.name} — ${route.endpoints}`;
    const statusLabel = document.getElementById('rm-detail-route-status');
    if (route.status === 'Active') {
        statusLabel.className = 'rm-badge-green';
        statusLabel.textContent = 'Active';
    } else {
        statusLabel.className = 'rm-badge-red';
        statusLabel.textContent = 'Suspended';
    }

    // Stops count label
    document.getElementById('rm-timeline-stops-count').textContent = `Stop sequence — ${route.stopsCount} stops`;

    // Render Timeline
    renderStopTimeline(route.id);

    // Render Map
    renderRouteMap(route.id);

    // Render Route Stats
    const statsContainer = document.getElementById('rm-route-stats-summary-container');
    if (statsContainer) {
        // Dynamic bus plate pills HTML
        const busPillsHtml = route.assignedBuses.map(plate => `
            <span class="rm-bus-pill">${plate}</span>
        `).join('');

        statsContainer.innerHTML = `
            <div class="rm-stat-summary-row">
                <span class="rm-stat-sum-lbl">Total distance:</span>
                <span class="rm-stat-sum-val">${route.distance}</span>
            </div>
            <div class="rm-stat-summary-row">
                <span class="rm-stat-sum-lbl">Avg trip duration:</span>
                <span class="rm-stat-sum-val">${ROUTE_DURATIONS[route.id]} min</span>
            </div>
            <div class="rm-stat-summary-row">
                <span class="rm-stat-sum-lbl">Peak hour:</span>
                <span class="rm-stat-sum-val">${route.peakHours}</span>
            </div>
            <div class="rm-stat-summary-row" style="border-bottom:none;">
                <span class="rm-stat-sum-lbl">Busiest stop:</span>
                <span class="rm-stat-sum-val" style="font-size:11.5px;">${route.busiestStop.split(' (')[0]}</span>
            </div>

            <div class="rm-assigned-buses-title">Buses on this route</div>
            <div class="rm-bus-pills-row">
                ${busPillsHtml}
            </div>
            
            <a href="#" class="rm-link-manage-assign" onclick="manageBusAssignments(event)">
                <i class="ti ti-arrow-right"></i> Manage assignments
            </a>
        `;
    }
}

function renderStopTimeline(routeId) {
    const timelineContainer = document.getElementById('rm-stop-timeline-container');
    if (!timelineContainer) return;

    const stops = stopsData[routeId] || [];

    if (stops.length === 0) {
        timelineContainer.innerHTML = `<div style="padding:16px;color:var(--color-text-secondary);font-size:13px;">No stops configured.</div>`;
        return;
    }

    timelineContainer.innerHTML = stops.map((stop, index) => {
        // Circle class rules
        let circleClass = 'rm-node-circle';
        let lineClass = 'rm-node-line';
        let labelHtml = '';

        if (index === 0) {
            circleClass += ' terminus';
            labelHtml = '<div class="rm-node-circle-label">Origin</div>';
        } else if (index === stops.length - 1) {
            circleClass += ' terminus';
            labelHtml = '<div class="rm-node-circle-label">Terminus</div>';
        } else if (stop.status === 'served') {
            circleClass += ' served';
        } else if (stop.status === 'current') {
            circleClass += ' current-next';
        } else {
            circleClass += ' future';
        }

        // Connector line details
        const isLast = index === stops.length - 1;
        
        // If connecting served stops
        if (stop.status === 'served' && stops[index + 1] && stops[index + 1].status === 'served') {
            lineClass += ' served-conn';
        }

        return `
            <div class="rm-timeline-node">
                <div class="rm-node-left">
                    <div class="${circleClass}">
                        ${labelHtml}
                    </div>
                    ${!isLast ? `<div class="${lineClass}"></div>` : ''}
                </div>
                <div class="rm-node-right">
                    <div class="rm-stop-name-row">
                        <span class="rm-stop-name">${stop.name}</span>
                        ${stop.landmark && stop.landmark !== 'origin' && stop.landmark !== 'terminus' 
                            ? `<span class="rm-stop-landmark">near ${stop.landmark}</span>` : ''}
                        
                        <i class="ti ti-grip-vertical rm-stop-drag" onclick="moveStop('${routeId}', ${index})" title="Move Stop Position"></i>
                        <i class="ti ti-trash rm-stop-delete" onclick="deleteStop('${routeId}', ${index})" title="Delete Stop"></i>
                    </div>
                    <div class="rm-stop-stats-row">
                        <span class="rm-stop-stat-val text-blue" style="color:#0C447C;">
                            <i class="ti ti-arrow-bar-up" style="color:#003F87;"></i> ${stop.boarding} avg boarding
                        </span>
                        <span class="rm-stop-stat-val">
                            <i class="ti ti-arrow-bar-down"></i> ${stop.alighting} avg alighting
                        </span>
                        ${stop.dwell !== '—' ? `
                        <span class="rm-stop-stat-val">
                            <i class="ti ti-clock"></i> ~${stop.dwell} dwell
                        </span>` : ''}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

// Simple handler to delete stop
async function deleteStop(routeId, index) {
    if (!confirm('Are you sure you want to delete this stop from the sequence?')) return;
    const stops = stopsData[routeId];
    if (stops && stops[index]) {
        const stopId = stops[index].id;
        const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.stopsBaseUrl) ? window.GoPasigConfig.stopsBaseUrl : '/admin/api/stops';
        
        try {
            const response = await fetch(`${baseUrl}/${stopId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            if (response.ok && data.success) {
                alert(data.message);
                if (typeof loadDatabaseFleetData === 'function') {
                    await loadDatabaseFleetData();
                }
                syncRoutesWithDatabase();
                renderRoutesTab();
            } else {
                alert(data.message || 'Failed to delete stop.');
            }
        } catch (error) {
            alert('Server connection error. Failed to delete stop.');
            console.error('AJAX Stop delete error:', error);
        }
    }
}

// Simple handler to move stop position for sequence reordering
async function moveStop(routeId, index) {
    const stops = stopsData[routeId];
    if (!stops || stops.length <= 1) return;
    
    let targetIndex = index + 1;
    if (index === stops.length - 1) {
        targetIndex = index - 1;
    }
    
    // Swap in local array copy first
    const temp = stops[index];
    stops[index] = stops[targetIndex];
    stops[targetIndex] = temp;
    
    const stopIds = stops.map(s => s.id);
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';
    
    try {
        const response = await fetch(`${baseUrl}/${routeId}/stops/reorder`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ stop_ids: stopIds })
        });
        
        const data = await response.json();
        if (response.ok && data.success) {
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
            syncRoutesWithDatabase();
            renderRoutesTab();
        } else {
            alert(data.message || 'Failed to reorder stops.');
        }
    } catch (error) {
        alert('Server connection error. Failed to reorder stops.');
        console.error('AJAX reorder error:', error);
    }
}

// Simple handler to suspend/unsuspend route from left/right panels
async function toggleSuspendRouteDetail() {
    const route = routesData.find(r => r.id === selectedRouteId);
    if (!route) return;
    
    const willSuspend = route.status === 'Active';
    const action = willSuspend ? 'suspend' : 'activate';
    const statusVal = willSuspend ? 'Suspended' : 'Active';
    
    if (!confirm(`Are you sure you want to ${action} ${route.name}?`)) return;
    
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';
    
    try {
        const response = await fetch(`${baseUrl}/${selectedRouteId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ status: statusVal })
        });
        
        const data = await response.json();
        if (response.ok && data.success) {
            alert(data.message);
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
            syncRoutesWithDatabase();
            renderRoutesTab();
        } else {
            alert(data.message || 'Failed to update route status.');
        }
    } catch (error) {
        alert('Server connection error. Failed to update route status.');
        console.error('AJAX route status update error:', error);
    }
}

// Add route placeholder stub
async function addNewRouteStub() {
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';
    
    try {
        const response = await fetch(baseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });
        
        const data = await response.json();
        if (response.ok && data.success) {
            alert(data.message);
            selectedRouteId = data.route.id.toString();
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
            syncRoutesWithDatabase();
            renderRoutesTab();
        } else {
            alert(data.message || 'Failed to create route.');
        }
    } catch (error) {
        alert('Server connection error. Failed to create route.');
        console.error('AJAX route create error:', error);
    }
}

// Edit route details stub
async function editRouteDetails() {
    const route = routesData.find(r => r.id === selectedRouteId);
    if (!route) return;
    
    const newEndpoints = prompt(`Enter new endpoints for ${route.name}:`, route.endpoints);
    if (!newEndpoints) return;
    
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.routesBaseUrl) ? window.GoPasigConfig.routesBaseUrl : '/admin/api/routes';
    
    try {
        const response = await fetch(`${baseUrl}/${selectedRouteId}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({ description: newEndpoints })
        });
        
        const data = await response.json();
        if (response.ok && data.success) {
            alert(data.message);
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
            syncRoutesWithDatabase();
            renderRoutesTab();
        } else {
            alert(data.message || 'Failed to update route endpoints.');
        }
    } catch (error) {
        alert('Server connection error. Failed to update route endpoints.');
        console.error('AJAX route edit error:', error);
    }
}

// ── SVG MAP PREVIEW DRAWING ──────────────────────────────────
let routePreviewMapInstance = null;
let routePreviewPolyline = null;
let routePreviewMarkers = [];
let scheduleRouteMapInstance = null;
let scheduleRouteMapLayers = [];

function addRouteMapBaseLayer(mapInstance) {
    try {
        if (L.gridLayer && typeof L.gridLayer.googleMutant === 'function') {
            L.gridLayer.googleMutant({
                type: 'roadmap'
            }).addTo(mapInstance);
            return;
        }
    } catch (error) {
        console.warn('Google Maps Mutant failed to load, falling back to CartoDB:', error);
    }

    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
        maxZoom: 20
    }).addTo(mapInstance);
}

function renderScheduleRouteMap() {
    const container = document.getElementById('rm-schedule-map-container');
    if (!container) return;

    // Skip rendering if container is hidden to prevent Leaflet initialization crash
    if (container.offsetWidth === 0 || container.offsetHeight === 0) {
        return;
    }

    const activeRoutes = routesDataDb.filter(route => normalizeRouteStatus(route.status) === 'Active');
    const routeCountBadge = document.getElementById('rm-schedule-map-route-count');
    if (routeCountBadge) {
        routeCountBadge.textContent = `${activeRoutes.length} active routes`;
    }

    if (typeof L === 'undefined') {
        container.innerHTML = `<div style="padding:40px;text-align:center;color:var(--color-text-secondary);">Map library is still loading. Please refresh if this stays blank.</div>`;
        return;
    }

    if (!activeRoutes.length) {
        container.innerHTML = `<div style="padding:40px;text-align:center;color:var(--color-text-secondary);">No active routes available.</div>`;
        return;
    }

    if (scheduleRouteMapInstance === null) {
        container.innerHTML = '';
        scheduleRouteMapInstance = L.map('rm-schedule-map-container', {
            zoomControl: true,
            attributionControl: false,
            scrollWheelZoom: false
        }).setView([14.5764, 121.0851], 13);

        addRouteMapBaseLayer(scheduleRouteMapInstance);
    } else {
        scheduleRouteMapLayers.forEach(layer => {
            try {
                if (scheduleRouteMapInstance.hasLayer(layer)) {
                    scheduleRouteMapInstance.removeLayer(layer);
                }
            } catch (e) {
                console.warn("Failed to remove scheduleRouteMapLayer:", e);
            }
        });
        scheduleRouteMapLayers = [];
    }

    const bounds = [];

    activeRoutes.forEach(route => {
        const routeId = route.id.toString();
        const strokeColor = routeColors[routeId] || '#003F87';

        if (route.polyline_coordinates && route.polyline_coordinates.length > 0) {
            const polyline = L.polyline(route.polyline_coordinates, {
                color: strokeColor,
                weight: 4,
                opacity: 0.82
            }).bindTooltip(route.name, {
                direction: 'top',
                sticky: true,
                className: 'font-sans font-bold text-[10px] px-1.5 py-0.5 rounded shadow-sm border border-slate-100'
            }).addTo(scheduleRouteMapInstance);

            scheduleRouteMapLayers.push(polyline);
            bounds.push(...route.polyline_coordinates);
        }

        if (route.stops && route.stops.length > 0) {
            route.stops.forEach(stop => {
                const lat = parseFloat(stop.lat);
                const lng = parseFloat(stop.lng);
                if (Number.isNaN(lat) || Number.isNaN(lng)) return;

                const marker = L.circleMarker([lat, lng], {
                    radius: 4,
                    fillColor: '#FFFFFF',
                    fillOpacity: 1,
                    color: strokeColor,
                    weight: 2
                }).bindTooltip(stop.name, {
                    direction: 'top',
                    className: 'font-sans font-bold text-[9px] px-1.5 py-0.5 rounded shadow-sm border border-slate-100'
                }).addTo(scheduleRouteMapInstance);

                scheduleRouteMapLayers.push(marker);
                bounds.push([lat, lng]);
            });
        }
    });

    if (bounds.length > 0) {
        scheduleRouteMapInstance.fitBounds(bounds, { padding: [24, 24] });
    }

    setTimeout(() => {
        scheduleRouteMapInstance.invalidateSize();
    }, 150);
}

function renderRouteMap(routeId) {
    const container = document.getElementById('rm-simulated-map-container');
    if (!container) return;

    // Skip rendering if container is hidden to prevent Leaflet initialization crash
    if (container.offsetWidth === 0 || container.offsetHeight === 0) {
        return;
    }

    const stops = stopsData[routeId] || [];
    const route = routesDataDb.find(r => r.id.toString() === routeId.toString());
    
    if (stops.length === 0 || !route) {
        container.innerHTML = `<div style="padding:40px;text-align:center;color:var(--color-text-secondary);">No map available.</div>`;
        return;
    }

    const strokeColor = routeColors[routeId.toString()] || '#003F87';

    if (typeof L === 'undefined') {
        container.innerHTML = `<div style="padding:40px;text-align:center;color:var(--color-text-secondary);">Map library is still loading. Please refresh if this stays blank.</div>`;
        return;
    }

    // If map is not initialized yet, initialize it
    if (routePreviewMapInstance === null) {
        container.innerHTML = ''; // clear svg content
        routePreviewMapInstance = L.map('rm-simulated-map-container', {
            zoomControl: false,
            attributionControl: false,
            scrollWheelZoom: false,
            doubleClickZoom: false,
            boxZoom: false,
            dragPan: true
        });

        addRouteMapBaseLayer(routePreviewMapInstance);
    } else {
        // Clear previous layers
        try {
            if (routePreviewPolyline && routePreviewMapInstance.hasLayer(routePreviewPolyline)) {
                routePreviewMapInstance.removeLayer(routePreviewPolyline);
            }
        } catch (e) {
            console.warn("Failed to remove routePreviewPolyline:", e);
        }
        routePreviewPolyline = null;

        routePreviewMarkers.forEach(m => {
            try {
                if (routePreviewMapInstance.hasLayer(m)) {
                    routePreviewMapInstance.removeLayer(m);
                }
            } catch (e) {
                console.warn("Failed to remove marker:", e);
            }
        });
        routePreviewMarkers = [];
    }

    // Draw route polyline
    if (route.polyline_coordinates && route.polyline_coordinates.length > 0) {
        routePreviewPolyline = L.polyline(route.polyline_coordinates, {
            color: strokeColor,
            weight: 4,
            opacity: 0.85
        }).addTo(routePreviewMapInstance);

        // Fit map bounds to show the entire route polyline!
        routePreviewMapInstance.fitBounds(routePreviewPolyline.getBounds(), { padding: [20, 20] });
    }

    // Draw stops
    stops.forEach((stop, index) => {
        const marker = L.circleMarker([stop.lat, stop.lng], {
            radius: 4.5,
            fillColor: '#FFFFFF',
            fillOpacity: 1,
            color: strokeColor,
            weight: 2
        }).bindTooltip(stop.name, {
            direction: 'top',
            className: 'font-sans font-bold text-[9px] px-1.5 py-0.5 rounded shadow-sm border border-slate-100'
        }).addTo(routePreviewMapInstance);

        routePreviewMarkers.push(marker);
    });

    // Invalidate size in case container size changed
    setTimeout(() => {
        routePreviewMapInstance.invalidateSize();
    }, 150);
}

// Redirect view to live fleet map screen
function viewLiveMapScreen(e) {
    if (e) e.preventDefault();
    if (typeof switchScreen === 'function') {
        switchScreen('map-view');
    }
}

// Manage Bus Assignments
function manageBusAssignments(e) {
    if (e) e.preventDefault();
    
    // Find current route details
    const route = routesData.find(r => r.id.toString() === selectedRouteId.toString());
    if (!route) {
        alert("Pumili muna ng ruta.");
        return;
    }

    // Check if the modal overlay already exists in DOM
    let modal = document.getElementById('rm-bus-assignment-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'rm-bus-assignment-modal';
        modal.className = 'rm-modal-overlay hidden';
        modal.innerHTML = `
            <div class="rm-modal-card" style="max-width: 500px;">
                <div class="rm-modal-header">
                    <span class="rm-modal-title-text" id="rm-bus-assign-title">Manage Bus Assignments</span>
                    <button class="rm-modal-close-btn" onclick="closeBusAssignmentModal()"><i class="ti ti-x"></i></button>
                </div>
                <div class="rm-modal-body" style="max-height: 400px; overflow-y: auto;">
                    <p class="text-slate-500 text-xs mb-4" id="rm-bus-assign-desc">Select the buses that should be assigned to this route.</p>
                    <div id="rm-bus-assign-list" class="space-y-2">
                        <!-- Checkboxes dynamically populated -->
                    </div>
                </div>
                <div class="rm-modal-footer">
                    <button class="rm-btn-outline rm-btn-sm" onclick="closeBusAssignmentModal()">Cancel</button>
                    <button class="rm-btn-primary rm-btn-sm" onclick="saveBusAssignments()">Save Changes</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    // Update texts
    document.getElementById('rm-bus-assign-title').textContent = `Manage Buses for: ${route.name}`;
    document.getElementById('rm-bus-assign-desc').textContent = `Manage the registered fleet of buses for route: ${route.name} (${route.description}).`;

    // Populate buses list container
    const listContainer = document.getElementById('rm-bus-assign-list');
    listContainer.innerHTML = '';

    const buses = (typeof fleetData !== 'undefined') ? fleetData : [];
    if (buses.length === 0) {
        listContainer.innerHTML = `<p class="text-xs text-slate-400 py-4 text-center">No buses found in the database. Please register buses first.</p>`;
    } else {
        buses.forEach(bus => {
            const isAssigned = bus.route === selectedRouteId.toString();
            const checkedAttr = isAssigned ? 'checked' : '';
            
            let badgeText = 'Unassigned';
            let badgeClass = 'bg-slate-100 text-slate-500 border border-slate-200';
            if (bus.route && bus.route !== 'None' && bus.route !== selectedRouteId.toString()) {
                const assignedRoute = routesData.find(r => r.id.toString() === bus.route);
                badgeText = assignedRoute ? `Assigned to: ${assignedRoute.name}` : `Assigned to Route ID ${bus.route}`;
                badgeClass = 'bg-[#FAF0E6] text-[#854F0B] border border-[#F5D8B3]';
            } else if (isAssigned) {
                badgeText = 'Currently Assigned';
                badgeClass = 'bg-[#EBF4FA] text-[#0C447C] border border-[#C5DFF4]';
            }

            listContainer.innerHTML += `
                <div class="flex items-center justify-between p-3 bg-slate-50 rounded-lg hover:bg-slate-100/80 transition-colors border border-slate-200" style="margin-bottom: 8px;">
                    <div class="flex items-center gap-3">
                        <input type="checkbox" id="assign-bus-${bus.id}" data-bus-id="${bus.id}" ${checkedAttr} class="rounded text-[#003F87] focus:ring-[#003F87] w-4 h-4 cursor-pointer" style="margin-right: 8px;">
                        <label for="assign-bus-${bus.id}" class="text-xs font-bold text-slate-800 cursor-pointer flex flex-col">
                            <span class="font-mono text-sm">${bus.plate}</span>
                            <span class="font-normal text-[11px] text-slate-400">Driver: ${bus.driver}</span>
                        </label>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold ${badgeClass}">
                        ${badgeText}
                    </span>
                </div>
            `;
        });
    }

    modal.classList.remove('hidden');
}

function closeBusAssignmentModal() {
    const modal = document.getElementById('rm-bus-assignment-modal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

async function saveBusAssignments() {
    const routeId = selectedRouteId;
    const listContainer = document.getElementById('rm-bus-assign-list');
    if (!listContainer) return;

    const checkboxes = listContainer.querySelectorAll('input[type="checkbox"]');
    const promises = [];

    checkboxes.forEach(checkbox => {
        const busId = checkbox.getAttribute('data-bus-id');
        const isChecked = checkbox.checked;
        const bus = fleetData.find(b => b.id.toString() === busId.toString());
        if (!bus) return;

        const wasAssigned = (bus.route === routeId.toString());

        if (isChecked && !wasAssigned) {
            // Assign
            promises.push(updateBusRouteAssignment(busId, routeId));
        } else if (!isChecked && wasAssigned) {
            // Unassign
            promises.push(updateBusRouteAssignment(busId, null));
        }
    });

    try {
        const saveBtn = document.querySelector('#rm-bus-assignment-modal .rm-btn-primary');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving...';
        }

        await Promise.all(promises);

        // Reload the fleet data dynamically
        if (typeof loadDatabaseFleetData === 'function') {
            await loadDatabaseFleetData();
        }

        // Close modal
        closeBusAssignmentModal();
        
        // Re-render route details to show updated buses
        if (typeof renderRouteDetails === 'function') {
            renderRouteDetails(routeId);
        }

        alert('Bus assignments updated successfully!');
    } catch (error) {
        console.error('Error saving bus assignments:', error);
        alert('Failed to save bus assignments. Please try again.');
    } finally {
        const saveBtn = document.querySelector('#rm-bus-assignment-modal .rm-btn-primary');
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.textContent = 'Save Changes';
        }
    }
}

async function updateBusRouteAssignment(busId, routeId) {
    const token = getCsrfToken();
    const url = `/admin/api/buses/${busId}/assign-route`;
    const response = await fetch(url, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({ route_id: routeId })
    });
    
    if (!response.ok) {
        throw new Error(`Failed to assign bus ID ${busId}`);
    }
    return await response.json();
}

// Bind to window to allow trigger from onclick attributes
window.closeBusAssignmentModal = closeBusAssignmentModal;
window.saveBusAssignments = saveBusAssignments;

// ── CONFLICT DETECTION SCANNER ──────────────────────────────
function reScanConflicts() {
    conflictsList = [];

    // 1. DYNAMIC SCAN: Check for double-booked drivers (within duration + 15 min buffer)
    for (let i = 0; i < schedulesData.length; i++) {
        const s1 = schedulesData[i];
        const route1 = routesData.find(r => r.id === s1.routeId);
        const duration1 = route1 ? ROUTE_DURATIONS[s1.routeId] : 30;

        const time1Parts = s1.time.split(':').map(Number);
        const start1 = time1Parts[0] * 60 + time1Parts[1];
        const end1 = start1 + duration1;

        for (let j = i + 1; j < schedulesData.length; j++) {
            const s2 = schedulesData[j];
            
            // Check driver double book
            if (s1.driver === s2.driver) {
                const time2Parts = s2.time.split(':').map(Number);
                const start2 = time2Parts[0] * 60 + time2Parts[1];
                
                // Overlap buffer window: 15 minutes changeover
                const isOverlapping = (start2 >= start1 && start2 < (end1 + 15)) || (start1 >= start2 && start1 < (start2 + 15));
                
                if (isOverlapping) {
                    const diffMin = Math.abs(start2 - start1);
                    conflictsList.push({
                        id: `drv-${s1.id}-${s2.id}`,
                        type: 'Driver conflict',
                        severity: 'High',
                        entityName: `${s1.driverName} — double booked`,
                        description: `Assigned to Route ${s2.routeId} at ${format12Hour(s2.time)} and Route ${s1.routeId} at ${format12Hour(s1.time)}. Only ${diffMin} minutes apart — insufficient changeover time.`,
                        affectedIds: [s1.id, s2.id],
                        routeId1: s1.routeId,
                        time1: s1.time,
                        routeId2: s2.routeId,
                        time2: s2.time
                    });
                }
            }

            // Check bus double book
            if (s1.bus === s2.bus) {
                const time2Parts = s2.time.split(':').map(Number);
                const start2 = time2Parts[0] * 60 + time2Parts[1];
                const route2 = routesData.find(r => r.id === s2.routeId);
                const duration2 = route2 ? ROUTE_DURATIONS[s2.routeId] : 30;

                // Overlap: trip 1 ends after trip 2 starts, or trip 2 ends after trip 1 starts
                const end2 = start2 + duration2;
                const isOverlapping = (start2 >= start1 && start2 < end1) || (start1 >= start2 && start1 < end2) || 
                                     // Also flag 2-hour window risk as high severity overlap risk
                                     (Math.abs(start2 - start1) <= 120);

                if (isOverlapping) {
                    conflictsList.push({
                        id: `bus-${s1.id}-${s2.id}`,
                        type: 'Bus conflict',
                        severity: 'High',
                        entityName: `Bus ${s1.bus} — assigned to two routes`,
                        description: `Scheduled on Route ${s2.routeId} at ${format12Hour(s2.time)} and Route ${s1.routeId} at ${format12Hour(s1.time)}. Route ${s2.routeId} trip (est. ${duration2} min) ends at ~${format12Hour(formatMinuteToTime(start2 + duration2))} — overlap risk if delayed.`,
                        affectedIds: [s1.id, s2.id],
                        routeId1: s1.routeId,
                        time1: s1.time,
                        routeId2: s2.routeId,
                        time2: s2.time
                    });
                }
            }
        }
    }

    // 2. GAP CHECK: Route C no coverage 1:00 to 4:00 PM
    const routeCTrips = schedulesData.filter(s => s.routeId === 'C');
    const hasGapC = !routeCTrips.some(s => {
        const hour = parseInt(s.time.split(':')[0]);
        return hour >= 13 && hour <= 16;
    });

    if (hasGapC) {
        conflictsList.push({
            id: 'gap-c-afternoon',
            type: 'Scheduling gap',
            severity: 'Medium',
            entityName: 'Route C — no coverage 1:00–4:00 PM',
            description: 'No bus assigned on Route C between 1:00 PM and 4:00 PM. Historical data shows avg 98 pax/trip during this period.',
            affectedIds: [],
            metaText: 'Route C · 1:00–4:00 PM'
        });
    }

    // Re-render display outputs
    renderConflictLists();
    
    // Update header conflict badge and numbers
    const totalCount = conflictsList.length;
    const headerBtn = document.getElementById('btn-conflict-check-header');
    
    if (headerBtn) {
        if (totalCount > 0) {
            headerBtn.innerHTML = `<i class="ti ti-alert-triangle"></i> Conflict check <span style="background:#A32D2D;color:#fff;border-radius:99px;padding:1px 5px;font-size:10px;margin-left:4px;">${totalCount}</span>`;
            headerBtn.style.color = '#A32D2D';
            headerBtn.style.borderColor = '#F09595';
        } else {
            headerBtn.innerHTML = `<i class="ti ti-circle-check"></i> Conflict check`;
            headerBtn.style.color = '#3B6D11';
            headerBtn.style.borderColor = '#EAF3DE';
        }
    }
}

function renderConflictLists() {
    const inlineContainer = document.getElementById('inline-conflict-list');
    const slideContainer = document.getElementById('slide-conflict-list');
    
    // Counts
    const drvConflicts = conflictsList.filter(c => c.type === 'Driver conflict').length;
    const busConflicts = conflictsList.filter(c => c.type === 'Bus conflict').length;
    const gapConflicts = conflictsList.filter(c => c.type === 'Scheduling gap').length;

    // Badges update
    const cLabel = `${conflictsList.length} conflict${conflictsList.length !== 1 ? 's' : ''} found`;
    document.getElementById('inline-conflict-count').textContent = cLabel;
    document.getElementById('slide-conflict-count').textContent = cLabel;

    document.getElementById('inline-stat-driver-conflict').textContent = `${drvConflicts} driver conflict${drvConflicts !== 1 ? 's' : ''}`;
    document.getElementById('inline-stat-bus-conflict').textContent = `${busConflicts} bus conflict${busConflicts !== 1 ? 's' : ''}`;
    document.getElementById('inline-stat-maint-conflict').textContent = `${gapConflicts} gap conflict${gapConflicts !== 1 ? 's' : ''}`;

    document.getElementById('slide-stat-driver-conflict').textContent = `${drvConflicts} Driver`;
    document.getElementById('slide-stat-bus-conflict').textContent = `${busConflicts} Bus`;

    const html = conflictsList.map(c => {
        const sevClass = c.severity === 'High' ? 'high' : 'medium';
        const sevText = c.severity;
        
        let chipsHtml = '';
        if (c.affectedIds.length > 0) {
            const s1 = schedulesData.find(s => s.id === c.affectedIds[0]);
            const s2 = schedulesData.find(s => s.id === c.affectedIds[1]);
            if (s1 && s2) {
                chipsHtml = `
                    <div class="rm-conflict-affected-row">
                        <span class="rm-affected-chip" onclick="focusScheduleCell('${s1.routeId}', '${s1.time}')">Route ${s1.routeId} · ${format12Hour(s1.time)}</span>
                        <span class="rm-affected-chip" onclick="focusScheduleCell('${s2.routeId}', '${s2.time}')">Route ${s2.routeId} · ${format12Hour(s2.time)}</span>
                    </div>
                `;
            }
        } else if (c.metaText) {
            chipsHtml = `<div class="rm-conflict-affected-row"><span class="rm-affected-chip">${c.metaText}</span></div>`;
        }

        // Show Resolve action button only for severities that can be resolved
        let resolveBtnHtml = '';
        if (c.severity === 'High') {
            resolveBtnHtml = `<button class="rm-btn-primary rm-btn-xs" style="background:#003F87;" onclick="openResolveModal('${c.id}')">Resolve</button>`;
        }

        return `
            <div class="rm-conflict-row severity-${sevClass}">
                <div class="rm-conflict-row-top">
                    <span class="rm-severity-chip ${sevClass}">${sevText} ${c.type}</span>
                    ${resolveBtnHtml}
                </div>
                <div class="rm-conflict-entity">${c.entityName}</div>
                <div class="rm-conflict-desc">${c.description}</div>
                ${chipsHtml}
            </div>
        `;
    }).join('');

    const emptyHtml = `<div style="text-align:center;padding:24px;color:var(--color-text-secondary);font-size:12px;">No conflicts detected. Everything is clear!</div>`;

    if (inlineContainer) inlineContainer.innerHTML = conflictsList.length > 0 ? html : emptyHtml;
    if (slideContainer) slideContainer.innerHTML = conflictsList.length > 0 ? html : emptyHtml;
}

// Highlight cell click to jump focus
function focusScheduleCell(routeId, time) {
    // If in stops tab, switch to schedule tab
    if (activeRoutesTab !== 'schedule') {
        switchRoutesTab('schedule');
    }
    // Simple alert representing the focus highlight
    alert(`Timetable cell focused: Route ${routeId} at ${format12Hour(time)}`);
}

// Side conflict check toggle
function toggleConflictPanel() {
    const drawer = document.getElementById('conflict-sliding-drawer');
    const overlay = document.getElementById('conflict-sliding-overlay');
    if (!drawer) return;

    if (drawer.classList.contains('rm-drawer--open')) {
        drawer.classList.remove('rm-drawer--open');
        overlay.classList.add('hidden');
    } else {
        overlay.classList.remove('hidden');
        drawer.classList.add('rm-drawer--open');
    }
}

function closeInlineConflictPanel() {
    document.getElementById('conflict-inline-panel').classList.add('hidden');
}

function toggleConflictPanelInline() {
    const card = document.getElementById('conflict-inline-panel');
    card.classList.toggle('hidden');
}

function resolveAllConflicts() {
    alert('Bulk resolution: Reassigning backup drivers and shifting overlapping bus gaps requires manual selection of options below.');
}

// ── RESOLVE CONFLICT MODAL FLOW ─────────────────────────────
function openResolveModal(conflictId) {
    currentResolvingConflictId = conflictId;
    const conflict = conflictsList.find(c => c.id === conflictId);
    if (!conflict) return;

    const modal = document.getElementById('rm-resolve-modal');
    const titleEl = document.getElementById('resolve-modal-title');
    const descEl = document.getElementById('resolve-modal-desc');

    titleEl.textContent = `Resolve conflict — ${conflict.type}`;
    descEl.textContent = conflict.description;

    // Reset resolution option selects
    const driverSelect = document.getElementById('resolve-select-driver');
    // Load available drivers
    driverSelect.innerHTML = driversList
        .filter(d => d.status === 'On Duty' && d.initials !== conflict.driver)
        .map(d => `<option value="${d.initials}">${d.firstName} ${d.lastName} (${d.initials})</option>`)
        .join('');

    // Pre-fill time wrap
    const timeInput = document.getElementById('resolve-input-time');
    if (conflict.time1) {
        // Offer standard delay offset of +45 mins (e.g. 7:15 AM to 8:00 AM)
        timeInput.value = '08:00';
    }

    // Toggle initial inputs
    toggleResolutionChoiceFields();

    modal.classList.remove('hidden');
}

function closeResolveModal() {
    document.getElementById('rm-resolve-modal').classList.add('hidden');
}

function toggleResolutionChoiceFields() {
    const isReassign = document.getElementById('res-choice-reassign').checked;
    const isAdjust = document.getElementById('res-choice-adjust').checked;

    document.getElementById('resolve-reassign-dropdown-wrap').classList.toggle('hidden', !isReassign);
    document.getElementById('resolve-time-wrap').classList.toggle('hidden', !isAdjust);
}

async function applyConflictResolution() {
    const conflict = conflictsList.find(c => c.id === currentResolvingConflictId);
    if (!conflict) return;

    const isReassign = document.getElementById('res-choice-reassign').checked;
    const isAdjust = document.getElementById('res-choice-adjust').checked;
    const isRemove = document.getElementById('res-choice-remove').checked;

    const affectedScheduleId = conflict.affectedIds[0];
    const schedule = schedulesData.find(s => s.id === affectedScheduleId);

    if (!schedule) return;

    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.schedulesBaseUrl) ? window.GoPasigConfig.schedulesBaseUrl : '/admin/api/schedules';

    try {
        if (isRemove) {
            const response = await fetch(`${baseUrl}/${affectedScheduleId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json'
                }
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                alert(data.message || 'Failed to remove schedule.');
                return;
            }
        } else {
            let driverInitials = schedule.driver;
            let timeVal = schedule.time;

            if (isReassign) {
                driverInitials = document.getElementById('resolve-select-driver').value;
            } else if (isAdjust) {
                timeVal = document.getElementById('resolve-input-time').value;
            }

            const payload = {
                route_id: schedule.routeId,
                bus_plate: schedule.bus,
                driver_initials: driverInitials,
                departure_time: timeVal
            };

            const response = await fetch(`${baseUrl}/${affectedScheduleId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                alert(data.message || 'Failed to update schedule.');
                return;
            }
        }

        closeResolveModal();
        await loadDatabaseSchedulesData();
        reScanConflicts();
        renderScheduleGrid();
        renderUpcomingTrips();
        renderConflictLists();
    } catch (error) {
        alert('Server connection error. Failed to resolve conflict.');
        console.error('AJAX Conflict resolution error:', error);
    }
}

// ── CREATE / EDIT SCHEDULE MODAL FLOW ────────────────────────
function openScheduleModal(mode, routeId = '1', timeStr = '08:00', scheduleId = null) {
    currentEditingScheduleId = scheduleId;
    
    const modal = document.getElementById('rm-schedule-modal');
    const titleEl = document.getElementById('rm-modal-title');
    const deleteBtn = document.getElementById('sf-delete-btn');
    
    // Clear warnings
    document.getElementById('modal-conflict-warning-card').classList.add('hidden');
    document.getElementById('sf-driver-expiry-warning').classList.add('hidden');

    // Dynamically populate route dropdown select options
    const routeSelect = document.getElementById('sf-route');
    if (routeSelect) {
        routeSelect.innerHTML = routesData.map(r => `<option value="${r.id}">${r.name} — ${r.endpoints}</option>`).join('');
    }

    // Prefill dropdown options
    populateModalDropdowns(routeId, timeStr, scheduleId);

    if (mode === 'create') {
        titleEl.textContent = 'Create new schedule';
        deleteBtn.classList.add('hidden');

        document.getElementById('sf-route').value = routeId;
        document.getElementById('sf-departure').value = timeStr;
        
        // Days check all weekdays
        ['M','T','W','Th','F'].forEach(d => document.getElementById(`day-${d}`).checked = true);
        ['Sa','Su'].forEach(d => document.getElementById(`day-${d}`).checked = false);

        document.getElementById('sf-repeat').value = 'Weekly';

        // Est Arrival Calc
        updateEstimatedArrivalTime(routeId, timeStr);
    } else {
        const schedule = schedulesData.find(s => s.id === scheduleId);
        if (!schedule) return;

        titleEl.textContent = 'Edit schedule';
        deleteBtn.classList.remove('hidden');

        document.getElementById('sf-route').value = schedule.routeId;
        document.getElementById('sf-bus').value = schedule.bus;
        document.getElementById('sf-driver').value = schedule.driver;
        document.getElementById('sf-departure').value = schedule.time;

        // Est Arrival Calc
        updateEstimatedArrivalTime(schedule.routeId, schedule.time);

        // Precheck days
        ['M','T','W','Th','F','Sa','Su'].forEach(d => {
            document.getElementById(`day-${d}`).checked = true; // default
        });

        // Trigger expiry check
        checkDriverLicenseExpiry(schedule.driver);
    }

    checkFormConflicts();
    modal.classList.remove('hidden');
}

function closeScheduleModal() {
    document.getElementById('rm-schedule-modal').classList.add('hidden');
}

function populateModalDropdowns(routeId, timeStr, scheduleId) {
    const busSelect = document.getElementById('sf-bus');
    const driverSelect = document.getElementById('sf-driver');

    // Clean selects
    busSelect.innerHTML = '';
    driverSelect.innerHTML = '';

    // Logic: Find buses and drivers already assigned to other routes at the selected time hour
    const hour = parseInt(timeStr.split(':')[0]);
    const conflictingSchedules = schedulesData.filter(s => {
        // Exclude current editing schedule
        if (scheduleId && s.id === scheduleId) return false;
        const sHour = parseInt(s.time.split(':')[0]);
        return sHour === hour;
    });

    const busyBuses = conflictingSchedules.map(s => s.bus);
    const busyDrivers = conflictingSchedules.map(s => s.driver);

    // Sync drivers list dynamically
    setupDriversPool();

    // Populate Buses
    const busesSource = (typeof fleetData !== 'undefined' && fleetData.length > 0) ? fleetData : [];
    busesSource.forEach(bus => {
        const isBusy = busyBuses.includes(bus.plate);
        const disabledAttr = isBusy ? 'disabled style="color:var(--color-text-secondary);cursor:not-allowed;"' : '';
        const labelSuffix = isBusy ? ` (Scheduled ${timeStr} ✗)` : ' — Active ✓';
        
        busSelect.innerHTML += `<option value="${bus.plate}" ${disabledAttr}>${bus.plate}${labelSuffix}</option>`;
    });

    // Populate Drivers
    driversList.forEach(driver => {
        const isBusy = busyDrivers.includes(driver.initials);
        const isSuspended = driver.status === 'Suspended';
        
        let disabledAttr = '';
        let labelSuffix = ' — Active ✓';

        if (isSuspended) {
            disabledAttr = 'disabled style="color:var(--color-text-secondary);cursor:not-allowed;"';
            labelSuffix = ' (Suspended ✗)';
        } else if (isBusy) {
            disabledAttr = 'disabled style="color:var(--color-text-secondary);cursor:not-allowed;"';
            labelSuffix = ` (Scheduled ${timeStr} ✗)`;
        }

        driverSelect.innerHTML += `<option value="${driver.initials}" ${disabledAttr}>${driver.firstName} ${driver.lastName} (${driver.initials})${labelSuffix}</option>`;
    });
}

function onModalRouteSelectChange() {
    const routeId = document.getElementById('sf-route').value;
    const timeStr = document.getElementById('sf-departure').value;
    
    // Update arrival time
    updateEstimatedArrivalTime(routeId, timeStr);
    
    // Update bus and driver dropdown availability list
    populateModalDropdowns(routeId, timeStr, currentEditingScheduleId);
    
    checkFormConflicts();
}

function onDepartureTimeChange() {
    const routeId = document.getElementById('sf-route').value;
    const timeStr = document.getElementById('sf-departure').value;
    
    updateEstimatedArrivalTime(routeId, timeStr);
    
    // Check conflicts
    checkFormConflicts();
}

function updateEstimatedArrivalTime(routeId, timeStr) {
    const duration = ROUTE_DURATIONS[routeId] || 30;
    const helperText = document.getElementById('sf-arrival-helper');
    
    if (helperText) {
        helperText.textContent = `Based on Route ${routeId} avg duration: ${duration} min`;
        helperText.style.display = 'block';
    }

    if (!timeStr) return;

    const parts = timeStr.split(':').map(Number);
    const depMin = parts[0] * 60 + parts[1];
    const arrMin = depMin + duration;
    
    const arrHour = Math.floor(arrMin / 60) % 24;
    const arrMinute = arrMin % 60;
    
    document.getElementById('sf-arrival').value = `${arrHour.toString().padStart(2, '0')}:${arrMinute.toString().padStart(2, '0')}`;
}

function onArrivalTimeManualEdit() {
    // If the user manually overrides arrival time, hide the helper helper text
    const helperText = document.getElementById('sf-arrival-helper');
    if (helperText) {
        helperText.style.display = 'none';
    }
}

// Triggers checking if selected values cause conflicts and renders warning IMMEDIATELY
function checkFormConflicts() {
    const driverVal = document.getElementById('sf-driver').value;
    const busVal = document.getElementById('sf-bus').value;
    const routeVal = document.getElementById('sf-route').value;
    const timeVal = document.getElementById('sf-departure').value;

    const warnCard = document.getElementById('modal-conflict-warning-card');
    const warnText = document.getElementById('modal-conflict-warning-text');

    warnCard.classList.add('hidden');

    if (!timeVal || !driverVal) return;

    // Perform check for other trips
    const hour = parseInt(timeVal.split(':')[0]);
    const duration = ROUTE_DURATIONS[routeVal];
    const startMin = hour * 60 + parseInt(timeVal.split(':')[1]);
    const endMin = startMin + duration;

    // Scan schedules for conflict
    const conflict = schedulesData.find(s => {
        // Ignore current edit schedule
        if (currentEditingScheduleId && s.id === currentEditingScheduleId) return false;
        
        // Match same driver or same bus
        const isSameDriver = s.driver === driverVal;
        const isSameBus = s.bus === busVal;

        if (isSameDriver || isSameBus) {
            const sParts = s.time.split(':').map(Number);
            const sStart = sParts[0] * 60 + sParts[1];
            const sRoute = routesData.find(r => r.id === s.routeId);
            const sDuration = sRoute ? ROUTE_DURATIONS[s.routeId] : 30;
            const sEnd = sStart + sDuration;

            // Overlaps if start2 < end1 and start1 < end2 (+ 15 min driver buffer)
            const buffer = isSameDriver ? 15 : 0;
            return (startMin < (sEnd + buffer)) && (sStart < (endMin + buffer));
        }
        return false;
    });

    if (conflict) {
        const isDriver = conflict.driver === driverVal;
        const entityName = isDriver ? `Driver ${conflict.driverName}` : `Bus ${conflict.bus}`;
        const relation = isDriver ? 'is already assigned to' : 'is already scheduled on';
        
        warnText.textContent = `${entityName} ${relation} Route ${conflict.routeId} at ${format12Hour(conflict.time)}. Select a different option or change the departure time.`;
        warnCard.classList.remove('hidden');
    }

    // License expiry warn
    checkDriverLicenseExpiry(driverVal);
}

function checkDriverLicenseExpiry(driverInitials) {
    const warningLabel = document.getElementById('sf-driver-expiry-warning');
    const driver = driversList.find(d => d.initials === driverInitials);
    
    if (driver && driver.expiryDate) {
        const exp = new Date(driver.expiryDate);
        const today = new Date('2025-12-10'); // matching standard currentActiveDate reference
        const diff = Math.floor((exp - today) / 86400000);
        
        if (diff <= 30) {
            warningLabel.textContent = `⚠ License expiring Dec 12 (in ${diff} days)`;
            warningLabel.classList.remove('hidden');
        } else {
            warningLabel.classList.add('hidden');
        }
    } else {
        warningLabel.classList.add('hidden');
    }
}

async function handleScheduleSubmit(e) {
    if (e) e.preventDefault();

    const routeVal = document.getElementById('sf-route').value;
    const busVal = document.getElementById('sf-bus').value;
    const driverVal = document.getElementById('sf-driver').value;
    const timeVal = document.getElementById('sf-departure').value;
    
    if (!timeVal) {
        alert('Please select a departure time.');
        return;
    }

    const payload = {
        route_id: routeVal,
        bus_plate: busVal,
        driver_initials: driverVal,
        departure_time: timeVal
    };

    const isEdit = currentEditingScheduleId !== null;
    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.schedulesBaseUrl) ? window.GoPasigConfig.schedulesBaseUrl : '/admin/api/schedules';
    const url = isEdit ? `${baseUrl}/${currentEditingScheduleId}` : baseUrl;
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
            closeScheduleModal();
            
            await loadDatabaseSchedulesData();
            reScanConflicts();
            renderScheduleGrid();
            renderUpcomingTrips();
        } else {
            alert(data.message || 'Validation error. Please verify schedule details.');
            console.error('Schedule submit failed:', data);
        }
    } catch (error) {
        alert('Server connection error. Failed to save schedule.');
        console.error('AJAX Schedule submit error:', error);
    }
}

async function handleDeleteSchedule() {
    if (currentEditingScheduleId === null) return;
    if (!confirm('Are you sure you want to delete this schedule entry?')) return;

    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.schedulesBaseUrl) ? window.GoPasigConfig.schedulesBaseUrl : '/admin/api/schedules';

    try {
        const response = await fetch(`${baseUrl}/${currentEditingScheduleId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (response.ok && data.success) {
            alert(data.message);
            closeScheduleModal();
            
            await loadDatabaseSchedulesData();
            reScanConflicts();
            renderScheduleGrid();
            renderUpcomingTrips();
        } else {
            alert(data.message || 'Failed to delete schedule.');
        }
    } catch (error) {
        alert('Server connection error. Failed to delete schedule.');
        console.error('AJAX Schedule delete error:', error);
    }
}

// ── ADD STOPS MODAL FLOW ────────────────────────────────────
let activeStopAddingRouteId = 'A';

function openAddStopToRouteModal() {
    activeStopAddingRouteId = selectedRouteId;
    const modal = document.getElementById('rm-add-stop-modal');
    document.getElementById('as-name').value = '';
    document.getElementById('as-landmark').value = '';
    document.getElementById('as-boarding').value = '15';
    document.getElementById('as-alighting').value = '10';
    document.getElementById('as-dwell').value = '45';
    modal.classList.remove('hidden');
}

function closeAddStopModal() {
    document.getElementById('rm-add-stop-modal').classList.add('hidden');
}

async function handleAddStopSubmit(e) {
    if (e) e.preventDefault();

    const name = document.getElementById('as-name').value.trim();

    if (!name) {
        alert('Please enter a stop name.');
        return;
    }

    const baseUrl = (window.GoPasigConfig && window.GoPasigConfig.stopsBaseUrl)
        ? window.GoPasigConfig.stopsBaseUrl
        : '/admin/api/stops';

    try {
        const response = await fetch(baseUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': getCsrfToken(),
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                route_id: parseInt(activeStopAddingRouteId, 10),
                name: name
            })
        });

        const data = await response.json();
        if (response.ok && data.success) {
            alert(data.message);
            closeAddStopModal();
            if (typeof loadDatabaseFleetData === 'function') {
                await loadDatabaseFleetData();
            }
            syncRoutesWithDatabase();
            renderRoutesTab();
        } else {
            alert(data.message || 'Failed to add stop.');
        }
    } catch (error) {
        alert('Server connection error. Failed to add stop.');
        console.error('AJAX stop create error:', error);
    }
}

// ── HELPERS ──────────────────────────────────────────────────
function format12Hour(time24) {
    if (!time24) return '';
    const parts = time24.split(':');
    let hours = parseInt(parts[0]);
    const minutes = parts[1];
    const ampm = hours >= 12 ? 'PM' : 'AM';
    hours = hours % 12;
    hours = hours ? hours : 12; // the hour '0' should be '12'
    return `${hours}:${minutes} ${ampm}`;
}

function formatMinuteToTime(totMinutes) {
    const hours = Math.floor(totMinutes / 60) % 24;
    const minutes = Math.floor(totMinutes % 60);
    return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
}

function exportScheduleCSV() {
    const headers = ['Trip ID', 'Route', 'Departure Time', 'Driver Initials', 'Driver Full Name', 'Bus Plate', 'Passengers', 'Status'];
    const rows = schedulesData.map(s => [
        s.id,
        s.routeId,
        format12Hour(s.time),
        s.driver,
        s.driverName,
        s.bus,
        s.pax,
        s.status
    ]);
    const csv = [headers, ...rows].map(r => r.map(v => `"${v}"`).join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url; a.download = 'gopasig-trips-schedule.csv'; a.click();
    URL.revokeObjectURL(url);
}

// ── KEYBOARD HANDLERS ─────────────────────────────────────────
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        closeScheduleModal();
        closeResolveModal();
        closeAddStopModal();
    }
});

// Setup listeners on boot
document.addEventListener('DOMContentLoaded', async () => {
    // Initializer will be triggered by switchScreen in navigation.js,
    // but just to be sure we initialize on direct DOM loading as well.
    setupDriversPool();
    await loadDatabaseSchedulesData();
    reScanConflicts();
});
