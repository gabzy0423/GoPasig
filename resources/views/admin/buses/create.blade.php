@extends('layouts.admin')

@section('title', 'Register Municipal Bus - GoPasig')

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
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Register Municipal Bus</h1>
                            <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                                <span>Dashboard</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Fleet</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Bus Management</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span class="text-[#003F87] font-bold">Register Bus</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- FORM CARD -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
                    <div class="mb-6">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Bus Unit Specifications</h2>
                        <p class="text-xs text-slate-500">Provide the plate number, driver, route, capacity, and current operational status to register a new bus.</p>
                    </div>

                    <form id="create-bus-form" onsubmit="handleBusCreateSubmit(event)" class="space-y-6">
                        @csrf
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Bus Plate No -->
                            <div class="space-y-2">
                                <label for="new-bus-plate" class="text-xs font-bold uppercase tracking-wider text-slate-500">Bus Plate No</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-id text-base"></i>
                                    </span>
                                    <input id="new-bus-plate" name="plate_number" type="text" placeholder="e.g. PAS-439" required
                                           class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium">Unique plate number or designation code for the bus.</p>
                            </div>

                            <!-- Seating Capacity -->
                            <div class="space-y-2">
                                <label for="new-bus-capacity" class="text-xs font-bold uppercase tracking-wider text-slate-500">Seating Capacity</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-users text-base"></i>
                                    </span>
                                    <input id="new-bus-capacity" name="capacity" type="number" placeholder="e.g. 45" value="{{ $defaultCapacity }}" min="{{ $minCapacity }}" max="{{ $maxCapacity }}" required
                                           class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium">Passenger limit of this bus unit (minimum {{ $minCapacity }}, maximum {{ $maxCapacity }}).</p>
                            </div>

                            <!-- Driver Assignment -->
                            <div class="space-y-2">
                                <label for="new-bus-driver" class="text-xs font-bold uppercase tracking-wider text-slate-500">Driver Assignment</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-steering-wheel text-base"></i>
                                    </span>
                                    <input id="new-bus-driver" name="driver_name" type="text" placeholder="e.g. Cardo Dalisay" required
                                           class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium">Name of the primary operator driving this unit.</p>
                            </div>

                            <!-- Route Assignment -->
                            <div class="space-y-2">
                                <label for="new-bus-route" class="text-xs font-bold uppercase tracking-wider text-slate-500">Route Assignment</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-route text-base"></i>
                                    </span>
                                    <select id="new-bus-route" name="route_id" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                        <option value="{{ \App\Models\Bus::DEFAULT_NEXT_STOP }}">{{ \App\Models\Bus::DEFAULT_NEXT_STOP }} - Unassigned</option>
                                        @foreach($routes as $route)
                                            <option value="{{ $route->id }}">{{ $route->name }} - {{ $route->description }}</option>
                                        @endforeach
                                    </select>
                                    <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                        <i class="ti ti-chevron-down text-sm"></i>
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium">The municipal transit line to dispatch this unit to.</p>
                            </div>

                            <!-- Status -->
                            <div class="space-y-2 md:col-span-2">
                                <label for="new-bus-status" class="text-xs font-bold uppercase tracking-wider text-slate-500">Status</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-activity text-base"></i>
                                    </span>
                                    <select id="new-bus-status" name="status" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                        <option value="active">Active (On road / dispatch-ready)</option>
                                        <option value="inactive">Inactive (Idle / off-duty)</option>
                                        <option value="maintenance">Maintenance (Undergoing repairs)</option>
                                    </select>
                                    <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                        <i class="ti ti-chevron-down text-sm"></i>
                                    </span>
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium">Current operational status determining its visibility in commuter apps.</p>
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

{{-- Inline script specific to create page --}}
<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Override switchScreen function to redirect back to dashboard with hash
        window.switchScreen = function(screenName) {
            window.location.href = "{{ route('admin.dashboard') }}#" + screenName;
        };
    });

    async function handleBusCreateSubmit(event) {
        event.preventDefault();

        const submitBtn = document.getElementById('bus-submit-btn');
        const plate = document.getElementById('new-bus-plate').value.trim();
        const route = document.getElementById('new-bus-route').value;
        const driver = document.getElementById('new-bus-driver').value.trim();
        const capacity = document.getElementById('new-bus-capacity').value;
        const status = document.getElementById('new-bus-status').value;

        const payload = {
            plate_number: plate,
            driver_name: driver || '{{ \App\Models\Bus::DEFAULT_DRIVER_NAME }}',
            capacity: parseInt(capacity),
            route_id: route === '{{ \App\Models\Bus::DEFAULT_NEXT_STOP }}' ? null : parseInt(route),
            status: status
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
                alert(data.message);
                // Redirect back to admin dashboard's buses tab
                window.location.href = "{{ route('admin.dashboard') }}#buses";
            } else {
                alert(data.message || 'Validation error. Please verify input data.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Register Bus';
                }
            }
        } catch (error) {
            alert('Server connection error. Failed to register bus.');
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
