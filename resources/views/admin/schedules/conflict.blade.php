{{-- ==================== SCHEDULING CONFLICT CHECK SCREEN ==================== --}}
<section id="screen-schedules-conflict" class="hidden">
    <!-- BREADCRUMB & HEADER -->
    <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
        <div class="flex items-center gap-4">
            <a href="#routes" onclick="switchScreen('routes'); return false;" 
               class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95" 
               title="Back to Schedule & Routes">
                <i class="ti ti-arrow-left text-lg"></i>
            </a>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Scheduling Conflict Check</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Operations</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Schedule & Routes</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span class="text-[#003F87] font-bold">Conflict Check</span>
                </div>
            </div>
        </div>
    </div>

    <!-- OVERVIEW STATISTICS -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex items-center gap-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-slate-600" id="total-badge-color">
                <i class="ti ti-alert-triangle text-xl"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Conflicts</p>
                <h3 class="text-lg font-black text-slate-900 mt-0.5" id="cnt-total">0 found</h3>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex items-center gap-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-600">
                <i class="ti ti-user-x text-xl"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Driver Overlaps</p>
                <h3 class="text-lg font-black text-slate-900 mt-0.5" id="cnt-drivers">0 conflicts</h3>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex items-center gap-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                <i class="ti ti-bus text-xl"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Bus Overlaps</p>
                <h3 class="text-lg font-black text-slate-900 mt-0.5" id="cnt-buses">0 conflicts</h3>
            </div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm flex items-center gap-4">
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                <i class="ti ti-timeline text-xl"></i>
            </div>
            <div>
                <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Scheduling Gaps</p>
                <h3 class="text-lg font-black text-slate-900 mt-0.5" id="cnt-gaps">0 gaps</h3>
            </div>
        </div>
    </div>

    <!-- MAIN WORKSPACE CONTAINER -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm min-h-[400px]">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-4 mb-6 gap-3">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-900">Active Conflict Queue</h2>
                <p class="text-xs text-slate-500">Live operational scan of schedule overlaps. Resolve items below to optimize driver dispatching times.</p>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="reScanConflicts()" class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                    <i class="ti ti-refresh"></i> Re-Scan schedules
                </button>
            </div>
        </div>

        <!-- CONFLICT LIST WRAPPER -->
        <div id="conflicts-container" class="space-y-4">
            <!-- Loaded dynamically via JS -->
            <div class="flex flex-col items-center justify-center py-16 text-slate-400">
                <i class="ti ti-loader text-3xl animate-spin"></i>
                <p class="text-xs font-semibold mt-2">Scanning schedules operational database...</p>
            </div>
        </div>
    </div>
</section>

<script>
    // System travel time duration references
    window.ROUTE_DURATIONS = window.ROUTE_DURATIONS || {
        1: 25,
        2: 45,
        3: 35,
        4: 40
    };
    var ROUTE_DURATIONS = window.ROUTE_DURATIONS;

    window.schedulesData = window.schedulesData || [];
    var schedulesData = window.schedulesData;

    window.driversList = window.driversList || [];
    var driversList = window.driversList;

    window.busesList = window.busesList || [];
    var busesList = window.busesList;

    window.conflictsList = window.conflictsList || [];
    var conflictsList = window.conflictsList;


    async function loadDatabaseResources() {
        try {
            // Load schedules
            const schedulesUrl = "{{ route('admin.api.schedules.index') }}";
            const resSchedules = await fetch(schedulesUrl);
            const dataSchedules = await resSchedules.json();
            if (resSchedules.ok && dataSchedules.success) {
                schedulesData = dataSchedules.schedules;
            }

            // Load fleet/buses list
            const fleetUrl = "{{ route('admin.api.fleet-data') }}";
            const resFleet = await fetch(fleetUrl);
            const dataFleet = await resFleet.json();
            if (resFleet.ok) {
                busesList = dataFleet.buses || [];
            }

            // Load drivers list
            const driversUrl = "{{ route('admin.api.drivers.index') }}";
            const resDrivers = await fetch(driversUrl);
            const dataDrivers = await resDrivers.json();
            if (resDrivers.ok && dataDrivers.success) {
                driversList = dataDrivers.drivers || [];
            }

            reScanConflicts();
        } catch (err) {
            console.error("Failed to load conflict checking databases:", err);
            document.getElementById('conflicts-container').innerHTML = `
                <div class="flex flex-col items-center justify-center py-16 text-rose-600">
                    <i class="ti ti-alert-triangle text-3xl"></i>
                    <p class="text-xs font-semibold mt-2">Error connecting to server resources. Please reload the page.</p>
                </div>
            `;
        }
    }

    function reScanConflicts() {
        conflictsList = [];

        // 1. Scan: Check for double-booked drivers (within duration + 15 min buffer)
        for (let i = 0; i < schedulesData.length; i++) {
            const s1 = schedulesData[i];
            const duration1 = ROUTE_DURATIONS[s1.routeId] || 30;

            const time1Parts = s1.time.split(':').map(Number);
            const start1 = time1Parts[0] * 60 + time1Parts[1];
            const end1 = start1 + duration1;

            for (let j = i + 1; j < schedulesData.length; j++) {
                const s2 = schedulesData[j];
                
                if (s1.driver && s2.driver && s1.driver === s2.driver) {
                    const time2Parts = s2.time.split(':').map(Number);
                    const start2 = time2Parts[0] * 60 + time2Parts[1];
                    
                    const isOverlapping = (start2 >= start1 && start2 < (end1 + 15)) || (start1 >= start2 && start1 < (start2 + 15));
                    
                    if (isOverlapping) {
                        const diffMin = Math.abs(start2 - start1);
                        conflictsList.push({
                            id: `drv-${s1.id}-${s2.id}`,
                            type: 'Driver conflict',
                            severity: 'High',
                            entityName: `${s1.driverName} (${s1.driver}) — double booked`,
                            description: `Assigned to Route ${s2.routeId} at ${format12Hour(s2.time)} and Route ${s1.routeId} at ${format12Hour(s1.time)}. Insufficient transition buffer (${diffMin} minutes).`,
                            affectedIds: [s1.id, s2.id],
                            driverInitials: s1.driver,
                            schedule1: s1,
                            schedule2: s2
                        });
                    }
                }

                // Check bus double book
                if (s1.bus && s2.bus && s1.bus === s2.bus) {
                    const time2Parts = s2.time.split(':').map(Number);
                    const start2 = time2Parts[0] * 60 + time2Parts[1];
                    const duration2 = ROUTE_DURATIONS[s2.routeId] || 30;
                    const end2 = start2 + duration2;

                    const isOverlapping = (start2 >= start1 && start2 < end1) || (start1 >= start2 && start1 < end2) || (Math.abs(start2 - start1) <= 120);

                    if (isOverlapping) {
                        conflictsList.push({
                            id: `bus-${s1.id}-${s2.id}`,
                            type: 'Bus conflict',
                            severity: 'High',
                            entityName: `Bus ${s1.bus} — assigned to two routes`,
                            description: `Scheduled on Route ${s2.routeId} at ${format12Hour(s2.time)} and Route ${s1.routeId} at ${format12Hour(s1.time)}. Potential transit delay overlap.`,
                            affectedIds: [s1.id, s2.id],
                            busPlate: s1.bus,
                            schedule1: s1,
                            schedule2: s2
                        });
                    }
                }
            }
        }

        // 2. GAP CHECK: Route C (ID 3) no coverage 1:00 to 4:00 PM
        const routeCTrips = schedulesData.filter(s => s.routeId === '3' || s.routeId === 'C');
        const hasGapC = !routeCTrips.some(s => {
            const hour = parseInt(s.time.split(':')[0]);
            return hour >= 13 && hour <= 16;
        });

        if (hasGapC) {
            conflictsList.push({
                id: 'gap-c-afternoon',
                type: 'Scheduling gap',
                severity: 'Medium',
                entityName: 'Route C — afternoon gap',
                description: 'No Libreng Sakay bus assigned on Route C between 1:00 PM and 4:00 PM. High commuter demand predicted.',
                affectedIds: [],
                metaText: 'Route C · 13:00–16:00'
            });
        }

        renderConflictsQueue();
    }

    function renderConflictsQueue() {
        const container = document.getElementById('conflicts-container');
        if (!container) return;
        
        // Update stats
        const drvCount = conflictsList.filter(c => c.type === 'Driver conflict').length;
        const busCount = conflictsList.filter(c => c.type === 'Bus conflict').length;
        const gapCount = conflictsList.filter(c => c.type === 'Scheduling gap').length;
        const total = conflictsList.length;

        const cntTotalEl = document.getElementById('cnt-total');
        if (cntTotalEl) cntTotalEl.textContent = `${total} found`;
        
        const cntDriversEl = document.getElementById('cnt-drivers');
        if (cntDriversEl) cntDriversEl.textContent = `${drvCount} overlap${drvCount !== 1 ? 's' : ''}`;
        
        const cntBusesEl = document.getElementById('cnt-buses');
        if (cntBusesEl) cntBusesEl.textContent = `${busCount} overlap${busCount !== 1 ? 's' : ''}`;
        
        const cntGapsEl = document.getElementById('cnt-gaps');
        if (cntGapsEl) cntGapsEl.textContent = `${gapCount} gap${gapCount !== 1 ? 's' : ''}`;

        const badgeColorBox = document.getElementById('total-badge-color');
        if (badgeColorBox) {
            if (total > 0) {
                badgeColorBox.className = "flex h-10 w-10 items-center justify-center rounded-lg bg-red-50 text-red-600 animate-pulse";
            } else {
                badgeColorBox.className = "flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600";
            }
        }

        if (total === 0) {
            container.innerHTML = `
                <div class="flex flex-col items-center justify-center py-16 text-slate-500 text-center space-y-4">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 text-emerald-600">
                        <i class="ti ti-circle-check text-3xl"></i>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-900">No Scheduling Conflicts Found</h3>
                        <p class="text-xs text-slate-500 mt-0.5">Everything is running smoothly! All drivers and buses are assigned with safe buffers.</p>
                    </div>
                </div>
            `;
            return;
        }

        container.innerHTML = conflictsList.map(c => {
            const isHigh = c.severity === 'High';
            const badgeClass = isHigh ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-amber-50 text-amber-800 border border-amber-100';
            const iconClass = isHigh ? 'ti-alert-circle text-red-500' : 'ti-alert-triangle text-amber-500';

            let resolutionControls = '';
            if (isHigh) {
                resolutionControls = `
                    <div class="mt-4 pt-4 border-t border-slate-100">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-2">Resolve Conflict Inline</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            
                            <!-- Reassign Option -->
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3.5 space-y-3">
                                <label class="flex items-center gap-2 text-xs font-bold text-slate-900 cursor-pointer">
                                    <input type="radio" name="res-choice-${c.id}" value="reassign" checked onchange="toggleResolutionChoice('${c.id}')" class="accent-[#003F87]">
                                    Reassign Driver
                                </label>
                                <div id="res-wrap-reassign-${c.id}" class="space-y-2">
                                    <select id="res-select-driver-${c.id}" class="w-full rounded-lg border border-slate-200 bg-white py-1.5 px-3 text-xs font-semibold outline-none focus:border-[#003F87]">
                                        ${driversList
                                            .filter(d => d.status === 'active' && d.initials !== c.driverInitials)
                                            .map(d => `<option value="${d.initials}">${d.first_name} ${d.last_name} (${d.initials})</option>`)
                                            .join('')}
                                    </select>
                                </div>
                            </div>

                            <!-- Shifting departure time -->
                            <div class="rounded-xl border border-slate-200 bg-slate-50/50 p-3.5 space-y-3">
                                <label class="flex items-center gap-2 text-xs font-bold text-slate-900 cursor-pointer">
                                    <input type="radio" name="res-choice-${c.id}" value="adjust" onchange="toggleResolutionChoice('${c.id}')" class="accent-[#003F87]">
                                    Adjust Departure
                                </label>
                                <div id="res-wrap-adjust-${c.id}" class="hidden space-y-2">
                                    <input type="time" id="res-time-${c.id}" value="08:00" class="w-full rounded-lg border border-slate-200 bg-white py-1.5 px-3 text-xs font-semibold outline-none focus:border-[#003F87]">
                                </div>
                            </div>

                            <!-- Remove schedule -->
                            <div class="rounded-xl border border-red-200 bg-red-50/20 p-3.5 flex flex-col justify-between">
                                <label class="flex items-center gap-2 text-xs font-bold text-red-700 cursor-pointer">
                                    <input type="radio" name="res-choice-${c.id}" value="remove" onchange="toggleResolutionChoice('${c.id}')" class="accent-red-600">
                                    Cancel & Delete Slot
                                </label>
                                <p class="text-[10px] text-slate-500 mt-1">Permanently deletes one of the overlapping trips from schedule grid database.</p>
                            </div>

                        </div>

                        <div class="flex items-center justify-end gap-2 mt-4">
                            <button onclick="applyResolution('${c.id}')" class="rounded-lg bg-[#003F87] px-4 py-2 text-xs font-bold text-white hover:bg-[#002d62] transition duration-150 shadow-sm cursor-pointer">
                                Apply Resolution
                            </button>
                        </div>
                    </div>
                `;
            } else {
                resolutionControls = `
                    <div class="mt-4 pt-4 border-t border-slate-100 flex items-center justify-between">
                        <p class="text-[11px] text-slate-500 font-medium">To fix gaps, navigate to scheduling board and create matching slots.</p>
                        <button onclick="switchScreen('routes')" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:bg-slate-50 transition">
                            Create Trip Slot
                        </button>
                    </div>
                `;
            }

            return `
                <div class="rounded-xl border border-slate-200 p-5 bg-white space-y-3 hover:shadow-md transition-shadow duration-200" id="conflict-card-${c.id}">
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider ${badgeClass}">
                            <i class="ti ${iconClass} text-xs"></i>
                            ${c.type}
                        </span>
                        <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Severity: ${c.severity}</span>
                    </div>

                    <div>
                        <h3 class="text-sm font-bold text-slate-900">${c.entityName}</h3>
                        <p class="text-xs text-slate-500 mt-1">${c.description}</p>
                    </div>

                    ${c.affectedIds.length > 0 ? `
                        <div class="flex gap-2.5 mt-2">
                            <span class="rounded bg-slate-100 border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                Slot 1: Route ${c.schedule1.routeId} at ${format12Hour(c.schedule1.time)}
                            </span>
                            <span class="rounded bg-slate-100 border border-slate-200 px-2.5 py-1 text-[11px] font-semibold text-slate-700">
                                Slot 2: Route ${c.schedule2.routeId} at ${format12Hour(c.schedule2.time)}
                            </span>
                        </div>
                    ` : ''}

                    ${resolutionControls}
                </div>
            `;
        }).join('');
    }

    function toggleResolutionChoice(conflictId) {
        const choice = document.querySelector(`input[name="res-choice-${conflictId}"]:checked`).value;
        const reassignWrap = document.getElementById(`res-wrap-reassign-${conflictId}`);
        const adjustWrap = document.getElementById(`res-wrap-adjust-${conflictId}`);

        if (reassignWrap) reassignWrap.classList.toggle('hidden', choice !== 'reassign');
        if (adjustWrap) adjustWrap.classList.toggle('hidden', choice !== 'adjust');
    }

    async function applyResolution(conflictId) {
        const conflict = conflictsList.find(c => c.id === conflictId);
        if (!conflict) return;

        const choice = document.querySelector(`input[name="res-choice-${conflictId}"]:checked`).value;
        const affectedScheduleId = conflict.affectedIds[0]; // Resolve on first affected schedule slot
        const schedule = schedulesData.find(s => s.id === affectedScheduleId);
        if (!schedule) return;

        const token = getCsrfToken();
        const baseUrl = "{{ url('admin/api/schedules') }}";

        try {
            if (choice === 'remove') {
                if (!confirm(`Are you sure you want to permanently cancel and delete Route ${schedule.routeId} schedule at ${format12Hour(schedule.time)}?`)) return;

                const response = await fetch(`${baseUrl}/${affectedScheduleId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    }
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    alert('Schedule slot successfully cancelled.');
                    await loadDatabaseResources();
                } else {
                    alert(data.message || 'Failed to remove schedule.');
                }
            } else {
                let driverInitials = schedule.driver;
                let departureTime = schedule.time;

                if (choice === 'reassign') {
                    driverInitials = document.getElementById(`res-select-driver-${conflictId}`).value;
                } else if (choice === 'adjust') {
                    departureTime = document.getElementById(`res-time-${conflictId}`).value;
                }

                const payload = {
                    route_id: schedule.routeId,
                    bus_plate: schedule.bus,
                    driver_initials: driverInitials,
                    departure_time: departureTime
                };

                const response = await fetch(`${baseUrl}/${affectedScheduleId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    alert('Schedule updated successfully. Conflict resolved!');
                    await loadDatabaseResources();
                } else {
                    alert(data.message || 'Failed to update schedule.');
                }
            }
        } catch (err) {
            console.error("Failed to execute inline conflict resolution AJAX:", err);
            alert('Server connection error. Failed to apply resolution.');
        }
    }

    // Helper: format military to 12-hour
    function format12Hour(timeStr) {
        if (!timeStr) return '';
        const parts = timeStr.split(':');
        let hours = parseInt(parts[0]);
        const minutes = parts[1];
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        return `${hours}:${minutes} ${ampm}`;
    }
</script>
