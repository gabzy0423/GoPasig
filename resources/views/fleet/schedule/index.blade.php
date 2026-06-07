<section id="screen-schedule" class="hidden" style="display: none;">
<div class="space-y-6">
    <!-- SECTION 1: PAGE HEADER + FILTER CONTROLS -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between shrink-0">
        <div>
            <h1 class="text-[22px] font-medium text-[#001F44]">Schedule Compliance</h1>
            <p class="text-[14px] text-slate-500 mt-0.5 font-normal">Libreng Sakay Program — Pasig City</p>
        </div>

        <!-- Filter Control Group -->
        <div class="flex flex-wrap items-center gap-2.5">
            <!-- Date range picker -->
            <div class="flex items-center gap-1 border border-black/15 bg-white rounded-lg px-2 py-1.5 text-xs font-medium text-[#001F44]">
                <i class="ti ti-calendar text-slate-500 text-[14px]"></i>
                <input type="date" id="compliance-date-from" value="{{ $dateFrom }}"
                    class="bg-transparent border-none p-0 outline-none w-[90px] focus:ring-0 text-[11px] font-semibold text-slate-700">
                <span class="text-slate-400">→</span>
                <input type="date" id="compliance-date-to" value="{{ $dateTo }}"
                    class="bg-transparent border-none p-0 outline-none w-[90px] focus:ring-0 text-[11px] font-semibold text-slate-700">
            </div>

            <!-- Route Dropdown -->
            <div class="flex items-center gap-1.5 border border-black/15 bg-white rounded-lg px-2 py-1.5 text-xs text-[#001F44]">
                <i class="ti ti-route text-slate-500 text-[14px]"></i>
                <select id="compliance-route-id"
                    class="bg-transparent border-none p-0 outline-none focus:ring-0 text-[11px] font-semibold text-slate-700 cursor-pointer">
                    <option value="all" {{ $selectedRoute === 'all' ? 'selected' : '' }}>All Routes</option>
                    @foreach($availableRoutes as $route)
                        <option value="{{ $route['id'] }}" {{ $selectedRoute == $route['id'] ? 'selected' : '' }}>{{ $route['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Driver Dropdown -->
            <div class="flex items-center gap-1.5 border border-black/15 bg-white rounded-lg px-2 py-1.5 text-xs text-[#001F44]">
                <i class="ti ti-id text-slate-500 text-[14px]"></i>
                <select id="compliance-driver-id"
                    class="bg-transparent border-none p-0 outline-none focus:ring-0 text-[11px] font-semibold text-slate-700 cursor-pointer">
                    <option value="all" {{ $selectedDriver === 'all' ? 'selected' : '' }}>All Drivers</option>
                    @foreach($availableDrivers as $drv)
                        <option value="{{ $drv['name'] }}" {{ $selectedDriver === $drv['name'] ? 'selected' : '' }}>{{ $drv['name'] }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Compliance Status Dropdown -->
            <div class="flex items-center gap-1.5 border border-black/15 bg-white rounded-lg px-2 py-1.5 text-xs text-[#001F44]">
                <i class="ti ti-shield-check text-slate-500 text-[14px]"></i>
                <select id="compliance-status-id"
                    class="bg-transparent border-none p-0 outline-none focus:ring-0 text-[11px] font-semibold text-slate-700 cursor-pointer">
                    <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>All Statuses</option>
                    <option value="On Time" {{ $selectedStatus === 'On Time' ? 'selected' : '' }}>On Time</option>
                    <option value="Late" {{ $selectedStatus === 'Late' ? 'selected' : '' }}>Late</option>
                    <option value="Missed" {{ $selectedStatus === 'Missed' ? 'selected' : '' }}>Missed</option>
                </select>
            </div>

            <!-- Apply Filters Button -->
            <button id="btn-apply-compliance-filters" type="button"
                class="h-[34px] flex items-center justify-center rounded-lg bg-[#003F87] px-4 text-xs font-semibold text-white hover:bg-[#002f66] transition-colors shadow-sm">
                Apply filters
            </button>

            <!-- Export CSV -->
            <button id="btn-export-compliance-csv" type="button" onclick="exportComplianceReport()"
                class="h-[34px] flex items-center justify-center gap-1.5 rounded-lg border border-black/15 bg-white px-3 text-xs font-medium text-[#001F44] hover:bg-slate-50 transition-colors shadow-sm">
                <span class="flex items-center gap-1.5">
                    <i class="ti ti-table-export text-slate-500 text-[14px]"></i>
                    <span>Export report</span>
                </span>
            </button>
        </div>
    </div>

    <!-- SECTION 2: SUMMARY COMPLIANCE METRIC CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- Card 1 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Overall on-time rate</span>
                <i class="ti ti-circle-check text-[16px] text-teal-600"></i>
            </div>
            @php $rateVal = $complianceSummary->on_time_rate; $rateColor = $rateVal >= 80 ? 'text-[#0F6E56]' : ($rateVal >= 60 ? 'text-[#854F0B]' : 'text-[#A32D2D]'); @endphp
            <span id="metric-on-time-rate" class="text-[24px] font-medium leading-none mt-2 {{ $rateColor }}">{{ $rateVal }}%</span>
        </div>

        <!-- Card 2 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Trips completed</span>
                <i class="ti ti-bus text-[16px] text-blue-600"></i>
            </div>
            <span id="metric-trips-completed" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $complianceSummary->trips_completed }}</span>
        </div>

        <!-- Card 3 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">On time</span>
                <i class="ti ti-check text-[16px] text-teal-600"></i>
            </div>
            <span id="metric-on-time-count" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $complianceSummary->on_time_count }}</span>
        </div>

        <!-- Card 4 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Late departures</span>
                <i class="ti ti-clock-exclamation text-[16px] text-amber-500"></i>
            </div>
            <span id="metric-late-count" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $complianceSummary->late_count }}</span>
        </div>

        <!-- Card 5 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Missed trips</span>
                <i class="ti ti-x text-[16px] text-red-500"></i>
            </div>
            <span id="metric-missed-count" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $complianceSummary->missed_count }}</span>
        </div>
    </div>

    <!-- SECTION 3: CHART ROW -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <!-- LEFT CHART: On-time rate per route -->
        <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
            <h2 class="text-[16px] font-medium text-[#001F44] mb-4">On-time rate per route</h2>
            <div class="h-[280px] relative">
                <div id="onTimeRatePerRouteChart" style="width: 100%; height: 100%;"></div>
            </div>
        </div>

        <!-- RIGHT CHART: Delay trend over time -->
        <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5 flex flex-col">
            <h2 class="text-[16px] font-medium text-[#001F44] mb-4">Delay trend over time</h2>
            <div class="flex-1 relative min-h-[280px] flex flex-col justify-center">
                <div id="delayTrendEmptyState" class="hidden absolute inset-0 z-10 flex flex-col items-center justify-center text-center bg-white/95">
                    <i class="ti ti-circle-check text-[32px] text-[#0F6E56] mb-1"></i>
                    <p class="text-[14px] font-semibold text-slate-400">No delays recorded for selected period</p>
                </div>
                <div class="h-full w-full">
                    <div id="delayTrendChart" style="width: 100%; height: 100%;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 4: TRIP-BY-TRIP COMPLIANCE TABLE -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="text-[16px] font-medium text-[#001F44]">Trip compliance log</h2>
                <p class="text-[13px] text-slate-500 font-normal">Schedule records from the database</p>
            </div>
            <span id="compliance-records-badge" class="rounded-full bg-slate-100 text-slate-600 px-3 py-1 text-xs font-semibold">
                {{ $rawTripLogsCount }} trips
            </span>
        </div>

        <div id="compliance-table-empty" class="hidden flex flex-col items-center justify-center py-12 text-slate-400">
            <i class="ti ti-calendar-off text-[40px] mb-2"></i>
            <p class="text-sm font-medium">No schedule records match the selected filters</p>
            <p class="text-xs mt-1">Try adjusting the date range or filters</p>
        </div>

        <div id="compliance-table-wrapper" class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed text-[13px]">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4 w-[8%] cursor-pointer select-none" onclick="sortComplianceTable('trip_id')">
                            <span class="flex items-center">Trip ID <i id="sort-icon-trip_id" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[9%] cursor-pointer select-none" onclick="sortComplianceTable('bus_id')">
                            <span class="flex items-center">Bus <i id="sort-icon-bus_id" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[14%] cursor-pointer select-none" onclick="sortComplianceTable('driver_name')">
                            <span class="flex items-center">Driver <i id="sort-icon-driver_name" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[16%] cursor-pointer select-none" onclick="sortComplianceTable('route_name')">
                            <span class="flex items-center">Route <i id="sort-icon-route_name" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[10%] text-center cursor-pointer select-none" onclick="sortComplianceTable('scheduled_departure')">
                            <span class="flex items-center justify-center">Sched. Depart <i id="sort-icon-scheduled_departure" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[10%] text-center cursor-pointer select-none" onclick="sortComplianceTable('actual_departure')">
                            <span class="flex items-center justify-center">Actual Depart <i id="sort-icon-actual_departure" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[10%] text-center cursor-pointer select-none" onclick="sortComplianceTable('variance_minutes')">
                            <span class="flex items-center justify-center">Variance <i id="sort-icon-variance_minutes" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[11%] cursor-pointer select-none" onclick="sortComplianceTable('status')">
                            <span class="flex items-center">Status <i id="sort-icon-status" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[12%] text-center">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="compliance-table-body">
                    @foreach($tripLogs as $row)
                        @php
                            $statusBadge = match($row['status']) {
                                'On Time' => (object)['bg' => 'bg-[#E1F5EE] text-[#0F6E56]', 'icon' => 'ti-check'],
                                'Late'    => (object)['bg' => 'bg-[#FAEEDA] text-[#854F0B]', 'icon' => 'ti-clock-exclamation'],
                                'Early'   => (object)['bg' => 'bg-[#E6F1FB] text-[#185FA5]', 'icon' => 'ti-clock-bolt'],
                                'Missed'  => (object)['bg' => 'bg-[#FCEBEB] text-[#A32D2D]', 'icon' => 'ti-x'],
                                default   => (object)['bg' => 'bg-slate-100 text-slate-600',  'icon' => 'ti-help'],
                            };

                            if ($row['status'] === 'Missed') {
                                $varText = '--'; $varColor = 'text-slate-400';
                            } else {
                                $minutes = $row['variance_minutes'];
                                if ($minutes >= -2 && $minutes <= 2) {
                                    $varText = 'On time'; $varColor = 'text-[#0F6E56] font-semibold';
                                } elseif ($minutes > 2) {
                                    $varText = '+' . $minutes . ' min'; $varColor = 'text-[#A32D2D] font-bold';
                                } else {
                                    $varText = '−' . abs($minutes) . ' min'; $varColor = 'text-[#0F6E56] font-semibold';
                                }
                            }
                        @endphp
                        <tr class="hover:bg-slate-50 transition-colors"
                            data-trip_id="{{ $row['trip_id'] }}"
                            data-bus_id="{{ $row['bus_id'] }}"
                            data-driver_name="{{ $row['driver_name'] }}"
                            data-route_name="{{ $row['route_name'] }}"
                            data-scheduled_departure="{{ $row['scheduled_departure'] }}"
                            data-actual_departure="{{ $row['actual_departure'] }}"
                            data-variance_minutes="{{ $row['variance_minutes'] }}"
                            data-status="{{ $row['status'] }}">
                            <td class="py-3 px-4 font-mono-custom text-[#001F44] font-medium">{{ $row['trip_id'] }}</td>
                            <td class="py-3 px-4 font-mono-custom text-slate-600">{{ $row['bus_id'] }}</td>
                            <td class="py-3 px-4 text-slate-700">
                                <span class="flex items-center gap-1">
                                    <span class="font-medium">{{ $row['driver_name'] }}</span>
                                    <i class="ti ti-id text-slate-400 text-[12px]"></i>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full inline-block" style="background-color: {{ $row['route_color'] }}"></span>
                                    <span class="font-medium text-[#001F44] text-[12px] bg-slate-50 border border-slate-100 rounded-full px-2 py-0.5">{{ $row['route_name'] }}</span>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono-custom text-slate-600">{{ $row['scheduled_departure'] }}</td>
                            <td class="py-3 px-4 text-center font-mono-custom text-slate-600">{{ $row['actual_departure'] }}</td>
                            <td class="py-3 px-4 text-center font-mono-custom {{ $varColor }}">{{ $varText }}</td>
                            <td class="py-3 px-4">
                                <span class="px-2.5 py-0.5 rounded-full text-[11px] font-semibold flex items-center gap-1 w-max {{ $statusBadge->bg }}">
                                    <i class="ti {{ $statusBadge->icon }}"></i>
                                    <span>{{ $row['status'] }}</span>
                                </span>
                            </td>
                            <td class="py-3 px-4 text-center">
                                <a href="/admin/live-map?trip={{ $row['schedule_id'] }}"
                                    class="inline-block px-2.5 py-1 rounded border border-black/10 text-[11px] font-bold text-[#003F87] hover:bg-slate-50 transition-colors">
                                    View trip
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Javascript Pagination Controls -->
        <div id="compliance-pagination-controls" class="mt-4"></div>
    </div>

    <!-- SECTION 5: DELAY PATTERN PANEL -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div>
            <h2 class="text-[16px] font-medium text-[#001F44]">Delay patterns</h2>
            <p class="text-[13px] text-slate-500 font-normal mb-4">Recurring delay hotspots and drivers — live from database</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Left: Most delayed routes -->
            <div class="space-y-4">
                <h3 class="text-[12px] font-bold uppercase tracking-wider text-slate-400">Most delayed routes</h3>
                <div class="space-y-3" id="delayed-routes-list">
                    @forelse($delayedRoutes as $dr)
                        @php $pct = min(100, ($dr['total_delay_minutes'] / 20) * 100); @endphp
                        <div class="flex items-center gap-3 h-[16px]">
                            <div class="flex items-center gap-2 w-[120px] shrink-0">
                                <span class="w-2 h-2 rounded-full inline-block shrink-0" style="background-color: {{ $dr['route_color'] }}"></span>
                                <span class="text-[13px] font-semibold text-[#001F44] truncate">{{ $dr['route_name'] }}</span>
                            </div>
                            <div class="flex-1 bg-[#F1EFE8] h-[4px] rounded-[2px] overflow-hidden">
                                <div class="bg-[#E24B4A] h-full rounded-[2px]" style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="text-[12px] text-slate-500 font-medium w-[60px] text-right shrink-0 font-mono-custom">{{ $dr['total_delay_minutes'] }} min</span>
                        </div>
                    @empty
                        <div class="flex items-center gap-2 text-[#0F6E56] py-3">
                            <i class="ti ti-circle-check text-[20px]"></i>
                            <p class="text-[13px] font-medium">All routes are running on time.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Right: Drivers with late departures -->
            <div class="space-y-4">
                <h3 class="text-[12px] font-bold uppercase tracking-wider text-slate-400">Drivers with late departures</h3>
                <div class="space-y-2" id="late-drivers-list">
                    @forelse($lateDrivers as $ld)
                        <div class="bg-white border-[0.5px] border-slate-200 rounded-md p-3 shadow-sm flex flex-col gap-2">
                            <div class="flex justify-between items-center">
                                <span class="text-[13px] font-semibold text-[#001F44]">{{ $ld['driver_name'] }}</span>
                                <span class="px-2 py-0.5 rounded text-[11px] font-bold bg-[#FAEEDA] text-[#854F0B] font-mono-custom">
                                    {{ $ld['late_count'] }} late {{ Str::plural('trip', $ld['late_count']) }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="flex items-center gap-1.5">
                                    <span class="w-2.5 h-2.5 rounded-full inline-block" style="background-color: {{ $ld['route_color'] }}"></span>
                                    <span class="text-[11px] font-bold text-[#001F44] bg-slate-50 border border-slate-100 rounded px-1.5 py-0.5">{{ $ld['assigned_route'] }}</span>
                                </span>
                                <span class="text-[11px] text-slate-400 font-semibold font-mono-custom">Avg delay: +{{ $ld['avg_delay_minutes'] }} min</span>
                            </div>
                        </div>
                    @empty
                        <div class="flex items-center gap-2 text-[#0F6E56] py-3">
                            <i class="ti ti-circle-check text-[20px]"></i>
                            <p class="text-[13px] font-medium">No drivers with late departures.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

    <script>
        window.GoPasigScheduleComplianceInitialData = {
            routeCompliance: @json($routeCompliance),
            delayTrend: @json($delayTrend),
            tripLogs: @json($tripLogs->items())
        };
    </script>

</section>

