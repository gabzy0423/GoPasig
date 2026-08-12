<section id="screen-drivers-show" class="hidden space-y-6"
         style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-tertiary:#E8E6DF;--color-border-secondary:#D6D3C9;">

    <!-- BREADCRUMB & HEADER -->
    <div class="driver-profile-no-print flex flex-col gap-1 border-b border-slate-200 pb-4 mb-4 shrink-0">
        <div class="flex items-center gap-4">
            <button onclick="switchScreen('drivers'); return false;" 
               class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 border-none" 
               title="Back to Driver Management">
                <i class="ti ti-arrow-left text-lg"></i>
            </button>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Driver Details</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Fleet</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Driver Management</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span id="dp-show-breadcrumb-name" class="text-[#003F87] font-bold">Driver details</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Component 1: Operational Header Banner -->
    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm relative overflow-hidden flex flex-col gap-6">
        <!-- Background decorative pulse element -->
        <div class="absolute top-0 inset-x-0 h-1 bg-gradient-to-r from-[#003F87] via-[#3b82f6] to-[#639922]"></div>
        
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
            <!-- Left Info -->
            <div class="flex items-start gap-4">
                <div id="dp-show-avatar" class="w-16 h-16 rounded-full bg-[#E6F1FB] border border-[#003F87]/20 text-[#003F87] font-bold text-xl flex items-center justify-center shadow-inner select-none shrink-0">
                    --
                </div>
                <div>
                    <h2 class="text-xl font-black text-slate-900 tracking-tight flex items-center gap-2 flex-wrap" id="dp-show-name">
                        --
                    </h2>
                    <p class="text-xs font-mono font-extrabold text-slate-450 mt-1 flex items-center gap-1.5">
                        <i class="ti ti-id-badge text-slate-450 text-sm"></i> Employee ID: <span id="dp-show-empid" class="text-slate-705">DR-0000</span>
                    </p>
                    
                    <!-- Badges Row -->
                    <div class="flex flex-wrap items-center gap-2 mt-3 select-none">
                        <span id="dp-show-status-badge" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider">
                            --
                        </span>
                        <span id="dp-show-compliance-badge" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider">
                            --
                        </span>
                        <span id="dp-show-rating-badge" class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider">
                            --
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right: Status Strip / Assignment Block -->
            <div class="border-t md:border-t-0 md:border-l border-slate-100 pt-4 md:pt-0 md:pl-6 flex-1 max-w-md">
                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider block mb-2 select-none">Current Assignment Summary</span>
                <div class="grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                    <div class="flex flex-col">
                        <span class="text-slate-400 font-semibold text-[10px]">Assigned Bus</span>
                        <span id="dp-show-bus-strip" class="text-slate-800 font-mono font-bold mt-0.5">--</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-slate-400 font-semibold text-[10px]">Current Route</span>
                        <span id="dp-show-route-strip" class="text-slate-800 font-bold mt-0.5">--</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-slate-400 font-semibold text-[10px]">Employment Status</span>
                        <span id="dp-show-shift-strip" class="text-slate-800 font-bold mt-0.5">--</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-slate-400 font-semibold text-[10px]">Active Trip</span>
                        <span id="dp-show-dispatch-strip" class="text-slate-800 font-bold mt-0.5">--</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Component 2: 3 KPI Cards Row -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        <!-- Card 1: Actual operations score -->
        <div class="rounded-2xl border-l-4 border-l-[#639922] border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block select-none">Operational Score Today</span>
            <span id="dp-show-stat-score-kpi" class="text-2xl font-black text-slate-800 block mt-1">--</span>
        </div>
        <!-- Card 2: Trips Completed -->
        <div class="rounded-2xl border-l-4 border-l-[#003F87] border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block select-none">Trips Completed</span>
            <span id="dp-show-stat-trips-kpi" class="text-2xl font-black text-slate-800 block mt-1">--</span>
        </div>
        <!-- Card 3: Incidents (30d) -->
        <div class="rounded-2xl border-l-4 border-l-[#E24B4A] border border-slate-200 bg-white p-4 shadow-sm">
            <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400 block select-none">Incidents (30 Days)</span>
            <span id="dp-show-stat-incidents-kpi" class="text-2xl font-black text-slate-800 block mt-1">--</span>
        </div>
    </div>

    <!-- MAIN TWO-COLUMN DASHBOARD GRID -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- LEFT COLUMN: PRIMARY OPERATIONAL DATA (2/3 width) -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Component 3: Conditional Operational Status Panel -->
            <div id="dp-show-operational-card" class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4 select-none">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight flex items-center gap-1.5">
                        <i class="ti ti-route text-[#003F87]"></i> Operational Status
                    </h3>
                    <span id="dp-show-active-indicator" class="inline-flex items-center gap-1 rounded px-2 py-0.5 text-[9px] font-extrabold uppercase tracking-wider">
                        --
                    </span>
                </div>
                
                <!-- Dynamic Content Wrapper -->
                <div id="dp-show-operational-content">
                    <!-- Loaded dynamically from current assignment and Trip state. -->
                </div>
            </div>

            <!-- Component 4: Actual Operations Performance Panel -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4 select-none">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight flex items-center gap-1.5">
                        <i class="ti ti-chart-bar text-[#003F87]"></i> Today's Operational Performance
                    </h3>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-6">
                    <!-- Left: Progress Bars -->
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-xs mb-1 font-semibold text-slate-650">
                                <span>Operational Score Today</span>
                                <span id="dp-show-perf-label" class="font-extrabold text-[#003F87]">-- / 100</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden">
                                <div id="dp-show-perf-bar" class="h-full bg-[#003F87] rounded-full transition-all duration-500" style="width:0%;"></div>
                            </div>
                        </div>

                    </div>

                    <!-- Right: Actual Trip and passenger-event statistics -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 border-l border-slate-100 pl-6">
                        <div class="flex flex-col">
                            <span class="text-slate-400 font-semibold text-[10px] uppercase">Trips Run Today</span>
                            <span id="dp-show-stat-trips" class="text-slate-800 font-black text-sm mt-1">--</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-slate-400 font-semibold text-[10px] uppercase">Recorded Boarded Today</span>
                            <span id="dp-show-stat-pax" class="text-[#003F87] font-black text-sm mt-1">--</span>
                        </div>
                        <div class="flex flex-col">
                            <span class="text-slate-400 font-semibold text-[10px] uppercase">Boarded / Trip Today</span>
                            <span id="dp-show-stat-avg" class="text-slate-800 font-black text-sm mt-1">--</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Component 5: Driver Activity Timeline (Grouped Date Sections) -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-100 pb-4 mb-4 select-none">
                    <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight flex items-center gap-1.5">
                        <i class="ti ti-timeline text-[#003F87]"></i> Recent Driver Activity
                    </h3>
                </div>
                
                <div class="relative pl-6 border-l-2 border-slate-200 space-y-6" id="dp-show-timeline-wrapper">
                    <!-- Content injected dynamically via drivers.js fillShowScreen() -->
                </div>
            </div>

            <!-- Component 9: Trip History Table (Showing Count display) -->
            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-black text-slate-800 uppercase tracking-tight">Commuter Service Trip History</h3>
                        <p class="text-[10px] text-slate-400 font-semibold mt-0.5 select-none" id="dp-show-trip-count">Showing 0 of 0 Trips</p>
                    </div>
                    
                    <!-- Current-driver history output -->
                    <div class="flex items-center gap-2">
                        <button onclick="exportCurrentDriverHistoryCSV(); return false;" class="flex items-center gap-1 text-[11px] font-bold text-slate-600 hover:text-slate-900 border border-slate-200 rounded px-2.5 py-1 bg-white cursor-pointer shadow-sm">
                            <i class="ti ti-download"></i> Export Recent History
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-150 select-none">
                                <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Date</th>
                                <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Bus</th>
                                <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Route</th>
                                <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-400 text-center">Trip ID</th>
                                <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-400 text-center">Recorded Boarded</th>
                                <th class="px-6 py-3.5 text-[10px] font-black uppercase tracking-wider text-slate-400">Status</th>
                            </tr>
                        </thead>
                        <tbody id="dp-show-trip-tbody" class="divide-y divide-slate-100 text-xs text-slate-700">
                            <!-- Injected dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN: SUPPORTING SIDEBAR PANEL (1/3 width) -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- Component 6: Priority-Grouped Alerts Panel -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 mb-4 select-none">Operational Alerts</h3>
                <div id="dp-show-alerts-priority-list" class="space-y-4">
                    <!-- Critical Section -->
                    <div id="dp-show-alert-section-critical" class="space-y-2">
                        <span class="text-[9px] font-black tracking-widest text-red-500 uppercase block select-none">Critical</span>
                        <div class="rounded-lg bg-rose-50 border border-rose-100 p-3 flex items-start gap-2.5 text-xs text-rose-700" id="dp-show-alert-critical-body">
                            <!-- Dynamic critical alerts -->
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="h-px bg-slate-100 my-2"></div>

                    <!-- Warning Section -->
                    <div id="dp-show-alert-section-warning" class="space-y-2">
                        <span class="text-[9px] font-black tracking-widest text-amber-500 uppercase block select-none">Warning</span>
                        <div class="rounded-lg bg-amber-50 border border-amber-100 p-3 flex items-start gap-2.5 text-xs text-amber-700" id="dp-show-alert-warning-body">
                            <!-- Dynamic warning alerts -->
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="h-px bg-slate-100 my-2"></div>

                    <!-- Information Section -->
                    <div class="space-y-2">
                        <span class="text-[9px] font-black tracking-widest text-[#003F87] uppercase block select-none">Information</span>
                        <div class="space-y-2" id="dp-show-alert-info-container">
                            <div id="dp-show-incident-summary" class="flex items-center gap-2 text-xs text-slate-700 font-semibold rounded-lg p-2.5">
                                <!-- Populated from actual Incident records in the last 30 days. -->
                            </div>
                            <div class="flex items-center gap-2 text-xs text-slate-700 font-semibold bg-[#E6F1FB] border border-[#003F87]/10 rounded-lg p-2.5" id="dp-show-dispatch-eligibility">
                                <i class="ti ti-circle-check text-[#003F87] text-sm"></i>
                                <span>Eligible for Dispatch</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Component 7: Categorized Quick Actions Panel -->
            <div class="driver-profile-no-print rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 mb-4 select-none">Administrative Actions</h3>
                
                <div class="space-y-4">
                    <!-- Driver Management Section -->
                    <div>
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-2 select-none">Driver Management</span>
                        <div class="flex flex-col gap-2">
                            <button id="dp-show-edit-btn" 
                               class="flex w-full items-center justify-start gap-2.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition shadow-sm cursor-pointer select-none">
                                <i class="ti ti-edit text-slate-450 text-sm"></i> Edit Driver Details
                            </button>
                            <button type="button" id="dp-show-suspend-btn"
                                    class="flex w-full items-center justify-start gap-2.5 rounded-lg border px-3.5 py-2.5 text-xs font-bold transition shadow-sm cursor-pointer select-none">
                                <i class="ti ti-ban text-sm"></i> Suspend Driver
                            </button>
                        </div>
                    </div>

                    <!-- Divider -->
                    <div class="h-px bg-slate-100"></div>

                    <!-- Reports Section -->
                    <div>
                        <span class="text-[9px] font-black text-slate-400 uppercase tracking-widest block mb-2 select-none">Reports & Outputs</span>
                        <div class="flex flex-col gap-2">
                            <button onclick="printCurrentDriverProfile(); return false;"
                               class="flex w-full items-center justify-start gap-2.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition shadow-sm cursor-pointer select-none">
                                <i class="ti ti-printer text-slate-450 text-sm"></i> Print Profile
                            </button>
                            <button onclick="exportCurrentDriverReportCSV(); return false;"
                               class="flex w-full items-center justify-start gap-2.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2.5 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition shadow-sm cursor-pointer select-none">
                                <i class="ti ti-download text-slate-450 text-sm"></i> Export Driver Report
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Component 8: Compliance Checklist Card -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 mb-4 select-none">Compliance & Licensing</h3>
                
                <div class="space-y-4">
                    <!-- Verification checklist -->
                    <div class="space-y-2">
                        <div class="flex items-center justify-between text-xs p-2 rounded-lg bg-slate-50" id="dp-show-compliance-license-check-wrapper">
                            <span class="font-semibold text-slate-700">Driver License Validity</span>
                            <span class="inline-flex items-center gap-1 font-bold" id="dp-show-compliance-license-check-value">
                                <i class="ti ti-circle-check text-emerald-500 text-sm"></i> Valid
                            </span>
                        </div>
                        <div class="flex items-center justify-between text-xs p-2 rounded-lg bg-slate-50" id="dp-show-compliance-dispatch-check-wrapper">
                            <span class="font-semibold text-slate-700">Dispatch Eligibility</span>
                            <span class="inline-flex items-center gap-1 font-bold" id="dp-show-compliance-dispatch-check-value">
                                <i class="ti ti-circle-check text-emerald-500 text-sm"></i> Eligible
                            </span>
                        </div>
                    </div>

                    <!-- Details grid -->
                    <div class="border-t border-slate-100 pt-3 text-xs space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-400 font-medium">License No:</span>
                            <span id="dp-show-license" class="text-slate-800 font-mono font-bold">--</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400 font-medium">License Expiry:</span>
                            <span id="dp-show-expiry" class="text-slate-800 font-semibold mt-0.5">--</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Component 10: Supporting Personal Contact Info Card (At absolute bottom) -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 class="text-xs font-black uppercase tracking-wider text-slate-400 mb-4 select-none">Personal Information</h3>
                <div class="space-y-4 text-xs">
                    <div>
                        <span class="text-slate-400 font-medium block">Mobile Contact</span>
                        <p id="dp-show-contact" class="text-slate-850 font-bold mt-1">--</p>
                    </div>
                    <div class="h-px bg-slate-100"></div>
                    <div>
                        <span class="text-slate-400 font-medium block">Residential Address</span>
                        <p id="dp-show-address" class="text-slate-800 font-semibold mt-1 leading-relaxed">--</p>
                    </div>
                    <div class="h-px bg-slate-100"></div>
                    <div>
                        <span class="text-slate-400 font-medium block">Emergency Contact Person</span>
                        <p id="dp-show-emergency" class="text-slate-800 font-semibold mt-1 leading-relaxed">--</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>

<style>
@media print {
    body.printing-driver-profile * {
        visibility: hidden !important;
    }

    body.printing-driver-profile #screen-drivers-show,
    body.printing-driver-profile #screen-drivers-show * {
        visibility: visible !important;
    }

    body.printing-driver-profile #screen-drivers-show {
        display: block !important;
        position: absolute;
        inset: 0;
        width: 100%;
        margin: 0 !important;
        padding: 0 !important;
        background: #ffffff !important;
        color-adjust: exact;
        print-color-adjust: exact;
        -webkit-print-color-adjust: exact;
    }

    body.printing-driver-profile #screen-drivers-show .driver-profile-no-print {
        display: none !important;
    }

    body.printing-driver-profile #screen-drivers-show .rounded-2xl {
        break-inside: avoid-page;
    }

    body.printing-driver-profile #screen-drivers-show table {
        break-inside: auto;
    }

    body.printing-driver-profile #screen-drivers-show thead {
        display: table-header-group;
    }

    body.printing-driver-profile #screen-drivers-show tr {
        break-inside: avoid;
    }

    body.printing-driver-profile #screen-drivers-show .shadow-sm,
    body.printing-driver-profile #screen-drivers-show .shadow-inner {
        box-shadow: none !important;
    }
}
</style>
