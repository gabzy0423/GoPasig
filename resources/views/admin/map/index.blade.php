<section id="screen-map-view" class="hidden animate-fade-in">
    <!-- Page-local full-height map shell. The global admin shell remains unchanged. -->
    <div id="live-map-wrapper" class="relative min-h-[560px] overflow-hidden rounded-[18px] border border-slate-200/80 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.16)] ring-1 ring-white/60 md:left-1/2 md:w-[calc(100vw-240px-3rem)] md:-translate-x-1/2 lg:h-[calc(100dvh-56px-3rem)]">
        <div id="live-map-canvas" class="h-[520px] w-full lg:h-full"></div>

        <!-- Floating top identity, filters, and controls -->
        <div class="map-ui-enter map-ui-enter-down relative z-[1000] m-3 flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white/90 p-2.5 shadow-[0_14px_34px_rgba(15,23,42,0.16)] ring-1 ring-white/70 backdrop-blur-md transition duration-150 lg:absolute lg:left-4 lg:right-[392px] lg:top-4 lg:m-0 xl:right-[408px]">
            <div class="flex min-w-0 flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <!-- Compact page identity -->
                <div class="min-w-[180px] shrink-0 select-none">
                    <h1 class="text-sm font-black text-slate-900">Live Fleet Tracker</h1>
                    <div class="mt-0.5 flex items-center gap-1 text-[10px] font-semibold text-slate-400">
                        <span>Dashboard</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Fleet</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span class="font-bold text-slate-600">Live Fleet Map</span>
                    </div>
                </div>

                <!-- Search and route filters -->
                <div class="flex min-w-0 flex-1 flex-col gap-2 md:flex-row md:items-center">
                    <div class="relative w-full md:w-[230px] md:shrink-0">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                            <i class="ti ti-search text-xs"></i>
                        </span>
                        <input type="text" id="universal-search" oninput="applyToolbarFilters()" placeholder="Search plate, driver, or route..." class="w-full rounded-xl border border-slate-200/90 bg-slate-50/80 py-2 pl-9 pr-3 text-xs font-semibold text-slate-900 shadow-inner outline-none transition placeholder-slate-400 focus:border-[#003F87] focus:bg-white focus-visible:ring-2 focus-visible:ring-[#003F87]/20">
                    </div>

                    <div class="map-chip-strip scrollbar-none flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto whitespace-nowrap py-1">
                        <span class="mr-1 shrink-0 text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Routes:</span>
                        <button onclick="toggleRouteFilter('all')" data-route-filter="all" class="shrink-0 cursor-pointer rounded-full bg-[#003F87] px-3.5 py-1.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#002f66] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#003F87]/25">All <span id="route-pill-all-count"></span></button>
                        <button onclick="toggleRouteFilter('1')" data-route-filter="1" class="shrink-0 cursor-pointer rounded-full border border-slate-200/80 bg-white/90 px-3.5 py-1.5 text-xs font-bold text-slate-600 transition hover:border-[#003F87]/30 hover:bg-[#E6F1FB]/60 hover:text-[#003F87] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#003F87]/20">Route 1 <span id="route-pill-1-count"></span></button>
                        <button onclick="toggleRouteFilter('2')" data-route-filter="2" class="shrink-0 cursor-pointer rounded-full border border-slate-200/80 bg-white/90 px-3.5 py-1.5 text-xs font-bold text-slate-600 transition hover:border-[#003F87]/30 hover:bg-[#E6F1FB]/60 hover:text-[#003F87] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#003F87]/20">Route 2 <span id="route-pill-2-count"></span></button>
                        <button onclick="toggleRouteFilter('3')" data-route-filter="3" class="shrink-0 cursor-pointer rounded-full border border-slate-200/80 bg-white/90 px-3.5 py-1.5 text-xs font-bold text-slate-600 transition hover:border-[#003F87]/30 hover:bg-[#E6F1FB]/60 hover:text-[#003F87] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#003F87]/20">Route 3 <span id="route-pill-3-count"></span></button>
                        <button onclick="toggleRouteFilter('4')" data-route-filter="4" class="shrink-0 cursor-pointer rounded-full border border-slate-200/80 bg-white/90 px-3.5 py-1.5 text-xs font-bold text-slate-600 transition hover:border-[#003F87]/30 hover:bg-[#E6F1FB]/60 hover:text-[#003F87] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#003F87]/20">Route 4 <span id="route-pill-4-count"></span></button>
                    </div>
                </div>

                <!-- Status, live state, and refresh -->
                <div class="flex shrink-0 flex-wrap items-center gap-2 sm:justify-end">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Status:</span>
                        <select id="map-status-filter" onchange="applyToolbarFilters()" class="rounded-xl border border-slate-200/90 bg-slate-50/80 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white focus-visible:ring-2 focus-visible:ring-[#003F87]/20">
                            <option value="all">All Statuses</option>
                            <option value="Active">Active</option>
                            <option value="Delayed">Delayed</option>
                            <option value="Breakdown">Offline / Alert</option>
                            <option value="Maintenance">Maintenance</option>
                        </select>
                    </div>

                    <div class="flex items-center gap-2 rounded-xl border border-emerald-100 bg-emerald-50/90 px-2.5 py-2 shadow-sm">
                        <span class="relative flex h-2 w-2 shrink-0">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#639922] opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-[#639922]"></span>
                        </span>
                        <span class="text-[10px] font-bold text-slate-600">
                            <span class="mr-1 font-extrabold uppercase tracking-wider text-[#639922]">LIVE</span> Auto-refresh: <span id="map-last-updated" class="font-bold">Just now</span> (5s)
                        </span>
                    </div>

                    <div class="flex items-center border-l border-slate-200 pl-2">
                        <button onclick="triggerManualRefresh()" class="relative cursor-pointer rounded-xl p-2 text-slate-500 transition hover:bg-[#E6F1FB]/70 hover:text-[#003F87] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#003F87]/20" aria-label="Refresh positions" title="Refresh positions">
                            <i id="map-refresh-icon" class="ti ti-refresh text-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Floating Map Legend -->
        <div class="map-ui-enter map-ui-enter-up pointer-events-none absolute bottom-4 left-4 z-[1000] min-w-[125px] select-none space-y-1.5 rounded-xl border border-slate-200/80 bg-white/90 p-2.5 text-[10px] font-bold text-slate-700 shadow-[0_12px_28px_rgba(15,23,42,0.14)] ring-1 ring-white/60 backdrop-blur-md max-lg:bottom-auto max-lg:top-[536px]">
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

        <!-- Floating Fleet Operations Panel -->
        <div class="map-ui-enter map-ui-enter-side relative z-[1000] m-3 flex max-h-[640px] flex-col rounded-[18px] border border-slate-200/90 bg-white/90 p-4 shadow-[0_18px_45px_rgba(15,23,42,0.16)] ring-1 ring-white/70 backdrop-blur-md transition duration-150 lg:absolute lg:bottom-4 lg:right-4 lg:top-[76px] lg:m-0 lg:max-h-none lg:w-[320px] xl:w-[360px]">
            <!-- Sidebar Header -->
            <div class="sticky top-0 z-10 flex shrink-0 items-center justify-between border-b border-slate-100 bg-white/80 pb-3.5 select-none backdrop-blur">
                <span class="text-xs font-extrabold uppercase tracking-widest text-slate-800">Fleet Operations</span>
                <span class="inline-flex rounded-full bg-[#E6F1FB] px-2.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-[#003F87]" id="sidebar-tracked-count">12 Buses Tracked</span>
            </div>

            <!-- Scrollable Content wrapper for all sections -->
            <div class="map-panel-scroll mt-3.5 flex-1 space-y-4 overflow-y-auto pr-1 scrollbar-thin scrollbar-thumb-slate-300">
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
                    <div class="map-panel-scroll max-h-[160px] overflow-y-auto space-y-2.5 pr-0.5 scrollbar-thin scrollbar-thumb-slate-300" id="fleet-sidebar-list">
                        <!-- Rendered dynamically by javascript -->
                    </div>
                </div>

                <!-- Section 3: Recent Activity -->
                <div class="space-y-2 border-t border-slate-100 pt-3">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 select-none">Recent Activity</span>
                    <div class="map-panel-scroll max-h-[110px] overflow-y-auto pr-0.5 scrollbar-thin scrollbar-thumb-slate-300 font-semibold" id="recent-activity-list">
                        <!-- Rendered dynamically by javascript -->
                    </div>
                </div>

                <!-- Section 4: Selected Vehicle -->
                <div class="space-y-2 border-t border-slate-100 pt-3" id="selected-vehicle-section">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400 select-none">Selected Vehicle</span>
                    <div id="selected-vehicle-panel" class="rounded-xl border border-slate-200/90 bg-slate-50/40 p-3.5 space-y-3">
                        <!-- Empty state -->
                        <div class="py-4 text-center text-slate-400 text-xs select-none">
                            <i class="ti ti-bus text-lg mb-1 block"></i>
                            Select a vehicle from the map or list to view details.
                        </div>
                    </div>
                </div>
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
            .map-ui-enter {
                animation: map-ui-enter 180ms ease-out both;
                will-change: opacity, transform;
            }
            .map-ui-enter-down { --map-ui-x: 0; --map-ui-y: -6px; }
            .map-ui-enter-up { --map-ui-x: 0; --map-ui-y: 6px; }
            .map-ui-enter-side { --map-ui-x: 6px; --map-ui-y: 0; }
            @keyframes map-ui-enter {
                from { opacity: 0; transform: translate(var(--map-ui-x, 0), var(--map-ui-y, 0)); }
                to { opacity: 1; transform: translate(0, 0); }
            }
            .map-panel-scroll {
                scrollbar-color: rgba(148, 163, 184, 0.7) transparent;
                scrollbar-width: thin;
            }
            .map-panel-scroll::-webkit-scrollbar { width: 8px; height: 8px; }
            .map-panel-scroll::-webkit-scrollbar-thumb {
                background: rgba(148, 163, 184, 0.55);
                border-radius: 999px;
                border: 2px solid transparent;
                background-clip: content-box;
            }
            .map-panel-scroll::-webkit-scrollbar-thumb:hover { background: rgba(100, 116, 139, 0.7); background-clip: content-box; }
            .map-chip-strip { overscroll-behavior-inline: contain; scroll-padding-inline: 12px; }
            .active-vehicle-card:focus-within,
            .active-vehicle-card:hover {
                background: #F8FBFF;
            }
            .active-vehicle-card[id] {
                outline: none;
            }
            #selected-vehicle-panel button:focus-visible,
            #fleet-sidebar-list button:focus-visible,
            #recent-activity-list button:focus-visible,
            .bm-btn-primary:focus-visible {
                outline: 2px solid rgba(0, 63, 135, 0.28);
                outline-offset: 2px;
            }
            @media (prefers-reduced-motion: reduce) {
                .map-ui-enter { animation: none; will-change: auto; }
                .bm-btn-primary { transition: none; }
            }        </style>
    </div>
</section>