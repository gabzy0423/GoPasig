@extends('layouts.admin')

@section('title', 'Register Electric Bus - GoPasig')

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
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Register Electric Bus</h1>
                            <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                                <span>Dashboard</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Fleet</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Bus Management</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span class="text-[#003F87] font-bold">Register Electric Bus</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FORM CARD -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
                    <div class="mb-6">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Register Electric Bus</h2>
                        <p class="text-xs text-slate-500">Register a new electric municipal bus asset. Operational assignments are configured after registration.</p>
                    </div>

                    <form id="create-bus-form" onsubmit="handleBusCreateSubmit(event)" class="space-y-6">
                        @csrf
                        
                        <!-- BUS IDENTIFICATION SECTION -->
                        <div class="border-b border-slate-100 pb-5">
                            <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4">Bus Identification</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Bus Plate No -->
                                <div class="space-y-2">
                                    <label for="new-bus-plate" class="text-xs font-bold uppercase tracking-wider text-slate-500">Plate Number</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-id text-base"></i>
                                        </span>
                                        <input id="new-bus-plate" name="plate_number" type="text" placeholder="e.g. PAS-439" required
                                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Unique plate designation code.</p>
                                </div>

                                <!-- Fleet Number -->
                                <div class="space-y-2">
                                    <label for="new-bus-fleet-number" class="text-xs font-bold uppercase tracking-wider text-slate-500">Fleet Number</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-hash text-base"></i>
                                        </span>
                                        <input id="new-bus-fleet-number" name="fleet_number" type="text" placeholder="e.g. BUS-001" required
                                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Unique fleet identifier (e.g. BUS-001).</p>
                                </div>

                                <!-- VIN / Chassis Number -->
                                <div class="space-y-2">
                                    <label for="new-bus-vin" class="text-xs font-bold uppercase tracking-wider text-slate-500">VIN / Chassis Number</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-fingerprint text-base"></i>
                                        </span>
                                        <input id="new-bus-vin" name="vin" type="text" placeholder="17-character VIN" required
                                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Immutable 17-character vehicle code.</p>
                                </div>
                            </div>
                        </div>

                        <!-- VEHICLE INFORMATION SECTION -->
                        <div class="border-b border-slate-100 pb-5">
                            <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4">Vehicle Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Manufacturer Dropdown -->
                                <div class="space-y-2">
                                    <label for="new-bus-manufacturer-select" class="text-xs font-bold uppercase tracking-wider text-slate-500">Manufacturer</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                                            <i class="ti ti-building text-base"></i>
                                        </span>
                                        <select id="new-bus-manufacturer-select" onchange="toggleCustomManufacturer(this)" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                            <option value="BYD">BYD</option>
                                            <option value="Yutong">Yutong</option>
                                            <option value="Higer">Higer</option>
                                            <option value="Golden Dragon">Golden Dragon</option>
                                            <option value="Ankai">Ankai</option>
                                            <option value="King Long">King Long</option>
                                            <option value="Others">Others (Specify below)</option>
                                        </select>
                                        <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                            <i class="ti ti-chevron-down text-sm"></i>
                                        </span>
                                    </div>
                                </div>

                                <!-- Custom Manufacturer -->
                                <div class="space-y-2 hidden" id="bm-manufacturer-custom-wrapper">
                                    <label for="new-bus-manufacturer-custom" class="text-xs font-bold uppercase tracking-wider text-slate-500">Specify Manufacturer</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-edit text-base"></i>
                                        </span>
                                        <input id="new-bus-manufacturer-custom" type="text" placeholder="e.g. Hyundai"
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                </div>

                                <!-- Model -->
                                <div class="space-y-2">
                                    <label for="new-bus-model" class="text-xs font-bold uppercase tracking-wider text-slate-500">Model</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-truck text-base"></i>
                                        </span>
                                        <input id="new-bus-model" name="model" type="text" placeholder="e.g. K9" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Model or chassis series designation.</p>
                                </div>

                                <!-- Year Model -->
                                <div class="space-y-2">
                                    <label for="new-bus-year-model" class="text-xs font-bold uppercase tracking-wider text-slate-500">Year Model</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-calendar text-base"></i>
                                        </span>
                                        <input id="new-bus-year-model" name="year_model" type="number" placeholder="e.g. 2024" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Production or release model year.</p>
                                </div>

                                <!-- Seating Capacity -->
                                <div class="space-y-2">
                                    <label for="new-bus-capacity" class="text-xs font-bold uppercase tracking-wider text-slate-500">Seating Capacity</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-users text-base"></i>
                                        </span>
                                        <input id="new-bus-capacity" name="capacity" type="number" placeholder="Enter seating capacity" value="{{ $defaultCapacity }}" min="{{ $minCapacity }}" max="{{ $maxCapacity }}" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Passenger limit of this bus unit (minimum {{ $minCapacity }}, maximum {{ $maxCapacity }}).</p>
                                </div>
                            </div>
                        </div>

                        <!-- ELECTRIC POWERTRAIN SECTION -->
                        <div class="border-b border-slate-100 pb-5">
                            <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4">Electric Powertrain</h3>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <!-- Battery Capacity -->
                                <div class="space-y-2">
                                    <label for="new-bus-battery-capacity" class="text-xs font-bold uppercase tracking-wider text-slate-500">Battery Capacity (kWh)</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-bolt text-base"></i>
                                        </span>
                                        <input id="new-bus-battery-capacity" name="battery_capacity_kwh" type="number" step="0.01" placeholder="e.g. 350.50" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Battery rating (10 - 1000 kWh).</p>
                                </div>

                                <!-- Maximum Charging Power -->
                                <div class="space-y-2">
                                    <label for="new-bus-max-charging-power" class="text-xs font-bold uppercase tracking-wider text-slate-500">Max Charging Power (kW)</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                            <i class="ti ti-plug text-base"></i>
                                        </span>
                                        <input id="new-bus-max-charging-power" name="max_charging_power_kw" type="number" step="0.01" placeholder="e.g. 150.00" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Maximum input rate (10 - 500 kW).</p>
                                </div>

                                <!-- Charging Port Type -->
                                <div class="space-y-2">
                                    <label for="new-bus-charging-port" class="text-xs font-bold uppercase tracking-wider text-slate-500">Charging Port Type</label>
                                    <div class="relative">
                                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                                            <i class="ti ti-socket text-base"></i>
                                        </span>
                                        <select id="new-bus-charging-port" name="charging_port_type" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                            <option value="CCS2">CCS2</option>
                                            <option value="GB/T">GB/T</option>
                                            <option value="CHAdeMO">CHAdeMO</option>
                                        </select>
                                        <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                            <i class="ti ti-chevron-down text-sm"></i>
                                        </span>
                                    </div>
                                    <p class="text-[10px] text-slate-400 font-medium">Standard charging plug model.</p>
                                </div>
                            </div>
                        </div>

                        <!-- OPERATIONAL STATUS SECTION -->
                        <div class="space-y-2">
                            <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-2">Operational Status</h3>
                            
                            <div class="rounded-lg border border-slate-100 bg-slate-50 p-3.5 flex items-start gap-3 text-left" id="bm-status-note-wrapper">
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 mt-0.5">
                                    <i class="ti ti-info-circle text-base"></i>
                                </span>
                                <p class="text-xs text-slate-600 font-semibold m-0 leading-relaxed">
                                    Newly registered buses are automatically placed in Standby (Inactive) status. Driver assignment, route assignment, GPS configuration, and live operational telemetry are configured after registration through the Dispatch workflow.
                                </p>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                            <a href="{{ route('admin.dashboard') }}#buses" 
                               class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer">
                                Cancel
                            </a>
                            <button type="submit" id="bus-submit-btn" 
                                    class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98]">
                                Register Bus
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
        const customInput = document.getElementById('new-bus-manufacturer-custom');
        if (select.value === 'Others') {
            wrapper.classList.remove('hidden');
            customInput.setAttribute('required', 'required');
        } else {
            wrapper.classList.add('hidden');
            customInput.removeAttribute('required');
            customInput.value = '';
        }
    }

    async function handleBusCreateSubmit(event) {
        event.preventDefault();

        const submitBtn = document.getElementById('bus-submit-btn');
        const plate = document.getElementById('new-bus-plate').value.trim();
        const capacity = document.getElementById('new-bus-capacity').value;
        const fleetNumber = document.getElementById('new-bus-fleet-number').value.trim();
        const vin = document.getElementById('new-bus-vin').value.trim();
        const model = document.getElementById('new-bus-model').value.trim();
        const yearModel = document.getElementById('new-bus-year-model').value;
        const batteryCapacity = document.getElementById('new-bus-battery-capacity').value;
        const maxChargingPower = document.getElementById('new-bus-max-charging-power').value;
        const chargingPort = document.getElementById('new-bus-charging-port').value;

        const mfgSelect = document.getElementById('new-bus-manufacturer-select').value;
        const manufacturer = mfgSelect === 'Others' ? document.getElementById('new-bus-manufacturer-custom').value.trim() : mfgSelect;
        const manufacturerCustom = mfgSelect === 'Others' ? document.getElementById('new-bus-manufacturer-custom').value.trim() : '';

        const payload = {
            plate_number: plate,
            capacity: parseInt(capacity),
            fleet_number: fleetNumber,
            vin: vin,
            manufacturer: mfgSelect,
            manufacturer_custom: manufacturerCustom,
            model: model,
            year_model: parseInt(yearModel),
            battery_capacity_kwh: parseFloat(batteryCapacity),
            max_charging_power_kw: parseFloat(maxChargingPower),
            charging_port_type: chargingPort
        };

        try {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Registering...';
            }

            const baseUrl = "{{ route('admin.api.buses.store') }}";
            
            const response = await fetch(baseUrl, {
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
                GoPasigUI.alert(data.message);
                // Redirect back to admin dashboard's buses tab
                window.location.href = "{{ route('admin.dashboard') }}#buses";
            } else {
                GoPasigUI.alert(data.message || 'Validation error. Please verify input data.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Register Bus';
                }
            }
        } catch (error) {
            GoPasigUI.alert('Server connection error. Failed to register bus.');
            console.error('AJAX Bus submit error:', error);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Register Bus';
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
