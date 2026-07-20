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
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Edit Scheduled Maintenance</h1>
                            <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                                <span>Dashboard</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Operations</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Maintenance Logs</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span class="text-[#003F87] font-bold">Edit Ticket ({{ $record->ticket_number }})</span>
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
                                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Modify Scheduled Details</h2>
                                <p class="text-xs text-slate-500">Only Technician / Service Provider, Schedule, and Work Order are editable.</p>
                            </div>

                            <form id="edit-schedule-maintenance-form" onsubmit="handleEditPageSubmit(event)" class="space-y-6">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Bus Unit (Read Only) -->
                                    <div class="space-y-2">
                                        <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Bus Unit</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                                <i class="ti ti-steering-wheel text-base"></i>
                                            </span>
                                            <input type="text" readonly
                                                   value="{{ $record->bus ? $record->bus->plate_number : 'Bus #' . $record->bus_id }}"
                                                   class="w-full rounded-lg border border-slate-200 bg-slate-100 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-500 cursor-not-allowed outline-none">
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-medium">The bus unit is locked and cannot be changed.</p>
                                    </div>

                                    <!-- Maintenance Type (Read Only) -->
                                    <div class="space-y-2">
                                        <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Maintenance Type</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                                <i class="ti ti-tool text-base"></i>
                                            </span>
                                            <input type="text" readonly
                                                   value="{{ $record->type }}"
                                                   class="w-full rounded-lg border border-slate-200 bg-slate-100 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-500 cursor-not-allowed outline-none">
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-medium">The maintenance type classification is locked.</p>
                                    </div>

                                    <!-- Technician / Service Provider -->
                                    <div class="space-y-2">
                                        <label for="maintenance-technician" class="text-xs font-bold uppercase tracking-wider text-slate-500">Technician / Service Provider</label>
                                        <div class="relative">
                                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                                <i class="ti ti-user-check text-base"></i>
                                            </span>
                                            <input id="maintenance-technician" name="technician_name" type="text" required
                                                   value="{{ $record->technician_name }}"
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
                                                   value="{{ $record->scheduled_at ? $record->scheduled_at->format('Y-m-d\TH:i') : '' }}"
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
                                            <input id="maintenance-duration" name="expected_duration_minutes" type="number"
                                                   value="{{ $record->expected_duration_minutes }}"
                                                   class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                                        </div>
                                        <p class="text-[10px] text-slate-400 font-medium">Estimated time needed to complete service tasks.</p>
                                    </div>

                                    <!-- Maintenance Work Order -->
                                    <div class="space-y-2 md:col-span-2">
                                        <label for="maintenance-desc" class="text-xs font-bold uppercase tracking-wider text-slate-500">Maintenance Work Order</label>
                                        <textarea id="maintenance-desc" name="description" rows="4"
                                                  class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] resize-none">{{ $record->description }}</textarea>
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
                                        Update Schedule
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Right Column: Info Panel -->
                    <div class="space-y-6">
                        <!-- Maintenance Metadata Info -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#003F87] mb-3 pb-1.5 border-b border-slate-100">Ticket Information</h3>
                            <dl class="space-y-3.5 text-xs font-semibold text-slate-800">
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ticket Number</dt>
                                    <dd class="text-slate-900 font-extrabold mt-0.5">{{ $record->ticket_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Status</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-100">Scheduled</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Created By</dt>
                                    <dd class="text-slate-800 mt-0.5">System</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Created On</dt>
                                    <dd class="text-slate-800 mt-0.5">{{ $record->created_at ? $record->created_at->timezone('Asia/Manila')->format('F d, Y g:i A') : '—' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Bus Summary Card -->
                        @php
                            $bus = \App\Models\Bus::find($record->getRawOriginal('bus_id'));
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#003F87] mb-3 pb-1.5 border-b border-slate-100">Bus Summary</h3>
                            <dl class="grid grid-cols-2 gap-4 text-xs font-semibold text-slate-800">
                                <div class="col-span-2">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Fleet Number</dt>
                                    <dd class="text-slate-900 font-extrabold text-sm mt-0.5">{{ $bus ? $bus->fleet_number : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Plate Number</dt>
                                    <dd class="text-slate-800 mt-0.5">{{ $bus ? $bus->plate_number : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Status</dt>
                                    <dd class="mt-0.5">
                                        <span class="inline-flex items-center rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-bold text-orange-700 border border-orange-100">Maintenance</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Manufacturer</dt>
                                    <dd class="text-slate-800 mt-0.5">{{ $bus ? $bus->manufacturer : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Model</dt>
                                    <dd class="text-slate-800 mt-0.5">{{ $bus ? $bus->model : '—' }}</dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                </div>

            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Set min date-time to current local time to prevent past selection
        const dateInput = document.getElementById('maintenance-date');
        const now = new Date();
        now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
        const localISO = now.toISOString().slice(0, 16);
        
        if (dateInput) {
            // Keep value but restrict min date
            dateInput.min = localISO;
        }

        // Override switchScreen function to redirect back to dashboard with hash
        window.switchScreen = function(screenName) {
            window.location.href = "{{ route('admin.dashboard') }}#" + screenName;
        };
    });

    async function handleEditPageSubmit(event) {
        event.preventDefault();

        const submitBtn = document.getElementById('maintenance-submit-btn');
        const technician = document.getElementById('maintenance-technician').value.trim();
        const description = document.getElementById('maintenance-desc').value.trim();
        const scheduledAt = document.getElementById('maintenance-date').value;
        const durationVal = document.getElementById('maintenance-duration').value;

        if (!scheduledAt) {
            alert('Please select a scheduled date/time.');
            return;
        }

        const payload = {
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
                submitBtn.textContent = 'Updating...';
            }

            const baseUrl = "/admin/api/maintenance/{{ $record->id }}";
            
            const response = await fetch(baseUrl, {
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
                window.location.href = "{{ route('admin.dashboard') }}#maintenance";
            } else {
                alert(data.message || 'Validation error. Please verify input data.');
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Update Schedule';
                }
            }
        } catch (error) {
            alert('Server connection error. Failed to update maintenance.');
            console.error('AJAX maintenance update error:', error);
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Update Schedule';
            }
        }
    }
</script>
@endsection
