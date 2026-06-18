<section id="screen-schedules-create" class="hidden space-y-6"
         style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-tertiary:#E8E6DF;--color-border-secondary:#D6D3C9;">

    <!-- BREADCRUMB & HEADER -->
    <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
        <div class="flex items-center gap-4">
            <button onclick="switchScreen('routes'); return false;" 
               class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 border-none" 
               title="Back to Schedule & Routes">
                <i class="ti ti-arrow-left text-lg"></i>
            </button>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Create New Schedule</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Operations</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Schedule & Routes</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span class="text-[#003F87] font-bold">Create Schedule</span>
                </div>
            </div>
        </div>
    </div>

                <!-- FORM CARD -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
                    <div class="mb-6">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Schedule Details</h2>
                        <p class="text-xs text-slate-500">Configure route assignment, vehicle assignment, active driver, and time configurations for the new Libreng Sakay route schedule slot.</p>
                    </div>

                    <form id="create-schedule-form" onsubmit="handleScheduleCreateSubmit(event)" class="space-y-6" novalidate>
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            
                            <!-- Route Selection -->
                            <div class="space-y-2">
                                <label for="sf-route" class="text-xs font-bold uppercase tracking-wider text-slate-500">Route</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-route text-base"></i>
                                    </span>
                                    <select id="sf-route" name="route_id" required onchange="onRouteSelectChange()"
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                        @foreach($routes as $route)
                                            <option value="{{ $route->id }}" {{ ($prefilledRouteId ?? '1') == $route->id ? 'selected' : '' }}>
                                                {{ $route->name }} — {{ $route->description ?? $route->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                        <i class="ti ti-chevron-down text-sm"></i>
                                    </span>
                                </div>
                            </div>

                            <!-- Departure Time -->
                            <div class="space-y-2">
                                <label for="sf-departure" class="text-xs font-bold uppercase tracking-wider text-slate-500">Departure Time</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-clock text-base"></i>
                                    </span>
                                    <input id="sf-departure" name="departure_time" type="time" value="{{ $prefilledTime ?? '08:00' }}" required oninput="onDepartureTimeChange()"
                                           class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                </div>
                            </div>

                            <!-- Bus Selection -->
                            <div class="space-y-2">
                                <label for="sf-bus" class="text-xs font-bold uppercase tracking-wider text-slate-500">Bus</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-bus text-base"></i>
                                    </span>
                                    <select id="sf-bus" name="bus_plate" required onchange="checkFormConflicts()"
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                        <!-- Will be dynamically populated by JS -->
                                    </select>
                                    <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                        <i class="ti ti-chevron-down text-sm"></i>
                                    </span>
                                </div>
                            </div>

                            <!-- Driver Selection -->
                            <div class="space-y-2">
                                <label for="sf-driver" class="text-xs font-bold uppercase tracking-wider text-slate-500">Driver</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-id text-base"></i>
                                    </span>
                                    <select id="sf-driver" name="driver_initials" required onchange="checkFormConflicts()"
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                        <!-- Will be dynamically populated by JS -->
                                    </select>
                                    <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                        <i class="ti ti-chevron-down text-sm"></i>
                                    </span>
                                </div>
                                <div id="sf-driver-expiry-warning" class="hidden flex items-center gap-1.5 text-[11px] text-amber-700 font-semibold">
                                    <i class="ti ti-alert-triangle text-amber-600"></i>
                                    <span id="sf-driver-expiry-warning-text">License expiring soon!</span>
                                </div>
                            </div>

                            <!-- Estimated Arrival Time -->
                            <div class="space-y-2">
                                <label for="sf-arrival" class="text-xs font-bold uppercase tracking-wider text-slate-500">Estimated Arrival Time</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-calendar-time text-base"></i>
                                    </span>
                                    <input id="sf-arrival" name="estimated_arrival" type="time" readonly
                                           class="w-full rounded-lg border border-slate-200 bg-slate-100 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-500 outline-none cursor-not-allowed">
                                </div>
                                <p id="sf-arrival-helper" class="text-[10px] text-slate-400 font-medium">Calculated based on route duration.</p>
                            </div>

                            <!-- Repeat Options -->
                            <div class="space-y-2">
                                <label for="sf-repeat" class="text-xs font-bold uppercase tracking-wider text-slate-500">Repeat Frequency</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-refresh text-base"></i>
                                    </span>
                                    <select id="sf-repeat" name="repeat" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                        <option value="One-time">One-time</option>
                                        <option value="Daily">Daily</option>
                                        <option value="Weekly" selected>Weekly</option>
                                        <option value="Custom">Custom</option>
                                    </select>
                                    <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                        <i class="ti ti-chevron-down text-sm"></i>
                                    </span>
                                </div>
                            </div>

                            <!-- Days Selection checkboxes -->
                            <div class="space-y-2 md:col-span-2">
                                <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Active Operational Days</label>
                                <div class="flex flex-wrap gap-2">
                                    @foreach(['M' => 'Monday', 'T' => 'Tuesday', 'W' => 'Wednesday', 'Th' => 'Thursday', 'F' => 'Friday', 'Sa' => 'Saturday', 'Su' => 'Sunday'] as $key => $name)
                                        <label class="relative flex-1 min-w-[70px] cursor-pointer">
                                            <input type="checkbox" id="day-{{ $key }}" value="{{ $key }}" {{ in_array($key, ['M','T','W','Th','F']) ? 'checked' : '' }} class="peer sr-only">
                                            <span class="flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-600 transition duration-150 hover:bg-slate-50 peer-checked:border-[#003F87] peer-checked:bg-[#003F87] peer-checked:text-white">
                                                {{ $key }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                        </div>

                        <!-- Conflict Warning Alert Box -->
                        <div id="modal-conflict-warning-card" class="hidden flex items-start gap-3 rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-800 leading-relaxed">
                            <i class="ti ti-alert-triangle text-base text-amber-600 shrink-0 mt-0.5"></i>
                            <div>
                                <p class="font-bold">Schedule Overlap Detected</p>
                                <p class="mt-0.5 text-amber-900/80" id="modal-conflict-warning-text">No conflicts detected.</p>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                            <button type="button" onclick="switchScreen('routes'); return false;" 
                               class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer border-none">
                                Cancel
                            </button>
                            <button type="submit" id="schedule-submit-btn" 
                                    class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none">
                                <i class="ti ti-plus mr-1"></i> Create Schedule
                            </button>
                        </div>
                    </form>
                </div>
</section>

<script>
    // Route duration mapping to calculate estimated arrival times
    window.ROUTE_DURATIONS = window.ROUTE_DURATIONS || {
        @foreach($routes as $route)
            "{{ $route->id }}": {{ $route->travel_time_minutes ?? $defaultTravelTime ?? 30 }},
        @endforeach
    };
    var ROUTE_DURATIONS = window.ROUTE_DURATIONS;

    // Schedule conflict buffer (driver fatigue protection) in minutes
    const scheduleBuffer = {{ $scheduleBuffer ?? 15 }};

    // Store raw schedules for conflict checks
    window.schedulesData = window.schedulesData || [];
    var schedulesData = window.schedulesData;

    var createPageDriversList = [];
    var createPageBusesList = [];

    // Expose openCreateScheduleForm helper globally to allow prefilling dynamically in the SPA
    function openCreateScheduleForm(routeId, timeStr) {
        const routeSelect = document.getElementById('sf-route');
        const departureInput = document.getElementById('sf-departure');
        
        if (routeSelect && routeId) {
            routeSelect.value = routeId;
        }
        if (departureInput) {
            departureInput.value = timeStr || "08:00";
        }
        
        // Reset checkbox days to default (Mon-Fri active)
        const days = ['M', 'T', 'W', 'Th', 'F', 'Sa', 'Su'];
        days.forEach(d => {
            const el = document.getElementById(`day-${d}`);
            if (el) {
                el.checked = ['M','T','W','Th','F'].includes(d);
            }
        });

        // Trigger calculations
        if (typeof onRouteSelectChange === 'function') {
            onRouteSelectChange();
        }

        switchScreen('schedules-create');
    }

    async function loadSchedulesAndResourcePools() {
        try {
            // Load schedules
            const schedulesUrl = "{{ route('admin.api.schedules.index') }}";
            const resSchedules = await fetch(schedulesUrl);
            const dataSchedules = await resSchedules.json();
            if (resSchedules.ok && dataSchedules.success) {
                schedulesData = dataSchedules.schedules;
            }

            // Load fleet data to know driver and bus structures
            const fleetUrl = "{{ route('admin.api.fleet-data') }}";
            const resFleet = await fetch(fleetUrl);
            const dataFleet = await resFleet.json();
            if (resFleet.ok) {
                createPageBusesList = dataFleet.buses || [];
            }

            // Load drivers list from driver API
            const driversUrl = "{{ route('admin.api.drivers.index') }}";
            const resDrivers = await fetch(driversUrl);
            const dataDrivers = await resDrivers.json();
            if (resDrivers.ok && dataDrivers.success) {
                createPageDriversList = (dataDrivers.drivers || []).map(d => {
                    d.initials = (d.first_name ? d.first_name.charAt(0).toUpperCase() : '') + (d.last_name ? d.last_name.charAt(0).toUpperCase() : '');
                    return d;
                });
            }
            
            // Re-populate and scan availability options
            syncDropdownAvailability();
        } catch (err) {
            console.error("Failed to load conflict verification datasets:", err);
        }
    }

    function syncDropdownAvailability() {
        const busSelect = document.getElementById('sf-bus');
        const driverSelect = document.getElementById('sf-driver');
        const timeVal = document.getElementById('sf-departure').value;

        if (!timeVal) return;

        const currentSelectedBus = busSelect.value;
        const currentSelectedDriver = driverSelect.value;

        const hour = parseInt(timeVal.split(':')[0]);
        
        // Find bus/driver scheduled in this exact hour slot
        const conflictingSchedules = schedulesData.filter(s => {
            const sHour = parseInt(s.time.split(':')[0]);
            return sHour === hour;
        });

        const busyBuses = conflictingSchedules.map(s => s.bus);
        const busyDrivers = conflictingSchedules.map(s => s.driver);

        // Repopulate Bus Select
        busSelect.innerHTML = createPageBusesList.map(bus => {
            const isBusy = busyBuses.includes(bus.plate_number);
            const disabledAttr = isBusy ? 'disabled style="color:var(--color-text-secondary);cursor:not-allowed;"' : '';
            const suffix = isBusy ? ` (Scheduled ${timeVal} ✗)` : ' — Active ✓';
            const selected = bus.plate_number === currentSelectedBus ? 'selected' : '';
            return `<option value="${bus.plate_number}" ${disabledAttr} ${selected}>${bus.plate_number}${suffix}</option>`;
        }).join('');

        // Repopulate Driver Select
        driverSelect.innerHTML = createPageDriversList.map(driver => {
            const isBusy = busyDrivers.includes(driver.initials);
            const isSuspended = driver.status === 'suspended';
            
            let disabledAttr = '';
            let suffix = ' — Active ✓';

            if (isSuspended) {
                disabledAttr = 'disabled style="color:var(--color-text-secondary);cursor:not-allowed;"';
                suffix = ' (Suspended ✗)';
            } else if (isBusy) {
                disabledAttr = 'disabled style="color:var(--color-text-secondary);cursor:not-allowed;"';
                suffix = ` (Scheduled ${timeVal} ✗)`;
            }
            const selected = driver.initials === currentSelectedDriver ? 'selected' : '';
            return `<option value="${driver.initials}" ${disabledAttr} ${selected}>${driver.first_name} ${driver.last_name} (${driver.initials})${suffix}</option>`;
        }).join('');

        checkFormConflicts();
    }

    function onRouteSelectChange() {
        const routeVal = document.getElementById('sf-route').value;
        const timeVal = document.getElementById('sf-departure').value;
        
        // Update estimated arrival time
        const duration = ROUTE_DURATIONS[routeVal] || 30;
        const helperText = document.getElementById('sf-arrival-helper');
        if (helperText) {
            helperText.textContent = `Based on Route ${routeVal} average duration: ${duration} minutes.`;
        }

        if (timeVal) {
            const parts = timeVal.split(':').map(Number);
            const depMinutes = parts[0] * 60 + parts[1];
            const arrMinutes = depMinutes + duration;
            
            const arrHour = Math.floor(arrMinutes / 60) % 24;
            const arrMinute = arrMinutes % 60;
            
            document.getElementById('sf-arrival').value = 
                `${arrHour.toString().padStart(2, '0')}:${arrMinute.toString().padStart(2, '0')}`;
        }

        syncDropdownAvailability();
    }

    function onDepartureTimeChange() {
        onRouteSelectChange();
    }

    function checkFormConflicts() {
        const driverVal = document.getElementById('sf-driver').value;
        const busVal = document.getElementById('sf-bus').value;
        const routeVal = document.getElementById('sf-route').value;
        const timeVal = document.getElementById('sf-departure').value;

        const warnCard = document.getElementById('modal-conflict-warning-card');
        const warnText = document.getElementById('modal-conflict-warning-text');
        const submitBtn = document.getElementById('schedule-submit-btn');

        warnCard.classList.add('hidden');
        if (submitBtn) {
            submitBtn.disabled = false;
        }

        if (!timeVal || !driverVal) return;

        // Perform time overlap scan
        const hour = parseInt(timeVal.split(':')[0]);
        const duration = ROUTE_DURATIONS[routeVal] || 30;
        const startMin = hour * 60 + parseInt(timeVal.split(':')[1]);
        const endMin = startMin + duration;

        const conflict = schedulesData.find(s => {
            const isSameDriver = s.driver === driverVal;
            const isSameBus = s.bus === busVal;

            if (isSameDriver || isSameBus) {
                const sParts = s.time.split(':').map(Number);
                const sStart = sParts[0] * 60 + sParts[1];
                const sDuration = ROUTE_DURATIONS[s.routeId] || 30;
                const sEnd = sStart + sDuration;

                const buffer = isSameDriver ? scheduleBuffer : 0;
                return (startMin < (sEnd + buffer)) && (sStart < (endMin + buffer));
            }
            return false;
        });

        if (conflict) {
            const isDriver = conflict.driver === driverVal;
            const entityName = isDriver ? `Driver ${conflict.driverName}` : `Bus ${conflict.bus}`;
            const relation = isDriver ? 'is already assigned to' : 'is already scheduled on';
            
            warnText.textContent = `${entityName} ${relation} Route ${conflict.routeId} at ${conflict.time}. Select a different option or change the departure time.`;
            warnCard.classList.remove('hidden');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        }

        // License expiry checking
        checkDriverLicenseExpiry(driverVal);
    }

    function checkDriverLicenseExpiry(driverInitials) {
        const warningLabel = document.getElementById('sf-driver-expiry-warning');
        const driver = createPageDriversList.find(d => d.initials === driverInitials);
        
        if (driver && driver.license_expiry) {
            const exp = new Date(driver.license_expiry);
            const today = new Date();
            today.setHours(0,0,0,0);
            const diff = Math.floor((exp - today) / 86400000);
            
            if (diff <= 30) {
                const labelText = document.getElementById('sf-driver-expiry-warning-text');
                labelText.textContent = diff < 0 
                    ? `Driver license has EXPIRED! Please suspend operations.` 
                    : `Driver license expiring soon in ${diff} days (${exp.toLocaleDateString()})`;
                warningLabel.classList.remove('hidden');
            } else {
                warningLabel.classList.add('hidden');
            }
        } else {
            warningLabel.classList.add('hidden');
        }
    }

    async function handleScheduleCreateSubmit(event) {
        event.preventDefault();

        const submitBtn = document.getElementById('schedule-submit-btn');
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

        try {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="ti ti-loader mr-1 animate-spin"></i> Creating...';
            }

            const response = await fetch("{{ route('admin.api.schedules.store') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (response.ok && data.success) {
                alert(data.message);
                switchScreen('routes');
                // Reload schedules in the grid and list dynamically
                if (typeof initRoutesDashboard === 'function') {
                    initRoutesDashboard();
                }
            } else {
                alert(data.message || 'Validation error. Please verify schedule details.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="ti ti-plus mr-1"></i> Create Schedule';
                }
            }
        } catch (error) {
            alert('Server connection error. Failed to save schedule.');
            console.error('AJAX Schedule submit error:', error);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="ti ti-plus mr-1"></i> Create Schedule';
            }
        }
    }
</script>

<style>
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .animate-fade-in {
        animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
</style>
