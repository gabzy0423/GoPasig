<section id="screen-map-view" class="hidden space-y-5 animate-fade-in">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Live Fleet Tracker</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span>Fleet</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Live Fleet Map</span>
        </div>
    </div>

                    <!-- Top Map Filters & Controls Bar -->
                    <div class="rounded-xl border border-[#E0E0E0] bg-white p-3 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between shrink-0 select-none">
                        <!-- Left: Search & Route Pills -->
                        <div class="flex flex-wrap items-center gap-3 flex-1 min-w-0">
                            <!-- Universal Search Input -->
                            <div class="relative w-full sm:w-[220px]">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400 pointer-events-none">
                                    <i class="ti ti-search text-xs"></i>
                                </span>
                                <input type="text" id="universal-search" oninput="applyToolbarFilters()" placeholder="Search plate, driver, or route..." class="w-full pl-8 pr-3 py-1.5 rounded-lg border border-slate-200 bg-slate-50 text-xs font-semibold text-slate-900 placeholder-slate-400 outline-none transition focus:border-[#003F87] focus:bg-white">
                            </div>

                            <!-- Route Pills (Horizontally Scrollable Container) -->
                            <div class="flex items-center gap-1.5 overflow-x-auto whitespace-nowrap scrollbar-none py-1 flex-1">
                                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 mr-1 shrink-0">Routes:</span>
                                <button onclick="toggleRouteFilter('all')" data-route-filter="all" class="rounded-full bg-[#003F87] px-3 py-1 text-xs font-bold text-white transition cursor-pointer shrink-0">All <span id="route-pill-all-count"></span></button>
                                <button onclick="toggleRouteFilter('1')" data-route-filter="1" class="rounded-full bg-slate-50 border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer shrink-0">Route 1 <span id="route-pill-1-count"></span></button>
                                <button onclick="toggleRouteFilter('2')" data-route-filter="2" class="rounded-full bg-slate-50 border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer shrink-0">Route 2 <span id="route-pill-2-count"></span></button>
                                <button onclick="toggleRouteFilter('3')" data-route-filter="3" class="rounded-full bg-slate-50 border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer shrink-0">Route 3 <span id="route-pill-3-count"></span></button>
                                <button onclick="toggleRouteFilter('4')" data-route-filter="4" class="rounded-full bg-slate-50 border border-slate-200 px-3 py-1 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer shrink-0">Route 4 <span id="route-pill-4-count"></span></button>
                            </div>
                        </div>

                        <!-- Right: Status Dropdown, Pulse LIVE box, Refresh button -->
                        <div class="flex flex-wrap items-center gap-3 justify-between sm:justify-end shrink-0">
                            <!-- Status Dropdown -->
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Status:</span>
                                <select id="map-status-filter" onchange="applyToolbarFilters()" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                                    <option value="all">All Statuses</option>
                                    <option value="Active">Active</option>
                                    <option value="Delayed">Delayed</option>
                                    <option value="Breakdown">Offline / Alert</option>
                                    <option value="Maintenance">Maintenance</option>
                                </select>
                            </div>
                            
                            <!-- Pulse LIVE Status box -->
                            <div class="flex items-center gap-2 rounded-lg bg-emerald-50 border border-emerald-100 px-3 py-1.5 shadow-sm">
                                <span class="relative flex h-2 w-2 shrink-0">
                                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#639922] opacity-75"></span>
                                    <span class="relative inline-flex rounded-full h-2 w-2 bg-[#639922]"></span>
                                </span>
                                <span class="text-[10px] font-bold text-slate-600">
                                    <span class="font-extrabold text-[#639922] tracking-wider uppercase mr-1">LIVE</span> • Auto-refresh: <span id="map-last-updated" class="font-bold">Just now</span> (5s interval)
                                </span>
                            </div>
                            
                            <!-- Refresh Action -->
                            <div class="flex items-center border-l border-slate-200 pl-3">
                                <button onclick="triggerManualRefresh()" class="relative rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-[#003F87] transition cursor-pointer" aria-label="Refresh positions">
                                    <i id="map-refresh-icon" class="ti ti-refresh text-lg"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Map Split Two-Column Layout -->
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-10">
                        <!-- Left Map Canvas (70%) -->
                        <div class="lg:col-span-7 rounded-xl border border-[#E0E0E0] bg-white overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.06)] relative h-[560px]" id="live-map-wrapper">
                            <div id="live-map-canvas" class="h-full w-full z-10"></div>
                            
                            <!-- Floating Map Legend -->
                            <div class="absolute bottom-4 left-4 bg-white/95 backdrop-blur-sm border border-slate-200 rounded-lg p-2.5 shadow-md z-[1000] space-y-1.5 text-[10px] font-bold text-slate-700 min-w-[125px] select-none pointer-events-none">
                                <div class="text-[9px] uppercase tracking-wider text-slate-400 font-extrabold mb-1">Status Legend</div>
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-full bg-[#639922] inline-block"></span>
                                    <span>Active</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-full bg-[#003F87] inline-block"></span>
                                    <span>Standby</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-full bg-[#BA7517] inline-block"></span>
                                    <span>Maintenance</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <span class="h-2.5 w-2.5 rounded-full bg-[#E24B4A] inline-block"></span>
                                    <span>Breakdown</span>
                                </div>
                                <div class="border-t border-slate-100 my-1 pt-1"></div>
                                <div class="flex items-center gap-1.5">
                                    <span class="w-4 h-0.5 bg-[#003F87] inline-block"></span>
                                    <span>Route Line</span>
                                </div>
                                <div class="flex items-center gap-1.5">
                                    <i class="ti ti-map-pin text-[#003F87] text-xs"></i>
                                    <span>Bus Stop</span>
                                </div>
                            </div>

                            <!-- Custom Leaflet & Dashboard Styles -->
                            <style>
                                .leaflet-container {
                                    font-family: inherit !important;
                                }
                                .leaflet-popup-content-wrapper {
                                    border-radius: 12px !important;
                                    border: 0.5px solid #E0E0E0 !important;
                                    box-shadow: 0 4px 12px rgba(0,0,0,0.05) !important;
                                    padding: 0px !important;
                                }
                                .leaflet-popup-content {
                                    margin: 12px !important;
                                }
                                .leaflet-bar {
                                    border: 0.5px solid #E0E0E0 !important;
                                    box-shadow: 0 1px 3px rgba(0,0,0,0.06) !important;
                                }
                                .custom-bus-marker-icon {
                                    background: none !important;
                                    border: none !important;
                                }
                                /* Hide default scrollbar for route pills container */
                                .scrollbar-none::-webkit-scrollbar {
                                    display: none;
                                }
                                .scrollbar-none {
                                    -ms-overflow-style: none;
                                    scrollbar-width: none;
                                }
                                /* Faded style for route pills with zero vehicle count */
                                .pill-count-zero {
                                    opacity: 0.55;
                                }
                                /* ── Buttons ── */
                                .bm-btn-primary {
                                    display: inline-flex;
                                    align-items: center;
                                    justify-content: center;
                                    gap: 6px;
                                    height: 36px;
                                    padding: 0 14px;
                                    background: #003F87;
                                    color: #ffffff;
                                    border: none;
                                    border-radius: 8px;
                                    font-size: 12px;
                                    font-weight: bold;
                                    cursor: pointer;
                                    transition: background 0.15s, transform 0.1s;
                                    white-space: nowrap;
                                }
                                .bm-btn-primary:hover {
                                    background: #002d62;
                                }
                                .bm-btn-primary:active {
                                    transform: scale(0.97);
                                }
                            </style>
                        </div>

                        <!-- Right Fleet Sidebar Panel (30%) -->
                        <div class="lg:col-span-3 rounded-xl border border-[#E0E0E0] bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[560px]">
                            <!-- Sidebar Header -->
                            <div class="border-b border-slate-100 pb-3 flex items-center justify-between shrink-0 select-none">
                                <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800">Fleet Operations</span>
                                <span class="inline-flex rounded-full bg-[#E6F1FB] px-2.5 py-0.5 text-[9px] font-bold text-[#003F87] uppercase tracking-wider" id="sidebar-tracked-count">12 Buses Tracked</span>
                            </div>

                            <!-- Scrollable Content wrapper for all sections -->
                            <div class="flex-1 overflow-y-auto space-y-4 pr-1 mt-3 scrollbar-thin scrollbar-thumb-slate-200">
                                
                                <!-- Section 1: Fleet Overview -->
                                <div class="space-y-2 select-none">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Fleet Overview</span>
                                    <div class="grid grid-cols-2 gap-2">
                                        <!-- Total Fleet (Full Width) -->
                                        <div class="col-span-2 relative bg-white border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[76px] shadow-sm border-l-[3px] border-l-slate-400">
                                            <div class="flex justify-between items-start leading-none">
                                                <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Total Fleet</span>
                                                <div class="h-5 w-5 rounded bg-slate-50 flex items-center justify-center text-slate-700">
                                                    <i class="ti ti-bus text-xs"></i>
                                                </div>
                                            </div>
                                            <div class="flex justify-between items-baseline mt-1 leading-none">
                                                <span class="text-lg font-black text-slate-900 leading-none" id="stats-total-fleet">0</span>
                                                <span class="text-[8px] text-slate-500 font-semibold truncate">Registered buses</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Active Fleet -->
                                        <div class="relative bg-white border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[76px] shadow-sm border-l-[3px] border-l-[#639922]">
                                            <div class="flex justify-between items-start leading-none">
                                                <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Active</span>
                                                <div class="h-5 w-5 rounded bg-emerald-50 flex items-center justify-center text-[#639922]">
                                                    <i class="ti ti-route text-xs"></i>
                                                </div>
                                            </div>
                                            <div class="flex justify-between items-baseline mt-1 leading-none">
                                                <span class="text-lg font-black text-slate-900 leading-none" id="stats-active">0</span>
                                                <span class="text-[8px] text-slate-500 font-semibold truncate">On road</span>
                                            </div>
                                        </div>
                                        
                                        <!-- Standby Fleet -->
                                        <div class="relative bg-white border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[76px] shadow-sm border-l-[3px] border-l-[#003F87]">
                                            <div class="flex justify-between items-start leading-none">
                                                <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Standby</span>
                                                <div class="h-5 w-5 rounded bg-blue-50 flex items-center justify-center text-[#003F87]">
                                                    <i class="ti ti-player-pause text-xs"></i>
                                                </div>
                                            </div>
                                            <div class="flex justify-between items-baseline mt-1 leading-none">
                                                <span class="text-lg font-black text-slate-900 leading-none" id="stats-standby">0</span>
                                                <span class="text-[8px] text-slate-500 font-semibold truncate">Ready for dispatch</span>
                                            </div>
                                        </div>

                                        <!-- Maintenance -->
                                        <div class="relative bg-white border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[76px] shadow-sm border-l-[3px] border-l-[#BA7517]">
                                            <div class="flex justify-between items-start leading-none">
                                                <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Maintenance</span>
                                                <div class="h-5 w-5 rounded bg-amber-50 flex items-center justify-center text-[#BA7517]">
                                                    <i class="ti ti-tool text-xs"></i>
                                                </div>
                                            </div>
                                            <div class="flex justify-between items-baseline mt-1 leading-none">
                                                <span class="text-lg font-black text-slate-900 leading-none" id="stats-maintenance">0</span>
                                                <span class="text-[8px] text-slate-500 font-semibold truncate">Unavailable</span>
                                            </div>
                                        </div>

                                        <!-- Breakdown -->
                                        <div class="relative bg-white border border-slate-200 rounded-xl p-3 flex flex-col justify-between h-[76px] shadow-sm border-l-[3px] border-l-[#E24B4A]">
                                            <div class="flex justify-between items-start leading-none">
                                                <span class="text-[9px] text-slate-450 font-bold uppercase tracking-wider truncate">Breakdown</span>
                                                <div class="h-5 w-5 rounded bg-rose-50 flex items-center justify-center text-[#E24B4A]">
                                                    <i class="ti ti-alert-triangle text-xs"></i>
                                                </div>
                                            </div>
                                            <div class="flex justify-between items-baseline mt-1 leading-none">
                                                <span class="text-lg font-black text-slate-900 leading-none" id="stats-breakdown">0</span>
                                                <span class="text-[8px] text-slate-500 font-semibold truncate">Offline alert</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Section 2: Active Vehicles -->
                                <div class="space-y-2">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 select-none">Active Vehicles</span>
                                    <div class="max-h-[160px] overflow-y-auto space-y-2 pr-0.5 scrollbar-thin scrollbar-thumb-slate-200" id="fleet-sidebar-list">
                                        <!-- Rendered dynamically by javascript -->
                                    </div>
                                </div>

                                <!-- Section 3: Recent Activity -->
                                <div class="space-y-2 border-t border-slate-100 pt-3">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 select-none">Recent Activity</span>
                                    <div class="max-h-[110px] overflow-y-auto pr-0.5 scrollbar-thin scrollbar-thumb-slate-200 font-semibold" id="recent-activity-list">
                                        <!-- Rendered dynamically by javascript -->
                                    </div>
                                </div>

                                <!-- Section 4: Selected Vehicle -->
                                <div class="space-y-2 border-t border-slate-100 pt-3" id="selected-vehicle-section">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 select-none">Selected Vehicle</span>
                                    <div id="selected-vehicle-panel" class="rounded-xl border border-slate-200 bg-white p-3 space-y-3">
                                        <!-- Empty state -->
                                        <div class="py-4 text-center text-slate-400 text-xs select-none">
                                            <i class="ti ti-bus text-lg mb-1 block"></i>
                                            Select a vehicle from the map or list to view details.
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </section>