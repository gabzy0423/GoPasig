@extends('layouts.admin')

@section('title', 'GoPasig Admin Dashboard')

@section('content')
<div class="flex h-screen overflow-hidden bg-slate-50">
    <!-- LEFT SIDEBAR -->
    <x-admin.sidebar active="maintenance" />

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
                        <a href="{{ route('admin.dashboard') }}#maintenance" 
                           class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 no-underline" 
                           title="Back to Maintenance Logs">
                            <i class="ti ti-arrow-left text-lg"></i>
                        </a>
                        <div>
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Schedule Maintenance</h1>
                            <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                                <span>Dashboard</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Operations</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Maintenance Logs</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span class="text-[#003F87] font-bold">Schedule Session</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Layout Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-6xl">
                    
                    <!-- Left: Form -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- FORM CARD -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <div class="mb-6 pb-4 border-b border-slate-100">
                                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Maintenance Ticket Details</h2>
                                <p class="text-xs text-slate-500">Fill in the details to schedule a maintenance session for a fleet unit.</p>
                            </div>

                            <form id="create-schedule-maintenance-form" onsubmit="handleCreatePageSubmit(event)" class="space-y-6">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Bus Unit -->
                                    <div class="space-y-2">
                                        <label for="maintenance-bus-id" class="text-xs font-bold uppercase tracking-wider text-slate-500">Bus Unit</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                                <i class="ti ti-steering-wheel text-base"></i>
                                            </span>
                                            <select id="maintenance-bus-id" name="bus_id" required
                                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                                <option value="">Select a Bus...</option>
                                                @foreach($buses as $bus)
                                                    <option value="{{ $bus->id }}">{{ $bus->plate_number }}</option>
                                                @endforeach
                                            </select>
                                            <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                                <i class="ti ti-chevron-down text-sm"></i>
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-medium">Select the bus unit that requires scheduled service.</p>
                                    </div>

                                    <!-- Maintenance Type -->
                                    <div class="space-y-2">
                                        <label for="maintenance-type" class="text-xs font-bold uppercase tracking-wider text-slate-500">Maintenance Type</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                                <i class="ti ti-tool text-base"></i>
                                            </span>
                                            <select id="maintenance-type" name="type" required
                                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                                @foreach($maintenanceTypes as $typeOption)
                                                    <option value="{{ $typeOption }}">
                                                        {{ $typeOption }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                                <i class="ti ti-chevron-down text-sm"></i>
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-medium">Classify this maintenance work ticket category.</p>
                                    </div>

                                    <!-- Technician / Service Provider -->
                                    <div class="space-y-2">
                                        <label for="maintenance-technician" class="text-xs font-bold uppercase tracking-wider text-slate-500">Technician / Service Provider</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                                <i class="ti ti-user-check text-base"></i>
                                            </span>
                                            <input id="maintenance-technician" name="technician_name" type="text" placeholder="e.g. Engr. Jose Rizal" required
                                                   class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-medium">Lead mechanic or service provider assigned to handle the repair.</p>
                                    </div>

                                    <!-- Scheduled Date & Time -->
                                    <div class="space-y-2">
                                        <label for="maintenance-date" class="text-xs font-bold uppercase tracking-wider text-slate-500">Scheduled Date & Time</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                                <i class="ti ti-calendar text-base"></i>
                                            </span>
                                            <input id="maintenance-date" name="scheduled_at" type="datetime-local" required
                                                   class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-medium">Date and time when the maintenance session is planned to begin.</p>
                                    </div>

                                    <!-- Estimated Duration (Minutes) -->
                                    <div class="space-y-2">
                                        <label for="maintenance-duration" class="text-xs font-bold uppercase tracking-wider text-slate-500">Estimated Duration (Minutes)</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                                <i class="ti ti-clock text-base"></i>
                                            </span>
                                            <input id="maintenance-duration" name="expected_duration_minutes" type="number" placeholder="e.g. 120"
                                                   class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-medium">Estimated time needed to complete service tasks.</p>
                                    </div>

                                    <!-- Maintenance Work Order -->
                                    <div class="space-y-2 md:col-span-2">
                                        <label for="maintenance-desc" class="text-xs font-bold uppercase tracking-wider text-slate-500">Maintenance Work Order</label>
                                        <textarea id="maintenance-desc" name="description" placeholder="Describe the maintenance work order (inspections, repairs, replacements, diagnostics)..." rows="4"
                                                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] resize-none"></textarea>
                                        <p class="text-[10px] text-slate-400 font-medium">Detailed description of the issues to fix or standard diagnostics to perform.</p>
                                    </div>
                                </div>

                                <!-- Form Actions -->
                                <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                                    <a href="{{ route('admin.dashboard') }}#maintenance" 
                                       class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer no-underline">
                                        Cancel
                                    </a>
                                    <button type="submit" id="maintenance-submit-btn" 
                                            class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none">
                                        Schedule Session
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Right: Info Panel & Bus Summary -->
                    <div class="space-y-6">
                        <!-- Maintenance Metadata Info -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#003F87] mb-3 pb-1.5 border-b border-slate-100">Ticket Information</h3>
                            <dl class="space-y-3.5 text-xs">
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Maintenance Ticket</dt>
                                    <dd class="text-slate-500 font-bold mt-1 text-[11px]">
                                        <i class="ti ti-info-circle text-[#003F87] mr-1"></i> Ticket number will be generated automatically after the maintenance record is created.
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Status</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-100">Scheduled</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Created By</dt>
                                    <dd class="text-slate-800 font-bold mt-0.5">System</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Created On</dt>
                                    <dd class="text-slate-500 text-[11px] mt-0.5 font-medium"><i class="ti ti-info-circle text-[#003F87] mr-1"></i> Will be set when record is saved.</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Bus Summary Card -->
                        <div id="bus-summary-card" class="hidden rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_2px_8px_rgba(0,0,0,0.04)] animate-fade-in">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#003F87] mb-3 pb-1.5 border-b border-slate-100">Selected Bus Summary</h3>
                            <dl class="grid grid-cols-2 gap-4 text-xs font-semibold">
                                <div class="col-span-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Fleet Number</dt>
                                    <dd id="summary-fleet-number" class="text-slate-800 font-extrabold text-sm mt-0.5">—</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Plate Number</dt>
                                    <dd id="summary-plate-number" class="text-slate-800 font-bold mt-0.5">—</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Status</dt>
                                    <dd class="mt-0.5">
                                        <span id="summary-current-status-badge" class="inline-flex items-center rounded-full bg-slate-50 px-2 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-100">Standby (Inactive)</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Manufacturer</dt>
                                    <dd id="summary-manufacturer" class="text-slate-800 font-bold mt-0.5">—</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Model</dt>
                                    <dd id="summary-model" class="text-slate-800 font-bold mt-0.5">—</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Year Model</dt>
                                    <dd id="summary-year-model" class="text-slate-800 font-bold mt-0.5">—</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Seating Capacity</dt>
                                    <dd id="summary-seating-capacity" class="text-slate-800 font-bold mt-0.5">—</dd>
                                </div>
                                <div class="col-span-2 pt-2 border-t border-slate-100">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Last Maintenance Date</dt>
                                    <dd id="summary-last-maintenance" class="text-slate-800 font-bold mt-0.5">—</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                </div>

            </div>
        </main>
    </div>
</div>

{{-- Inline script specific to create page --}}
<script>
    const busesData = @json($buses);

    document.addEventListener('DOMContentLoaded', () => {
        // Set min date-time to current local time to prevent past selection
        const dateInput = document.getElementById('maintenance-date');
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        const localISO = now.toISOString().slice(0, 16);
        
        if (dateInput) {
            dateInput.value = localISO;
            dateInput.min = localISO;
        }

        // Setup change handler on Bus select dropdown
        const busSelect = document.getElementById('maintenance-bus-id');
        if (busSelect) {
            busSelect.addEventListener('change', function() {
                const selectedBusId = this.value;
                const card = document.getElementById('bus-summary-card');
                if (!selectedBusId) {
                    card.classList.add('hidden');
                    return;
                }
                const bus = busesData.find(b => b.id == selectedBusId);
                if (bus) {
                    document.getElementById('summary-fleet-number').textContent = bus.fleet_number || 'N/A';
                    document.getElementById('summary-plate-number').textContent = bus.plate_number || 'N/A';
                    document.getElementById('summary-manufacturer').textContent = bus.manufacturer || 'N/A';
                    document.getElementById('summary-model').textContent = bus.model || 'N/A';
                    document.getElementById('summary-year-model').textContent = bus.year_model || 'N/A';
                    document.getElementById('summary-seating-capacity').textContent = bus.capacity || 'N/A';
                    
                    // Display last maintenance date if exists
                    const lastMaint = bus.last_maintenance_date;
                    if (lastMaint) {
                        const maintDate = new Date(lastMaint);
                        document.getElementById('summary-last-maintenance').textContent = maintDate.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
                    } else {
                        document.getElementById('summary-last-maintenance').textContent = 'No previous records';
                    }

                    card.classList.remove('hidden');
                }
            });
        }
    });

    async function handleCreatePageSubmit(event) {
        event.preventDefault();

        const submitBtn = document.getElementById('maintenance-submit-btn');
        const busId = document.getElementById('maintenance-bus-id').value;
        const type = document.getElementById('maintenance-type').value;
        const technician = document.getElementById('maintenance-technician').value.trim();
        const description = document.getElementById('maintenance-desc').value.trim();
        const scheduledAt = document.getElementById('maintenance-date').value;
        const durationVal = document.getElementById('maintenance-duration').value;

        if (!busId || !scheduledAt) {
            alert('Please select a bus unit and scheduled date/time.');
            return;
        }

        const payload = {
            bus_id: parseInt(busId),
            type: type,
            technician_name: technician,
            description: description,
            scheduled_at: scheduledAt,
        };

        if (durationVal) {
            payload.expected_duration_minutes = parseInt(durationVal);
        }

        try {
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Scheduling...';
            }

            const baseUrl = "{{ route('admin.api.maintenance.store') }}";
            
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
                window.location.href = "{{ route('admin.dashboard') }}#maintenance";
            } else {
                alert(data.message || 'Validation error. Please verify input data.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Schedule Session';
                }
            }
        } catch (error) {
            alert('Server connection error. Failed to schedule maintenance.');
            console.error('AJAX maintenance submit error:', error);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Schedule Session';
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
