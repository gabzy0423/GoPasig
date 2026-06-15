<section id="screen-routes" class="hidden animate-fade-in" style="display: none;">
<div class="space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Route Performance</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span>Fleet</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Route Performance</span>
        </div>
    </div>
    <!-- SECTION 1: ROUTE SELECTOR -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-end shrink-0">

        <!-- Filter / Tabs Group -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Route Tabs -->
            <div class="flex items-center bg-slate-100 p-1 rounded-lg flex-wrap gap-1">
                <button type="button" id="tab-route-all" onclick="selectRouteTab('all')"
                    class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all {{ $selectedRoute === 'all' ? 'bg-[#003F87] text-white shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                    All Routes
                </button>
                @foreach($availableRoutes as $route)
                    <button type="button" id="tab-route-{{ $route['id'] }}" onclick="selectRouteTab('{{ $route['id'] }}')"
                        class="px-3 py-1.5 rounded-md text-xs font-semibold transition-all {{ $selectedRoute == $route['id'] ? 'bg-[#003F87] text-white shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                        {{ $route['name'] }}
                    </button>
                @endforeach
            </div>

            <!-- Date Range Picker -->
            <div class="flex items-center gap-1.5 border border-black/15 bg-white rounded-lg px-2.5 py-1.5 text-xs font-medium text-[#001F44]">
                <i class="ti ti-calendar text-slate-500 text-[14px]"></i>
                <input type="date" id="route-start-date" value="{{ $startDate }}"
                    class="bg-transparent border-none p-0 outline-none w-[90px] focus:ring-0 text-[11px] font-semibold text-slate-700">
                <span class="text-slate-400">→</span>
                <input type="date" id="route-end-date" value="{{ $endDate }}"
                    class="bg-transparent border-none p-0 outline-none w-[90px] focus:ring-0 text-[11px] font-semibold text-slate-700">
            </div>

            <!-- Export CSV Button -->
            <button id="btn-export-routes-csv" type="button" onclick="exportRouteReport()"
                class="h-[34px] flex items-center justify-center gap-1.5 rounded-lg border border-black/15 bg-white px-3 text-xs font-medium text-[#001F44] hover:bg-slate-50 transition-colors">
                <span class="flex items-center gap-1.5">
                    <i class="ti ti-file-export text-slate-500 text-[14px]"></i>
                    <span>Export Report</span>
                </span>
            </button>
        </div>
    </div>

    <!-- SECTION 2: ROUTE PERFORMANCE SUMMARY CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- Card 1 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Trips completed</span>
                <i class="ti ti-bus text-[16px] text-[#003F87]"></i>
            </div>
            <span id="metric-trips-completed" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $routePerformanceSummary->trips_completed }}</span>
        </div>

        <!-- Card 2 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">On-time rate</span>
                <i class="ti ti-clock-check text-[16px] text-[#0F6E56]"></i>
            </div>
            <div class="flex flex-col mt-2">
                @php $otColor = $routePerformanceSummary->on_time_rate >= $routePerformanceSummary->on_time_target ? 'text-[#3B6D11]' : 'text-[#A32D2D]'; @endphp
                <span id="metric-on-time-rate" class="text-[24px] font-medium {{ $otColor }} leading-none">{{ $routePerformanceSummary->on_time_rate }}%</span>
                <span class="text-[11px] text-slate-400 font-semibold mt-1">target: {{ $routePerformanceSummary->on_time_target }}%</span>
            </div>
        </div>

        <!-- Card 3 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Avg headway</span>
                <i class="ti ti-timeline text-[16px] text-amber-500"></i>
            </div>
            <div class="flex flex-col mt-2">
                @php $hwColor = $routePerformanceSummary->avg_headway <= $routePerformanceSummary->headway_target ? 'text-[#3B6D11]' : 'text-[#A32D2D]'; @endphp
                <span id="metric-avg-headway" class="text-[24px] font-medium {{ $hwColor }} leading-none">
                    {{ $routePerformanceSummary->avg_headway > 0 ? $routePerformanceSummary->avg_headway . ' min' : 'N/A' }}
                </span>
                <span class="text-[11px] text-slate-400 font-semibold mt-1">target: &le;{{ $routePerformanceSummary->headway_target }} min</span>
            </div>
        </div>

        <!-- Card 4 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Incidents recorded</span>
                <i class="ti ti-alert-triangle text-[16px] text-[#E24B4A]"></i>
            </div>
            @php $devColor = $routePerformanceSummary->deviations_count > 0 ? 'text-[#A32D2D]' : 'text-[#3B6D11]'; @endphp
            <span id="metric-incidents" class="text-[24px] font-medium leading-none mt-2 {{ $devColor }}">{{ $routePerformanceSummary->deviations_count }}</span>
        </div>

        <!-- Card 5 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Stop adherence rate</span>
                <i class="ti ti-map-pin-check text-[16px] text-purple-600"></i>
            </div>
            <span id="metric-stop-adherence" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $routePerformanceSummary->stop_adherence_rate }}%</span>
        </div>
    </div>

    <!-- SECTION 3: CHART ROW -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- LEFT: Headway Regularity Chart -->
        <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                <h2 class="text-[16px] font-medium text-[#001F44]">Headway regularity</h2>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded-sm inline-block bg-[#378ADD]"></span>
                        <span class="text-slate-500 text-[11px]">Actual headway</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-4 h-0.5 border-t-2 border-dashed border-[#888780] inline-block"></span>
                        <span class="text-slate-500 text-[11px]">Target (15 min)</span>
                    </div>
                </div>
            </div>

            <div id="routeHeadwayEmptyState" class="hidden h-[260px] flex flex-col items-center justify-center text-slate-400">
                <i class="ti ti-chart-line-off text-[40px] block mb-2"></i>
                <p class="text-sm font-medium">Not enough data for headway chart</p>
                <p class="text-xs mt-1">Requires at least 2 scheduled trips per route</p>
            </div>
            <div id="headwayRegularityChart" style="width: 100%; height: 260px;"></div>
        </div>

        <!-- RIGHT: Schedule Compliance per Trip Chart -->
        <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                <h2 class="text-[16px] font-medium text-[#001F44]">Schedule compliance per trip</h2>
                <div class="flex items-center gap-3">
                    <div class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded-sm inline-block bg-[#639922]"></span>
                        <span class="text-slate-500 text-[11px]">On time / Early</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded-sm inline-block bg-[#BA7517]"></span>
                        <span class="text-slate-500 text-[11px]">1–5 min late</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <span class="w-3 h-3 rounded-sm inline-block bg-[#E24B4A]"></span>
                        <span class="text-slate-500 text-[11px]">&gt;5 min late</span>
                    </div>
                </div>
            </div>

            <div id="routeComplianceEmptyState" class="hidden h-[260px] flex flex-col items-center justify-center text-slate-400">
                <i class="ti ti-calendar-off text-[40px] block mb-2"></i>
                <p class="text-sm font-medium">No schedule data for this period</p>
            </div>
            <div id="scheduleComplianceChart" style="width: 100%; height: 260px;"></div>
        </div>
    </div>

    <!-- SECTION 4: STOP ADHERENCE TABLE -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
            <div>
                <h2 class="text-[16px] font-medium text-[#001F44]">Stop adherence log</h2>
                <p class="text-[13px] text-slate-500 font-normal">Stops and service coverage per route</p>
            </div>
            <div class="flex items-center gap-2">
                @php
                    $selectedRouteObj = collect($availableRoutes)->firstWhere('id', (int)$selectedRoute);
                    $badgeLabel = $selectedRoute === 'all' ? 'All Routes' : ($selectedRouteObj ? $selectedRouteObj['name'] : 'Route ' . $selectedRoute);
                    $colorPaletteStop = ['#003F87', '#3B6D11', '#854F0B', '#6B21A8', '#0F6E56', '#DC2626'];
                    $badgeColor = $selectedRoute === 'all' ? '#6b7280' : ($colorPaletteStop[((int)$selectedRoute - 1) % count($colorPaletteStop)] ?? '#6b7280');
                @endphp
                <span class="flex items-center gap-2 rounded-full border border-black/10 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                    <span id="route-pill-color" class="w-2.5 h-2.5 rounded-full inline-block" style="background-color: {{ $badgeColor }}"></span>
                    <span id="route-pill-label">{{ $badgeLabel }}</span>
                </span>
            </div>
        </div>

        <div id="stop-table-empty" class="hidden text-center py-12 text-slate-400">
            <i class="ti ti-map-pin-off text-[40px] block mb-2"></i>
            <p class="text-sm font-medium">No stops found for the selected route</p>
        </div>

        <div id="stop-table-wrapper" class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed text-[13px]">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4 w-[24%] cursor-pointer select-none" onclick="sortStopTable('stop_name')">
                            <span class="flex items-center">Stop Name <i id="sort-icon-stop_name" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[18%] cursor-pointer select-none" onclick="sortStopTable('route_name')">
                            <span class="flex items-center">Route <i id="sort-icon-route_name" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[8%] text-center cursor-pointer select-none" onclick="sortStopTable('sequence')">
                            <span class="flex items-center justify-center">Seq <i id="sort-icon-sequence" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[14%] text-center cursor-pointer select-none" onclick="sortStopTable('scheduled_time')">
                            <span class="flex items-center justify-center">First Trip <i id="sort-icon-scheduled_time" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[14%] cursor-pointer select-none" onclick="sortStopTable('status')">
                            <span class="flex items-center">Status <i id="sort-icon-status" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[12%] text-center cursor-pointer select-none" onclick="sortStopTable('buses_passed')">
                            <span class="flex items-center justify-center">Buses Served <i id="sort-icon-buses_passed" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="stop-table-body">
                    <!-- Loaded dynamically via performance.js -->
                </tbody>
            </table>
        </div>

        <!-- JS Pagination controls container -->
        <div id="stop-pagination-controls" class="mt-4"></div>
    </div>

    <!-- SECTION 5: DEVIATION LOG PANEL -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-[16px] font-medium text-[#001F44]">Recorded incidents</h2>
                <p class="text-[13px] text-slate-500 font-normal">Route violations and operational events from DB</p>
            </div>
            <div class="flex items-center gap-3 relative">
                <span id="incidents-log-badge" class="rounded-full px-2.5 py-0.5 text-[12px] font-semibold"></span>

                <button id="btn-deviation-dropdown-toggle" onclick="toggleDeviationDropdown()"
                    class="p-1.5 rounded-lg border border-black/10 hover:bg-slate-50 transition-colors text-slate-600" title="Filter incidents">
                    <i class="ti ti-filter text-[18px]"></i>
                </button>

                <!-- Deviation type filter dropdown -->
                <div id="deviation-filter-dropdown" class="hidden absolute right-0 top-10 z-20 w-48 bg-white border border-slate-200 rounded-lg shadow-xl p-3 space-y-2">
                    <div class="flex items-center justify-between pb-1.5 border-b border-slate-100">
                        <span class="text-xs font-semibold text-[#001F44]">Filter Type</span>
                        <button onclick="clearDeviationFilters()" class="text-[10px] text-blue-600 font-medium hover:underline">Clear all</button>
                    </div>
                    <div class="space-y-1.5 pt-1.5">
                        @foreach(['Off-Route', 'Long Dwell', 'Early Departure', 'Route Skip', 'Speed Anomaly'] as $type)
                            <label class="flex items-center gap-2 text-xs text-slate-600 font-medium cursor-pointer">
                                <input type="checkbox" value="{{ $type }}" class="deviation-filter-checkbox rounded border-slate-300 text-[#003F87] focus:ring-[#003F87] w-3.5 h-3.5">
                                <span>{{ $type }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-3" id="incidents-log-feed">
            <!-- Loaded dynamically via performance.js -->
        </div>
    </div>

    <!-- SECTION 6: ROUTE HEALTH SCORE CARD -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div class="grid grid-cols-1 md:grid-cols-10 gap-5 items-center">
            <!-- Left Score display -->
            <div class="md:col-span-4 flex flex-col items-center md:items-start text-center md:text-left md:border-r border-slate-100 md:pr-6">
                <span class="text-[11px] text-slate-500 uppercase tracking-wider font-bold">Overall route health</span>
                @php
                    $healthScore = $routeHealthScore->overall_score;
                    $healthColor = $healthScore >= 80 ? 'text-[#3B6D11]' : ($healthScore >= 60 ? 'text-[#854F0B]' : 'text-[#A32D2D]');
                @endphp
                <div class="flex items-baseline mt-2">
                    <span id="health-overall-score" class="text-[48px] font-medium leading-none {{ $healthColor }}">{{ $healthScore }}</span>
                    <span class="text-[20px] text-slate-400 ml-1">/100</span>
                </div>
                <span id="health-score-label" class="text-[14px] font-bold mt-1 uppercase tracking-wide {{ $healthColor }}">{{ $routeHealthScore->score_label }}</span>
                <p class="text-[11px] text-slate-400 italic mt-3">
                    Computed from live DB data: on-time rate, headway regularity, stop coverage, and incidents.
                </p>
            </div>

            <!-- Right breakdown progress bars -->
            <div class="md:col-span-6 space-y-3.5">
                <span class="text-[11px] text-slate-500 uppercase tracking-wider font-bold block">Score components (25 pts each)</span>

                <div class="space-y-1">
                    <div class="flex justify-between items-center text-[12px] font-semibold">
                        <span class="text-slate-600">On-time rate</span>
                        <span class="font-mono-custom text-[#001F44]">{{ $routeHealthScore->on_time_score }} / 25</span>
                    </div>
                    <div class="w-full h-[6px] bg-slate-100 rounded-full overflow-hidden">
                        <div id="progress-health-ot" class="h-full bg-[#378ADD] rounded-full transition-all duration-[600ms] ease-out"
                            style="width: {{ ($routeHealthScore->on_time_score / 25) * 100 }}%"></div>
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="flex justify-between items-center text-[12px] font-semibold">
                        <span class="text-slate-600">Headway regularity</span>
                        <span class="font-mono-custom text-[#001F44]">{{ $routeHealthScore->headway_score }} / 25</span>
                    </div>
                    <div class="w-full h-[6px] bg-slate-100 rounded-full overflow-hidden">
                        <div id="progress-health-hw" class="h-full bg-[#639922] rounded-full transition-all duration-[600ms] ease-out"
                            style="width: {{ ($routeHealthScore->headway_score / 25) * 100 }}%"></div>
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="flex justify-between items-center text-[12px] font-semibold">
                        <span class="text-slate-600">Stop coverage</span>
                        <span class="font-mono-custom text-[#001F44]">{{ $routeHealthScore->stop_adherence_score }} / 25</span>
                    </div>
                    <div class="w-full h-[6px] bg-slate-100 rounded-full overflow-hidden">
                        <div id="progress-health-stop" class="h-full bg-[#BA7517] rounded-full transition-all duration-[600ms] ease-out"
                            style="width: {{ ($routeHealthScore->stop_adherence_score / 25) * 100 }}%"></div>
                    </div>
                </div>

                <div class="space-y-1">
                    <div class="flex justify-between items-center text-[12px] font-semibold">
                        <span class="text-slate-600">Zero incidents</span>
                        <span class="font-mono-custom text-[#001F44]">{{ $routeHealthScore->deviation_score }} / 25</span>
                    </div>
                    <div class="w-full h-[6px] bg-slate-100 rounded-full overflow-hidden">
                        <div id="progress-health-dev" class="h-full bg-[#533AB7] rounded-full transition-all duration-[600ms] ease-out"
                            style="width: {{ ($routeHealthScore->deviation_score / 25) * 100 }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

    <script>
        // Load initial data state to protect from layout redraw lag
        window.GoPasigRoutesInitialData = {
            headway: @json($headwayData),
            schedule: @json($scheduleCompliance),
            stops: @json($stopAdherence->items()) // We pass the full first page of stops to render immediately
        };
    </script>

</section>

