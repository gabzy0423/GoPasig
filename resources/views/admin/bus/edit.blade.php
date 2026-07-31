@extends('layouts.admin')

@section('title', 'Edit Bus Details - GoPasig')

@section('content')
<div class="flex h-screen overflow-hidden bg-slate-50">
    <!-- LEFT SIDEBAR -->
    <x-admin.sidebar active="buses" />

    <!-- MAIN APP WRAPPER -->
    <div class="flex flex-1 flex-col overflow-hidden">
        <!-- TOP HEADER BAR (56px) -->
        <x-admin.header />

        <!-- MAIN SCROLLABLE CANVAS -->
        <main class="flex-1 overflow-y-auto bg-slate-50/50 p-6">
            <div class="mx-auto w-full max-w-[1366px]">
                
                <!-- BREADCRUMB & HEADER -->
                <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('admin.dashboard') }}#buses" 
                           class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95" 
                           title="Back to Bus Management">
                            <i class="ti ti-arrow-left text-lg"></i>
                        </a>
                        <div>
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Edit Bus Details</h1>
                            <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                                <span>Dashboard</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Fleet</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Bus Management</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span class="text-[#003F87] font-bold">Edit Bus</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FORM CARD -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
                    <div class="mb-6">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Bus Unit Specifications</h2>
                        <p class="text-xs text-slate-500">Update the plate number, driver, route, capacity, or current operational status for this bus.</p>
                    </div>

                    <form id="edit-bus-form" onsubmit="handleBusEditSubmit(event)" class="space-y-6">
                        @csrf
                        @method('PUT')
                        
                        <!-- BUS IDENTIFICATION SECTION -->
                        <div class="border-b border-slate-100 pb-5">
                            <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4">Bus Identification</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Bus Plate No -->
                                <div class="space-y-2">
                                    <label for="edit-bus-plate" class="text-xs font-bold uppercase tracking-wider text-slate-500">Plate Number</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-id text-base"></i>
                                        </span>
                                        <input id="edit-bus-plate" name="plate_number" type="text" value="{{ $bus->plate_number }}" disabled
                                               class="w-full rounded-lg border border-slate-200 bg-slate-100 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-500 cursor-not-allowed outline-none">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Plate number is fixed and unique.</p>
                                </div>

                                <!-- Fleet Number -->
                                <div class="space-y-2">
                                    <label for="edit-bus-fleet-number" class="text-xs font-bold uppercase tracking-wider text-slate-500">Fleet Number</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-hash text-base"></i>
                                        </span>
                                        <input id="edit-bus-fleet-number" name="fleet_number" type="text" value="{{ $bus->fleet_number }}" required
                                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Internal fleet asset identifier.</p>
                                </div>

                                <!-- VIN / Chassis Number -->
                                <div class="space-y-2">
                                    <label for="edit-bus-vin" class="text-xs font-bold uppercase tracking-wider text-slate-500">VIN / Chassis Number</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-fingerprint text-base"></i>
                                        </span>
                                        <input id="edit-bus-vin" name="vin" type="text" value="{{ $bus->vin }}" disabled
                                               class="w-full rounded-lg border border-slate-200 bg-slate-100 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-500 cursor-not-allowed outline-none">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">VIN is fixed and unique.</p>
                                </div>
                            </div>
                        </div>

                        <!-- VEHICLE INFORMATION SECTION -->
                        <div class="border-b border-slate-100 pb-5">
                            <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4">Vehicle Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Manufacturer Dropdown -->
                                <div class="space-y-2">
                                    <label for="edit-bus-manufacturer-select" class="text-xs font-bold uppercase tracking-wider text-slate-500">Manufacturer</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                                            <i class="ti ti-building text-base"></i>
                                        </span>
                                        @php
                                            $predefinedManufacturers = ['BYD', 'Yutong', 'Higer', 'Golden Dragon', 'Ankai', 'King Long'];
                                            $isCustomMfg = !in_array($bus->manufacturer, $predefinedManufacturers);
                                        @endphp
                                        <select id="edit-bus-manufacturer-select" onchange="toggleCustomManufacturer(this)" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                            @foreach($predefinedManufacturers as $mfg)
                                                <option value="{{ $mfg }}" {{ $bus->manufacturer === $mfg ? 'selected' : '' }}>{{ $mfg }}</option>
                                            @endforeach
                                            <option value="Others" {{ $isCustomMfg ? 'selected' : '' }}>Others (Specify below)</option>
                                        </select>
                                        <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                            <i class="ti ti-chevron-down text-sm"></i>
                                        </span>
                                    </div>
                                </div>

                                <!-- Custom Manufacturer -->
                                <div class="space-y-2 {{ $isCustomMfg ? '' : 'hidden' }}" id="bm-manufacturer-custom-wrapper">
                                    <label for="edit-bus-manufacturer-custom" class="text-xs font-bold uppercase tracking-wider text-slate-500">Specify Manufacturer</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-edit text-base"></i>
                                        </span>
                                        <input id="edit-bus-manufacturer-custom" type="text" value="{{ $isCustomMfg ? $bus->manufacturer : '' }}" placeholder="e.g. Hyundai"
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                </div>

                                <!-- Model -->
                                <div class="space-y-2">
                                    <label for="edit-bus-model" class="text-xs font-bold uppercase tracking-wider text-slate-500">Model</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-truck text-base"></i>
                                        </span>
                                        <input id="edit-bus-model" name="model" type="text" value="{{ $bus->model }}" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Model or chassis series designation.</p>
                                </div>

                                <!-- Year Model -->
                                <div class="space-y-2">
                                    <label for="edit-bus-year-model" class="text-xs font-bold uppercase tracking-wider text-slate-500">Year Model</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-calendar text-base"></i>
                                        </span>
                                        <input id="edit-bus-year-model" name="year_model" type="number" value="{{ $bus->year_model }}" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Production or release model year.</p>
                                </div>

                                <!-- Seating Capacity -->
                                <div class="space-y-2">
                                    <label for="edit-bus-capacity" class="text-xs font-bold uppercase tracking-wider text-slate-500">Seating Capacity</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-users text-base"></i>
                                        </span>
                                        <input id="edit-bus-capacity" name="capacity" type="number" placeholder="Enter seating capacity" value="{{ $bus->capacity }}"
                                            min="{{ \App\Models\SystemSetting::get('bus_capacity_min', 10) }}" max="{{ \App\Models\SystemSetting::get('bus_capacity_max', 150) }}" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Passenger limit of this bus unit (minimum 10, maximum 150).</p>
                                </div>
                            </div>
                        </div>

                        <!-- ELECTRIC POWERTRAIN SECTION -->
                        <div class="border-b border-slate-100 pb-5">
                            <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4">Electric Powertrain</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Battery Capacity -->
                                <div class="space-y-2">
                                    <label for="edit-bus-battery-capacity" class="text-xs font-bold uppercase tracking-wider text-slate-500">Battery Capacity (kWh)</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-bolt text-base"></i>
                                        </span>
                                        <input id="edit-bus-battery-capacity" name="battery_capacity_kwh" type="number" step="0.01" value="{{ $bus->battery_capacity_kwh }}" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Battery rating (10 - 1000 kWh).</p>
                                </div>

                                <!-- Maximum Charging Power -->
                                <div class="space-y-2">
                                    <label for="edit-bus-max-charging-power" class="text-xs font-bold uppercase tracking-wider text-slate-500">Max Charging Power (kW)</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-plug text-base"></i>
                                        </span>
                                        <input id="edit-bus-max-charging-power" name="max_charging_power_kw" type="number" step="0.01" value="{{ $bus->max_charging_power_kw }}" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Maximum input rate (10 - 500 kW).</p>
                                </div>

                                <!-- Charging Port Type -->
                                <div class="space-y-2">
                                    <label for="edit-bus-charging-port" class="text-xs font-bold uppercase tracking-wider text-slate-500">Charging Port Type</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                                            <i class="ti ti-socket text-base"></i>
                                        </span>
                                        <select id="edit-bus-charging-port" name="charging_port_type" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                            <option value="CCS2" {{ $bus->charging_port_type === 'CCS2' ? 'selected' : '' }}>CCS2</option>
                                            <option value="GB/T" {{ $bus->charging_port_type === 'GB/T' ? 'selected' : '' }}>GB/T</option>
                                            <option value="CHAdeMO" {{ $bus->charging_port_type === 'CHAdeMO' ? 'selected' : '' }}>CHAdeMO</option>
                                        </select>
                                        <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                            <i class="ti ti-chevron-down text-sm"></i>
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Standard charging plug model.</p>
                                </div>
                            </div>
                        </div>

                        <!-- OPERATIONAL ASSIGNMENTS SECTION -->
                        <div class="border-b border-slate-100 pb-5">
                            <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4">Operational Assignments</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Driver Assignment -->
                                <div class="space-y-2">
                                    <label for="edit-bus-driver" class="text-xs font-bold uppercase tracking-wider text-slate-500">Driver Assignment</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                                            <i class="ti ti-steering-wheel text-base"></i>
                                        </span>
                                        <select id="edit-bus-driver" name="driver_name" required
                                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                            @php
                                                $activeBuses = \App\Models\Bus::where('status', 'active')
                                                    ->where('plate_number', '!=', $bus->plate_number)
                                                    ->pluck('plate_number')
                                                    ->toArray();
                                            @endphp
                                            <option value="{{ \App\Models\Bus::DEFAULT_DRIVER_NAME }}" {{ $bus->driver_name === \App\Models\Bus::DEFAULT_DRIVER_NAME ? 'selected' : '' }}>Unassigned (None)</option>
                                            @foreach($drivers as $driver)
                                                @php
                                                    $fullName = $driver->first_name . ' ' . $driver->last_name;
                                                    $isAssignedToOtherActiveBus = in_array($driver->assigned_bus, $activeBuses);
                                                    $isCurrentDriver = ($bus->driver_name === $fullName);
                                                @endphp
                                                @if(!$isAssignedToOtherActiveBus || $isCurrentDriver)
                                                    @if($driver->status !== 'suspended')
                                                        <option value="{{ $fullName }}" {{ $bus->driver_name === $fullName ? 'selected' : '' }}>{{ $fullName }}</option>
                                                    @endif
                                                @endif
                                            @endforeach
                                        </select>
                                        <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                            <i class="ti ti-chevron-down text-sm"></i>
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Name of the primary operator driving this unit.</p>
                                </div>

                                <!-- Route Assignment -->
                                <div class="space-y-2">
                                    <label for="edit-bus-route" class="text-xs font-bold uppercase tracking-wider text-slate-500">Route Assignment</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-route text-base"></i>
                                        </span>
                                        <select id="edit-bus-route" name="route_id" required
                                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                            <option value="{{ \App\Models\Bus::DEFAULT_NEXT_STOP }}" {{ is_null($bus->route_id) ? 'selected' : '' }}>{{ \App\Models\Bus::DEFAULT_NEXT_STOP }} - Unassigned</option>
                                            @foreach($routes as $route)
                                                <option value="{{ $route->id }}" {{ $bus->route_id == $route->id ? 'selected' : '' }}>{{ $route->name }} - {{ $route->description }}</option>
                                            @endforeach
                                        </select>
                                        <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                            <i class="ti ti-chevron-down text-sm"></i>
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">The municipal transit line to dispatch this unit to.</p>
                                </div>
                            </div>
                        </div>

                        <!-- OPERATIONAL STATUS SECTION -->
                        <div class="space-y-2">
                            <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-2">Operational Status</h3>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i class="ti ti-activity text-base"></i>
                                </span>
                                <select id="edit-bus-status" name="status" required
                                         class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                    @if($bus->status === 'active')
                                        <option value="active" disabled selected>Active (Current Status)</option>
                                    @endif
                                    <option value="inactive" {{ $bus->status === 'inactive' ? 'selected' : '' }}>Standby (Inactive)</option>
                                    <option value="maintenance" {{ $bus->status === 'maintenance' ? 'selected' : '' }}>Maintenance</option>
                                    <option value="breakdown" {{ $bus->status === 'breakdown' ? 'selected' : '' }}>Breakdown</option>
                                </select>
                                <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                    <i class="ti ti-chevron-down text-sm"></i>
                                </span>
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">Current operational status determining its visibility in commuter apps.</p>
                        </div>

                        <!-- Form Actions -->
                        <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                            <a href="{{ route('admin.dashboard') }}#buses" 
                               class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer">
                                Cancel
                            </a>
                            <button type="submit" id="bus-submit-btn" 
                                    class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98]">
                                Update Bus Details
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Override switchScreen function to redirect back to dashboard with hash
        window.switchScreen = function(screenName) {
            window.location.href = "{{ route('admin.dashboard') }}#" + screenName;
        };
    });

    function toggleCustomManufacturer(select) {
        const wrapper = document.getElementById('bm-manufacturer-custom-wrapper');
        const customInput = document.getElementById('edit-bus-manufacturer-custom');
        if (select.value === 'Others') {
            wrapper.classList.remove('hidden');
            customInput.setAttribute('required', 'required');
        } else {
            wrapper.classList.add('hidden');
            customInput.removeAttribute('required');
            customInput.value = '';
        }
    }

    async function handleBusEditSubmit(event) {
        event.preventDefault();

        const submitBtn = document.getElementById('bus-submit-btn');
        const route = document.getElementById('edit-bus-route').value;
        const driver = document.getElementById('edit-bus-driver').value.trim();
        const capacity = document.getElementById('edit-bus-capacity').value;
        const status = document.getElementById('edit-bus-status').value;

        const mfgSelect = document.getElementById('edit-bus-manufacturer-select').value;
        const manufacturer = mfgSelect === 'Others' ? document.getElementById('edit-bus-manufacturer-custom').value.trim() : mfgSelect;
        const manufacturerCustom = mfgSelect === 'Others' ? document.getElementById('edit-bus-manufacturer-custom').value.trim() : '';

        const payload = {
            fleet_number: document.getElementById('edit-bus-fleet-number').value.trim(),
            manufacturer: mfgSelect,
            manufacturer_custom: manufacturerCustom,
            model: document.getElementById('edit-bus-model').value.trim(),
            year_model: parseInt(document.getElementById('edit-bus-year-model').value),
            battery_capacity_kwh: parseFloat(document.getElementById('edit-bus-battery-capacity').value),
            max_charging_power_kw: parseFloat(document.getElementById('edit-bus-max-charging-power').value),
            charging_port_type: document.getElementById('edit-bus-charging-port').value,
            driver_name: driver || '{{ \App\Models\Bus::DEFAULT_DRIVER_NAME }}',
            capacity: parseInt(capacity),
            route_id: route === '{{ \App\Models\Bus::DEFAULT_NEXT_STOP }}' ? null : parseInt(route),
            status: status.toLowerCase()
        };

        try {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Updating...';
            }

            const baseUrl = "{{ route('admin.api.buses.store') }}";
            const url = `${baseUrl}/{{ $bus->id }}`;
            
            const response = await fetch(url, {
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
                GoPasigUI.alert(data.message);
                // Redirect back to admin dashboard's buses tab
                window.location.href = "{{ route('admin.dashboard') }}#buses";
            } else {
                GoPasigUI.alert(data.message || 'Validation error. Please verify input data.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Update Bus Details';
                }
            }
        } catch (error) {
            GoPasigUI.alert('Server connection error. Failed to update bus details.');
            console.error('AJAX Bus update error:', error);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update Bus Details';
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
@endsection
