<section id="screen-drivers-show" class="hidden space-y-6"
         style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-tertiary:#E8E6DF;--color-border-secondary:#D6D3C9;">

    <!-- BREADCRUMB & HEADER -->
    <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
        <div class="flex items-center gap-4">
            <button onclick="switchScreen('drivers'); return false;" 
               class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 border-none" 
               title="Back to Driver Management">
                <i class="ti ti-arrow-left text-lg"></i>
            </button>
            <div>
                <h1 class="text-xl font-bold text-slate-900 tracking-tight">Driver Profile</h1>
                <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                    <span>Dashboard</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Fleet</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span>Driver Management</span>
                    <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                    <span id="dp-show-breadcrumb-name" class="text-[#003F87] font-bold">Driver Details</span>
                </div>
            </div>
        </div>
    </div>

    <!-- PROFILE CONTAINER -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 animate-fade-in">
        
        <!-- LEFT SIDE: PROFILE CARD & STATS -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Profile Card -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_2px_8px_rgba(0,0,0,0.04)] text-center relative overflow-hidden">
                <!-- Background Accent -->
                <div class="absolute top-0 inset-x-0 h-24 bg-gradient-to-r from-slate-100 to-slate-200 opacity-60"></div>
                
                <!-- Avatar -->
                <div class="relative flex justify-center mt-6 mb-4">
                    <div id="dp-show-avatar" class="w-20 h-20 rounded-full bg-[#E6F1FB] border-4 border-white text-[#003F87] font-bold text-2xl flex items-center justify-center shadow-md select-none">
                        --
                    </div>
                </div>
                
                <!-- Identity Info -->
                <h2 class="text-lg font-bold text-slate-900 tracking-tight" id="dp-show-name">--</h2>
                <p class="text-xs font-mono font-bold text-slate-400 mt-0.5" id="dp-show-empid">EMP-0000</p>
                
                <!-- Contact Details -->
                <div class="mt-4 pt-4 border-t border-slate-100 text-left space-y-2 text-xs">
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-medium">License No:</span>
                        <span id="dp-show-license" class="text-slate-800 font-mono font-bold">--</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-medium">License Expiry:</span>
                        <span id="dp-show-expiry" class="text-slate-800 font-semibold">--</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-slate-400 font-medium">Contact:</span>
                        <span id="dp-show-contact" class="text-slate-800 font-semibold">--</span>
                    </div>
                    <div class="flex justify-between" id="dp-show-bus-row">
                        <span class="text-slate-400 font-medium">Assigned Bus:</span>
                        <span id="dp-show-bus" class="text-slate-800 font-mono font-bold">--</span>
                    </div>
                    <div class="flex justify-between" id="dp-show-route-row">
                        <span class="text-slate-400 font-medium">Assigned Route:</span>
                        <span id="dp-show-route" class="inline-flex rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wider">
                            --
                        </span>
                    </div>
                </div>

                <!-- Actions Row -->
                <div class="mt-6 pt-6 border-t border-slate-100 flex items-center gap-2">
                    <button id="dp-show-edit-btn" 
                       class="flex-1 rounded-lg border border-slate-200 bg-white py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 hover:text-slate-900 transition shadow-sm cursor-pointer flex items-center justify-center gap-1.5 border-none">
                        <i class="ti ti-edit"></i> Edit Details
                    </button>
                    <button type="button" id="dp-show-suspend-btn"
                            class="flex-1 rounded-lg border py-2 text-xs font-bold transition shadow-sm cursor-pointer flex items-center justify-center gap-1.5 border-none">
                        <i class="ti ti-ban"></i> Suspend
                    </button>
                </div>
            </div>

            <!-- Performance Card -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Performance Index</h3>
                    <span id="dp-show-perf-label" class="text-xs font-extrabold text-[#003F87]">100 / 100</span>
                </div>
                <div class="h-2 w-full rounded-full bg-slate-100 overflow-hidden mb-2">
                    <div id="dp-show-perf-bar" class="h-full bg-gradient-to-r from-blue-500 to-[#003F87] rounded-full transition-all duration-500" 
                         style="width: 100%;"></div>
                </div>
                <p class="text-[10px] text-slate-400 leading-normal">Score evaluated from trip schedule compliance, speed limits compliance, and commuter feedback logs over the past 30 days.</p>
            </div>
        </div>

        <!-- RIGHT SIDE: STATS STRIP & TRIP HISTORY -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Stats Cards Strip -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Trips Today</span>
                    <span id="dp-show-stat-trips" class="text-xl font-bold text-slate-800 block mt-1">0</span>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Pax Served</span>
                    <span id="dp-show-stat-pax" class="text-xl font-bold text-slate-800 block mt-1">0</span>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Avg Pax/Trip</span>
                    <span id="dp-show-stat-avg" class="text-xl font-bold text-slate-800 block mt-1">0.0</span>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block">Incidents (30d)</span>
                    <span id="dp-show-stat-incidents" class="text-xl font-bold block mt-1">0</span>
                </div>
            </div>

            <!-- Trip History Table Card -->
            <div class="rounded-2xl border border-slate-200 bg-white shadow-[0_2px_8px_rgba(0,0,0,0.04)] overflow-hidden">
                <div class="px-6 py-5 border-b border-slate-100 flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-800">Trip History — Last 7 Days</h3>
                    <span id="dp-show-trip-count" class="text-xs text-slate-400 font-semibold">0 records</span>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-slate-50 border-b border-slate-100">
                                <th class="px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Date</th>
                                <th class="px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Bus</th>
                                <th class="px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Route</th>
                                <th class="px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 text-center">Trips</th>
                                <th class="px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-400 text-center">Pax Boarded</th>
                                <th class="px-6 py-3.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">Status</th>
                            </tr>
                        </thead>
                        <tbody id="dp-show-trip-tbody" class="divide-y divide-slate-100 text-xs">
                            <!-- Loaded by JS -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Home Address & Emergency Details Card -->
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4">Residential & Emergency Contact Data</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                    <div>
                        <span class="text-slate-400 font-medium block">Residential Address</span>
                        <p id="dp-show-address" class="text-slate-800 font-semibold mt-1 leading-relaxed">--</p>
                    </div>
                    <div>
                        <span class="text-slate-400 font-medium block">Emergency Contact</span>
                        <p id="dp-show-emergency" class="text-slate-800 font-semibold mt-1 leading-relaxed">--</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

</section>
