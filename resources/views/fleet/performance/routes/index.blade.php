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
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Trips run</span>
                <i class="ti ti-bus text-[16px] text-[#003F87]"></i>
            </div>
            <span id="metric-trips-run" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $routePerformanceSummary->trips_run }}</span>
        </div>

        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Completed</span>
                <i class="ti ti-circle-check text-[16px] text-[#0F6E56]"></i>
            </div>
            <span id="metric-trips-completed" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $routePerformanceSummary->completed_trips }}</span>
        </div>

        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Ongoing</span>
                <i class="ti ti-player-play text-[16px] text-[#378ADD]"></i>
            </div>
            <span id="metric-trips-ongoing" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $routePerformanceSummary->ongoing_trips }}</span>
        </div>

        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Dispatched</span>
                <i class="ti ti-send text-[16px] text-[#854F0B]"></i>
            </div>
            <span id="metric-trips-dispatched" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $routePerformanceSummary->dispatched_trips }}</span>
        </div>

        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Cancelled</span>
                <i class="ti ti-circle-x text-[16px] text-[#E24B4A]"></i>
            </div>
            <span id="metric-trips-cancelled" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $routePerformanceSummary->cancelled_trips }}</span>
        </div>

        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Avg trip duration</span>
                <i class="ti ti-clock text-[16px] text-[#533AB7]"></i>
            </div>
            <span id="metric-avg-trip-duration" class="text-[20px] font-medium text-[#001F44] leading-none mt-2">{{ $routePerformanceSummary->avg_trip_duration_label }}</span>
        </div>
    </div>

    <!-- SECTION 3: CHART ROW -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- LEFT: Actual Headway by Direction -->
        <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                <div>
                    <h2 class="text-[16px] font-medium text-[#001F44]">Actual headway by direction</h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Average gap between consecutive Trip starts on the same service day</p>
                </div>
                <span class="rounded bg-blue-50 px-2 py-1 text-[10px] font-semibold uppercase text-[#003F87]">Actual starts</span>
            </div>

            <div id="routeHeadwayEmptyState" class="hidden h-[260px] flex flex-col items-center justify-center text-slate-400">
                <i class="ti ti-chart-line-off text-[40px] block mb-2"></i>
                <p class="text-sm font-medium">Not enough actual Trip starts</p>
                <p class="text-xs mt-1">Two starts in the same direction and Manila service day are required</p>
            </div>
            <div id="headwayRegularityChart" style="width: 100%; height: 260px;"></div>
        </div>

        <!-- RIGHT: Actual Trip Duration by Direction -->
        <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                <div>
                    <h2 class="text-[16px] font-medium text-[#001F44]">Actual trip duration by direction</h2>
                    <p class="text-[11px] text-slate-500 mt-0.5">Valid completed Trip start-to-end durations</p>
                </div>
                <span class="rounded bg-emerald-50 px-2 py-1 text-[10px] font-semibold uppercase text-emerald-700">Completed trips</span>
            </div>

            <div id="routeDurationEmptyState" class="hidden h-[260px] flex flex-col items-center justify-center text-slate-400">
                <i class="ti ti-clock-off text-[40px] block mb-2"></i>
                <p class="text-sm font-medium">No valid completed durations</p>
                <p class="text-xs mt-1">Completed Trips need valid start and end timestamps</p>
            </div>
            <div id="tripDurationChart" style="width: 100%; height: 260px;"></div>
        </div>
    </div>

    <!-- SECTION 4: RECORDED STOP ACTIVITY TABLE -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
            <div>
                <h2 class="text-[16px] font-medium text-[#001F44]">Recorded stop activity</h2>
                <p class="text-[13px] text-slate-500 font-normal">Accepted driver passenger updates grouped by confirmed route stop</p>
            </div>
            <div class="flex items-center gap-2">
                @php
                    $selectedRouteObj = collect($availableRoutes)->firstWhere('id', (int)$selectedRoute);
                    $badgeLabel = $selectedRoute === 'all' ? 'All Routes' : ($selectedRouteObj ? $selectedRouteObj['name'] : 'Route ' . $selectedRoute);
                    $badgeColor = $selectedRoute === 'all' ? '#6b7280' : ($selectedRouteObj['color'] ?? '#6b7280');
                @endphp
                <span class="flex items-center gap-2 rounded-full border border-black/10 bg-slate-50 px-3 py-1 text-xs font-semibold text-slate-700">
                    <span id="route-pill-color" class="w-2.5 h-2.5 rounded-full inline-block" style="background-color: {{ $badgeColor }}"></span>
                    <span id="route-pill-label">{{ $badgeLabel }}</span>
                </span>
            </div>
        </div>

        <div id="stop-table-empty" class="{{ $stopAdherence->isEmpty() ? '' : 'hidden' }} text-center py-12 text-slate-400">
            <i class="ti ti-map-pin-off text-[40px] block mb-2"></i>
            <p class="text-sm font-medium">No recorded stop activity</p>
            <p class="text-xs mt-1">No accepted passenger updates match the selected period</p>
        </div>

        <div id="stop-table-wrapper" class="{{ $stopAdherence->isEmpty() ? 'hidden' : '' }} overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed text-[13px]">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4 w-[24%] cursor-pointer select-none" onclick="sortStopTable('stop_name')">
                            <span class="flex items-center">Stop Name <i id="sort-icon-stop_name" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[24%] cursor-pointer select-none" onclick="sortStopTable('display_label')">
                            <span class="flex items-center">Route Direction <i id="sort-icon-display_label" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[8%] text-center cursor-pointer select-none" onclick="sortStopTable('sequence')">
                            <span class="flex items-center justify-center">Seq <i id="sort-icon-sequence" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[14%] text-center cursor-pointer select-none" onclick="sortStopTable('recorded_boarded')">
                            <span class="flex items-center justify-center">Recorded Boarded <i id="sort-icon-recorded_boarded" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[14%] text-center cursor-pointer select-none" onclick="sortStopTable('recorded_alighted')">
                            <span class="flex items-center justify-center">Recorded Alighted <i id="sort-icon-recorded_alighted" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[12%] text-center cursor-pointer select-none" onclick="sortStopTable('trips_recorded')">
                            <span class="flex items-center justify-center">Trips Recorded <i id="sort-icon-trips_recorded" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
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

    <!-- SECTION 5: OPERATIONAL INCIDENT LOG -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-[16px] font-medium text-[#001F44]">Operational incidents</h2>
                <p class="text-[13px] text-slate-500 font-normal">Actual driver-reported incidents for the selected period</p>
            </div>
            <span id="incidents-log-badge" class="rounded-full px-2.5 py-0.5 text-[12px] font-semibold"></span>
        </div>

        <div class="space-y-3" id="incidents-log-feed">
            <!-- Loaded dynamically via performance.js -->
        </div>
    </div>

    <!-- SECTION 6: ROUTE HEALTH SCORE CARD -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div class="grid grid-cols-1 md:grid-cols-10 gap-5 items-center">
            <div class="md:col-span-4 flex flex-col items-center md:items-start text-center md:text-left md:border-r border-slate-100 md:pr-6">
                <span class="text-[11px] text-slate-500 uppercase tracking-wider font-bold">Overall route health</span>
                <span id="health-overall-score" class="text-[24px] font-medium leading-none text-slate-400 mt-3">No data</span>
                <span id="health-score-label" class="text-[12px] font-bold mt-2 uppercase tracking-wide text-slate-400">Insufficient evidence</span>
                <p id="health-data-note" class="text-[11px] text-slate-400 italic mt-3">
                    Equal-weight score from actual completion, headway, and recorded incidents. Incomplete evidence fails closed.
                </p>
            </div>

            <div class="md:col-span-6 space-y-3.5">
                <span class="text-[11px] text-slate-500 uppercase tracking-wider font-bold block">Actual component scores</span>

                <div class="space-y-1">
                    <div class="flex justify-between items-center text-[12px] font-semibold">
                        <span class="text-slate-600">Trip completion reliability</span>
                        <span id="health-completion-score" class="font-mono-custom text-slate-400">No data</span>
                    </div>
                    <div class="w-full h-[6px] bg-slate-100 rounded-full overflow-hidden">
                        <div id="progress-health-completion" class="h-full bg-[#378ADD] rounded-full transition-all duration-[600ms] ease-out" style="width: 0%"></div>
                    </div>
                    <p id="health-completion-evidence" class="text-[10px] text-slate-400">No finalized Trips</p>
                </div>

                <div class="space-y-1">
                    <div class="flex justify-between items-center text-[12px] font-semibold">
                        <span class="text-slate-600">Headway consistency</span>
                        <span id="health-headway-score" class="font-mono-custom text-slate-400">No data</span>
                    </div>
                    <div class="w-full h-[6px] bg-slate-100 rounded-full overflow-hidden">
                        <div id="progress-health-hw" class="h-full bg-[#639922] rounded-full transition-all duration-[600ms] ease-out" style="width: 0%"></div>
                    </div>
                    <p id="health-headway-evidence" class="text-[10px] text-slate-400">Insufficient same-direction gaps</p>
                </div>

                <div class="space-y-1">
                    <div class="flex justify-between items-center text-[12px] font-semibold">
                        <span class="text-slate-600">Recorded incident-free trips</span>
                        <span id="health-incident-score" class="font-mono-custom text-slate-400">No data</span>
                    </div>
                    <div class="w-full h-[6px] bg-slate-100 rounded-full overflow-hidden">
                        <div id="progress-health-incidents" class="h-full bg-[#BA7517] rounded-full transition-all duration-[600ms] ease-out" style="width: 0%"></div>
                    </div>
                    <p id="health-incident-evidence" class="text-[10px] text-slate-400">No started Trips</p>
                </div>

            </div>
        </div>
    </div>
</div>

    <script>
        // Load initial data state to protect from layout redraw lag
        window.GoPasigRoutesInitialData = {
            headway: @json($headwayData),
            duration: @json($tripDurationData),
            stops: @json($stopActivityData)
        };
    </script>

</section>
