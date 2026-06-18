<section id="screen-overview" class="space-y-6 animate-fade-in">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Fleet Operations Overview</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Overview</span>
        </div>
    </div>

    @if(isset($missingThresholdKey) && $missingThresholdKey)
    <x-ui.alerts variant="error" icon="exclamation-triangle" class="animate-fade-in-up">
        <x-ui.alerts.heading>Warning</x-ui.alerts.heading>
        <x-ui.alerts.description>The critical simulation setting <code>default_demand_threshold</code> is missing from the <code>dispatch_simulation_defaults</code> table. Please seed or configure this setting immediately.</x-ui.alerts.description>
        <x-slot:controls>
            <x-ui.button size="xs" color="red" onclick="switchScreen('settings')" class="cursor-pointer font-bold uppercase tracking-wider">
                Configure Now
            </x-ui.button>
        </x-slot:controls>
    </x-ui.alerts>
    @endif

    <!-- ==================== SECTION 1: WELCOME HEADER + DATE/TIME STRIP ==================== -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between bg-[#003F87] p-5 sm:px-6 py-4 rounded-xl shadow-md gap-4 text-white">
        <div>
            @php
                $hour = \Carbon\Carbon::now('Asia/Manila')->hour;
                $greeting = 'Good morning';
                if ($hour >= 12 && $hour < 17) {
                    $greeting = 'Good afternoon';
                } elseif ($hour >= 17) {
                    $greeting = 'Good evening';
                }
            @endphp
            <h2 class="text-[18px] font-bold text-white tracking-tight">{{ $greeting }}, {{ Auth::user() ? Auth::user()->name : 'System Admin' }}</h2>
            <p class="text-[13px] text-white/70 font-semibold mt-0.5">Central Control Operator — Pasig City Libreng Sakay Program</p>
        </div>
        
        <div class="flex flex-wrap items-center gap-4 md:justify-end text-[13px] font-semibold">
            <!-- Calendar Strip -->
            <div class="flex items-center gap-1.5 bg-white/10 px-3 py-1.5 rounded-lg backdrop-blur-sm">
                <i class="ti ti-calendar text-base opacity-95"></i>
                <span>{{ date('l, F d, Y') }}</span>
            </div>
            
            <!-- Live Clock -->
            <div class="flex items-center gap-1.5 bg-white/10 px-3 py-1.5 rounded-lg backdrop-blur-sm">
                <i class="ti ti-clock text-base opacity-95"></i>
                <span id="admin-live-clock" class="font-mono tracking-wider">--:--:-- --</span>
            </div>
            
            <!-- System Status Chip -->
            <div id="system-status-container">
                @php
                    $statusColor = match($systemStatus) {
                        'critical' => 'red',
                        'degraded' => 'yellow',
                        default    => 'green',
                    };
                    $statusLabel = match($systemStatus) {
                        'critical' => 'System Critical',
                        'degraded' => 'System Degraded',
                        default    => 'Systems Nominal',
                    };
                    $dotColor = match($systemStatus) {
                        'critical' => 'bg-red-500',
                        'degraded' => 'bg-yellow-400',
                        default    => 'bg-[#639922]',
                    };
                @endphp
                <x-ui.badge color="{{ $statusColor }}" variant="outline" pill class="uppercase text-[11px] tracking-wider py-1.5 font-bold shadow-sm">
                    <span class="h-1.5 w-1.5 rounded-full {{ $dotColor }} animate-pulse mr-1.5"></span>
                    {{ $statusLabel }}
                </x-ui.badge>
            </div>
        </div>
    </div>

    <!-- ==================== SECTION 2: METRIC CARDS 4-COLUMN ROW ==================== -->
    <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
        
        <!-- Metric 1: Active Buses -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[112px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Active Buses</span>
                <div class="h-8 w-8 rounded-lg bg-[#EBF4FA] flex items-center justify-center text-[#003F87]">
                    <i class="ti ti-bus text-lg"></i>
                </div>
            </div>
            <div class="mt-1">
                <span class="text-[26px] font-black text-slate-900 leading-none" id="metric-active-buses">0</span>
                <div class="text-[11px] text-[#639922] font-semibold mt-0.5 flex items-center gap-0.5" id="metric-active-buses-sub">
                    <span class="h-1.5 w-1.5 rounded-full bg-[#639922] animate-pulse mr-0.5"></span>
                    <span>Normal fleet ops</span>
                </div>
            </div>
        </div>

        <!-- Metric 2: Buses in Route -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[112px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Buses in Route</span>
                <div class="h-8 w-8 rounded-lg bg-[#F3F9EA] flex items-center justify-center text-[#639922]">
                    <i class="ti ti-map-pin text-lg"></i>
                </div>
            </div>
            <div class="mt-1">
                <span class="text-[26px] font-black text-slate-900 leading-none" id="metric-buses-in-route">0</span>
                <div class="text-[11px] text-slate-500 font-semibold mt-0.5">
                    <span>On active transit lines</span>
                </div>
            </div>
        </div>

        <!-- Metric 3: Under Maintenance -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[112px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 border-l-[3px] border-l-[#BA7517]">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Under Maintenance</span>
                <div class="h-8 w-8 rounded-lg bg-[#FEF7ED] flex items-center justify-center text-[#BA7517]">
                    <i class="ti ti-tool text-lg"></i>
                </div>
            </div>
            <div class="mt-1">
                <span class="text-[26px] font-black text-slate-900 leading-none" id="metric-under-maintenance">0</span>
                <div class="text-[11px] text-slate-500 font-semibold mt-0.5">
                    <span>Scheduled checkups</span>
                </div>
            </div>
        </div>

        <!-- Metric 4: Service Alerts -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[112px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 border-l-[3px] border-l-[#E24B4A]">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Service Alerts</span>
                <div class="h-8 w-8 rounded-lg bg-[#FDF2F2] flex items-center justify-center text-[#E24B4A]">
                    <i class="ti ti-bell-ringing text-lg"></i>
                </div>
            </div>
            <div class="mt-1">
                <span class="text-[26px] font-black text-slate-900 leading-none" id="metric-service-alerts">0</span>
                <div class="text-[11px] text-[#A32D2D] font-bold mt-0.5 flex items-center gap-0.5" id="metric-service-alerts-sub">
                    <i class="ti ti-alert-triangle"></i>
                    <span>Action required</span>
                </div>
            </div>
        </div>

    </div>

    <!-- ==================== SECTION 3: 2-COLUMN MAP & DISPATCH LAYOUT ==================== -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        
        <!-- LEFT (65%): Live Fleet Map Panel -->
        <div class="lg:col-span-2 rounded-xl border border-slate-200 bg-white overflow-hidden shadow-sm flex flex-col h-[420px] hover:shadow-md transition-shadow">
            <!-- Map Header -->
            <div class="border-b border-slate-100 bg-slate-50/50 px-4 py-3 flex items-center justify-between shrink-0">
                <div class="flex items-center gap-2">
                    <span class="flex h-2.5 w-2.5 rounded-full bg-[#639922] animate-pulse"></span>
                    <span class="text-[12px] font-extrabold uppercase tracking-wider text-slate-800">Live Vehicle Visualizer</span>
                </div>
                <span class="text-[10px] font-extrabold text-[#003F87] bg-[#E6F1FB] px-2.5 py-0.5 rounded-full uppercase tracking-widest">{{ $primaryRouteName }}</span>
            </div>
            
            <!-- Live Google Maps Visualizer -->
            <div class="flex-1 bg-[#F4F6F9] relative overflow-hidden select-none">
                <div id="overview-map" class="h-full w-full z-10"></div>
            </div>
        </div>

        <!-- RIGHT (35%): Today's Dispatch Queue -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm flex flex-col h-[420px] hover:shadow-md transition-shadow">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
                <span class="text-[12px] font-extrabold uppercase tracking-wider text-slate-800">Dispatch Queue</span>
                <x-ui.button variant="soft" size="xs" color="blue" onclick="switchScreen('dispatch')" class="text-[11px] font-extrabold uppercase tracking-wider cursor-pointer">Manage Queue</x-ui.button>
            </div>
            
            <!-- Dispatch List (Scrollable) -->
            <div id="dispatch-queue-list" class="flex-1 overflow-y-auto py-2 space-y-3 mt-3 scrollbar-thin scrollbar-thumb-slate-200 pr-1">
                <div class="text-center py-16 text-xs font-semibold text-slate-400">Loading active dispatch queue...</div>
            </div>
        </div>

    </div>

    <!-- ==================== SECTION 4: BOTTOM 3-COLUMN ROW ==================== -->
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        
        <!-- Col 1: Recent Trip Logs Table -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm flex flex-col h-[320px] hover:shadow-md transition-shadow">
            <div class="border-b border-slate-100 pb-3 shrink-0 flex items-center justify-between">
                <span class="text-[12px] font-extrabold uppercase tracking-wider text-slate-800">Recent Trip Logs</span>
                <span class="text-[9px] font-extrabold uppercase tracking-widest text-slate-400">Last 5 trips</span>
            </div>
            
            <div class="flex-1 overflow-x-auto overflow-y-auto mt-3 scrollbar-thin scrollbar-thumb-slate-200">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-extrabold uppercase tracking-widest text-slate-400">
                            <th class="pb-2 font-bold">Time</th>
                            <th class="pb-2 font-bold">Route</th>
                            <th class="pb-2 font-bold">Driver</th>
                            <th class="pb-2 font-bold text-right">Status</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs font-semibold text-slate-700 divide-y divide-slate-100/50" id="trip-logs-tbody">
                        <tr>
                            <td colspan="4" class="py-12 text-center text-xs text-slate-400 font-semibold">Loading recent trip logs...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Col 2: Fleet Status Breakdown Donut Chart -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm flex flex-col h-[320px] hover:shadow-md transition-shadow">
            <div class="border-b border-slate-100 pb-3 shrink-0">
                <span class="text-[12px] font-extrabold uppercase tracking-wider text-slate-800">Fleet Status Breakdown</span>
            </div>
            
            <div class="flex-1 flex flex-col items-center justify-center mt-2 relative select-none">
                <!-- Center Count Text inside donut chart -->
                <div class="absolute flex flex-col items-center justify-center leading-none">
                    <span class="text-3xl font-black text-slate-900" id="donut-total-buses">0</span>
                    <span class="text-[9px] font-extrabold uppercase tracking-widest text-slate-400 mt-1">Total Buses</span>
                </div>
                
                <!-- Vector SVG Donut Chart representing breakdown -->
                <svg class="h-36 w-36 transform -rotate-90" viewBox="0 0 100 100">
                    <!-- Base Circle -->
                    <circle cx="50" cy="50" r="38" fill="none" stroke="#F1F5F9" stroke-width="9" />
                    <!-- SVG circles dynamically rendering dashboard allocations -->
                    <circle id="donut-circle-active" cx="50" cy="50" r="38" fill="none" stroke="#639922" stroke-width="9" 
                            stroke-dasharray="0 239" stroke-dashoffset="0" />
                    <circle id="donut-circle-maintenance" cx="50" cy="50" r="38" fill="none" stroke="#BA7517" stroke-width="9" 
                            stroke-dasharray="0 239" stroke-dashoffset="0" />
                    <circle id="donut-circle-alert" cx="50" cy="50" r="38" fill="none" stroke="#E24B4A" stroke-width="9" 
                            stroke-dasharray="0 239" stroke-dashoffset="0" />
                </svg>
                
                <!-- Legend Grid -->
                <div class="mt-3 grid grid-cols-3 gap-x-2 text-[10px] font-bold text-slate-500 w-full shrink-0 justify-items-center">
                    <div class="flex items-center gap-1.5" id="donut-legend-active"><span class="h-2.5 w-2.5 rounded-full bg-[#639922]"></span> Active (0)</div>
                    <div class="flex items-center gap-1.5" id="donut-legend-maintenance"><span class="h-2.5 w-2.5 rounded-full bg-[#BA7517]"></span> Maint (0)</div>
                    <div class="flex items-center gap-1.5" id="donut-legend-alert"><span class="h-2.5 w-2.5 rounded-full bg-[#E24B4A]"></span> Alert (0)</div>
                </div>
            </div>
        </div>

        <!-- Col 3: Upcoming Maintenance Alerts -->
        <div class="rounded-xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm flex flex-col h-[320px] hover:shadow-md transition-shadow">
            <div class="border-b border-slate-100 pb-3 shrink-0 flex items-center justify-between">
                <span class="text-[12px] font-extrabold uppercase tracking-wider text-slate-800">Maintenance Schedule</span>
                <x-ui.button variant="soft" size="xs" color="blue" onclick="switchScreen('maintenance')" class="text-[11px] font-extrabold uppercase tracking-wider cursor-pointer">View Records</x-ui.button>
            </div>
            
            <!-- Upcoming Alerts List -->
            <div id="overview-maintenance-list" class="flex-1 overflow-y-auto mt-3 space-y-3 scrollbar-thin scrollbar-thumb-slate-200 pr-1">
                <div class="text-center py-16 text-xs font-semibold text-slate-400">Loading schedules...</div>
            </div>
        </div>

    </div>

    <!-- ==================== SCRIPTS BLOCK ==================== -->
    <script>
        // Inject dynamic bus capacity limit from PHP controller
        const busCapacityLimit = {{ $busCapacityLimit }};

        // Inject dynamic map settings from PHP controller
        const mapCenterLat = {{ $mapCenterLat }};
        const mapCenterLng = {{ $mapCenterLng }};
        const mapZoom = {{ $mapZoom }};
        const pollingInterval = {{ $pollingInterval }};

        // JS time update every 1000ms for Admin Clock
        (function() {
            function updateAdminClock() {
                const el = document.getElementById('admin-live-clock');
                if (!el) return;
                const now = new Date();
                let hours = now.getHours();
                const minutes = String(now.getMinutes()).padStart(2, '0');
                const seconds = String(now.getSeconds()).padStart(2, '0');
                const ampm = hours >= 12 ? 'PM' : 'AM';
                hours = hours % 12;
                hours = hours ? hours : 12; // the hour '0' should be '12'
                const hoursStr = String(hours).padStart(2, '0');
                el.textContent = `${hoursStr}:${minutes}:${seconds} ${ampm}`;
            }
            setInterval(updateAdminClock, 1000);
            updateAdminClock();
        })();
    </script>

</section>