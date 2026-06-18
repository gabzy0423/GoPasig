{{-- ==================== FLEET UTILIZATION ANALYTICS SCREEN ==================== --}}
<section id="screen-analytics-fleet-utilization" class="hidden space-y-8 animate-fade-in">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Reports & Analytics - Fleet Utilization</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span>Reports & Analytics</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Fleet Utilization</span>
        </div>
    </div>

    <!-- ==================== SECTION 1 — TOP KPI OVERVIEW STRIP ==================== -->
    <div class="grid grid-cols-2 gap-4 md:grid-cols-6 border-b border-slate-100 pb-6 shrink-0">
        <!-- Card 1 -->
        <div class="rounded-lg bg-slate-50 p-4 flex flex-col gap-3 border-l-4 border-[#003F87]">
            <div class="flex items-center justify-between shrink-0">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 leading-none">Total Pax Today</span>
                <span class="text-[#003F87]"><i class="ti ti-users text-base"></i></span>
            </div>
            <p class="text-xl font-black text-slate-900 leading-none" id="kpi-pax-today">1,284</p>
            <div class="leading-none mt-1">
                <span class="text-[11px] font-bold text-[#639922] flex items-center gap-0.5" id="kpi-pax-today-trend"><i class="ti ti-trending-up"></i> +8% vs yesterday</span>
                <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider">across all routes</p>
            </div>
        </div>

        <!-- Card 2 -->
        <div class="rounded-lg bg-slate-50 p-4 flex flex-col gap-3">
            <div class="flex items-center justify-between shrink-0">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 leading-none">Pax This Week</span>
                <span class="text-[#003F87]"><i class="ti ti-calendar text-base"></i></span>
            </div>
            <p class="text-xl font-black text-slate-900 leading-none" id="kpi-pax-week">8,471</p>
            <div class="leading-none mt-1">
                <span class="text-[11px] font-bold text-[#639922] flex items-center gap-0.5" id="kpi-pax-week-trend"><i class="ti ti-trending-up"></i> +3% vs last week</span>
                <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider">Mon–Sun running total</p>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="rounded-lg bg-slate-50 p-4 flex flex-col gap-3">
            <div class="flex items-center justify-between shrink-0">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 leading-none">Avg Pax / Trip</span>
                <span class="text-[#003F87]"><i class="ti ti-chart-bar text-base"></i></span>
            </div>
            <p class="text-xl font-black text-slate-900 leading-none" id="kpi-avg-pax">43.2</p>
            <div class="leading-none mt-1">
                <span class="text-[11px] font-bold text-[#E24B4A] flex items-center gap-0.5" id="kpi-avg-pax-trend"><i class="ti ti-trending-down"></i> -2% vs yesterday</span>
                <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider">fleet average</p>
            </div>
        </div>

        <!-- Card 4 -->
        <div class="rounded-lg bg-slate-50 p-4 flex flex-col gap-3">
            <div class="flex items-center justify-between shrink-0">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 leading-none">Trips Completed</span>
                <span class="text-[#003F87]"><i class="ti ti-route text-base"></i></span>
            </div>
            <p class="text-xl font-black text-slate-900 leading-none" id="kpi-trips-completed">29</p>
            <div class="leading-none mt-1">
                <span class="inline-flex rounded-full bg-[#E8F4E0] px-2 py-0.5 text-[9px] font-bold text-[#639922]" id="kpi-trips-completed-percent">91% Completion</span>
                <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider" id="kpi-trips-completed-sub">of 32 scheduled</p>
            </div>
        </div>

        <!-- Card 5 -->
        <div class="rounded-lg bg-slate-50 p-4 flex flex-col gap-3">
            <div class="flex items-center justify-between shrink-0">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 leading-none">Fleet Util.</span>
                <span class="text-slate-400 flex items-center select-none shrink-0" title="9 of 12 active">
                    <svg class="h-6 w-6 transform -rotate-90" viewBox="0 0 36 36">
                        <circle cx="18" cy="18" r="14" fill="none" stroke="#E2E8F0" stroke-width="4.5" />
                        <circle cx="18" cy="18" r="14" fill="none" stroke="#003F87" stroke-width="4.5" stroke-dasharray="66 88" />
                    </svg>
                </span>
            </div>
            <p class="text-xl font-black text-slate-900 leading-none" id="kpi-fleet-util">78%</p>
            <div class="leading-none mt-1">
                <span class="text-[11px] font-bold text-slate-400 flex items-center gap-0.5"><i class="ti ti-minus"></i> Neutral</span>
                <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider" id="kpi-fleet-util-sub">9 of 12 buses active</p>
            </div>
        </div>

        <!-- Card 6 -->
        <div class="rounded-lg bg-slate-50 p-4 flex flex-col gap-3">
            <div class="flex items-center justify-between shrink-0">
                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 leading-none">On-Time Rate</span>
                <span class="text-[#BA7517]"><i class="ti ti-bell-ringing text-base"></i></span>
            </div>
            <p class="text-xl font-black text-slate-900 leading-none" id="kpi-on-time-rate">87%</p>
            <div class="leading-none mt-1">
                <span class="text-[11px] font-bold text-[#BA7517] flex items-center gap-0.5"><i class="ti ti-trending-down"></i> -5% vs yesterday</span>
                <p class="text-[9px] font-bold text-slate-400 mt-1 uppercase tracking-wider" id="kpi-on-time-sub">4 delayed trips today</p>
            </div>
        </div>
    </div>

    <!-- ==================== SECTION 3B — BUS RIDERSHIP SUMMARY ==================== -->
    <div id="analytics-fleet-utilization" class="space-y-3">
        <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800 block">Bus ridership summary — today</span>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6" id="bus-summary-cards-grid">
            <!-- Rendered dynamically by javascript -->
        </div>
    </div>

    <!-- ==================== SECTION 3C — PASSENGER LOAD TIMELINE CHART ==================== -->
    <div class="rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] space-y-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3 shrink-0">
            <div>
                <h2 class="text-xs font-extrabold uppercase tracking-widest text-slate-800">Passenger load over time — by bus</h2>
                <p class="text-[10px] font-bold text-slate-400 mt-0.5">Hourly passenger on-board count per active bus unit</p>
            </div>
            <div class="flex items-center gap-4 text-xs font-bold text-slate-500">
                <div id="pax-load-timeline-legend" class="flex flex-wrap items-center gap-3">
                    <!-- Populated dynamically -->
                </div>
                <span class="text-[9px] font-extrabold bg-rose-50 text-[#E24B4A] border border-[#E24B4A]/10 px-2.5 py-0.5 rounded-full uppercase tracking-wider">Ref Limit: {{ $busCapacityLimit }} pax</span>
            </div>
        </div>

        <!-- Timeline Line Chart canvas -->
        <div class="relative h-[280px]">
            <canvas id="pax-load-timeline-chart"></canvas>
        </div>
    </div>

    <!-- ==================== SECTION 4 — DISPATCH DEMAND PREDICTION ==================== -->
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-10">
        <!-- 4A. DISPATCH PREDICTION PANEL (Hero Column 65% width) -->
        <div class="lg:col-span-6 rounded-xl border-l-4 border-[#003F87] border-y border-r border-slate-200 bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[480px]">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
                <span class="text-xs font-extrabold uppercase tracking-widest text-[#003F87] flex items-center gap-1.5">
                    <i class="ti ti-sparkles text-base animate-pulse"></i>
                    Dispatch Demand Forecast
                </span>
                <span class="text-[9px] font-extrabold uppercase tracking-widest text-slate-400">Based on 30-day ridership average</span>
            </div>

            <!-- Scrollable Tomorrow's Schedule prediction table -->
            <div class="flex-1 overflow-y-auto mt-4 pr-1.5 scrollbar-thin scrollbar-thumb-slate-200">
                <table class="w-full text-left border-collapse table-fixed">
                    <thead>
                        <tr class="border-b border-slate-100 text-[10px] font-extrabold uppercase tracking-widest text-slate-400 bg-slate-50/50">
                            <th class="px-3 py-2 font-bold w-[16%]">Time Slot</th>
                            <th class="px-3 py-2 font-bold w-[16%]">Pred Pax</th>
                            <th class="px-3 py-2 font-bold w-[12%]">Rec.</th>
                            <th class="px-3 py-2 font-bold w-[12%]">Sched.</th>
                            <th class="px-3 py-2 font-bold w-[14%] text-center">Gap</th>
                            <th class="px-3 py-2 font-bold w-[30%]">Action Needed</th>
                        </tr>
                    </thead>
                    <tbody class="text-xs font-semibold text-slate-700 divide-y divide-slate-100" id="forecast-schedule-tbody">
                        <!-- Rendered dynamically by javascript -->
                    </tbody>
                </table>
            </div>

            <!-- Blue-bordered Dispatch Summary Info Card below -->
            <div class="mt-4 border border-[#003F87]/20 bg-[#F0F5FF]/60 rounded-xl p-3 flex gap-3 shrink-0 items-center">
                <span class="text-[#003F87] bg-white/80 p-2 rounded-lg"><i class="ti ti-info-circle text-base"></i></span>
                <div class="leading-normal">
                    <p class="text-[10px] font-black uppercase text-[#003F87] tracking-wider">Tomorrow's Dispatch Action Plan</p>
                    <p class="text-[11px] text-slate-600 font-semibold mt-0.5">Shortages detected: <strong class="text-slate-900">8 buses</strong> across 6 peak hours. Busiest expected: <strong class="text-slate-900">7–8 AM (+2) and 5–6 PM (+2)</strong>. Pre-position 2 standby buses at Pasig Terminal for 6:45 AM and 4:45 PM deployment.</p>
                </div>
            </div>
        </div>

        <!-- 4B & 4C. ROUTE BREAKDOWN & 30-DAY TREND (35% width) -->
        <div class="lg:col-span-4 space-y-6 flex flex-col h-[480px]">
            <!-- 4B. Route Breakdown Tabs Card -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[180px] shrink-0">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2 shrink-0">
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-800">Forecast by route — tomorrow</span>
                    <div id="forecast-route-tabs" class="flex flex-wrap gap-1 text-[9px] font-extrabold">
                        <!-- Populated dynamically -->
                    </div>
                </div>
                <div class="flex-1 flex flex-col justify-center space-y-2 mt-2 leading-none">
                    <div class="flex justify-between text-xs font-bold text-slate-500">
                        <span>Expected Route Volume:</span>
                        <span class="text-slate-900 font-extrabold" id="pred-route-vol">1,284 pax / day</span>
                    </div>
                    <div class="flex justify-between text-xs font-bold text-slate-500">
                        <span>Recommended Dispatches:</span>
                        <span class="text-slate-900 font-extrabold" id="pred-route-rec">29 recommended</span>
                    </div>
                    <div class="bg-[#FEF7ED] border border-[#BA7517]/10 p-2.5 rounded-lg text-[#8F530B] font-extrabold text-[11px] shrink-0 text-center uppercase tracking-wider" id="pred-route-busiest">
                        Expected highest boarding: Pasig City Hall · 7–8 AM · ~67 passengers
                    </div>
                </div>
            </div>

            <!-- 4C. 30-day Ridership Trend Chart Card -->
            <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex-1 flex flex-col min-h-0">
                <div class="flex items-center justify-between border-b border-slate-100 pb-2 shrink-0">
                    <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-800">Historical ridership — last 30 days</span>
                    <span class="text-[9px] font-bold text-slate-400">Total basis</span>
                </div>
                
                <!-- Canvas Chart -->
                <div class="flex-1 min-h-0 mt-3 relative">
                    <canvas id="historical-trend-chart" class="w-full h-full"></canvas>
                </div>

                <div class="mt-2.5 border-t border-slate-50 pt-2 grid grid-cols-3 gap-2 text-[9px] font-bold text-slate-500 shrink-0 text-center">
                    <div>Wkday Avg: <strong class="text-slate-800 block text-[10px] mt-0.5">1,247 pax</strong></div>
                    <div>Wkend Avg: <strong class="text-slate-800 block text-[10px] mt-0.5">891 pax</strong></div>
                    <div>Growth: <strong class="text-[#639922] block text-[10px] mt-0.5">+4.2%</strong></div>
                </div>
            </div>
        </div>
    </div>
</section>


{{-- ==================== ROUTE PERFORMANCE ANALYTICS SCREEN ==================== --}}
<section id="screen-analytics-route-performance" class="hidden space-y-8 animate-fade-in">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Reports & Analytics - Route Performance</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span>Reports & Analytics</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Route Performance</span>
        </div>
    </div>

    <!-- ==================== SECTION 2 — RIDERSHIP ANALYTICS ==================== -->
    <div id="analytics-route-performance" class="space-y-6">
        <!-- 2A. HOURLY RIDERSHIP CHART -->
        <div class="rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] space-y-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3 shrink-0">
                <div>
                    <h2 class="text-sm font-extrabold uppercase tracking-widest text-slate-800">Hourly ridership by route</h2>
                    <p class="text-[10px] font-bold text-slate-400 mt-0.5">Admins dispatch planning baseline</p>
                </div>
                <div class="flex items-center gap-4 text-xs font-bold text-slate-500">
                    <!-- Custom HTML Legend -->
                    <div id="hourly-chart-legend" class="flex flex-wrap items-center gap-3">
                        <!-- Populated dynamically -->
                    </div>
                    <span class="text-[10px] font-extrabold bg-slate-100 text-slate-600 px-2.5 py-0.5 rounded uppercase tracking-wider">Today, May 24</span>
                </div>
            </div>

            <!-- Chart Canvas wrapper -->
            <div class="relative h-[300px]">
                <canvas id="hourly-ridership-chart" class="w-full h-full"></canvas>
            </div>

            <div class="border-t border-slate-100 pt-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between shrink-0">
                <p class="text-xs font-bold text-slate-400 italic">Peak hours identified: 7–8 AM and 5–6 PM. Use these to plan additional dispatches.</p>
                <div class="flex gap-2">
                    <span class="inline-flex rounded-full bg-[#E6F1FB] px-2.5 py-0.5 text-[9px] font-bold text-[#003F87] uppercase tracking-wider">AM Peak · 322 pax</span>
                    <span class="inline-flex rounded-full bg-[#E6F1FB] px-2.5 py-0.5 text-[9px] font-bold text-[#003F87] uppercase tracking-wider">PM Peak · 298 pax</span>
                </div>
            </div>
        </div>

        <!-- 2B. ROUTE RIDERSHIP BREAKDOWN (Doughnut & Comparison Table) -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-10">
            <!-- Left Card: Doughnut Chart (40%) -->
            <div class="lg:col-span-4 rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[360px]">
                <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-3 block">Passengers by route today</span>
                
                <div class="flex-1 flex flex-col items-center justify-center relative select-none mt-2">
                    <!-- Doughnut center text overlay -->
                    <div class="absolute flex flex-col items-center justify-center leading-none">
                        <span class="text-2xl font-black text-slate-900" id="doughnut-total-pax">1,284</span>
                        <span class="text-[9px] font-extrabold uppercase tracking-widest text-slate-400 mt-1">Total Pax</span>
                    </div>
                    <div class="h-44 w-44">
                        <canvas id="route-doughnut-chart"></canvas>
                    </div>
                </div>

                <!-- Custom Legend with Trends below -->
                <div id="route-doughnut-legend" class="border-t border-slate-100 pt-3 grid grid-cols-2 sm:grid-cols-4 gap-2 text-[10px] font-bold text-slate-500 shrink-0 justify-items-center w-full">
                    <!-- Populated dynamically -->
                </div>
            </div>

            <!-- Right Card: Route Comparison Table (60%) -->
            <div class="lg:col-span-6 rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[360px]">
                <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-3 block">Route comparison table</span>
                
                <div class="flex-1 overflow-x-auto mt-4 scrollbar-thin scrollbar-thumb-slate-200">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-widest text-slate-400">
                                <th class="pb-2 font-bold">Route</th>
                                <th class="pb-2 font-bold">Trips Today</th>
                                <th class="pb-2 font-bold">Total Pax</th>
                                <th class="pb-2 font-bold">Avg Pax/Trip</th>
                                <th class="pb-2 font-bold">Peak Hour</th>
                                <th class="pb-2 font-bold text-right">Busiest Stop</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium text-slate-700 divide-y divide-slate-100" id="route-comparison-tbody">
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-3 font-bold text-[#003F87]">Route A</td>
                                <td class="py-3">11 trips</td>
                                <td class="py-3">532 pax</td>
                                <td class="py-3">48.4</td>
                                <td class="py-3"><span class="inline-flex rounded-full bg-[#E6F1FB] px-2 py-0.5 text-[9px] font-bold text-[#003F87]">7–8 AM</span></td>
                                <td class="py-3 text-right">Pasig City Hall</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-3 font-bold text-[#639922]">Route B</td>
                                <td class="py-3">10 trips</td>
                                <td class="py-3">421 pax</td>
                                <td class="py-3">42.1</td>
                                <td class="py-3"><span class="inline-flex rounded-full bg-[#E6F1FB] px-2 py-0.5 text-[9px] font-bold text-[#003F87]">7–8 AM</span></td>
                                <td class="py-3 text-right">Ortigas Center</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-3 font-bold text-[#BA7517]">Route C</td>
                                <td class="py-3">8 trips</td>
                                <td class="py-3">331 pax</td>
                                <td class="py-3">41.4</td>
                                <td class="py-3"><span class="inline-flex rounded-full bg-[#E6F1FB] px-2 py-0.5 text-[9px] font-bold text-[#003F87]">5–6 PM</span></td>
                                <td class="py-3 text-right">Shaw Blvd</td>
                            </tr>
                            <!-- Footer Totals Row -->
                            <tr class="font-bold bg-slate-50 border-t border-slate-200">
                                <td class="py-3 pl-2">Totals</td>
                                <td class="py-3">29 trips</td>
                                <td class="py-3">1,284 pax</td>
                                <td class="py-3">44.3 avg</td>
                                <td class="py-3">—</td>
                                <td class="py-3 text-right pr-2">—</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- 2C. RIDERSHIP HEATMAP (7-day × 18-hour) -->
        <div class="rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] space-y-4">
            <div class="border-b border-slate-100 pb-3 shrink-0">
                <h2 class="text-xs font-extrabold uppercase tracking-widest text-slate-800">Weekly ridership pattern — last 7 days</h2>
                <p class="text-[10px] font-bold text-slate-400 mt-0.5">Identify recurring peak periods for dispatch planning</p>
            </div>

            <!-- Heatmap Matrix Board scrollable wrapper -->
            <div class="overflow-x-auto pb-2 scrollbar-thin scrollbar-thumb-slate-200">
                <div class="min-w-[700px] space-y-2">
                    <!-- Column Headers -->
                    <div class="flex items-center gap-1.5 pl-[60px]">
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">5A</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">6A</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">7A</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">8A</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">9A</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">10A</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">11A</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">12P</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">1P</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">2P</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">3P</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">4P</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">5P</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">6P</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">7P</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">8P</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase">9P</div>
                        <div class="w-8 text-[9px] font-bold text-slate-400 text-center uppercase text-slate-400/60">10P</div>
                    </div>
                    
                    <!-- Render matrix rows (Mon-Sun) -->
                    <div class="space-y-1.5" id="heatmap-matrix-grid">
                        <!-- Rendered dynamically by javascript -->
                    </div>
                </div>
            </div>

            <!-- Legend swatches and Insight Chips -->
            <div class="border-t border-slate-100 pt-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between shrink-0">
                <!-- Legend -->
                <div class="flex items-center gap-1.5 text-[10px] font-bold text-slate-500">
                    <span>Low</span>
                    <span class="h-5 w-5 rounded bg-[#F0F5FF]"></span>
                    <span class="h-5 w-5 rounded bg-[#B5D4F4]"></span>
                    <span class="h-5 w-5 rounded bg-[#378ADD]"></span>
                    <span class="h-5 w-5 rounded bg-[#185FA5]"></span>
                    <span class="h-5 w-5 rounded bg-[#003F87]"></span>
                    <span>High</span>
                </div>

                <!-- Insight Chips -->
                <div id="heatmap-insights-container" class="flex flex-wrap gap-2 text-[10px] font-bold">
                    <!-- Populated dynamically -->
                </div>
            </div>
        </div>

        <!-- 2D. STOP-LEVEL BOARDING DATA -->
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-10">
            <!-- Left: Horizontal Bar Chart (60%) -->
            <div class="lg:col-span-6 rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[440px]">
                <div class="border-b border-slate-100 pb-3 shrink-0 flex items-center justify-between">
                    <div>
                        <h2 class="text-xs font-extrabold uppercase tracking-widest text-slate-800">Boarding demand by stop</h2>
                        <p class="text-[10px] font-bold text-slate-400 mt-0.5">Stops with highest passenger volume — capacity planning priority</p>
                    </div>
                    <!-- Legend for intensity -->
                    <div class="flex items-center gap-1.5 text-[9px] font-bold text-slate-400 uppercase">
                        <span>Light volume</span>
                        <span class="h-3 w-8 bg-gradient-to-r from-[#B5D4F4] to-[#003F87] rounded"></span>
                        <span>High Demand</span>
                    </div>
                </div>

                <!-- Horizontal Chart canvas container (380px wrapper) -->
                <div class="flex-1 min-h-0 mt-4">
                    <canvas id="stop-boarding-chart" class="w-full h-full"></canvas>
                </div>
            </div>

            <!-- Right: Top 5 Stops Breakdown Table (40%) -->
            <div class="lg:col-span-4 rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[440px]">
                <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800 border-b border-slate-100 pb-3 block">Top 5 stops passenger flow</span>
                
                <div class="flex-1 overflow-x-auto mt-4 scrollbar-thin scrollbar-thumb-slate-200">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-widest text-slate-400">
                                <th class="pb-2 font-bold">Stop</th>
                                <th class="pb-2 font-bold">Routes</th>
                                <th class="pb-2 font-bold">Avg Boarding</th>
                                <th class="pb-2 font-bold">Avg Alight</th>
                                <th class="pb-2 font-bold text-right">Net Change</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs font-medium text-slate-700 divide-y divide-slate-100" id="top-stops-tbody">
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-2.5 font-bold">Pasig City Hall</td>
                                <td class="py-2.5 text-[#003F87] font-bold">A, B</td>
                                <td class="py-2.5">218 / day</td>
                                <td class="py-2.5">142 / day</td>
                                <td class="py-2.5 text-right text-[#639922] font-bold">+76</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-2.5 font-bold">Kapitolyo</td>
                                <td class="py-2.5 text-[#003F87] font-bold">A</td>
                                <td class="py-2.5">187 / day</td>
                                <td class="py-2.5">179 / day</td>
                                <td class="py-2.5 text-right text-[#639922] font-bold">+8</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-2.5 font-bold">Ortigas Center</td>
                                <td class="py-2.5 text-[#003F87] font-bold">A, C</td>
                                <td class="py-2.5">165 / day</td>
                                <td class="py-2.5">204 / day</td>
                                <td class="py-2.5 text-right text-[#E24B4A] font-bold">-39</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-2.5 font-bold">Rosario</td>
                                <td class="py-2.5 text-[#003F87] font-bold">B, C</td>
                                <td class="py-2.5">143 / day</td>
                                <td class="py-2.5">98 / day</td>
                                <td class="py-2.5 text-right text-[#639922] font-bold">+45</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50 transition">
                                <td class="py-2.5 font-bold">Shaw Blvd</td>
                                <td class="py-2.5 text-[#003F87] font-bold">C</td>
                                <td class="py-2.5">121 / day</td>
                                <td class="py-2.5">110 / day</td>
                                <td class="py-2.5 text-right text-[#639922] font-bold">+11</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>


{{-- ==================== DRIVER PERFORMANCE ANALYTICS SCREEN ==================== --}}
<section id="screen-analytics-driver-performance" class="hidden space-y-8 animate-fade-in">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Reports & Analytics - Driver Performance</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span>Reports & Analytics</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Driver Performance</span>
        </div>
    </div>

    <!-- ==================== SECTION 3A — PASSENGERS PER BUS TRIP TABLE ==================== -->
    <div class="rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] space-y-4">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-slate-100 pb-3 shrink-0">
            <div>
                <h2 class="text-xs font-extrabold uppercase tracking-widest text-slate-800">Passenger count per trip — today</h2>
                <p class="text-[10px] font-bold text-slate-400 mt-0.5">Real-time driver accountability & capacity analytics database</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <select onchange="filterTripTableByRoute()" id="trip-route-filter" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                    <option value="all">All Routes</option>
                    <!-- Populated dynamically -->
                </select>
                <button onclick="exportCSVDataMock()" class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-50 transition cursor-pointer">
                    <i class="ti ti-download text-sm"></i>
                    Export CSV
                </button>
            </div>
        </div>

        <!-- Data-Dense Full-Width Table Layout -->
        <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-slate-200">
            <table class="w-full text-left border-collapse table-fixed min-w-[800px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-widest text-slate-500">
                        <th class="px-4 py-2.5 font-bold w-[8%]">Trip #</th>
                        <th class="px-4 py-2.5 font-bold w-[10%]">Bus Plate</th>
                        <th class="px-4 py-2.5 font-bold w-[14%]">Driver</th>
                        <th class="px-4 py-2.5 font-bold w-[10%]">Route</th>
                        <th class="px-4 py-2.5 font-bold w-[9%]">Departure</th>
                        <th class="px-4 py-2.5 font-bold w-[9%]">Arrival</th>
                        <th class="px-4 py-2.5 font-bold w-[10%]">Pax Boarded</th>
                        <th class="px-4 py-2.5 font-bold w-[10%]">Pax Alighted</th>
                        <th class="px-4 py-2.5 font-bold w-[10%]">Peak Load</th>
                        <th class="px-4 py-2.5 font-bold w-[10%]">Capacity %</th>
                    </tr>
                </thead>
                <tbody class="text-xs font-semibold text-slate-700 divide-y divide-slate-100" id="trip-pax-tbody">
                    <!-- Rendered dynamically by javascript -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- ==================== SECTION 3D — DRIVER PERFORMANCE TABLE ==================== -->
    <div id="analytics-driver-performance" class="rounded-xl border border-[#E0E0E0] bg-white p-5 shadow-[0_1px_3px_rgba(0,0,0,0.06)] space-y-4">
        <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800 block border-b border-slate-100 pb-3">Ridership by driver — today</span>
        
        <div class="overflow-x-auto scrollbar-thin scrollbar-thumb-slate-200">
            <table class="w-full text-left border-collapse min-w-[700px]">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-extrabold uppercase tracking-widest text-slate-400">
                        <th class="px-5 py-3 font-bold w-[10%]">Rank</th>
                        <th class="px-5 py-3 font-bold">Driver</th>
                        <th class="px-5 py-3 font-bold">Assigned Bus</th>
                        <th class="px-5 py-3 font-bold">Assigned Route</th>
                        <th class="px-5 py-3 font-bold">Trips Today</th>
                        <th class="px-5 py-3 font-bold">Total Pax Served</th>
                        <th class="px-5 py-3 font-bold">Avg Pax/Trip</th>
                        <th class="px-5 py-3 font-bold">Peak Load Reached</th>
                        <th class="px-5 py-3 font-bold text-right">Incidents</th>
                    </tr>
                </thead>
                <tbody class="text-xs font-semibold text-slate-700 divide-y divide-slate-100" id="driver-performance-tbody">
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="px-5 py-3.5"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#EF9F27] text-[10px] font-black text-[#633806]" title="Rank 1 Gold">#1</span></td>
                        <td class="px-5 py-3.5 font-bold">Ana Flores</td>
                        <td class="px-5 py-3.5 font-mono">TUV-3301</td>
                        <td class="px-5 py-3.5 text-[#BA7517] font-bold">Route C</td>
                        <td class="px-5 py-3.5">5 trips</td>
                        <td class="px-5 py-3.5 font-extrabold text-[#003F87]">221 pax</td>
                        <td class="px-5 py-3.5">44.2</td>
                        <td class="px-5 py-3.5 text-rose-600 font-extrabold">45 <span class="text-[9px] font-bold bg-rose-50 border border-rose-100 px-1.5 py-0.5 rounded-full uppercase ml-1">Full x3</span></td>
                        <td class="px-5 py-3.5 text-right text-emerald-600 font-bold"><i class="ti ti-check text-base"></i></td>
                    </tr>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="px-5 py-3.5"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#D3D1C7] text-[10px] font-black text-[#444441]" title="Rank 2 Silver">#2</span></td>
                        <td class="px-5 py-3.5 font-bold">Juan dela Cruz</td>
                        <td class="px-5 py-3.5 font-mono">PJY-8821</td>
                        <td class="px-5 py-3.5 text-[#003F87] font-bold">Route A</td>
                        <td class="px-5 py-3.5">4 trips</td>
                        <td class="px-5 py-3.5 font-extrabold text-[#003F87]">187 pax</td>
                        <td class="px-5 py-3.5">46.8</td>
                        <td class="px-5 py-3.5 text-rose-600 font-extrabold">45 <span class="text-[9px] font-bold bg-rose-50 border border-rose-100 px-1.5 py-0.5 rounded-full uppercase ml-1">Full x2</span></td>
                        <td class="px-5 py-3.5 text-right text-emerald-600 font-bold"><i class="ti ti-check text-base"></i></td>
                    </tr>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="px-5 py-3.5"><span class="flex h-6 w-6 items-center justify-center rounded-full bg-[#F5C4B3] text-[10px] font-black text-[#712B13]" title="Rank 3 Bronze">#3</span></td>
                        <td class="px-5 py-3.5 font-bold">Maria Santos</td>
                        <td class="px-5 py-3.5 font-mono">QRS-4412</td>
                        <td class="px-5 py-3.5 text-[#639922] font-bold">Route B</td>
                        <td class="px-5 py-3.5">4 trips</td>
                        <td class="px-5 py-3.5 font-extrabold text-[#003F87]">163 pax</td>
                        <td class="px-5 py-3.5">40.8</td>
                        <td class="px-5 py-3.5 text-slate-500">43</td>
                        <td class="px-5 py-3.5 text-right"><span class="inline-flex rounded-full bg-rose-50 border border-rose-100 px-2 py-0.5 text-[9px] font-bold text-rose-600">1 alert</span></td>
                    </tr>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="px-5 py-3.5"><span class="flex h-6 w-6 items-center justify-center text-slate-400 text-xs font-extrabold">#4</span></td>
                        <td class="px-5 py-3.5 font-bold">Carlos Bautista</td>
                        <td class="px-5 py-3.5 font-mono">WXY-9988</td>
                        <td class="px-5 py-3.5 text-[#639922] font-bold">Route B</td>
                        <td class="px-5 py-3.5">3 trips</td>
                        <td class="px-5 py-3.5 font-extrabold text-[#003F87]">121 pax</td>
                        <td class="px-5 py-3.5">40.3</td>
                        <td class="px-5 py-3.5 text-slate-500">39</td>
                        <td class="px-5 py-3.5 text-right text-emerald-600 font-bold"><i class="ti ti-check text-base"></i></td>
                    </tr>
                    <tr class="hover:bg-slate-50/50 transition">
                        <td class="px-5 py-3.5"><span class="flex h-6 w-6 items-center justify-center text-slate-400 text-xs font-extrabold">#5</span></td>
                        <td class="px-5 py-3.5 font-bold">Pedro Garcia</td>
                        <td class="px-5 py-3.5 font-mono">MNO-2211</td>
                        <td class="px-5 py-3.5 text-[#003F87] font-bold">Route A</td>
                        <td class="px-5 py-3.5">3 trips</td>
                        <td class="px-5 py-3.5 font-extrabold text-[#003F87]">118 pax</td>
                        <td class="px-5 py-3.5">39.3</td>
                        <td class="px-5 py-3.5 text-rose-600 font-extrabold">45 <span class="text-[9px] font-bold bg-rose-50 border border-rose-100 px-1.5 py-0.5 rounded-full uppercase ml-1">Full x1</span></td>
                        <td class="px-5 py-3.5 text-right text-emerald-600 font-bold"><i class="ti ti-check text-base"></i></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ==================== SECTION 5 — REPORT GENERATION ==================== -->
    <livewire:admin.report-builder />
</section>
