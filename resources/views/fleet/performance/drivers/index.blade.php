<section id="screen-drivers" class="hidden animate-fade-in" style="display: none;">
<div class="space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Driver Performance</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span>Fleet</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Driver Performance</span>
        </div>
    </div>
    <!-- Success Alert Box -->
    <div id="driver-success-alert" class="hidden p-3 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up">
        <div class="flex items-center gap-1.5">
            <i class="ti ti-circle-check text-[16px]"></i>
            <span id="driver-alert-message"></span>
        </div>
        <button onclick="document.getElementById('driver-success-alert').classList.add('hidden')" class="text-[#3B6D11] hover:opacity-80"><i class="ti ti-x"></i></button>
    </div>

    <!-- SECTION 1: FILTER CONTROLS -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-end shrink-0">

        <div class="flex flex-wrap items-center gap-3">
            <!-- Date Range -->
            <div class="flex items-center gap-1.5 border border-black/15 bg-white rounded-lg px-2.5 py-1.5 text-xs font-medium text-[#001F44]">
                <i class="ti ti-calendar text-slate-500 text-[14px]"></i>
                <input type="date" id="driver-start-date" name="start_date" value="{{ $startDate }}"
                    class="bg-transparent border-none p-0 outline-none w-[90px] focus:ring-0 text-[11px] font-semibold text-slate-700">
                <span class="text-slate-400">→</span>
                <input type="date" id="driver-end-date" name="end_date" value="{{ $endDate }}"
                    class="bg-transparent border-none p-0 outline-none w-[90px] focus:ring-0 text-[11px] font-semibold text-slate-700">
            </div>

            <!-- Route Dropdown -->
            <select id="driver-route-id" name="route_id"
                class="text-xs border border-black/15 bg-white rounded-lg px-3 py-2 font-medium text-[#001F44] outline-none cursor-pointer focus:border-[#003F87]">
                <option value="all">All Routes</option>
                @foreach($availableRoutes as $route)
                    <option value="{{ $route['id'] }}" {{ $selectedRoute == $route['id'] ? 'selected' : '' }}>{{ $route['name'] }}</option>
                @endforeach
            </select>

            <!-- Status Dropdown -->
            <select id="driver-status" name="status"
                class="text-xs border border-black/15 bg-white rounded-lg px-3 py-2 font-medium text-[#001F44] outline-none cursor-pointer focus:border-[#003F87]">
                <option value="all" {{ $selectedStatus === 'all' ? 'selected' : '' }}>All Drivers</option>
                <option value="On duty" {{ $selectedStatus === 'On duty' ? 'selected' : '' }}>On Duty</option>
                <option value="Off duty" {{ $selectedStatus === 'Off duty' ? 'selected' : '' }}>Off Duty</option>
                <option value="Suspended" {{ $selectedStatus === 'Suspended' ? 'selected' : '' }}>Suspended</option>
            </select>

            <!-- Export Button -->
            <button id="btn-export-drivers-csv" type="button"
                class="h-[34px] flex items-center justify-center gap-1.5 rounded-lg border border-black/15 bg-white px-3 text-xs font-medium text-[#001F44] hover:bg-slate-50 transition-colors">
                <span class="flex items-center gap-1.5">
                    <i class="ti ti-table-export text-slate-500 text-[14px]"></i>
                    <span>Export Report</span>
                </span>
            </button>
        </div>
    </div>

    <!-- SECTION 2: SUMMARY METRIC CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3">
        <!-- Metric Card 1 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Total drivers</span>
                <i class="ti ti-id text-[16px] text-[#003F87]"></i>
            </div>
            <span id="metric-total-drivers" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $driverMetrics->total_drivers }}</span>
        </div>

        <!-- Metric Card 2 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Drivers with trips</span>
                <i class="ti ti-steering-wheel text-[16px] text-[#0F6E56]"></i>
            </div>
            <span id="metric-on-duty-today" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $driverMetrics->on_duty_today }}</span>
        </div>

        <!-- Metric Card 3 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Avg performance score</span>
                <i class="ti ti-star text-[16px] text-amber-500"></i>
            </div>
            <span id="metric-avg-score" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $driverMetrics->avg_performance_score ?? 'No data' }}</span>
        </div>

        <!-- Metric Card 4 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Incidents this period</span>
                <i class="ti ti-alert-triangle text-[16px] text-[#E24B4A]"></i>
            </div>
            <span id="metric-total-incidents" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $driverMetrics->incidents_this_period }}</span>
        </div>

        <!-- Metric Card 5 -->
        <div class="bg-slate-50 rounded-md p-4 flex flex-col justify-between h-[96px] shadow-sm">
            <div class="flex justify-between items-start">
                <span class="text-[11px] text-slate-500 font-semibold uppercase tracking-wider">Avg trips per driver</span>
                <i class="ti ti-repeat text-[16px] text-purple-600"></i>
            </div>
            <span id="metric-avg-trips" class="text-[24px] font-medium text-[#001F44] leading-none mt-2">{{ $driverMetrics->avg_trips_per_driver }}</span>
        </div>
    </div>

    <!-- SECTION 3: CHART ROW -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <!-- LEFT: Leaderboard -->
        <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-[16px] font-medium text-[#001F44]">Top drivers</h2>
                <span class="text-[13px] text-slate-400 font-normal">By performance score</span>
            </div>

            <div class="divide-y divide-slate-100" id="top-drivers-list">
                @forelse($topDrivers as $top)
                    @php
                        $avatarClasses = match($top['rank']) {
                            1 => 'bg-blue-200 text-blue-800',
                            2 => 'bg-teal-200 text-teal-800',
                            3 => 'bg-amber-200 text-amber-800',
                            4 => 'bg-orange-200 text-orange-800',
                            default => 'bg-purple-200 text-purple-800',
                        };
                        $rowStyle = $top['rank'] === 1 ? 'border-l-[3px] border-[#003F87] bg-[#E6F1FB] pl-3' : '';
                        $scorePill = $top['performance_score'] === null
                            ? 'bg-slate-100 text-slate-400'
                            : ($top['performance_score'] >= 85
                                ? 'bg-[#EAF3DE] text-[#3B6D11]'
                                : ($top['performance_score'] >= 70 ? 'bg-[#FAEEDA] text-[#854F0B]' : 'bg-[#FCEBEB] text-[#A32D2D]'));
                        $scoreLabel = $top['performance_score'] ?? 'No data';
                    @endphp
                    <div class="flex items-center justify-between py-2.5 transition-all duration-150 {{ $rowStyle }}">
                        <div class="flex items-center gap-3">
                            <span class="text-[18px] font-medium text-slate-400 w-5">{{ $top['rank'] }}</span>
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-xs font-semibold {{ $avatarClasses }}">
                                {{ $top['initials'] }}
                            </div>
                            <div class="flex flex-col">
                                <span class="text-[14px] font-medium text-[#001F44]">{{ $top['driver_name'] }}</span>
                                <span class="flex items-center gap-1 mt-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full inline-block" style="background-color: {{ $top['route_color'] }}"></span>
                                    <span class="text-slate-400 text-[11px] font-medium">{{ $top['assigned_route'] }}</span>
                                </span>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-1">
                            <span class="px-2 py-0.5 rounded text-[13px] font-semibold tracking-wide {{ $scorePill }}">{{ $scoreLabel }}</span>
                            <span class="text-[11px] text-slate-400 font-medium">{{ $top['trips_run'] }} trips</span>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-6 text-slate-400">No driver records found.</div>
                @endforelse
            </div>
        </div>

        <!-- RIGHT: Score Distribution Chart -->
        <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
            <h2 class="text-[16px] font-medium text-[#001F44] mb-4">Performance score distribution</h2>

            <div id="driver-chart-wrapper" class="h-[280px] relative">
                <div id="scoreDistributionChart" style="width: 100%; height: 100%;"></div>
            </div>
            <div class="mt-4 flex flex-wrap items-center justify-center gap-4 border-t border-slate-50 pt-3">
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-sm inline-block bg-[#639922]"></span>
                    <span class="text-slate-600 text-xs font-semibold">Excellent (&ge;85)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-sm inline-block bg-[#BA7517]"></span>
                    <span class="text-slate-600 text-xs font-semibold">Needs improvement (70–84)</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="w-3 h-3 rounded-sm inline-block bg-[#E24B4A]"></span>
                    <span class="text-slate-600 text-xs font-semibold">Poor (&lt;70)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- SECTION 4: DRIVER PERFORMANCE TABLE -->
    <div class="bg-white border-[0.5px] border-slate-200 shadow-sm rounded-lg p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between mb-4">
            <div class="flex items-center gap-2">
                <h2 class="text-[16px] font-medium text-[#001F44]">All drivers</h2>
                <span id="driver-records-count" class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[12px] font-medium text-slate-500">{{ count($driverLogs) }} records</span>
            </div>
            <div class="relative w-full sm:w-[200px]">
                <i class="ti ti-search absolute left-3 top-2.5 text-slate-400 text-[14px]"></i>
                <input type="text" id="driver-search-input" value="{{ $search }}"
                    placeholder="Search driver name..."
                    class="w-full text-xs pl-8 pr-3 py-2 border border-black/10 rounded-lg outline-none focus:border-[#003F87] transition-all">
            </div>
        </div>

        <div id="driver-table-empty" class="hidden flex flex-col items-center justify-center py-12 text-slate-400">
            <i class="ti ti-user-off text-[40px] mb-2"></i>
            <p class="text-sm font-medium">No drivers match the selected filters</p>
        </div>

        <div id="driver-table-wrapper" class="overflow-x-auto">
            <table class="w-full text-left border-collapse table-fixed text-[13px]">
                <thead>
                    <tr class="bg-slate-50/50 border-b border-slate-100 text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        <th class="py-3 px-4 w-[20%] cursor-pointer select-none" onclick="sortDriverTable('driver_name')">
                            <span class="flex items-center">Driver <i id="sort-icon-driver_name" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[16%] cursor-pointer select-none" onclick="sortDriverTable('assigned_route')">
                            <span class="flex items-center">Assigned Route <i id="sort-icon-assigned_route" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[10%] cursor-pointer select-none" onclick="sortDriverTable('status')">
                            <span class="flex items-center">Status <i id="sort-icon-status" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[10%] text-center cursor-pointer select-none" onclick="sortDriverTable('trips_run')">
                            <span class="flex items-center justify-center">Trips Run <i id="sort-icon-trips_run" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[12%] text-center cursor-pointer select-none" onclick="sortDriverTable('recorded_boarded')">
                            <span class="flex items-center justify-center">Recorded Boarded <i id="sort-icon-recorded_boarded" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[10%] text-center cursor-pointer select-none" onclick="sortDriverTable('incidents')">
                            <span class="flex items-center justify-center">Incidents <i id="sort-icon-incidents" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[12%] text-center cursor-pointer select-none" onclick="sortDriverTable('avg_trip_time_minutes')">
                            <span class="flex items-center justify-center">Avg Trip Time <i id="sort-icon-avg_trip_time_minutes" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                        <th class="py-3 px-4 w-[10%] text-center cursor-pointer select-none" onclick="sortDriverTable('performance_score')">
                            <span class="flex items-center justify-center">Score <i id="sort-icon-performance_score" class="ti ti-arrows-sort text-slate-300 ml-1 sort-icon"></i></span>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100" id="driver-table-body">
                    @foreach($driverLogs as $row)
                        @php
                            $statusBg = match(strtolower($row['status'])) {
                                'on duty'   => 'bg-[#E1F5EE] text-[#0F6E56]',
                                'off duty'  => 'bg-[#F1EFE8] text-[#5F5E5A]',
                                default     => 'bg-[#FCEBEB] text-[#A32D2D]',
                            };
                            $scoreBg = $row['performance_score'] === null
                                ? 'bg-slate-100 text-slate-400'
                                : ($row['performance_score'] >= 85
                                    ? 'bg-[#EAF3DE] text-[#3B6D11]'
                                    : ($row['performance_score'] >= 70 ? 'bg-[#FAEEDA] text-[#854F0B]' : 'bg-[#FCEBEB] text-[#A32D2D]'));
                            $scoreLabel = $row['performance_score'] ?? 'No data';
                        @endphp
                        <tr class="hover:bg-slate-50 cursor-pointer transition-colors"
                            onclick="openDriverDrawer('{{ $row['driver_id'] }}')"
                            data-driver_name="{{ $row['driver_name'] }}"
                            data-assigned_route="{{ $row['assigned_route'] }}"
                            data-status="{{ $row['status'] }}"
                            data-trips_run="{{ $row['trips_run'] }}"
                            data-recorded_boarded="{{ $row['recorded_boarded'] }}"
                            data-incidents="{{ $row['incidents'] }}"
                            data-avg_trip_time_minutes="{{ $row['avg_trip_time_minutes'] }}"
                            data-performance_score="{{ $row['performance_score'] }}">
                            <td class="py-3 px-4">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-7 h-7 rounded-full bg-blue-100 text-blue-700 flex items-center justify-center text-[11px] font-bold shrink-0">
                                        {{ $row['initials'] }}
                                    </div>
                                    <div class="flex flex-col min-w-0">
                                        <span class="font-medium text-[#001F44] truncate">{{ $row['driver_name'] }}</span>
                                        <span class="text-[11px] text-slate-400 font-mono-custom">{{ $row['emp_id'] }}</span>
                                    </div>
                                </div>
                            </td>
                            <td class="py-3 px-4">
                                <span class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full inline-block shrink-0" style="background-color: {{ $row['route_color'] }}"></span>
                                    <span class="font-medium text-[#001F44]">{{ $row['assigned_route'] }}</span>
                                </span>
                            </td>
                            <td class="py-3 px-4">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold tracking-wide {{ $statusBg }}">{{ $row['status'] }}</span>
                            </td>
                            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">{{ $row['trips_run'] }}</td>
                            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">{{ number_format($row['recorded_boarded']) }}</td>
                            <td class="py-3 px-4 text-center font-mono-custom">
                                @if($row['incidents'] > 0)
                                    <span class="text-[#A32D2D] font-bold">{{ $row['incidents'] }}</span>
                                @else
                                    <span class="text-slate-400 font-medium">0</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-center font-mono-custom text-slate-700">
                                {{ $row['avg_trip_time_minutes'] > 0 ? $row['avg_trip_time_minutes'] . ' min' : 'No data' }}
                            </td>
                            <td class="py-3 px-4 text-center">
                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold tracking-wide {{ $scoreBg }}">{{ $scoreLabel }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- SECTION 5: INDIVIDUAL DRIVER DETAIL DRAWER -->
    <div id="driver-drawer" class="fixed inset-0 z-50 overflow-hidden hidden" aria-labelledby="slide-over-title" role="dialog" aria-modal="true">
        <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" onclick="closeDriverDrawer()"></div>

        <div class="absolute inset-y-0 right-0 max-w-full flex">
            <div class="w-screen max-w-full sm:max-w-[420px] bg-white border-l border-slate-200 shadow-2xl flex flex-col justify-between p-5 overflow-y-auto transform translate-x-full transition-transform duration-300 ease-in-out relative" id="driver-drawer-content">

                <button type="button" onclick="closeDriverDrawer()" class="absolute top-4 right-4 text-slate-400 hover:text-slate-600 transition-colors">
                    <i class="ti ti-x text-[20px]"></i>
                </button>

                <div class="space-y-5">
                    <!-- Header Section -->
                    <div class="flex items-center gap-4 animate-pulse-placeholder" id="drawer-header-section"></div>

                    <div class="border-t border-slate-100"></div>

                    <!-- Skeleton loader -->
                    <div id="drawer-loading-skeleton" class="hidden space-y-4 w-full">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="h-14 bg-slate-100 animate-pulse rounded"></div>
                            <div class="h-14 bg-slate-100 animate-pulse rounded"></div>
                            <div class="h-14 bg-slate-100 animate-pulse rounded"></div>
                            <div class="h-14 bg-slate-100 animate-pulse rounded"></div>
                        </div>
                        <div class="h-20 bg-slate-100 animate-pulse rounded"></div>
                    </div>

                    <!-- Main details content -->
                    <div id="drawer-details-body" class="space-y-5"></div>
                </div>

            </div>
        </div>
    </div>
</div>

    <script>
        // Load initial data state to protect from layout redraw lag
        window.GoPasigDriversInitialData = @json($driverLogs);
    </script>

</section>
