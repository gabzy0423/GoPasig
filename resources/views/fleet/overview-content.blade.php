<section id="screen-overview" class="space-y-5 animate-fade-in">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Fleet Operations Overview</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Overview</span>
        </div>
    </div>
    <style>
        .toast-notification.show {
            transform: translateY(0) !important;
            opacity: 1 !important;
        }
    </style>

    <!-- ==================== SECTION 1: WELCOME HEADER + DATE/TIME STRIP ==================== -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between bg-[#003F87] p-4 sm:px-6 py-4 rounded-xl shadow-md gap-4 text-white">
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
            <h2 class="text-[18px] font-bold text-white tracking-tight">{{ $greeting }}, {{ $operator->name }}</h2>
            <p class="text-[13px] text-white/70 font-semibold mt-0.5">Fleet Operator — Pasig City Libreng Sakay Program</p>
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
                <span id="live-clock" class="font-mono tracking-wider">--:--:-- --</span>
            </div>
            
            <!-- Operational status chip -->
            <div>
                @if($inService)
                    <span class="inline-flex items-center gap-1 bg-[#A8F0C6] text-[#0F6E56] font-bold px-3 py-1.5 rounded-lg uppercase text-[11px] tracking-wider shadow-sm">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#0F6E56] animate-pulse"></span>
                        In Service
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 bg-[#E6E5E0] text-[#5F5E5A] font-bold px-3 py-1.5 rounded-lg uppercase text-[11px] tracking-wider shadow-sm">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#5F5E5A]"></span>
                        Off Service
                    </span>
                @endif
            </div>
        </div>
    </div>

    <!-- ==================== SECTION 2: PRIMARY KPI METRIC CARDS ==================== -->
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
        
        <!-- KPI 1: Active buses -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col justify-between h-[104px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Active now</span>
                <div class="h-7 w-7 rounded-lg bg-[#E6F1FB] flex items-center justify-center text-[#003F87]">
                    <i class="ti ti-bus text-base"></i>
                </div>
            </div>
            <div class="mt-1">
                <span id="kpi-active-buses" class="text-[24px] font-bold text-slate-900 leading-none">{{ $overviewKpi['active_buses'] }}</span>
                <div class="text-[11px] text-[#3B6D11] font-semibold mt-0.5 flex items-center gap-0.5">
                    <i class="ti ti-trending-up"></i>
                    <span id="kpi-active-buses-delta">{{ $overviewKpi['deltas']->active_buses_yesterday }}</span>
                </div>
            </div>
        </div>

        <!-- KPI 2: Delayed buses -->
        <div id="kpi-container-delayed-buses" class="relative bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col justify-between h-[104px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 {{ $overviewKpi['delayed_buses'] > 0 ? 'border-l-[3px] border-l-[#BA7517]' : '' }}">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Delayed</span>
                <div class="h-7 w-7 rounded-lg bg-[#FAEEDA] flex items-center justify-center text-[#BA7517]">
                    <i class="ti ti-clock-exclamation text-base"></i>
                </div>
            </div>
            <div class="mt-1">
                <span id="kpi-delayed-buses" class="text-[24px] font-bold text-slate-900 leading-none">{{ $overviewKpi['delayed_buses'] }}</span>
                <div class="text-[11px] text-slate-400 font-semibold mt-0.5 flex items-center gap-0.5">
                    <span>—</span>
                    <span id="kpi-delayed-buses-delta">{{ $overviewKpi['deltas']->delayed_buses_yesterday }}</span>
                </div>
            </div>
        </div>

        <!-- KPI 3: Offline buses -->
        <div id="kpi-container-offline-buses" class="relative bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col justify-between h-[104px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 {{ $overviewKpi['offline_buses'] > 0 ? 'border-l-[3px] border-l-[#E24B4A]' : '' }}">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Offline</span>
                <div class="h-7 w-7 rounded-lg bg-[#FCEBEB] flex items-center justify-center text-[#E24B4A]">
                    <i class="ti ti-bus-off text-base"></i>
                </div>
            </div>
            <div class="mt-1">
                <span id="kpi-offline-buses" class="text-[24px] font-bold text-slate-900 leading-none">{{ $overviewKpi['offline_buses'] }}</span>
                <div class="text-[11px] text-[#3B6D11] font-semibold mt-0.5 flex items-center gap-0.5">
                    <i class="ti ti-trending-down text-[#E24B4A] rotate-180"></i>
                    <span id="kpi-offline-buses-delta" class="text-[#3B6D11]">{{ $overviewKpi['deltas']->offline_buses_yesterday }}</span>
                </div>
            </div>
        </div>

        <!-- KPI 4: Idle buses -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col justify-between h-[104px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Idle</span>
                <div class="h-7 w-7 rounded-lg bg-slate-100 flex items-center justify-center text-[#888780]">
                    <i class="ti ti-parking text-base"></i>
                </div>
            </div>
            <div class="mt-1">
                <span id="kpi-idle-buses" class="text-[24px] font-bold text-slate-900 leading-none">{{ $overviewKpi['idle_buses'] }}</span>
                <div class="text-[11px] text-slate-400 font-semibold mt-0.5 flex items-center gap-0.5">
                    <span>—</span>
                    <span id="kpi-idle-buses-delta">{{ $overviewKpi['deltas']->idle_buses_yesterday }}</span>
                </div>
            </div>
        </div>

        <!-- KPI 5: Trips completed -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col justify-between h-[104px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Trips done</span>
                <div class="h-7 w-7 rounded-lg bg-[#EBF4FA] flex items-center justify-center text-[#378ADD]">
                    <i class="ti ti-route text-base"></i>
                </div>
            </div>
            <div class="mt-1">
                <span id="kpi-trips-completed" class="text-[24px] font-bold text-slate-900 leading-none">{{ $overviewKpi['trips_completed'] }}</span>
                <div class="text-[11px] text-[#3B6D11] font-semibold mt-0.5 flex items-center gap-0.5">
                    <i class="ti ti-trending-up"></i>
                    <span id="kpi-trips-completed-delta">{{ $overviewKpi['deltas']->trips_completed_yesterday }}</span>
                </div>
            </div>
        </div>

        <!-- KPI 6: Total passengers -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col justify-between h-[104px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Riders today</span>
                <div class="h-7 w-7 rounded-lg bg-[#F3F9EA] flex items-center justify-center text-[#639922]">
                    <i class="ti ti-users text-base"></i>
                </div>
            </div>
            <div class="mt-1">
                <span id="kpi-total-passengers" class="text-[24px] font-bold text-slate-900 leading-none">{{ number_format($overviewKpi['total_passengers']) }}</span>
                <div class="text-[11px] text-[#3B6D11] font-semibold mt-0.5 flex items-center gap-0.5">
                    <i class="ti ti-trending-up"></i>
                    <span id="kpi-total-passengers-delta">{{ $overviewKpi['deltas']->total_passengers_yesterday }}</span>
                </div>
            </div>
        </div>

        <!-- KPI 7: Avg utilization -->
        <div class="relative bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col justify-between h-[104px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Utilization</span>
                <div class="h-7 w-7 rounded-lg bg-[#FAF0E6] flex items-center justify-center text-[#854F0B]">
                    <i class="ti ti-gauge text-base"></i>
                </div>
            </div>
            <div class="mt-1">
                <span id="kpi-avg-utilization" class="text-[24px] font-bold text-slate-900 leading-none">{{ $overviewKpi['avg_utilization'] }}%</span>
                <div class="text-[11px] text-[#3B6D11] font-semibold mt-0.5 flex items-center gap-0.5">
                    <i class="ti ti-trending-up"></i>
                    <span id="kpi-avg-utilization-delta">{{ $overviewKpi['deltas']->avg_utilization_yesterday }}</span>
                </div>
            </div>
        </div>

        <!-- KPI 8: Open incidents -->
        <div id="kpi-container-open-incidents" class="relative bg-white border border-slate-200 rounded-xl p-3.5 flex flex-col justify-between h-[104px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 {{ $overviewKpi['open_incidents'] > 0 ? 'border-l-[3px] border-l-[#E24B4A]' : '' }}">
            <div class="flex justify-between items-start">
                <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest">Incidents</span>
                <div class="h-7 w-7 rounded-lg bg-[#FCEBEB] flex items-center justify-center text-[#E24B4A]">
                    <i class="ti ti-alert-triangle text-base"></i>
                </div>
            </div>
            <div class="mt-1">
                <span id="kpi-open-incidents" class="text-[24px] font-bold text-slate-900 leading-none">{{ $overviewKpi['open_incidents'] }}</span>
                <div class="text-[11px] text-[#A32D2D] font-semibold mt-0.5 flex items-center gap-0.5">
                    <i class="ti ti-trending-up text-[#E24B4A]"></i>
                    <span id="kpi-open-incidents-delta" class="text-[#A32D2D]">{{ $overviewKpi['deltas']->open_incidents_yesterday }}</span>
                </div>
            </div>
        </div>

    </div>

    <!-- ==================== SECTION 3: TWO-COLUMN ROW: FLEET MAP PREVIEW | ACTIVE INCIDENTS ==================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        
        <!-- LEFT: Live fleet map preview (60%) -->
        <div class="lg:col-span-7 bg-white border border-slate-200 rounded-xl shadow-sm flex flex-col overflow-hidden">
            <div class="px-4 py-3 border-b border-slate-200 flex items-center justify-between bg-white shrink-0">
                <div class="flex items-center gap-2">
                    <span class="text-[14px] font-bold text-slate-800 tracking-tight uppercase">Live fleet map</span>
                    <span class="flex h-2 w-2 relative">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                    </span>
                </div>
                <a href="{{ route('fleet.monitor') }}" class="text-[12px] font-extrabold text-[#003F87] hover:text-[#002D62] transition flex items-center gap-1 uppercase tracking-wider">
                    <span>Open full map</span>
                    <i class="ti ti-external-link"></i>
                </a>
            </div>
            
            <!-- Map Area: 300px -->
            <div class="h-[300px] w-full bg-slate-50 relative">
                <div id="previewMap" class="h-full w-full z-10"></div>
            </div>
            
            <div class="px-4 py-2.5 border-t border-slate-200 bg-slate-50 text-[12px] font-semibold text-slate-500 shrink-0">
                <span id="map-status-bus-count">{{ $activeCount }} buses on-route · Updated just now</span>
            </div>
        </div>

        <!-- RIGHT: Active incidents feed (40%) -->
        <div class="lg:col-span-5 bg-white border border-slate-200 rounded-xl shadow-sm flex flex-col p-4 sm:p-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
                <div class="flex items-center gap-2">
                    <h3 class="text-[14px] font-bold text-slate-800 tracking-tight uppercase">Active incidents</h3>
                    <span id="active-incidents-badge" class="px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase {{ $openIncidents > 0 ? 'bg-[#FCEBEB] text-[#A32D2D]' : 'bg-slate-100 text-slate-500' }}">
                        {{ $openIncidents }}
                    </span>
                </div>
                <a href="{{ route('fleet.incidents') }}" class="text-[12px] font-extrabold text-[#003F87] hover:text-[#002D62] transition uppercase tracking-wider">View all</a>
            </div>
            
            <!-- Scrollable list of active incidents -->
            <div id="active-incidents-feed" class="flex-grow overflow-y-auto space-y-2 mt-4 max-h-[250px] pr-1">
                @if(count($activeIncidents) > 0)
                    @foreach($activeIncidents as $incident)
                        <div class="p-3 bg-slate-50 rounded-lg border border-slate-100 hover:border-slate-200 transition-colors flex flex-col space-y-2 relative">
                            <div class="flex items-center justify-between">
                                @if($incident['severity'] === 'High')
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wide bg-[#FCEBEB] text-[#A32D2D]">High</span>
                                @elseif($incident['severity'] === 'Medium')
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wide bg-[#FAEEDA] text-[#854F0B]">Medium</span>
                                @else
                                    <span class="inline-flex rounded px-1.5 py-0.5 text-[9px] font-extrabold uppercase tracking-wide bg-[#EAF3DE] text-[#3B6D11]">Low</span>
                                @endif
                                
                                <span class="text-[11px] text-slate-400 font-semibold">{{ \Carbon\Carbon::parse($incident['reported_at'])->diffForHumans() }}</span>
                            </div>
                            
                            <div>
                                <h4 class="text-[13px] font-bold text-slate-800 leading-snug">{{ $incident['title'] }}</h4>
                                <p class="text-[11.5px] text-slate-500 font-medium mt-0.5 flex items-center gap-1">
                                    <i class="ti ti-map-pin text-[13px] text-slate-400"></i>
                                    <span>{{ $incident['location'] }} · {{ $incident['affected_route'] }}</span>
                                </p>
                            </div>
                            
                            <div class="pt-1">
                                <button onclick="resolveIncidentAction('{{ $incident['id'] }}')" class="w-full h-7 border border-slate-200 text-[11px] font-extrabold text-slate-700 hover:bg-white bg-slate-100/50 hover:border-slate-300 rounded transition cursor-pointer text-center flex items-center justify-center gap-1 uppercase tracking-wider">
                                    <span>Resolve Incident</span>
                                </button>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="h-[180px] flex flex-col items-center justify-center text-center space-y-2">
                        <div class="h-12 w-12 rounded-full bg-[#F3F9EA] text-[#639922] flex items-center justify-center">
                            <i class="ti ti-circle-check text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-[13px] font-bold text-slate-800">No active incidents</p>
                            <p class="text-[11.5px] text-slate-400 font-medium mt-0.5">All routes are operating within normal tolerances.</p>
                        </div>
                    </div>
                @endif
            </div>
        </div>

    </div>

    <!-- ==================== SECTION 4: TWO-COLUMN ROW: ROUTE HEALTH SUMMARY | SCHEDULE COMPLIANCE ==================== -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        
        <!-- LEFT: Route health summary -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm flex flex-col p-4 sm:p-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
                <h3 class="text-[14px] font-bold text-slate-800 tracking-tight uppercase">Route health</h3>
                <a href="{{ route('fleet.routes') }}" class="text-[12px] font-extrabold text-[#003F87] hover:text-[#002D62] transition uppercase tracking-wider">View details</a>
            </div>
            
            <div id="route-health-container" class="flex-grow space-y-3 mt-4">
                @foreach($routeHealth as $route)
                    <div class="p-3 bg-white border border-slate-100 rounded-lg hover:border-slate-200 transition-colors space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="h-2.5 w-2.5 rounded-full shrink-0" style="background-color: {{ $route['route_color'] }}"></span>
                                <h4 class="text-[13px] font-bold text-slate-800 leading-snug">{{ $route['route_name'] }}</h4>
                            </div>
                            
                            <div>
                                @if($route['health_status'] === 'On Track')
                                    <span class="inline-flex items-center gap-1 rounded bg-[#EAF3DE] text-[#3B6D11] text-[10px] font-bold px-2 py-0.5 uppercase tracking-wide">
                                        <i class="ti ti-circle-check text-[11px]"></i>
                                        <span>On Track</span>
                                    </span>
                                @elseif($route['health_status'] === 'Minor Delay')
                                    <span class="inline-flex items-center gap-1 rounded bg-[#FAEEDA] text-[#854F0B] text-[10px] font-bold px-2 py-0.5 uppercase tracking-wide">
                                        <i class="ti ti-clock text-[11px]"></i>
                                        <span>Minor Delay</span>
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded bg-[#FCEBEB] text-[#A32D2D] text-[10px] font-bold px-2 py-0.5 uppercase tracking-wide">
                                        <i class="ti ti-alert-triangle text-[11px]"></i>
                                        <span>Disrupted</span>
                                    </span>
                                @endif
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-3 gap-2 text-[11.5px] text-slate-500 font-semibold">
                            <div>Active: <strong class="text-slate-700 font-bold font-mono">{{ $route['buses_on_route'] }} buses</strong></div>
                            <div>Trips done: <strong class="text-slate-700 font-bold font-mono">{{ $route['completed_trips'] }}</strong></div>
                            <div class="text-right">Avg headway: <strong class="text-slate-700 font-bold font-mono">{{ $route['avg_headway'] }}m</strong></div>
                        </div>
                        
                        <!-- Thin progress bar completed/scheduled -->
                        @php
                            $progressPct = $route['scheduled_trips'] > 0 ? ($route['completed_trips'] / $route['scheduled_trips']) * 100 : 0;
                        @endphp
                        <div class="w-full bg-[#E6E5E0] h-1 rounded-full overflow-hidden">
                            <div class="h-full rounded-full" style="width: {{ $progressPct }}%; background-color: {{ $route['route_color'] }}"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- RIGHT: Schedule compliance strip -->
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm flex flex-col p-4 sm:p-5">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
                <h3 class="text-[14px] font-bold text-slate-800 tracking-tight uppercase">Schedule compliance</h3>
                <a href="{{ route('fleet.schedule') }}" class="text-[12px] font-extrabold text-[#003F87] hover:text-[#002D62] transition uppercase tracking-wider">View full report</a>
            </div>
            
            <div class="flex-grow grid grid-cols-1 sm:grid-cols-12 gap-4 items-center mt-4">
                <!-- Left Column: Donut Chart (5/12 width) -->
                <div class="sm:col-span-5 flex justify-center">
                    <div class="relative w-[130px] h-[130px] flex items-center justify-center">
                        <canvas id="complianceChart" class="w-full h-full" data-pct="{{ $scheduleCompliance['compliance_pct'] }}"></canvas>
                        <div class="absolute inset-0 flex items-center justify-center flex-col pointer-events-none">
                            <span id="compliance-chart-center-pct" class="text-[18px] font-extrabold text-[#003F87]">{{ $scheduleCompliance['compliance_pct'] }}%</span>
                            <span class="text-[8.5px] text-slate-400 font-extrabold uppercase tracking-wide">Rate</span>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column: Description & Breakdown (7/12 width) -->
                <div class="sm:col-span-7 space-y-3">
                    <div class="text-left">
                        <span id="compliance-pct-text" class="text-[20px] font-extrabold text-[#003F87] leading-none">{{ $scheduleCompliance['compliance_pct'] }}%</span>
                        <span class="text-[13px] text-slate-500 font-semibold ml-1">of trips on schedule today</span>
                    </div>
                    
                    <!-- Compliance Breakdown rows -->
                    <div class="space-y-2.5 text-[13px] font-semibold text-slate-600">
                        <div class="flex items-center justify-between py-2.5 px-3.5 bg-slate-50/50 border border-slate-100 rounded-xl hover:bg-slate-50 hover:border-slate-200 transition-colors">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center bg-[#639922]/10 text-[#639922]">
                                    <i class="ti ti-circle-check text-base"></i>
                                </div>
                                <span class="text-slate-700 font-semibold">On time trips</span>
                            </div>
                            <span id="compliance-on-time-count" class="font-bold font-mono text-[14px] text-slate-900">{{ $scheduleCompliance['on_time'] }}</span>
                        </div>
                        
                        <div class="flex items-center justify-between py-2.5 px-3.5 bg-slate-50/50 border border-slate-100 rounded-xl hover:bg-slate-50 hover:border-slate-200 transition-colors">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center bg-[#BA7517]/10 text-[#BA7517]">
                                    <i class="ti ti-clock text-base"></i>
                                </div>
                                <span class="text-slate-700 font-semibold">Delayed trips</span>
                            </div>
                            <span id="compliance-delayed-count" class="font-bold font-mono text-[14px] text-slate-900">{{ $scheduleCompliance['delayed'] }}</span>
                        </div>
                        
                        <div class="flex items-center justify-between py-2.5 px-3.5 bg-slate-50/50 border border-slate-100 rounded-xl hover:bg-slate-50 hover:border-slate-200 transition-colors">
                            <div class="flex items-center gap-2.5">
                                <div class="w-7 h-7 rounded-lg flex items-center justify-center bg-[#E24B4A]/10 text-[#E24B4A]">
                                    <i class="ti ti-x text-base"></i>
                                </div>
                                <span class="text-slate-700 font-semibold">Cancelled trips</span>
                            </div>
                            <span id="compliance-cancelled-count" class="font-bold font-mono text-[14px] text-slate-900">{{ $scheduleCompliance['cancelled'] }}</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="pt-3 border-t border-slate-100 text-[11.5px] text-slate-400 font-semibold italic mt-4">
                <span id="compliance-as-of-info">Based on {{ $scheduleCompliance['trips_evaluated'] }} trips evaluated as of {{ $scheduleCompliance['as_of'] }}</span>
            </div>
        </div>

    </div>

    <!-- ==================== SECTION 5: QUICK ACTION BUTTONS ==================== -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-5">
        <h3 class="text-[12px] font-extrabold uppercase tracking-widest text-slate-400 mb-3">Quick actions</h3>
        
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Button 1: Dispatch a bus -->
            <a href="{{ route('fleet.dashboard') }}" class="h-9 px-4 rounded-lg bg-[#003F87] text-white hover:bg-[#002D62] transition text-[13px] font-bold flex items-center justify-center gap-1.5 uppercase tracking-wider shadow-sm">
                <i class="ti ti-bus-stop text-base"></i>
                <span>Dispatch a bus</span>
            </a>

            <!-- Button 2: Log an incident -->
            <button onclick="openLogIncidentModal()" class="h-9 px-4 rounded-lg border border-[#E24B4A] text-[#E24B4A] hover:bg-[#FCEBEB] transition text-[13px] font-bold flex items-center justify-center gap-1.5 uppercase tracking-wider cursor-pointer">
                <i class="ti ti-alert-triangle text-base"></i>
                <span>Log an incident</span>
            </button>

            <!-- Button 3: Schedule maintenance -->
            <a href="{{ route('fleet.maintenance') }}" class="h-9 px-4 rounded-lg border border-[#003F87] text-[#003F87] hover:bg-[#E6F1FB] transition text-[13px] font-bold flex items-center justify-center gap-1.5 uppercase tracking-wider shadow-sm">
                <i class="ti ti-tool text-base"></i>
                <span>Schedule maintenance</span>
            </a>


            <!-- Button 5: Generate report -->
            <a href="{{ route('fleet.analytics') }}" class="h-9 px-4 rounded-lg border border-[#003F87] text-[#003F87] hover:bg-[#E6F1FB] transition text-[13px] font-bold flex items-center justify-center gap-1.5 uppercase tracking-wider shadow-sm">
                <i class="ti ti-file-analytics text-base"></i>
                <span>Generate report</span>
            </a>
        </div>
    </div>

    <!-- ==================== SECTION 6: RECENT ACTIVITY LOG ==================== -->
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4 sm:p-5 flex flex-col">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
            <h3 class="text-[14px] font-bold text-slate-800 tracking-tight uppercase">Recent activity</h3>
            <span class="text-[12px] font-extrabold text-[#003F87] hover:text-[#002D62] transition uppercase tracking-wider cursor-default">View all logs</span>
        </div>
        
        <!-- Timeline Log content -->
        <div id="recent-activity-container" class="overflow-y-auto space-y-4 max-h-[280px] mt-4 pr-1 relative pl-4 border-l border-slate-100">
            @if(count($recentActivity) > 0)
                @foreach($recentActivity as $activity)
                    @php
                        // Color node based on activity type
                        $nodeColor = '#888780'; // default Login/other
                        if ($activity['type'] === 'Dispatch') $nodeColor = '#003F87';
                        elseif ($activity['type'] === 'Incident') $nodeColor = '#E24B4A';
                        elseif ($activity['type'] === 'Maintenance') $nodeColor = '#BA7517';
                        elseif ($activity['type'] === 'Trip end') $nodeColor = '#639922';
                    @endphp
                    <div class="relative py-0.5 group flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 transition-all rounded p-1 hover:bg-slate-50">
                        <!-- Timeline circle node absolute positioning relative to content -->
                        <span class="absolute h-2 w-2 rounded-full border-2 border-white shadow-sm -left-[20.5px] z-10" style="background-color: {{ $nodeColor }}"></span>
                        
                        <div class="flex-1 min-w-0">
                            <span class="text-[13px] text-slate-700 font-semibold">{{ $activity['description'] }}</span>
                        </div>
                        
                        <div class="shrink-0 text-right sm:pl-4">
                            <span class="text-[11.5px] text-slate-400 font-bold font-mono">{{ $activity['timestamp'] }}</span>
                        </div>
                    </div>
                @endforeach
            @else
                <div class="py-8 text-center text-slate-400 text-[13px] font-bold">
                    No recent activities recorded today
                </div>
            @endif
        </div>
    </div>

    <!-- ==================== MODAL A: LOG INCIDENT MODAL ==================== -->
    <div id="log-incident-modal" class="fixed inset-0 z-50 items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden animate-fade-in-up">
        <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl shadow-xl overflow-hidden flex flex-col">
            <!-- Header -->
            <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                <span class="text-sm font-extrabold uppercase tracking-widest text-[#003F87] flex items-center gap-1.5">
                    <i class="ti ti-alert-triangle text-[#E24B4A] text-lg"></i>
                    <span>Log Operational Incident</span>
                </span>
                <button onclick="closeLogIncidentModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i class="ti ti-x text-lg"></i></button>
            </div>
            
            <!-- Body -->
            <form onsubmit="submitIncidentForm(event)" id="incident-form" class="p-5 space-y-4">
                <!-- Title -->
                <div class="space-y-1">
                    <label for="incident-title-input" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Incident Description</label>
                    <input id="incident-title-input" type="text" placeholder="e.g. Bus breakdown, flat tire..." class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                </div>
                
                <!-- Severity & Route -->
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label for="incident-severity-input" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Severity</label>
                        <select id="incident-severity-input" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                            <option value="Low">Low</option>
                            <option value="Medium" selected>Medium</option>
                            <option value="High">High</option>
                        </select>
                    </div>
                    
                    <div class="space-y-1">
                        <label for="incident-route-input" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Affected Route</label>
                        <select id="incident-route-input" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                            @foreach($routes as $route)
                                <option value="{{ $route['id'] }}">{{ $route['name'] }} ({{ $route['description'] }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Location -->
                <div class="space-y-1">
                    <label for="incident-location-input" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Location</label>
                    <input id="incident-location-input" type="text" placeholder="e.g. Near Tiendesitas Stop..." class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                </div>

                <!-- Footer / Submit -->
                <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 shrink-0">
                    <button type="button" onclick="closeLogIncidentModal()" class="rounded-lg bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200 transition cursor-pointer">Cancel</button>
                    <button type="submit" class="rounded-lg bg-[#E24B4A] px-5 py-2 text-xs font-extrabold text-white hover:bg-[#A32D2D] transition cursor-pointer flex items-center gap-1 uppercase tracking-wider">
                        <span>Log Incident</span>
                    </button>
                </div>
            </form>
        </div>
    </div>


    <!-- TOAST CONTAINER -->
    <div id="overview-toast" class="toast-notification flex items-center gap-2" style="position: fixed; bottom: 24px; right: 24px; color: white; padding: 12px 18px; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,0.15); z-index: 2000; font-size: 13px; transform: translateY(100px); opacity: 0; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1); pointer-events: none;">
        <i id="overview-toast-icon" class="ti ti-circle-check text-emerald-400 text-base"></i>
        <span id="overview-toast-message">Action successful!</span>
    </div>

</section>
