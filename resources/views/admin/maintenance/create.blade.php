@extends('layouts.admin')

@section('title', 'Schedule Maintenance - GoPasig')

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
                           class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95" 
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

                <!-- FORM CARD -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
                    <div class="mb-6">
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
                                            <option value="{{ $bus->id }}">{{ $bus->plate_number }} ({{ $bus->driver_name ?: 'No Assigned Driver' }})</option>
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

                            <!-- Technician / Lead -->
                            <div class="space-y-2">
                                <label for="maintenance-technician" class="text-xs font-bold uppercase tracking-wider text-slate-500">Technician / Lead</label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                        <i class="ti ti-user-check text-base"></i>
                                    </span>
                                    <input id="maintenance-technician" name="technician" type="text" placeholder="e.g. Engr. Jose Rizal" required
                                           class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                </div>
                                <p class="text-[10px] text-slate-400 font-medium">Lead mechanic or technician assigned to handle the inspection/repair.</p>
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

                            <!-- Tasks / Description -->
                            <div class="space-y-2 md:col-span-2">
                                <label for="maintenance-desc" class="text-xs font-bold uppercase tracking-wider text-slate-500">Tasks / Description</label>
                                <textarea id="maintenance-desc" name="description" placeholder="Describe the maintenance tasks..." rows="4"
                                          class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] resize-none"></textarea>
                                <p class="text-[10px] text-slate-400 font-medium">Detailed description of the issues to fix or the standard maintenance checks to perform.</p>
                            </div>
                        </div>

                        <!-- Form Actions -->
                        <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                            <a href="{{ route('admin.dashboard') }}#maintenance" 
                               class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer">
                                Cancel
                            </a>
                            <button type="submit" id="maintenance-submit-btn" 
                                    class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98]">
                                Schedule Session
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

        // Set default date-time to current local time
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        const dateInput = document.getElementById('maintenance-date');
        if (dateInput) {
            dateInput.value = now.toISOString().slice(0, 16);
        }
    });

    async function handleCreatePageSubmit(event) {
        event.preventDefault();

        const submitBtn = document.getElementById('maintenance-submit-btn');
        const busId = document.getElementById('maintenance-bus-id').value;
        const type = document.getElementById('maintenance-type').value;
        const technician = document.getElementById('maintenance-technician').value.trim();
        const notes = document.getElementById('maintenance-desc').value.trim();
        const scheduledAt = document.getElementById('maintenance-date').value;

        if (!busId || !scheduledAt) {
            alert('Please select a bus unit and scheduled date/time.');
            return;
        }

        // Combine technician and notes into a single description field to respect DB schema
        const combinedDescription = `Technician: ${technician} | Notes: ${notes || 'Regular maintenance check'}`;

        const payload = {
            bus_id: parseInt(busId),
            type: type,
            description: combinedDescription,
            scheduled_at: scheduledAt,
            status: 'scheduled'
        };

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
                // Redirect back to admin dashboard's maintenance tab
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
