<section id="screen-buses" class="hidden space-y-8"
    style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-tertiary:#E8E6DF;--color-border-secondary:#D6D3C9;">

    <!-- LIST CONTAINER -->
    <div id="buses-list-container" class="space-y-8">
        {{-- PAGE HEADER ROW --}}
        <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 shrink-0">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-xl font-bold text-slate-900">Bus Management</h1>
                    <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
                        <span>Dashboard</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Fleet</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span id="buses-breadcrumb-current" class="text-slate-600 font-bold">Bus Management</span>
                    </div>
                    <p class="text-[11px] text-slate-550 font-semibold mt-1" id="bm-buses-registered-label">0 registered municipal buses in Pasig Libreng Sakay Fleet</p>
                </div>
                <div>
                    <button onclick="openAddBusModal('add'); return false;"
                        class="bm-btn-primary flex items-center gap-2 border-none">
                        <i class="ti ti-plus"></i> Add bus registration
                    </button>
                </div>
            </div>
        </div>

        <!-- Primary Fleet Status Cards (4 Columns) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <!-- Card 1: Total Fleet -->
            <div onclick="toggleBusCardFilter('all', this)" class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer" data-bus-card-filter="all">
                <div class="flex justify-between items-start">
                    <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Total Fleet</span>
                    <div class="h-6 w-6 rounded bg-slate-100 flex items-center justify-center text-slate-700">
                        <i class="ti ti-bus text-sm"></i>
                    </div>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-[20px] font-black text-slate-900 leading-none" id="bm-stat-total">0</span>
                    <span class="text-[9px] text-slate-500 font-semibold truncate">All registered assets</span>
                </div>
            </div>

            <!-- Card 2: Active / On Road -->
            <div onclick="toggleBusCardFilter('active', this)" class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer border-l-[3px] border-l-[#639922]" data-bus-card-filter="active">
                <div class="flex justify-between items-start">
                    <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Active</span>
                    <div class="h-6 w-6 rounded bg-emerald-50 flex items-center justify-center text-[#639922]">
                        <i class="ti ti-route text-sm"></i>
                    </div>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-[20px] font-black text-slate-900 leading-none" id="bm-stat-active">0</span>
                    <span class="text-[9px] text-slate-500 font-semibold truncate">Currently on trips</span>
                </div>
            </div>

            <!-- Card 3: Standby -->
            <div onclick="toggleBusCardFilter('inactive', this)" class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer border-l-[3px] border-l-[#003F87]" data-bus-card-filter="inactive">
                <div class="flex justify-between items-start">
                    <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Standby</span>
                    <div class="h-6 w-6 rounded bg-blue-50 flex items-center justify-center text-[#003F87]">
                        <i class="ti ti-player-pause text-sm"></i>
                    </div>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-[20px] font-black text-slate-900 leading-none" id="bm-stat-inactive">0</span>
                    <span class="text-[9px] text-slate-500 font-semibold truncate">Available for service</span>
                </div>
            </div>

            <!-- Card 4: Maintenance -->
            <div onclick="toggleBusCardFilter('maintenance', this)" class="relative bg-white border border-slate-200 rounded-xl p-4 flex flex-col justify-between h-[92px] shadow-sm hover:shadow-md transition-all duration-200 hover:-translate-y-0.5 cursor-pointer border-l-[3px] border-l-[#BA7517]" data-bus-card-filter="maintenance">
                <div class="flex justify-between items-start">
                    <span class="text-[10px] text-slate-400 font-extrabold uppercase tracking-widest truncate">Maintenance</span>
                    <div class="h-6 w-6 rounded bg-amber-50 flex items-center justify-center text-[#BA7517]">
                        <i class="ti ti-tool text-sm"></i>
                    </div>
                </div>
                <div class="mt-1 flex items-baseline gap-1.5">
                    <span class="text-[20px] font-black text-slate-900 leading-none" id="bm-stat-maintenance">0</span>
                    <span class="text-[9px] text-slate-500 font-semibold truncate">In repair facility</span>
                </div>
            </div>
        </div>

        <!-- Fleet Health Indicators (Secondary Row, Compact cards) -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
            <!-- Indicator 1: Breakdown -->
            <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[72px] shadow-sm border-l-[3px] border-l-[#E24B4A]">
                <div class="flex justify-between items-center">
                    <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Breakdown</span>
                    <div class="h-5 w-5 rounded bg-rose-50 flex items-center justify-center text-[#E24B4A]">
                        <i class="ti ti-alert-triangle text-xs"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-[16px] font-black text-slate-800 leading-none" id="bm-health-breakdown">0</span>
                    <span class="text-[9px] text-slate-450 font-medium truncate">Unserviceable alerts</span>
                </div>
            </div>

            <!-- Indicator 2: Under Observation -->
            <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[72px] shadow-sm border-l-[3px] border-l-slate-400">
                <div class="flex justify-between items-center">
                    <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Under Observation</span>
                    <div class="h-5 w-5 rounded bg-slate-200 flex items-center justify-center text-slate-600">
                        <i class="ti ti-eye text-xs"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-[16px] font-black text-slate-800 leading-none" id="bm-health-observation">0</span>
                    <span class="text-[9px] text-slate-450 font-medium truncate">Monitored assets</span>
                </div>
            </div>

            <!-- Indicator 3: Assigned Buses -->
            <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[72px] shadow-sm border-l-[3px] border-l-[#639922]">
                <div class="flex justify-between items-center">
                    <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Assigned Buses</span>
                    <div class="h-5 w-5 rounded bg-emerald-50 flex items-center justify-center text-[#639922]">
                        <i class="ti ti-circle-check text-xs"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-[16px] font-black text-slate-800 leading-none" id="bm-health-assigned">0</span>
                    <span class="text-[9px] text-slate-450 font-medium truncate">Active operational slots</span>
                </div>
            </div>

            <!-- Indicator 4: Available for Dispatch -->
            <div class="relative bg-slate-50 border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[72px] shadow-sm border-l-[3px] border-l-[#003F87]">
                <div class="flex justify-between items-center">
                    <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Available for Dispatch</span>
                    <div class="h-5 w-5 rounded bg-blue-50 flex items-center justify-center text-[#003F87]">
                        <i class="ti ti-send text-xs"></i>
                    </div>
                </div>
                <div class="flex items-baseline gap-1.5">
                    <span class="text-[16px] font-black text-slate-800 leading-none" id="bm-health-dispatch">0</span>
                    <span class="text-[9px] text-slate-450 font-medium truncate">Standby ready assets</span>
                </div>
            </div>
        </div>

        {{-- SEARCH & UTILITY TOOLBAR --}}
        <div class="flex flex-col md:flex-row md:items-center gap-4 w-full bg-white p-4 border border-slate-200 rounded-xl shadow-sm select-none">
            <!-- Left Region: Search input (only flexible element) -->
            <div class="relative flex-1 min-w-0">
                <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-base"></i>
                <input id="bus-search" type="text" 
                       class="w-full pl-10 pr-4 py-2.5 border border-slate-200 rounded-lg text-sm text-slate-800 focus:outline-none focus:border-[#003F87] focus:ring-1 focus:ring-[#003F87] transition-all" 
                       placeholder="Search buses by plate number, driver, or route..."
                       oninput="searchBusesTable()">
            </div>
            
            <!-- Center Region: Information chips -->
            <div class="flex items-center gap-3 whitespace-nowrap shrink-0 text-xs font-semibold text-slate-500">
                <div class="flex items-center gap-1 text-slate-400 select-none px-1">
                    <span>Last updated: <span id="bm-last-updated" class="font-mono text-slate-655 font-bold">Just now</span></span>
                </div>
            </div>

            <!-- Right Region: Action buttons -->
            <div class="flex items-center gap-2 shrink-0">
                <button onclick="loadDatabaseFleetData().then(fetchBuses); return false;" class="flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold transition cursor-pointer shadow-sm">
                    <i class="ti ti-refresh text-slate-550"></i> Refresh
                </button>
                <button onclick="exportBusesCSV(); return false;" class="flex items-center justify-center gap-1.5 px-3.5 py-2 rounded-lg border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-bold transition cursor-pointer shadow-sm">
                    <i class="ti ti-download text-slate-550"></i> Export CSV
                </button>
            </div>
        </div>

        <!-- Table Title and Showing Counter -->
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 select-none">Bus Fleet Listing</h3>
            <span id="bm-showing-count" class="text-xs font-semibold text-slate-500">Showing 0 of 0 buses</span>
        </div>

        {{-- MAIN TABLE CARD --}}
        <div class="bm-table-card">
            <div class="overflow-x-auto w-full">
                <table class="bm-table">
                    <thead>
                        <tr class="bm-thead-row">
                            <th class="bm-th">Plate Number</th>
                            <th class="bm-th">Assigned Route</th>
                            <th class="bm-th">Assigned Driver</th>
                            <th class="bm-th text-center" style="text-align:center;">Capacity</th>
                            <th class="bm-th text-center" style="text-align:center;">Pax Boarded</th>
                            <th class="bm-th text-center" style="text-align:center;">Speed</th>
                            <th class="bm-th">Next Stop</th>
                            <th class="bm-th">Status</th>
                            <th class="bm-th text-right pr-6">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="buses-tbody">
                        {{-- Populated dynamically by buses.js --}}
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination Controls -->
        <div id="buses-pagination" class="flex justify-end items-center gap-1.5 mt-4 select-none"></div>
    </div>

    <!-- FORM CONTAINER -->
    <div id="buses-form-container" class="hidden space-y-6">
        <!-- BREADCRUMB & HEADER -->
        <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 shrink-0">
            <div class="flex items-center gap-4">
                <button onclick="closeAddBusModal(); return false;"
                    class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 border-none"
                    title="Back to Bus Management">
                    <i class="ti ti-arrow-left text-lg"></i>
                </button>
                <div>
                    <h1 class="text-xl font-bold text-slate-900 tracking-tight" id="add-bus-modal-title">Register
                        Electric Bus</h1>
                    <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                        <span>Dashboard</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Fleet</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Bus Management</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span class="text-[#003F87] font-bold" id="buses-breadcrumb-current-sub">Register Electric Bus</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- FORM CARD -->
        <div
            class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] hover:shadow-[0_4px_12px_rgba(0,0,0,0.06)] transition-all duration-300 animate-fade-in max-w-4xl">
            <div class="mb-6">
                <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1" id="add-bus-modal-title-sec">
                    Register Electric Bus</h2>
                <p class="text-xs text-slate-500" id="add-bus-modal-desc">Register a new electric municipal bus asset. Operational assignments are configured after registration.</p>
            </div>

            <form id="add-bus-form" onsubmit="handleBusSubmit(event)" class="space-y-6">
                <input type="hidden" id="edit-bus-id" value="">
                
                <!-- BUS IDENTIFICATION SECTION -->
                <div class="border-b border-slate-100 pb-5">
                    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4">Bus Identification</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Bus Plate No -->
                        <div class="space-y-2">
                            <label for="new-bus-plate" class="text-xs font-bold uppercase tracking-wider text-slate-500">Plate Number</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i class="ti ti-id text-base"></i>
                                </span>
                                <input id="new-bus-plate" name="plate_number" type="text" placeholder="e.g. PAS-439" required
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">Unique plate designation code.</p>
                        </div>

                        <!-- Fleet Number -->
                        <div class="space-y-2">
                            <label for="new-bus-fleet-number" class="text-xs font-bold uppercase tracking-wider text-slate-500">Fleet Number</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i class="ti ti-hash text-base"></i>
                                </span>
                                <input id="new-bus-fleet-number" name="fleet_number" type="text" placeholder="e.g. BUS-001" required
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">Unique fleet identifier (e.g. BUS-001).</p>
                        </div>

                        <!-- VIN / Chassis Number -->
                        <div class="space-y-2">
                            <label for="new-bus-vin" class="text-xs font-bold uppercase tracking-wider text-slate-500">VIN / Chassis Number</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i class="ti ti-fingerprint text-base"></i>
                                </span>
                                <input id="new-bus-vin" name="vin" type="text" placeholder="17-character VIN" required
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">Immutable 17-character vehicle code.</p>
                        </div>
                    </div>
                </div>

                <!-- VEHICLE INFORMATION SECTION -->
                <div class="border-b border-slate-100 pb-5">
                    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4">Vehicle Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Manufacturer Dropdown -->
                        <div class="space-y-2">
                            <label for="new-bus-manufacturer-select" class="text-xs font-bold uppercase tracking-wider text-slate-500">Manufacturer</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                                    <i class="ti ti-building text-base"></i>
                                </span>
                                <select id="new-bus-manufacturer-select" onchange="toggleCustomManufacturer(this)" required
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                    <option value="BYD">BYD</option>
                                    <option value="Yutong">Yutong</option>
                                    <option value="Higer">Higer</option>
                                    <option value="Golden Dragon">Golden Dragon</option>
                                    <option value="Ankai">Ankai</option>
                                    <option value="King Long">King Long</option>
                                    <option value="Others">Others (Specify below)</option>
                                </select>
                                <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                    <i class="ti ti-chevron-down text-sm"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Custom Manufacturer (Hidden initially) -->
                        <div class="space-y-2 hidden" id="bm-manufacturer-custom-wrapper">
                            <label for="new-bus-manufacturer-custom" class="text-xs font-bold uppercase tracking-wider text-slate-500">Specify Manufacturer</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i class="ti ti-edit text-base"></i>
                                </span>
                                <input id="new-bus-manufacturer-custom" type="text" placeholder="e.g. Hyundai"
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                            </div>
                        </div>

                        <!-- Model -->
                        <div class="space-y-2">
                            <label for="new-bus-model" class="text-xs font-bold uppercase tracking-wider text-slate-500">Model</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i class="ti ti-truck text-base"></i>
                                </span>
                                <input id="new-bus-model" name="model" type="text" placeholder="e.g. K9" required
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">Model or chassis series designation.</p>
                        </div>

                        <!-- Year Model -->
                        <div class="space-y-2">
                            <label for="new-bus-year-model" class="text-xs font-bold uppercase tracking-wider text-slate-500">Year Model</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i class="ti ti-calendar text-base"></i>
                                </span>
                                <input id="new-bus-year-model" name="year_model" type="number" placeholder="e.g. 2024" required
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">Production or release model year.</p>
                        </div>

                        <!-- Seating Capacity -->
                        <div class="space-y-2">
                            <label for="new-bus-capacity" class="text-xs font-bold uppercase tracking-wider text-slate-500">Seating Capacity</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i class="ti ti-users text-base"></i>
                                </span>
                                <input id="new-bus-capacity" name="capacity" type="number" placeholder="e.g. 45" value="45"
                                    min="{{ \App\Models\SystemSetting::get('bus_capacity_min', 10) }}" max="{{ \App\Models\SystemSetting::get('bus_capacity_max', 150) }}" required
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">Passenger limit of this bus unit (minimum 10, maximum 150).</p>
                        </div>
                    </div>
                </div>

                <!-- ELECTRIC POWERTRAIN SECTION -->
                <div class="border-b border-slate-100 pb-5">
                    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4">Electric Powertrain</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Battery Capacity -->
                        <div class="space-y-2">
                            <label for="new-bus-battery-capacity" class="text-xs font-bold uppercase tracking-wider text-slate-500">Battery Capacity (kWh)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i class="ti ti-bolt text-base"></i>
                                </span>
                                <input id="new-bus-battery-capacity" name="battery_capacity_kwh" type="number" step="0.01" placeholder="e.g. 350.50" required
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">Battery rating (10 - 1000 kWh).</p>
                        </div>

                        <!-- Maximum Charging Power -->
                        <div class="space-y-2">
                            <label for="new-bus-max-charging-power" class="text-xs font-bold uppercase tracking-wider text-slate-500">Max Charging Power (kW)</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                    <i class="ti ti-plug text-base"></i>
                                </span>
                                <input id="new-bus-max-charging-power" name="max_charging_power_kw" type="number" step="0.01" placeholder="e.g. 150.00" required
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87]">
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">Maximum input rate (10 - 500 kW).</p>
                        </div>

                        <!-- Charging Port Type -->
                        <div class="space-y-2">
                            <label for="new-bus-charging-port" class="text-xs font-bold uppercase tracking-wider text-slate-500">Charging Port Type</label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-3 flex items-center text-slate-400 pointer-events-none">
                                    <i class="ti ti-socket text-base"></i>
                                </span>
                                <select id="new-bus-charging-port" name="charging_port_type" required
                                    class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                    <option value="CCS2">CCS2</option>
                                    <option value="GB/T">GB/T</option>
                                    <option value="CHAdeMO">CHAdeMO</option>
                                </select>
                                <span class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                    <i class="ti ti-chevron-down text-sm"></i>
                                </span>
                            </div>
                            <p class="text-[10px] text-slate-400 font-medium">Standard charging plug model.</p>
                        </div>
                    </div>
                </div>

                <!-- OPERATIONAL STATUS SECTION -->
                <div class="space-y-2">
                    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-2">Operational Status</h3>
                    
                    <!-- Status (Edit Mode) -->
                    <div class="space-y-2 md:col-span-2 hidden" id="bm-status-select-wrapper">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center text-slate-400">
                                <i class="ti ti-activity text-base"></i>
                            </span>
                            <select id="new-bus-status" name="status"
                                class="w-full rounded-lg border border-slate-200 bg-slate-50 py-2.5 pl-10 pr-10 text-xs font-semibold text-slate-900 outline-none transition duration-200 focus:border-[#003F87] focus:bg-white focus:ring-1 focus:ring-[#003F87] appearance-none cursor-pointer">
                                <option value="inactive">Standby (Inactive)</option>
                                <option value="maintenance">Maintenance (Undergoing repairs)</option>
                                <option value="breakdown">Breakdown (Emergency breakdown)</option>
                            </select>
                            <span
                                class="absolute inset-y-0 right-3 flex items-center pointer-events-none text-slate-400">
                                <i class="ti ti-chevron-down text-sm"></i>
                            </span>
                        </div>
                        <p class="text-[10px] text-slate-400 font-medium">Current operational status determining its
                            visibility in commuter apps.</p>
                    </div>

                    <!-- Operational Status Note (Add Mode) -->
                    <div class="rounded-lg border border-slate-100 bg-slate-50 p-3.5 flex items-start gap-3 text-left" id="bm-status-note-wrapper">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500 mt-0.5">
                            <i class="ti ti-info-circle text-base"></i>
                        </span>
                        <p class="text-xs text-slate-600 font-semibold m-0 leading-relaxed">
                            Newly registered buses are automatically placed in Standby (Inactive) status. Driver assignment, route assignment, GPS configuration, and live operational telemetry are configured after registration through the Dispatch workflow.
                        </p>
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                    <button type="button" id="bus-cancel-btn" onclick="closeAddBusModal(); return false;"
                        class="rounded-lg bg-slate-100 px-5 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer border-none">
                        Cancel
                    </button>
                    <button type="submit" id="bus-submit-btn"
                        class="rounded-lg bg-[#003F87] px-6 py-2.5 text-xs font-extrabold text-white hover:bg-[#002D62] transition duration-200 shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none">
                        Register Bus
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

{{-- ==================== SCOPED CSS FOR BUS MANAGEMENT ==================== --}}
<style>
    #screen-buses {
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    }

    /* ── Page Header ── */
    .bm-page-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .bm-h1 {
        font-size: 20px;
        font-weight: 500;
        color: var(--color-text-primary);
        margin: 0;
        line-height: 1.3;
    }

    .bm-subtitle {
        font-size: 13px;
        color: var(--color-text-secondary);
        margin: 2px 0 0;
    }

    .bm-page-header-right {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* ── Buttons ── */
    .bm-btn-primary {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        height: 36px;
        padding: 0 14px;
        background: #003F87;
        color: #ffffff;
        border: none;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.15s;
        white-space: nowrap;
    }

    .bm-btn-primary:hover {
        background: #002d62;
    }

    .bm-icon-btn {
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: 0.5px solid var(--color-border-tertiary);
        background: var(--color-background-primary);
        color: var(--color-text-secondary);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 15px;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .bm-icon-btn:hover {
        background: var(--color-background-secondary);
        color: #003F87;
        border-color: #003F87;
        transform: translateY(-1px);
    }

    .bm-icon-btn--danger:hover {
        background: #FCEBEB;
        color: #A32D2D;
        border-color: #F09595;
    }

    /* ── Stats Strip ── */
    .bm-stats-strip {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 12px;
        margin-bottom: 16px;
    }

    .bm-stat-card {
        display: flex;
        flex-direction: column;
        gap: 4px;
        background: var(--color-background-secondary);
        border-radius: 8px;
        padding: 14px 16px;
    }

    .bm-stat-label {
        font-size: 12px;
        color: var(--color-text-secondary);
        font-weight: 500;
    }

    .bm-stat-value {
        font-size: 22px;
        font-weight: 500;
        line-height: 1.2;
    }

    /* ── Filter Bar ── */
    .bm-filter-bar {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 16px;
        flex-wrap: wrap;
    }

    .bm-search-wrapper {
        position: relative;
        width: 220px;
        flex-shrink: 0;
    }

    .bm-search-icon {
        position: absolute;
        left: 9px;
        top: 50%;
        transform: translateY(-50%);
        color: var(--color-text-secondary);
        font-size: 14px;
        pointer-events: none;
    }

    .bm-search-input {
        width: 100%;
        height: 34px;
        padding: 0 10px 0 30px;
        border: 0.5px solid var(--color-border-secondary);
        border-radius: 8px;
        font-size: 12px;
        color: var(--color-text-primary);
        background: var(--color-background-primary);
        outline: none;
        transition: border-color 0.15s;
        box-sizing: border-box;
    }

    .bm-search-input:focus {
        border-color: #003F87;
    }

    .bm-filter-btn {
        height: 34px;
        padding: 0 12px;
        border-radius: 8px;
        border: 0.5px solid var(--color-border-secondary);
        background: var(--color-background-secondary);
        color: var(--color-text-secondary);
        font-size: 12px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
    }

    .bm-filter-btn.active {
        background: #003F87;
        color: #ffffff;
        border-color: #003F87;
    }

    .bm-count-label {
        font-size: 12px;
        color: var(--color-text-secondary);
        margin-left: auto;
    }

    /* ── Table Card ── */
    .bm-table-card {
        background: var(--color-background-primary);
        border: 0.5px solid var(--color-border-tertiary);
        border-radius: 12px;
        overflow: hidden;
    }

    .bm-table {
        width: 100%;
        border-collapse: collapse;
    }

    .bm-thead-row {
        background: var(--color-background-secondary);
    }

    .bm-th {
        padding: 10px 14px;
        font-size: 11px;
        font-weight: 500;
        color: var(--color-text-secondary);
        text-transform: uppercase;
        letter-spacing: 0.04em;
        border-bottom: 0.5px solid var(--color-border-tertiary);
        text-align: left;
        white-space: nowrap;
    }

    .bm-td {
        padding: 11px 14px;
        font-size: 13px;
        color: var(--color-text-primary);
        vertical-align: middle;
        border-bottom: 0.5px solid var(--color-border-tertiary);
    }

    .bm-tbody-row {
        transition: background 0.1s;
    }

    .bm-tbody-row:hover {
        background: #EEF3FF;
    }

    .bm-tbody-row:last-child .bm-td {
        border-bottom: none;
    }

    /* Custom Badges */
    .bm-route-pill {
        display: inline-block;
        padding: 2.5px 8px;
        border-radius: 99px;
        font-size: 11px;
        font-weight: 600;
    }

    .bm-route-1 {
        background: #E6F1FB;
        color: #0C447C;
    }

    .bm-route-2 {
        background: #EAF3DE;
        color: #3B6D11;
    }

    .bm-route-3 {
        background: #FAEEDA;
        color: #854F0B;
    }

    .bm-route-4 {
        background: #FCEBEB;
        color: #A32D2D;
    }

    .bm-route-none {
        background: #F1EFE8;
        color: #5F5E5A;
    }

    /* Transitions & Animations */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .animate-fade-in {
        animation: fadeIn 0.25s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }

    /* Floating row dropdown menu actions container */
    .bm-dropdown-menu {
        position: absolute;
        right: 1.5rem;
        top: 2.25rem;
        z-index: 50;
        min-width: 140px;
        background-color: #ffffff;
        border: 1px solid #D6D3C9;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        padding: 4px 0;
        animation: dropdownFadeIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    }
    @keyframes dropdownFadeIn {
        from {
            opacity: 0;
            transform: scale(0.95) translateY(-5px);
        }
        to {
            opacity: 1;
            transform: scale(1) translateY(0);
        }
    }
    .bm-dropdown-item {
        display: flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        padding: 8px 12px;
        font-size: 11px;
        font-weight: 700;
        color: #1A1917;
        text-align: left;
        transition: background 0.1s ease;
        text-decoration: none;
    }
    .bm-dropdown-item:hover {
        background-color: #F8F7F4;
    }
    .bm-dropdown-item i {
        font-size: 13px;
    }
    .bm-dropdown-divider {
        height: 1px;
        background-color: #E8E6DF;
        margin: 4px 0;
    }

    /* --- Pagination Controls --- */
    .bm-page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 32px;
        min-width: 32px;
        padding: 0 8px;
        border: 1px solid #D6D3C9;
        border-radius: 6px;
        background: #ffffff;
        color: #5F5E5A;
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.15s ease;
    }
    .bm-page-btn:hover:not(:disabled) {
        background: #F8F7F4;
        color: #1A1917;
        border-color: #A8A6A0;
    }
    .bm-page-btn.active {
        background: #003F87;
        color: #ffffff;
        border-color: #003F87;
    }
    .bm-page-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }
</style>