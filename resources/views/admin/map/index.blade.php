<section id="screen-map-view" class="hidden animate-fade-in">
    <!-- Page-local full-height map shell. The global admin shell remains unchanged. -->
    <div id="live-map-wrapper" class="relative min-h-[560px] overflow-hidden rounded-[18px] border border-slate-200/80 bg-white shadow-[0_18px_45px_rgba(15,23,42,0.16)] ring-1 ring-white/60 md:left-1/2 md:w-[calc(100vw-240px-3rem)] md:-translate-x-1/2 lg:h-[calc(100dvh-56px-3rem)]">
        <div id="live-map-canvas" class="h-[520px] w-full lg:h-full"></div>

        <!-- Floating top identity, filters, and controls -->
        <div id="live-map-toolbar" class="map-ui-enter map-ui-enter-down relative z-[1000] m-3 flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white/90 p-2.5 shadow-[0_14px_34px_rgba(15,23,42,0.16)] ring-1 ring-white/70 backdrop-blur-md transition duration-150 lg:absolute lg:left-4 lg:right-4 lg:top-4 lg:m-0 2xl:right-[408px]">
            <div class="flex min-w-0 flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
                <!-- Compact page identity -->
                <div class="w-[155px] shrink-0 select-none 2xl:w-auto 2xl:min-w-[170px]">
                    <h1 class="text-sm font-black text-slate-900">Live Fleet Tracker</h1>
                    <div class="mt-0.5 flex items-center gap-1 text-[10px] font-semibold text-slate-400">
                        <span>Dashboard</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Fleet</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span class="font-bold text-slate-600">Live Map</span>
                    </div>
                </div>

                <!-- Live state, route filters, and direction visibility -->
                <div class="flex min-w-0 flex-1 flex-col gap-2 md:flex-row md:items-center">
                    <div class="flex shrink-0 items-center gap-1 rounded-xl border border-emerald-100 bg-emerald-50/90 px-2 py-2 text-[10px] text-slate-600 shadow-sm 2xl:gap-1.5 2xl:px-2.5 2xl:text-[12px]">
                        <span class="font-mono-custom" id="live-map-tracked-count">0 buses tracked</span>
                        <span class="text-slate-300">/</span>
                        <div class="flex items-center gap-1">
                            <span class="relative flex h-2 w-2 shrink-0">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-[#639922] opacity-75"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-[#639922]"></span>
                            </span>
                            <span>Live</span>
                        </div>
                    </div>

                    <div id="live-map-route-filters" class="map-chip-strip scrollbar-none flex min-w-0 flex-1 items-center gap-1.5 overflow-x-auto rounded-xl border border-slate-200/80 bg-slate-100/80 p-1 whitespace-nowrap">
                        <button onclick="toggleRouteFilter('all')" data-route-filter="all" class="route-chip shrink-0 rounded-lg border border-slate-200/80 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-[#001F44] shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#003F87]/20 2xl:px-3 2xl:text-[12px]">All</button>
                    </div>

                    <div id="live-map-direction-filters" class="flex shrink-0 items-center gap-1.5 rounded-xl border border-slate-200/80 bg-white/90 px-1.5 py-2 text-[9px] font-semibold text-slate-600 shadow-sm 2xl:gap-3 2xl:px-2.5 2xl:text-[11px]" aria-label="Route direction visibility">
                        <label class="flex cursor-pointer items-center gap-1 whitespace-nowrap 2xl:gap-1.5" for="live-map-direction-outbound">
                            <input id="live-map-direction-outbound" type="checkbox" checked onchange="filterLiveMapRouteDirection('outbound', this.checked)" class="h-3.5 w-3.5 accent-[#003F87]">
                            <span class="w-3 border-t-2 border-[#003F87] 2xl:w-4"></span>
                            <span>OUT solid</span>
                        </label>
                        <label class="flex cursor-pointer items-center gap-1 whitespace-nowrap 2xl:gap-1.5" for="live-map-direction-inbound">
                            <input id="live-map-direction-inbound" type="checkbox" checked onchange="filterLiveMapRouteDirection('inbound', this.checked)" class="h-3.5 w-3.5 accent-[#003F87]">
                            <span class="w-3 border-t-2 border-dashed border-[#003F87] 2xl:w-4"></span>
                            <span>IN dashed</span>
                        </label>
                    </div>
                </div>

                <!-- Status filter -->
                <div class="flex shrink-0 items-center gap-1 sm:justify-end 2xl:gap-2">
                    <span class="text-[9px] font-extrabold uppercase tracking-wide text-slate-400 2xl:text-[10px] 2xl:tracking-widest">Status:</span>
                    <select id="map-status-filter" onchange="applyToolbarFilters()" class="rounded-xl border border-slate-200/90 bg-white px-2 py-2 text-[12px] font-semibold text-[#001F44] outline-none transition focus:border-[#003F87] focus-visible:ring-2 focus-visible:ring-[#003F87]/20 2xl:px-3 2xl:text-[13px]">
                        <option value="all">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Delayed">Delayed</option>
                        <option value="Breakdown">Offline / Alert</option>
                        <option value="Maintenance">Maintenance</option>
                    </select>
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
        <div class="map-ui-enter map-ui-enter-side relative z-[1000] m-3 flex max-h-[640px] flex-col rounded-[18px] border border-slate-200/90 bg-white/90 p-4 shadow-[0_18px_45px_rgba(15,23,42,0.16)] ring-1 ring-white/70 backdrop-blur-md transition duration-150 lg:absolute lg:bottom-4 lg:right-4 lg:top-[140px] lg:m-0 lg:max-h-none lg:w-[320px] xl:top-[76px] xl:w-[360px]">
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
