<section id="screen-analytics" class="hidden animate-fade-in" style="display: none;">
<div class="space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Reports &amp; Analytics</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Reports &amp; Analytics</span>
        </div>
    </div>
    <style>
        :root {
            --color-background-secondary: #F8FAFC;
        }
        .metric-card-bg {
            background-color: var(--color-background-secondary);
        }
    </style>

    <!-- Success Message Alert Container -->
    <div id="analytics-alert" class="hidden p-3 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up">
        <div class="flex items-center gap-1.5">
            <i class="ti ti-circle-check text-[16px]"></i>
            <span id="analytics-alert-message"></span>
        </div>
        <button onclick="document.getElementById('analytics-alert').classList.add('hidden')" class="text-[#3B6D11] hover:opacity-80"><i class="ti ti-x"></i></button>
    </div>

    <!-- SECTION 1: EXPORT CONTROLS -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-end shrink-0">

        <!-- Export Controls Group -->
        <div class="flex flex-wrap items-center gap-3">
            <!-- Date Range Picker -->
            <div class="flex items-center gap-1.5 border border-black/15 bg-white rounded-lg px-2.5 py-1.5 text-xs font-medium text-[#001F44]">
                <i class="ti ti-calendar text-slate-500 text-[14px]"></i>
                <input type="date" id="analytics-start-date" name="start_date" value="{{ $startDate }}" class="bg-transparent border-none p-0 outline-none w-[90px] focus:ring-0 text-[11px] font-semibold text-slate-700">
                <span class="text-slate-400">→</span>
                <input type="date" id="analytics-end-date" name="end_date" value="{{ $endDate }}" class="bg-transparent border-none p-0 outline-none w-[90px] focus:ring-0 text-[11px] font-semibold text-slate-700">
            </div>

            <!-- Route Dropdown (dynamic from DB) -->
            <select id="analytics-route-id" name="route_id" class="text-xs border border-black/15 bg-white rounded-lg px-3 py-2 font-medium text-[#001F44] outline-none cursor-pointer focus:border-[#003F87]">
                <option value="all">All Routes</option>
                @foreach($availableRoutes as $route)
                    <option value="{{ $route['id'] }}" {{ $selectedRoute == $route['id'] ? 'selected' : '' }}>{{ $route['name'] }}</option>
                @endforeach
            </select>

            <!-- Report Type Dropdown -->
            <select id="analytics-report-type" name="report_type" class="text-xs border border-black/15 bg-white rounded-lg px-3 py-2 font-medium text-[#001F44] outline-none cursor-pointer focus:border-[#003F87]">
                <option value="daily" {{ $reportType == 'daily' ? 'selected' : '' }}>Daily Summary</option>
                <option value="route" {{ $reportType == 'route' ? 'selected' : '' }}>Route Performance</option>
                <option value="utilization" {{ $reportType == 'utilization' ? 'selected' : '' }}>Bus Utilization</option>
                <option value="trends" {{ $reportType == 'trends' ? 'selected' : '' }}>Passenger Trends</option>
            </select>

            <!-- Download PDF -->
            <button id="btn-export-pdf" class="h-[34px] flex items-center justify-center gap-1.5 rounded-lg border border-black/15 bg-white px-3 text-xs font-medium text-[#001F44] hover:bg-slate-50 transition-colors">
                <span class="flex items-center gap-1.5">
                    <i class="ti ti-file-type-pdf text-slate-500 text-[15px]"></i>
                    <span>Download PDF</span>
                </span>
            </button>

            <!-- Download CSV (real) -->
            <button id="btn-export-csv" class="h-[34px] flex items-center justify-center gap-1.5 rounded-lg border border-black/15 bg-white px-3 text-xs font-medium text-[#001F44] hover:bg-slate-50 transition-colors">
                <span class="flex items-center gap-1.5">
                    <i class="ti ti-table-export text-slate-500 text-[15px]"></i>
                    <span>Download CSV</span>
                </span>
            </button>
        </div>
    </div>

    <!-- SECTION 2: SUMMARY METRIC CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <!-- Card 1 -->
        <div class="metric-card-bg rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Recorded boarded</span>
                <i class="ti ti-users text-[18px] text-[#0F6E56]"></i>
            </div>
            <span id="metric-total-passengers" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $metricSummary->total_passengers }}</span>
        </div>

        <!-- Card 2 -->
        <div class="metric-card-bg rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Trips completed</span>
                <i class="ti ti-bus text-[18px] text-[#003F87]"></i>
            </div>
            <span id="metric-trips-completed" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $metricSummary->trips_completed }}</span>
        </div>

        <!-- Card 3 -->
        <div class="metric-card-bg rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Avg boarded / trip</span>
                <i class="ti ti-chart-bar text-[18px] text-[#BA7517]"></i>
            </div>
            <span id="metric-avg-per-trip" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $metricSummary->avg_per_trip }}</span>
        </div>

        <!-- Card 4 -->
        <div class="metric-card-bg rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Fleet utilization</span>
                <i class="ti ti-gauge text-[18px] text-[#E24B4A]"></i>
            </div>
            <span id="metric-utilization-rate" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $metricSummary->utilization_rate }}</span>
        </div>

        <!-- Card 5 -->
        <div class="metric-card-bg rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm min-w-0">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider truncate">Busiest route</span>
                <i class="ti ti-route text-[18px] text-purple-600"></i>
            </div>
            <div class="flex flex-col mt-2 min-w-0">
                <span id="metric-busiest-route" class="text-[20px] font-semibold text-[#001F44] leading-tight truncate">{{ $metricSummary->busiest_route }}</span>
                <span id="metric-busiest-route-count" class="text-[11px] text-slate-500 font-medium truncate">({{ $metricSummary->busiest_route_count }} boarded)</span>
            </div>
        </div>

        <!-- Card 6 -->
        <div class="metric-card-bg rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Peak boarding slot</span>
                <i class="ti ti-clock text-[18px] text-slate-500"></i>
            </div>
            <span id="metric-peak-hour" class="text-[14px] font-semibold text-[#001F44] mt-2 leading-tight">{{ $metricSummary->peak_hour }}</span>
        </div>
    </div>

    <!-- SECTION 3: CHART ROW -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- LEFT: Recorded boarded per route -->
        <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5 flex flex-col">
            <h2 class="text-[16px] font-medium text-[#001F44] mb-4">Recorded boarded per route</h2>
            <div class="flex-1 relative min-h-[280px] flex flex-col justify-center">
                <div id="routePassengersEmptyState" class="hidden absolute inset-0 z-10 flex flex-col items-center justify-center text-center bg-white/95">
                    <i class="ti ti-chart-bar-off text-[32px] text-slate-400 mb-1"></i>
                    <p class="text-[14px] font-semibold text-slate-400">No recorded boarding data available</p>
                </div>
                <div class="h-[280px] w-full">
                    <div id="routePassengersChart" style="width: 100%; height: 100%;"></div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Recorded boarding trend -->
        <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5 flex flex-col">
            <h2 class="text-[16px] font-medium text-[#001F44] mb-4">Recorded boarding trend</h2>
            <div class="flex-1 relative min-h-[280px] flex flex-col justify-center">
                <div id="hourlyRidershipEmptyState" class="hidden absolute inset-0 z-10 flex flex-col items-center justify-center text-center bg-white/95">
                    <i class="ti ti-circle-check text-[32px] text-[#0F6E56] mb-1"></i>
                    <p class="text-[14px] font-semibold text-slate-400">No boarding events recorded for selected period</p>
                </div>
                <div class="h-[280px] w-full">
                    <div id="hourlyRidershipChart" style="width: 100%; height: 100%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 4: BUS ACTUAL OPERATIONS TABLE -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-[16px] font-medium text-[#001F44]">Bus actual operations log</h2>
            <span id="bus-log-count" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[12px] font-medium text-slate-500">{{ count($busLogs) }} buses</span>
        </div>

        <div id="bus-table-empty" class="{{ count($busLogs) === 0 ? '' : 'hidden' }} text-center py-12 text-slate-400">
            <i class="ti ti-bus-off text-[40px] block mb-2"></i>
            <p class="text-sm font-medium">No bus data for the selected period</p>
        </div>

        <div id="bus-table-wrapper" class="{{ count($busLogs) === 0 ? 'hidden' : '' }} overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed text-[13px]">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4 w-[12%] cursor-pointer select-none" onclick="sortTable('bus_id')">
                            <span class="flex items-center">Bus ID <i id="sort-icon-bus_id" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[22%] cursor-pointer select-none" onclick="sortTable('assigned_route')">
                            <span class="flex items-center">Assigned Route <i id="sort-icon-assigned_route" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[11%] text-center cursor-pointer select-none" onclick="sortTable('trips_completed')">
                            <span class="flex items-center justify-center">Trips Run <i id="sort-icon-trips_completed" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[13%] text-center cursor-pointer select-none" onclick="sortTable('total_passengers')">
                            <span class="flex items-center justify-center">Recorded Boarded <i id="sort-icon-total_passengers" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[10%] text-center cursor-pointer select-none" onclick="sortTable('peak_load')">
                            <span class="flex items-center justify-center">Peak Load <i id="sort-icon-peak_load" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[10%] text-center cursor-pointer select-none" onclick="sortTable('capacity')">
                            <span class="flex items-center justify-center">Capacity <i id="sort-icon-capacity" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[14%] text-center cursor-pointer select-none" onclick="sortTable('utilization_rate')">
                            <span class="flex items-center justify-center">Utilization % <i id="sort-icon-utilization_rate" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[16%] cursor-pointer select-none" onclick="sortTable('status')">
                            <span class="flex items-center">Status <i id="sort-icon-status" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="bus-table-body">
                    @foreach($busLogs as $row)
                        @php
                            if ($row->utilization_rate >= 90) {
                                $utilBg    = 'bg-[#FCEBEB] text-[#A32D2D]';
                                $utilLabel = 'High load';
                            } elseif ($row->utilization_rate >= 70) {
                                $utilBg    = 'bg-[#FAEEDA] text-[#854F0B]';
                                $utilLabel = 'Moderate';
                            } else {
                                $utilBg    = 'bg-[#EAF3DE] text-[#3B6D11]';
                                $utilLabel = 'Normal';
                            }

                            if ($row->status === 'Operating') {
                                $statusBg = 'bg-[#E6F1FB] text-[#0C447C]';
                            } elseif ($row->status === 'Ready') {
                                $statusBg = 'bg-[#E1F5EE] text-[#0F6E56]';
                            } elseif ($row->status === 'Standby' || $row->status === 'Inactive') {
                                $statusBg = 'bg-[#F1EFE8] text-[#5F5E5A]';
                            } elseif ($row->status === 'Breakdown') {
                                $statusBg = 'bg-[#FAEEDA] text-[#854F0B]';
                            } else {
                                $statusBg = 'bg-[#FCEBEB] text-[#A32D2D]';
                            }
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors"
                            data-bus_id="{{ $row->bus_id }}"
                            data-assigned_route="{{ $row->assigned_route }}"
                            data-trips_completed="{{ $row->trips_completed }}"
                            data-total_passengers="{{ $row->total_passengers }}"
                            data-peak_load="{{ $row->peak_load }}"
                            data-capacity="{{ $row->capacity }}"
                            data-utilization_rate="{{ $row->utilization_rate }}"
                            data-status="{{ $row->status }}">
                            <td class="py-3 px-4 font-mono-custom text-[#001F44] font-semibold">{{ $row->bus_id }}</td>
                            <td class="py-3 px-4">
                                <span class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full inline-block" style="background-color: {{ $row->route_color }}"></span>
                                    <span class="font-medium text-[#001F44]">{{ $row->assigned_route }}</span>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">{{ $row->trips_completed }}</td>
                            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">{{ number_format($row->total_passengers) }}</td>
                            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">{{ $row->peak_load }}</td>
                            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">{{ $row->capacity }}</td>
                            <td class="py-3 px-4 text-center">
                                <div class="inline-flex flex-col items-center gap-1.5 w-full">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wide {{ $utilBg }}">{{ $utilLabel }} ({{ $row->utilization_rate }}%)</span>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold tracking-wide {{ $statusBg }}">{{ $row->status }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 5: DISPATCH RECOMMENDATION PANEL -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-[16px] font-medium text-[#001F44]">Dispatch recommendations</h2>
                <p class="text-[13px] text-slate-500 font-normal">Standby until Dispatch Intelligence is fully aligned</p>
            </div>
            <div class="flex items-center gap-3">
                <span id="recommendations-last-updated" class="text-[12px] text-slate-400 font-normal">Last updated: {{ $lastUpdatedTime }}</span>
                <button id="btn-refresh-recommendations" class="p-1.5 rounded-lg border border-black/10 hover:bg-slate-50 transition-colors text-slate-600" title="Refresh Analytics">
                     <i class="ti ti-refresh text-[18px]"></i>
                </button>
            </div>
        </div>

        <div id="recommendations-empty" class="text-center py-10 text-slate-400">
            <i class="ti ti-route-off text-[40px] block mb-2"></i>
            <p class="text-sm font-semibold">Dispatch recommendations are on standby.</p>
            <p class="text-xs mt-1">Recommendations will return after Dispatch Intelligence is fully operationally aligned.</p>
        </div>
        <div id="recommendations-container" class="hidden grid-cols-1 md:grid-cols-3 gap-3"></div>
    </div>
</div>

    <script>
        window.GoPasigAnalyticsInitialData = {
            routeSummary: @json($routeSummary),
            hourlyRidership: @json($hourlyRidership)
        };
    </script>

</section>
