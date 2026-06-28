{{-- ==================== FLEET MAINTENANCE SCREEN ==================== --}}
<section id="screen-maintenance" class="hidden space-y-6"
         style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-tertiary:#E8E6DF;--color-border-secondary:#D6D3C9;">

    <!-- LIST CONTAINER -->
    <div id="maintenance-list-container" class="space-y-6">
        {{-- PAGE HEADER ROW --}}
        <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 shrink-0">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Fleet Maintenance Logs</h1>
                    <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
                        <span>Dashboard</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Operations</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span id="maintenance-breadcrumb-current" class="text-slate-600 font-bold">Maintenance Logs</span>
                    </div>
                    <p class="text-[11px] text-slate-500 font-semibold mt-1">Track repairs, scheduled maintenance, and bus operational status</p>
                </div>
                <div>
                    <button onclick="openScheduleMaintenanceModal(); return false;" class="bm-btn-primary flex items-center gap-2 rounded-lg bg-[#003F87] px-4 py-2 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer border-none">
                        <i class="ti ti-plus"></i> Schedule Maintenance
                    </button>
                </div>
            </div>
        </div>

        <!-- Timeline-style List container -->
        <div class="space-y-4 max-w-3xl" id="maintenance-logs-container">
            <!-- Populated dynamically via public/js/admin-dashboard/maintenance.js -->
            <div class="py-8 text-center text-slate-400 font-semibold text-xs">
                Loading maintenance logs...
            </div>
        </div>
    </div>

    <!-- INSPECTION FORM CONTAINER (Hidden by default) -->
    <div id="maintenance-inspection-container" class="hidden space-y-6">
        <!-- BREADCRUMB & HEADER -->
        <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 shrink-0">
            <div class="flex items-center gap-4">
                <button onclick="closeInspectionModal(); return false;" 
                   class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 border-none" 
                   title="Back to Maintenance Logs">
                    <i class="ti ti-arrow-left text-lg"></i>
                </button>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Safety Inspection Checklist</h1>
                    <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                        <span>Dashboard</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Operations</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Maintenance Logs</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span class="text-[#003F87] font-bold">Inspection Verification</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- INSPECTION FORM CARD -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
            <div class="mb-6">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Final Safety Validation</h2>
                <p class="text-xs text-slate-500">Verify that all maintenance work has been completed and the bus is operationally safe before returning to service.</p>
            </div>

            <form id="inspection-form" onsubmit="handleInspectionSubmit(event)" class="space-y-6">
                <!-- Bus Info (Read-only) -->
                <div class="rounded-lg bg-slate-50 p-4 border border-slate-100">
                    <p class="text-[10px] font-bold uppercase text-slate-400 mb-2">Maintenance Record</p>
                    <div class="grid grid-cols-2 gap-4 text-xs">
                        <div>
                            <span class="text-slate-500 font-semibold">Bus:</span>
                            <span class="text-slate-900 font-bold" id="inspection-bus-label">—</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-semibold">Status:</span>
                            <span class="text-slate-900 font-bold" id="inspection-record-status">—</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-semibold">Technician:</span>
                            <span class="text-slate-900 font-bold" id="inspection-technician-label">—</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-semibold">Work Completed:</span>
                            <span class="text-slate-900 font-bold" id="inspection-date-label">—</span>
                        </div>
                    </div>
                </div>

                <!-- Inspection Decision -->
                <div class="space-y-3">
                    <label class="text-xs font-bold uppercase tracking-wider text-slate-500">Inspection Result</label>
                    <div class="space-y-2">
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 hover:border-[#003F87] hover:bg-blue-50 cursor-pointer transition-all">
                            <input type="radio" name="inspection_passed" value="true" required
                                   class="w-4 h-4 text-[#639922] cursor-pointer">
                            <div>
                                <span class="text-xs font-bold text-slate-900">✅ PASSED - Bus is operationally safe and ready for service</span>
                                <p class="text-[10px] text-slate-500 font-medium mt-0.5">All maintenance work verified complete and safety checks passed</p>
                            </div>
                        </label>
                        <label class="flex items-center gap-3 p-3 rounded-lg border border-slate-200 hover:border-[#E24B4A] hover:bg-red-50 cursor-pointer transition-all">
                            <input type="radio" name="inspection_passed" value="false" required
                                   class="w-4 h-4 text-[#E24B4A] cursor-pointer">
                            <div>
                                <span class="text-xs font-bold text-slate-900">❌ FAILED - Additional work or repairs needed</span>
                                <p class="text-[10px] text-slate-500 font-medium mt-0.5">Bus remains in maintenance status. Must be re-worked and re-inspected.</p>
                            </div>
                        </label>
                    </div>
                </div>

                <!-- Inspector Name -->
                <div class="space-y-2">
                    <label for="inspection-by" class="text-xs font-bold uppercase tracking-wider text-slate-500">Inspector Name</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                            <i class="ti ti-user-check text-base"></i>
                        </span>
                        <input id="inspection-by" name="inspected_by" type="text" placeholder="e.g. Engr. Maria Santos" required
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                    </div>
                    <p class="text-[10px] text-slate-400 font-medium">Name of the person verifying this inspection (must be different from technician who did the work)</p>
                </div>

                <!-- Inspection Notes -->
                <div class="space-y-2">
                    <label for="inspection-notes" class="text-xs font-bold uppercase tracking-wider text-slate-500">Inspection Notes (Optional)</label>
                    <textarea id="inspection-notes" name="inspection_notes" placeholder="Any additional remarks or observations from the inspection..." rows="3"
                              class="w-full rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] resize-none"></textarea>
                    <p class="text-[10px] text-slate-400 font-medium">Document any issues found or observations during inspection</p>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                    <button type="button" onclick="closeInspectionModal(); return false;" 
                       class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer border-none">
                        Cancel
                    </button>
                    <button type="submit" id="inspection-submit-btn" 
                            class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none">
                        <i class="ti ti-check"></i> Submit Inspection
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- FORM CONTAINER -->
    <div id="maintenance-form-container" class="hidden space-y-6">
        <!-- BREADCRUMB & HEADER -->
        <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 shrink-0">
            <div class="flex items-center gap-4">
                <button onclick="closeScheduleMaintenanceModal(); return false;" 
                   class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 border-none" 
                   title="Back to Maintenance Logs">
                    <i class="ti ti-arrow-left text-lg"></i>
                </button>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">Schedule Maintenance</h1>
                    <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                        <span>Dashboard</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Operations</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Maintenance Logs</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span class="text-[#003F87] font-bold" id="maintenance-breadcrumb-current-sub">Schedule Session</span>
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

            <form id="schedule-maintenance-form" onsubmit="handleMaintenanceSubmit(event)" class="space-y-6">
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
                                {{-- Options are loaded dynamically by maintenance.js from global fleetData --}}
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
                    <button type="button" onclick="closeScheduleMaintenanceModal(); return false;" 
                       class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer border-none">
                        Cancel
                    </button>
                    <button type="submit" id="maintenance-submit-btn" 
                            class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none">
                        Schedule Session
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

{{-- ==================== SCOPED CSS FOR MAINTENANCE ==================== --}}
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