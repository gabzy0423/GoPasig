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
                    <div class="rounded-xl border border-[#E0E0E0] bg-white p-3 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between shrink-0">
                        <!-- Left: Route Filters -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400 mr-1">Routes:</span>
                            <button onclick="toggleRouteFilter('all')" data-route-filter="all" class="rounded-full bg-[#003F87] px-3.5 py-1.5 text-xs font-bold text-white transition cursor-pointer">All Routes</button>
                            <button onclick="toggleRouteFilter('1')" data-route-filter="1" class="rounded-full bg-slate-50 border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">Route 1 (P2P)</button>
                            <button onclick="toggleRouteFilter('2')" data-route-filter="2" class="rounded-full bg-slate-50 border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">Route 2 (Ligaya)</button>
                            <button onclick="toggleRouteFilter('3')" data-route-filter="3" class="rounded-full bg-slate-50 border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">Route 3 (Shaw)</button>
                            <button onclick="toggleRouteFilter('4')" data-route-filter="4" class="rounded-full bg-slate-50 border border-slate-200 px-3.5 py-1.5 text-xs font-bold text-slate-600 hover:bg-slate-100 transition cursor-pointer">Route 4 (Nagpayong)</button>
                        </div>

                        <!-- Right: Status Dropdown & Refresh button -->
                        <div class="flex items-center gap-3 justify-between sm:justify-end">
                            <div class="flex items-center gap-2">
                                <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Status:</span>
                                <select id="map-status-filter" onchange="filterMapByStatus()" class="rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                                    <option value="all">All Statuses</option>
                                    <option value="Active">Active</option>
                                    <option value="Delayed">Delayed</option>
                                    <option value="Breakdown">Offline / Alert</option>
                                </select>
                            </div>
                            
                            <div class="flex items-center gap-2 border-l border-slate-200 pl-3">
                                <button onclick="triggerManualRefresh()" class="relative rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-[#003F87] transition cursor-pointer" aria-label="Refresh positions">
                                    <i id="map-refresh-icon" class="ti ti-refresh text-lg"></i>
                                </button>
                                <span id="map-last-updated" class="text-[10px] font-bold text-slate-400">Last updated 5s ago</span>
                            </div>
                        </div>
                    </div>

                    <!-- Map Split Two-Column Layout -->
                    <div class="grid grid-cols-1 gap-6 lg:grid-cols-10">
                        <!-- Left Map Canvas (70%) -->
                        <div class="lg:col-span-7 rounded-xl border border-[#E0E0E0] bg-white overflow-hidden shadow-[0_1px_3px_rgba(0,0,0,0.06)] relative h-[560px]" id="live-map-wrapper">
                            <div id="live-map-canvas" class="h-full w-full z-10"></div>
                            
                            <!-- Custom Leaflet Styles to clean tiles and popups -->
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
                            </style>
                        </div>

                        <!-- Right Fleet Sidebar Panel (30%) -->
                        <div class="lg:col-span-3 rounded-xl border border-[#E0E0E0] bg-white p-4 shadow-[0_1px_3px_rgba(0,0,0,0.06)] flex flex-col h-[560px]">
                            <!-- Sidebar Header -->
                            <div class="border-b border-slate-100 pb-3 flex items-center justify-between shrink-0">
                                <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800">Fleet Overview</span>
                                <span class="inline-flex rounded-full bg-[#E6F1FB] px-2.5 py-0.5 text-[9px] font-bold text-[#003F87] uppercase tracking-wider">12 Buses Tracked</span>
                            </div>

                            <!-- Summary stat mini chips 2x2 grid -->
                            <div class="grid grid-cols-2 gap-2 my-3 shrink-0">
                                <div class="rounded-lg border border-slate-100 bg-[#E8F4E0]/40 p-2 flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-[#639922]"></span>
                                    <div class="leading-none">
                                        <p class="text-[9px] font-extrabold uppercase text-slate-400">Active</p>
                                        <p class="text-xs font-black text-[#639922] mt-0.5" id="stats-active">9</p>
                                    </div>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-[#FEF7ED]/40 p-2 flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-[#BA7517]"></span>
                                    <div class="leading-none">
                                        <p class="text-[9px] font-extrabold uppercase text-slate-400">Delayed</p>
                                        <p class="text-xs font-black text-[#BA7517] mt-0.5" id="stats-delayed">2</p>
                                    </div>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-[#FDF2F2]/40 p-2 flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-[#E24B4A]"></span>
                                    <div class="leading-none">
                                        <p class="text-[9px] font-extrabold uppercase text-slate-400">Alerts</p>
                                        <p class="text-xs font-black text-[#E24B4A] mt-0.5" id="stats-alerts">1</p>
                                    </div>
                                </div>
                                <div class="rounded-lg border border-slate-100 bg-slate-50/50 p-2 flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full bg-[#888780]"></span>
                                    <div class="leading-none">
                                        <p class="text-[9px] font-extrabold uppercase text-slate-400">Idle</p>
                                        <p class="text-xs font-black text-slate-500 mt-0.5" id="stats-idle">0</p>
                                    </div>
                                </div>
                            </div>

                            <!-- Scrollable Cards List -->
                            <div class="flex-1 overflow-y-auto space-y-2.5 pr-0.5 scrollbar-thin scrollbar-thumb-slate-200" id="fleet-sidebar-list">
                                <!-- Rendered dynamically by javascript -->
                            </div>
                        </div>
                    </div>
                </section>