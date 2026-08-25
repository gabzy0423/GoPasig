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
                    <a href="{{ route('admin.maintenance.create') }}" class="bm-btn-primary no-underline">
                        <i class="ti ti-plus"></i> Schedule Maintenance
                    </a>
                </div>
            </div>
        </div>

        <!-- Metric Summary Cards (4 Columns) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <!-- Card 1: Total Records (Clicks filter 'all') -->
            <div onclick="toggleMaintenanceFilter('all', this)" class="relative bg-white border border-slate-200 rounded-xl p-5 flex flex-col justify-between h-[106px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer" data-card-filter="all">
                <div class="flex justify-between items-start">
                    <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Total Records</span>
                    <div class="h-7 w-7 rounded bg-slate-100 flex items-center justify-center text-slate-700">
                        <i class="ti ti-clipboard-list text-base"></i>
                    </div>
                </div>
                <div class="mt-1">
                    <span class="text-[24px] font-black text-slate-900 leading-none" id="maint-stat-total">{{ $totalRecords }}</span>
                    <div class="text-[10px] text-slate-550 font-semibold mt-0.5">All tickets</div>
                </div>
            </div>

            <!-- Card 2: Scheduled (Clicks filter 'scheduled') -->
            <div onclick="toggleMaintenanceFilter('scheduled', this)" class="relative bg-white border border-slate-200 rounded-xl p-5 flex flex-col justify-between h-[106px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer border-l-[3px] border-l-[#003F87]" data-card-filter="scheduled">
                <div class="flex justify-between items-start">
                    <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Scheduled</span>
                    <div class="h-7 w-7 rounded bg-blue-50 flex items-center justify-center text-[#003F87]">
                        <i class="ti ti-calendar-event text-base"></i>
                    </div>
                </div>
                <div class="mt-1">
                    <span class="text-[24px] font-black text-slate-900 leading-none" id="maint-stat-scheduled">{{ $scheduledCount }}</span>
                    <div class="text-[10px] text-slate-550 font-semibold mt-0.5">Pending execution</div>
                </div>
            </div>

            <!-- Card 3: In Progress (Clicks filter 'in_progress') -->
            <div onclick="toggleMaintenanceFilter('in_progress', this)" class="relative bg-white border border-slate-200 rounded-xl p-5 flex flex-col justify-between h-[106px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer border-l-[3px] border-l-[#BA7517]" data-card-filter="in_progress">
                <div class="flex justify-between items-start">
                    <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">In Progress</span>
                    <div class="h-7 w-7 rounded bg-amber-50 flex items-center justify-center text-[#BA7517]">
                        <i class="ti ti-tool text-base"></i>
                    </div>
                </div>
                <div class="mt-1">
                    <span class="text-[24px] font-black text-slate-900 leading-none" id="maint-stat-in-progress">{{ $inProgressCount }}</span>
                    <div class="text-[10px] text-slate-550 font-semibold mt-0.5">Under repair</div>
                </div>
            </div>

            <!-- Card 4: Completed (Clicks filter 'completed') -->
            <div onclick="toggleMaintenanceFilter('completed', this)" class="relative bg-white border border-slate-200 rounded-xl p-5 flex flex-col justify-between h-[106px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer border-l-[3px] border-l-[#639922]" data-card-filter="completed">
                <div class="flex justify-between items-start">
                    <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Completed</span>
                    <div class="h-7 w-7 rounded bg-emerald-50 flex items-center justify-center text-[#639922]">
                        <i class="ti ti-circle-check text-base"></i>
                    </div>
                </div>
                <div class="mt-1">
                    <span class="text-[24px] font-black text-slate-900 leading-none" id="maint-stat-completed">{{ $completedCount }}</span>
                    <div class="text-[10px] text-slate-550 font-semibold mt-0.5">Passed inspection</div>
                </div>
            </div>
        </div>

        <!-- Fleet Health Indicators Section -->
        <div class="space-y-3">
            <h2 class="text-xs font-bold text-slate-450 uppercase tracking-widest select-none">Fleet Health Indicators</h2>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <!-- Card 1: Under Observation -->
                <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[80px] shadow-sm border-l-[3px] border-l-[#8C7B1F]">
                    <div class="flex justify-between items-start">
                        <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Under Observation</span>
                        <div class="h-5 w-5 rounded bg-yellow-50 flex items-center justify-center text-[#8C7B1F]">
                            <i class="ti ti-eye text-xs"></i>
                        </div>
                    </div>
                    <div class="mt-0.5 flex items-baseline gap-1.5">
                        <span class="text-[18px] font-black text-slate-700 leading-none" id="maint-stat-observation">{{ $observationCount }}</span>
                        <span class="text-[9px] text-slate-400 font-semibold leading-none">Buses with active obs</span>
                    </div>
                </div>

                <!-- Card 2: Requires Repair -->
                <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[80px] shadow-sm border-l-[3px] border-l-[#D13B3B]">
                    <div class="flex justify-between items-start">
                        <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Requires Repair</span>
                        <div class="h-5 w-5 rounded bg-rose-50 flex items-center justify-center text-[#D13B3B]">
                            <i class="ti ti-alert-triangle text-xs"></i>
                        </div>
                    </div>
                    <div class="mt-0.5 flex items-baseline gap-1.5">
                        <span class="text-[18px] font-black text-slate-700 leading-none" id="maint-stat-observation-failed">{{ $requiringRepairCount }}</span>
                        <span class="text-[9px] text-slate-400 font-semibold leading-none">Active inspection fails</span>
                    </div>
                </div>

                <!-- Card 3: Overdue -->
                <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[80px] shadow-sm border-l-[3px] border-l-[#E24B4A]">
                    <div class="flex justify-between items-start">
                        <span class="text-[9px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Overdue</span>
                        <div class="h-5 w-5 rounded bg-red-50 flex items-center justify-center text-[#E24B4A]">
                            <i class="ti ti-clock text-xs"></i>
                        </div>
                    </div>
                    <div class="mt-0.5 flex items-baseline gap-1.5">
                        <span class="text-[18px] font-black text-slate-700 leading-none" id="maint-stat-overdue">{{ $overdueCount }}</span>
                        <span class="text-[9px] text-slate-400 font-semibold leading-none">Past due work</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- SEARCH & UTILITY TOOLBAR --}}
        <div class="flex flex-col md:flex-row md:items-center gap-4 w-full bg-white p-4 border border-slate-200 rounded-xl shadow-sm select-none">
            <!-- Left Region: Search input (only flexible element) -->
            <div class="relative flex-1 min-w-0">
                <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                <input id="maintenance-search" type="text" 
                       class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-lg text-sm text-slate-800 focus:outline-none focus:border-[#003F87] focus:ring-1 focus:ring-[#003F87] transition-all" 
                       placeholder="Search maintenance records by plate, ticket, technician, type..."
                       oninput="searchMaintenanceTable()">
            </div>
            
            <!-- Center Region: Information chips -->
            <div class="flex items-center gap-3 whitespace-nowrap shrink-0 text-xs font-semibold text-slate-500">
                <div class="flex items-center gap-1.5 bg-slate-50 border border-slate-150 px-3 py-2 rounded-lg select-none">
                    <span>⏱ Avg. Duration:</span>
                    <span id="maint-stat-duration" class="font-bold text-slate-700" title="{{ $averageDuration }}">{{ $averageDuration }}</span>
                </div>
                <div class="flex items-center gap-1 text-slate-400 select-none px-1">
                    <span>Last updated: <span id="maint-last-updated" class="font-mono text-slate-655 font-bold">Just now</span></span>
                </div>
            </div>

            <!-- Right Region: Action buttons -->
            <div class="flex items-center gap-2 shrink-0">
                <button onclick="fetchMaintenanceLogs(); return false;" class="flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold transition cursor-pointer shadow-sm">
                    <i class="ti ti-refresh text-slate-550"></i> Refresh
                </button>
                <button onclick="exportMaintenanceCSV(); return false;" class="flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold transition cursor-pointer shadow-sm">
                    <i class="ti ti-download text-slate-550"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Table Title and Showing Counter -->
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 select-none">Maintenance Records</h3>
            <span id="maint-showing-count" class="text-xs font-semibold text-slate-500">Showing 0 of 0 logs</span>
        </div>

        <!-- Logs Table -->
        <div class="bm-table-card">
            <div class="overflow-x-auto w-full">
                <table class="bm-table">
                    <thead>
                        <tr class="bm-thead-row">
                            <th class="bm-th">Ticket Number</th>
                            <th class="bm-th">Bus</th>
                            <th class="bm-th">Maintenance Type</th>
                            <th class="bm-th">Scheduled Date</th>
                            <th class="bm-th">Technician / Service Provider</th>
                            <th class="bm-th">Status</th>
                            <th class="bm-th text-right pr-6">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="maintenance-table-body">
                        <tr>
                            <td colspan="7" class="px-5 py-8 text-center text-xs font-semibold text-slate-400">
                                Loading maintenance logs...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div id="maintenance-pagination" class="flex justify-end items-center gap-1.5 mt-4 select-none"></div>
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
</section>

{{-- ==================== SCOPED CSS FOR MAINTENANCE ==================== --}}
<style>
    #screen-maintenance {
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    /* ── Primary Buttons ── */
    .bm-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 36px;
        padding: 0 14px;
        background: #003F87;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.15s;
        white-space: nowrap;
    }

    .bm-btn-primary:hover {
        background: #002d62;
    }

    /* ── Action Circular Buttons ── */
    .bm-icon-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-primary);
        color: var(--color-text-secondary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .bm-icon-btn:hover {
        background: var(--color-background-secondary);
        color: #003F87;
        border-color: #003F87;
        transform: translateY(-1px);
    }

    .bm-icon-btn--danger:hover {
        background: #FCEBEB;
        color: #A32D2D;
        border-color: #F09595;
    }

    /* --- Actions Dropdown --- */
    .bm-dropdown-menu {
        position: absolute;
        top: 30px;
        right: 0;
        width: 140px;
        background: #ffffff;
        border: 0.5px solid #D6D3C9;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        z-index: 50;
        padding: 4px 0;
    }

    .bm-dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 8px 12px;
        border: none;
        background: transparent;
        font-size: 12px;
        font-weight: 500;
        color: #1A1917;
        text-align: left;
        cursor: pointer;
        transition: background 0.1s;
    }

    .bm-dropdown-item:hover {
        background: #F8F7F4;
    }

    .bm-dropdown-item i {
        font-size: 14px;
    }

    .bm-dropdown-divider {
        height: 0.5px;
        background: #E8E6DF;
        margin: 4px 0;
    }

    .bm-count-label {
        font-size: 12px;
        color: var(--color-text-secondary);
        margin-left: auto;
    }

    /* ── Table Card ── */
    .bm-table-card {
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: 12px;
        overflow: hidden;
    }

    .bm-table {
        width: 100%;
        border-collapse: collapse;
    }

    .bm-thead-row {
        background: var(--color-background-secondary);
    }

    .bm-th {
        padding: 10px 14px;
        font-size: 11px;
        font-weight: 500;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        text-align: left;
        white-space: nowrap;
    }

    .bm-td {
        padding: 11px 14px;
        font-size: 13px;
        color: var(--color-text-primary);
        vertical-align: middle;
        border-bottom: 0.5px solid var(--color-border-tertiary);
    }

    .bm-tbody-row {
        transition: background 0.1s;
    }

    .bm-tbody-row:hover {
        background: #EEF3FF;
    }

    .bm-tbody-row:last-child .bm-td {
        border-bottom: none;
    }

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

    /* --- Pagination Controls --- */
    .bm-page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        min-width: 32px;
        padding: 0 8px;
        border: 1px solid #D6D3C9;
        border-radius: 6px;
        background: #ffffff;
        color: #5F5E5A;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .bm-page-btn:hover:not(:disabled) {
        background: #F8F7F4;
        color: #1A1917;
        border-color: #A8A6A0;
    }
    .bm-page-btn.active {
        background: #003F87;
        color: #ffffff;
        border-color: #003F87;
    }
    .bm-page-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
</style>
