<section id="screen-schedules-edit" class="hidden space-y-6"
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
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Edit Schedule</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Operations</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Schedule & Routes</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span class="text-[#003F87] font-bold">Edit Schedule</span>
                </div>
            </div>
        </div>
    </div>

    <!-- FORM CARD -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
        <div class="mb-6 flex justify-between items-start">
            <div>
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Schedule Details</h2>
                <p class="text-xs text-slate-500">Configure route assignment, vehicle assignment, active driver, and time configurations for the Libreng Sakay route schedule slot.</p>
            </div>
        </div>

        <form id="edit-schedule-form" onsubmit="handleScheduleEditPageSubmit(event)" class="space-y-6" novalidate>
            @csrf
            <input type="hidden" id="sf-edit-schedule-id" name="schedule_id" value="">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                
                <!-- Route Selection -->
                <div class="space-y-2">
                    <label for="sf-edit-route" class="text-xs font-bold uppercase tracking-wider text-slate-500">Route</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-route text-base"></i>
                        </span>
                        <select id="sf-edit-route" name="route_id" required onchange="onEditPageRouteSelectChange()"
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                            @foreach($routes as $route)
                                <option value="{{ $route->id }}">
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
                    <label for="sf-edit-departure" class="text-xs font-bold uppercase tracking-wider text-slate-500">Departure Time</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-clock text-base"></i>
                        </span>
                        <input id="sf-edit-departure" name="departure_time" type="time" required oninput="onEditPageDepartureTimeChange()"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                </div>

                <!-- Bus Selection -->
                <div class="space-y-2">
                    <label for="sf-edit-bus" class="text-xs font-bold uppercase tracking-wider text-slate-500">Bus</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-bus text-base"></i>
                        </span>
                        <select id="sf-edit-bus" name="bus_plate" required onchange="checkEditPageFormConflicts()"
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
                    <label for="sf-edit-driver" class="text-xs font-bold uppercase tracking-wider text-slate-500">Driver</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-id text-base"></i>
                        </span>
                        <select id="sf-edit-driver" name="driver_id" required onchange="checkEditPageFormConflicts()"
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                            <!-- Will be dynamically populated by JS -->
                        </select>
                        <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                            <i class="ti ti-chevron-down text-sm"></i>
                        </span>
                    </div>
                    <div id="sf-edit-driver-expiry-warning" class="hidden flex items-center gap-1.5 text-[11px] text-amber-700 font-semibold">
                        <i class="ti ti-alert-triangle text-amber-600"></i>
                        <span id="sf-edit-driver-expiry-warning-text">License expiring soon!</span>
                    </div>
                </div>

                <!-- Estimated Arrival Time -->
                <div class="space-y-2">
                    <label for="sf-edit-arrival" class="text-xs font-bold uppercase tracking-wider text-slate-500">Estimated Arrival Time</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-calendar-time text-base"></i>
                        </span>
                        <input id="sf-edit-arrival" name="estimated_arrival" type="time" readonly
                               class="w-full rounded-lg border border-slate-200 bg-slate-100 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-500 outline-none cursor-not-allowed">
                    </div>
                    <p id="sf-edit-arrival-helper" class="text-[10px] text-slate-400 font-medium">Calculated based on route duration.</p>
                </div>

                <!-- Repeat Options -->
                <div class="space-y-2">
                    <label for="sf-edit-repeat" class="text-xs font-bold uppercase tracking-wider text-slate-500">Repeat Frequency</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-refresh text-base"></i>
                        </span>
                        <select id="sf-edit-repeat" name="repeat" required
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
                                <input type="checkbox" id="day-edit-{{ $key }}" value="{{ $key }}" class="peer sr-only">
                                <span class="flex h-10 items-center justify-center rounded-lg border border-slate-200 bg-white text-xs font-bold text-slate-600 transition duration-150 hover:bg-slate-50 peer-checked:border-[#003F87] peer-checked:bg-[#003F87] peer-checked:text-white">
                                    {{ $key }}
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

            </div>

            <!-- Conflict Warning Alert Box -->
            <div id="edit-modal-conflict-warning-card" class="hidden flex items-start gap-3 rounded-xl bg-amber-50 border border-amber-200 p-4 text-xs text-amber-800 leading-relaxed">
                <i class="ti ti-alert-triangle text-base text-amber-600 shrink-0 mt-0.5"></i>
                <div>
                    <p class="font-bold">Schedule Overlap Detected</p>
                    <p class="mt-0.5 text-amber-900/80" id="edit-modal-conflict-warning-text">No conflicts detected.</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="pt-6 flex items-center justify-between border-t border-slate-100 mt-8">
                <button type="button" onclick="handleEditPageDeleteSchedule()" 
                        class="text-xs font-semibold text-rose-600 hover:text-rose-800 transition duration-200 cursor-pointer underline border-none bg-none bg-transparent">
                    Delete Schedule Slot
                </button>
                <div class="flex items-center gap-3">
                    <button type="button" onclick="switchScreen('routes'); return false;" 
                       class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer border-none">
                        Cancel
                    </button>
                    <button type="submit" id="edit-schedule-submit-btn" 
                            class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none">
                        Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</section>

<script>
    var editPageDriversList = [];
    var editPageBusesList = [];
    const editScheduleBuffer = {{ $scheduleBuffer }};
    const editBusScheduleBuffer = {{ $busScheduleBuffer }};
    const editLicenseWarningDays = {{ $licenseWarningDays }};
    const editDefaultTravelTime = {{ $defaultTravelTime }};

    // Expose openEditScheduleForm helper globally to allow prefilling dynamically in the SPA page
    async function openEditScheduleForm(scheduleId) {
        if (!scheduleId) return;
        
        // Ensure lists are loaded
        if (window.schedulesData.length === 0 || editPageDriversList.length === 0 || editPageBusesList.length === 0) {
            await loadSchedulesAndResourcePoolsForEditPage();
        }

        const schedule = window.schedulesData.find(s => s.id === scheduleId);
        if (!schedule) {
            alert('Schedule entry not found.');
            return;
        }

        document.getElementById('sf-edit-schedule-id').value = scheduleId;
        document.getElementById('sf-edit-route').value = schedule.routeId;
        document.getElementById('sf-edit-departure').value = schedule.time;

        // Prefill select options and pick the active ones
        syncEditPageDropdownAvailability();
        
        document.getElementById('sf-edit-bus').value = schedule.bus;
        document.getElementById('sf-edit-driver').value = schedule.driverId;
        document.getElementById('sf-edit-repeat').value = 'Weekly'; // default

        // Precheck days
        ['M','T','W','Th','F','Sa','Su'].forEach(d => {
            const el = document.getElementById(`day-edit-${d}`);
            if (el) el.checked = true;
        });

        // Trigger calculations
        onEditPageRouteSelectChange();

        // Switch to the edit screen
        switchScreen('schedules-edit');
    }

    async function loadSchedulesAndResourcePoolsForEditPage() {
        try {
            // Load schedules
            const schedulesUrl = "{{ route('admin.api.schedules.index') }}";
            const resSchedules = await fetch(schedulesUrl);
            const dataSchedules = await resSchedules.json();
            if (resSchedules.ok && dataSchedules.success) {
                window.schedulesData = dataSchedules.schedules;
            }

            // Load fleet data to know driver and bus structures
            const fleetUrl = "{{ route('admin.api.fleet-data') }}";
            const resFleet = await fetch(fleetUrl);
            const dataFleet = await resFleet.json();
            if (resFleet.ok) {
                editPageBusesList = dataFleet.buses || [];
            }

            // Load drivers list from driver API
            const driversUrl = "{{ route('admin.api.drivers.index') }}";
            const resDrivers = await fetch(driversUrl);
            const dataDrivers = await resDrivers.json();
            if (resDrivers.ok && dataDrivers.success) {
                editPageDriversList = (dataDrivers.drivers || []).map(d => {
                    d.initials = (d.first_name ? d.first_name.charAt(0).toUpperCase() : '') + (d.last_name ? d.last_name.charAt(0).toUpperCase() : '');
                    return d;
                });
            }
        } catch (err) {
            console.error("Failed to load edit verification datasets:", err);
        }
    }

    function syncEditPageDropdownAvailability() {
        const busSelect = document.getElementById('sf-edit-bus');
        const driverSelect = document.getElementById('sf-edit-driver');
        const timeVal = document.getElementById('sf-edit-departure').value;
        const scheduleId = parseInt(document.getElementById('sf-edit-schedule-id').value);

        if (!timeVal) return;

        const currentSelectedBus = busSelect.value;
        const currentSelectedDriver = driverSelect.value;

        const hour = parseInt(timeVal.split(':')[0]);
        
        // Find bus/driver scheduled in this exact hour slot (excluding current schedule)
        const conflictingSchedules = window.schedulesData.filter(s => {
            if (s.id === scheduleId) return false;
            const sHour = parseInt(s.time.split(':')[0]);
            return sHour === hour;
        });

        const busyBuses = conflictingSchedules.map(s => s.bus);
        const busyDrivers = conflictingSchedules.map(s => String(s.driverId));

        // Repopulate Bus Select
        busSelect.innerHTML = editPageBusesList.map(bus => {
            const isBusy = busyBuses.includes(bus.plate_number);
            const disabledAttr = isBusy ? 'disabled style="color:var(--color-text-secondary);cursor:not-allowed;"' : '';
            const suffix = isBusy ? ` (Scheduled ${timeVal} ✗)` : ' — Active ✓';
            const selected = bus.plate_number === currentSelectedBus ? 'selected' : '';
            return `<option value="${bus.plate_number}" ${disabledAttr} ${selected}>${bus.plate_number}${suffix}</option>`;
        }).join('');

        // Repopulate Driver Select
        driverSelect.innerHTML = editPageDriversList.map(driver => {
            const isBusy = busyDrivers.includes(String(driver.id));
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
            const selected = String(driver.id) === String(currentSelectedDriver) ? 'selected' : '';
            return `<option value="${driver.id}" ${disabledAttr} ${selected}>${driver.first_name} ${driver.last_name} (${driver.initials})${suffix}</option>`;
        }).join('');

        checkEditPageFormConflicts();
    }

    function onEditPageRouteSelectChange() {
        const routeVal = document.getElementById('sf-edit-route').value;
        const timeVal = document.getElementById('sf-edit-departure').value;
        
        // Update estimated arrival time
        const duration = window.ROUTE_DURATIONS[routeVal] || editDefaultTravelTime;
        const helperText = document.getElementById('sf-edit-arrival-helper');
        if (helperText) {
            helperText.textContent = `Based on Route ${routeVal} average duration: ${duration} minutes.`;
        }

        if (timeVal) {
            const parts = timeVal.split(':').map(Number);
            const depMinutes = parts[0] * 60 + parts[1];
            const arrMinutes = depMinutes + duration;
            
            const arrHour = Math.floor(arrMinutes / 60) % 24;
            const arrMinute = arrMinutes % 60;
            
            document.getElementById('sf-edit-arrival').value = 
                `${arrHour.toString().padStart(2, '0')}:${arrMinute.toString().padStart(2, '0')}`;
        }

        syncEditPageDropdownAvailability();
    }

    function onEditPageDepartureTimeChange() {
        onEditPageRouteSelectChange();
    }

    function checkEditPageFormConflicts() {
        const driverVal = document.getElementById('sf-edit-driver').value;
        const busVal = document.getElementById('sf-edit-bus').value;
        const routeVal = document.getElementById('sf-edit-route').value;
        const timeVal = document.getElementById('sf-edit-departure').value;
        const scheduleId = parseInt(document.getElementById('sf-edit-schedule-id').value);

        const warnCard = document.getElementById('edit-modal-conflict-warning-card');
        const warnText = document.getElementById('edit-modal-conflict-warning-text');
        const submitBtn = document.getElementById('edit-schedule-submit-btn');

        warnCard.classList.add('hidden');
        if (submitBtn) {
            submitBtn.disabled = false;
        }

        if (!timeVal || !driverVal) return;

        // Perform time overlap scan
        const hour = parseInt(timeVal.split(':')[0]);
        const duration = window.ROUTE_DURATIONS[routeVal] || editDefaultTravelTime;
        const startMin = hour * 60 + parseInt(timeVal.split(':')[1]);
        const endMin = startMin + duration;

        const conflict = window.schedulesData.find(s => {
            if (s.id === scheduleId) return false;
            const isSameDriver = String(s.driverId) === String(driverVal);
            const isSameBus = s.bus === busVal;

            if (isSameDriver || isSameBus) {
                const sParts = s.time.split(':').map(Number);
                const sStart = sParts[0] * 60 + sParts[1];
                const sDuration = window.ROUTE_DURATIONS[s.routeId] || editDefaultTravelTime;
                const sEnd = sStart + sDuration;

                const buffer = isSameDriver ? editScheduleBuffer : editBusScheduleBuffer;
                return (startMin < (sEnd + buffer)) && (sStart < (endMin + buffer));
            }
            return false;
        });

        if (conflict) {
            const isDriver = String(conflict.driverId) === String(driverVal);
            const entityName = isDriver ? `Driver ${conflict.driverName}` : `Bus ${conflict.bus}`;
            const relation = isDriver ? 'is already assigned to' : 'is already scheduled on';
            
            warnText.textContent = `${entityName} ${relation} Route ${conflict.routeId} at ${conflict.time}. Select a different option or change the departure time.`;
            warnCard.classList.remove('hidden');
            if (submitBtn) {
                submitBtn.disabled = true;
            }
        }

        // License expiry checking
        checkEditPageDriverLicenseExpiry(driverVal);
    }

    function checkEditPageDriverLicenseExpiry(driverId) {
        const warningLabel = document.getElementById('sf-edit-driver-expiry-warning');
        const driver = editPageDriversList.find(d => String(d.id) === String(driverId));
        
        if (driver && driver.license_expiry) {
            const exp = new Date(driver.license_expiry);
            const today = new Date();
            today.setHours(0,0,0,0);
            const diff = Math.floor((exp - today) / 86400000);
            
            if (diff <= editLicenseWarningDays) {
                const labelText = document.getElementById('sf-edit-driver-expiry-warning-text');
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

    async function handleScheduleEditPageSubmit(event) {
        event.preventDefault();

        const submitBtn = document.getElementById('edit-schedule-submit-btn');
        const scheduleId = document.getElementById('sf-edit-schedule-id').value;
        const routeVal = document.getElementById('sf-edit-route').value;
        const busVal = document.getElementById('sf-edit-bus').value;
        const driverVal = document.getElementById('sf-edit-driver').value;
        const timeVal = document.getElementById('sf-edit-departure').value;
        
        if (!timeVal) {
            alert('Please select a departure time.');
            return;
        }

        const payload = {
            route_id: routeVal,
            bus_plate: busVal,
            driver_id: driverVal,
            departure_time: timeVal
        };

        try {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="ti ti-loader mr-1 animate-spin"></i> Saving...';
            }

            const response = await fetch(`{{ url('admin/api/schedules') }}/${scheduleId}`, {
                method: 'PUT',
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
                    submitBtn.innerHTML = 'Save Changes';
                }
            }
        } catch (error) {
            alert('Server connection error. Failed to save schedule.');
            console.error('AJAX Schedule edit page submit error:', error);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Changes';
            }
        }
    }

    async function handleEditPageDeleteSchedule() {
        const scheduleId = document.getElementById('sf-edit-schedule-id').value;
        if (!scheduleId) return;
        if (!confirm('Are you sure you want to permanently delete this schedule slot?')) return;

        try {
            const response = await fetch(`{{ url('admin/api/schedules') }}/${scheduleId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json'
                }
            });

            const data = await response.json();

            if (response.ok && data.success) {
                alert(data.message);
                switchScreen('routes');
                if (typeof initRoutesDashboard === 'function') {
                    initRoutesDashboard();
                }
            } else {
                alert(data.message || 'Failed to delete schedule slot.');
            }
        } catch (error) {
            alert('Server connection error. Failed to delete schedule slot.');
            console.error('AJAX Schedule delete error:', error);
        }
    }
</script>
