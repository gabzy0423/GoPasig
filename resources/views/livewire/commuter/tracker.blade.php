<div wire:poll.5s class="w-full flex flex-col gap-0 select-none pb-6" 
     x-data="{ 
         showDrawer: false, 
         lat: $wire.entangle('lat'), 
         lng: $wire.entangle('lng'),
         hasLocation: false,
         requestLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        this.lat = position.coords.latitude;
                        this.lng = position.coords.longitude;
                        this.hasLocation = true;
                        $wire.updateLocation(position.coords.latitude, position.coords.longitude);
                    },
                    (error) => {
                        console.warn('Location access denied:', error);
                        this.hasLocation = false;
                    }
                );
            }
        }
    }"
    x-init="requestLocation()">
 
    <!-- Toast Notification for suspended route -->
    <div x-data="{ showToast: false, toastMessage: '' }"
         x-on:route-suspended.window="toastMessage = $event.detail[0].message; showToast = true; setTimeout(() => { showToast = false }, 5000)"
         x-show="showToast"
         x-transition
         class="fixed top-20 left-1/2 transform -translate-x-1/2 z-[100] bg-rose-600 text-white text-xs font-bold px-4 py-3 rounded-xl shadow-lg flex items-center gap-2 max-w-[90%] border border-rose-500"
         style="display: none;">
        <i class="ti ti-ban text-sm flex-shrink-0"></i>
        <span x-text="toastMessage"></span>
    </div>


    @if($breakdownAlert)
        <div class="w-full bg-[#FCEBEB] border-b border-[#E24B4A] px-4 py-3 flex items-start gap-3 flex-shrink-0 animate-pulse">
            <i class="ti ti-alert-triangle text-[#A32D2D] text-lg flex-shrink-0 mt-0.5"></i>
            <span class="text-xs font-bold text-[#A32D2D] leading-snug">{{ $breakdownAlert }}</span>
        </div>
    @endif

    @if($maintenanceAlert)
        <div class="w-full bg-[#FAEEDA] border-b border-[#BA7517] px-4 py-3 flex items-start gap-3 flex-shrink-0 animate-pulse">
            <i class="ti ti-tool text-[#854F0B] text-lg flex-shrink-0 mt-0.5"></i>
            <span class="text-xs font-bold text-[#854F0B] leading-snug">{{ $maintenanceAlert }}</span>
        </div>
    @endif

    <!-- SECTION 2 — SERVICE ALERT BANNER (Conditional) -->
    @if($activeAlerts->isNotEmpty())
        @php
            $firstAlert = $activeAlerts->first();
            $isSuspension = $firstAlert->type === 'suspension';
        @endphp
        <div class="w-full flex justify-between items-center px-4 py-2.5 transition-colors border-l-[4px] border border-t-slate-100 border-r-slate-100 border-b-slate-100 flex-shrink-0
                    {{ $isSuspension ? 'bg-[#FCEBEB] border-l-[#E24B4A]' : 'bg-[#FAEEDA] border-l-[#BA7517]' }}">
            <div class="flex items-center gap-2 min-w-0">
                <i class="ti ti-alert-triangle text-base flex-shrink-0 {{ $isSuspension ? 'text-[#A32D2D]' : 'text-[#BA7517]' }}"></i>
                <span class="text-xs font-bold truncate pr-2 {{ $isSuspension ? 'text-[#A32D2D]' : 'text-[#854F0B]' }}">
                    {{ $firstAlert->title }}: {{ $firstAlert->message }}
                </span>
            </div>
            <button @click="showDrawer = true" class="text-xs font-bold underline whitespace-nowrap active:opacity-70 text-[#003F87]">
                See all
            </button>
        </div>
    @endif

    <!-- SECTION 3 — ROUTE FILTER CHIPS -->
    <div class="w-full bg-white border-b border-slate-100 py-2.5 flex-shrink-0">
        <div class="flex items-center gap-2.5 px-4 overflow-x-auto no-scrollbar" style="-webkit-overflow-scrolling: touch;">
            
            <!-- All Routes -->
            <button wire:click="setRoute(null)"
                    wire:loading.attr="disabled"
                    wire:target="setRoute"
                    class="flex-shrink-0 px-4 py-1.5 rounded-full text-xs font-bold transition-all duration-200 active:scale-95 disabled:opacity-60 disabled:pointer-events-none inline-flex items-center gap-1.5
                           {{ is_null($selectedRouteId) ? 'bg-[#003F87] text-white shadow-sm' : 'bg-white border border-[#C8C7C2] text-slate-500' }}">
                <i wire:loading wire:target="setRoute(null)" class="ti ti-loader-2 animate-spin"></i>
                <span>All Routes</span>
            </button>

            <!-- Route Chips dynamically -->
            @foreach($routes as $route)
                @php
                    $dotColor = $route->color ?: '#003F87';
                    $isActive = $selectedRouteId === $route->id;
                @endphp
                <button wire:click="setRoute({{ $route->id }})"
                        wire:loading.attr="disabled"
                        wire:target="setRoute"
                        class="flex-shrink-0 px-4 py-1.5 rounded-full text-xs font-bold transition-all duration-200 active:scale-95 flex items-center gap-1.5 disabled:opacity-60 disabled:pointer-events-none
                               {{ $isActive ? 'bg-[#003F87] text-white shadow-sm' : 'bg-white border border-[#C8C7C2] text-slate-500 hover:bg-slate-50' }}">
                    <i wire:loading wire:target="setRoute({{ $route->id }})" class="ti ti-loader-2 animate-spin"></i>
                    @if(!$isActive)
                        <span wire:loading.remove wire:target="setRoute({{ $route->id }})" class="h-2 w-2 rounded-full flex-shrink-0" style="background-color: {{ $dotColor }};"></span>
                    @endif
                    <span>{{ $route->name }}</span>
                </button>
            @endforeach

        </div>
    </div>

    <!-- SECTION 4 — LIVE MAP CANVAS -->
    <div class="w-full relative h-[320px] bg-slate-100 flex-shrink-0 border-b border-slate-200 shadow-inner">
        <!-- Google Map element -->
        <div id="map" class="w-full h-full" wire:ignore></div>

        <!-- MAP CUSTOM CONTROLS overlay (Bottom Right) -->
        <div class="absolute bottom-4 right-4 z-30 flex flex-col gap-1.5 select-none">
            <!-- Commuter Location Center -->
            <button @click="requestLocation()" class="w-9 h-9 bg-white border border-slate-200 rounded-lg flex items-center justify-center shadow-md active:scale-90 text-slate-700 transition-transform" title="Center on My Location">
                <i class="ti ti-current-location text-lg"></i>
            </button>
            <!-- Zoom In -->
            <button id="zoom-in" class="w-9 h-9 bg-white border border-slate-200 rounded-lg flex items-center justify-center shadow-md active:scale-90 text-slate-700 transition-transform">
                <i class="ti ti-plus text-lg"></i>
            </button>
            <!-- Zoom Out -->
            <button id="zoom-out" class="w-9 h-9 bg-white border border-slate-200 rounded-lg flex items-center justify-center shadow-md active:scale-90 text-slate-700 transition-transform">
                <i class="ti ti-minus text-lg"></i>
            </button>
        </div>
    </div>

    <!-- SECTION 5 — NEAREST BUS CARD -->
    <div class="px-4 pt-4 flex-shrink-0 select-none">
        <div class="bg-white border border-slate-100 rounded-2xl shadow-[0_6px_24px_rgba(15,23,42,0.03)] p-4 flex flex-col gap-3">
            <div class="flex justify-between items-center">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Nearest bus to you</h3>
                <i class="ti ti-navigation text-sm text-[#003F87]"></i>
            </div>

            <div x-show="hasLocation && @json(!is_null($nearestBus))">
                @if($nearestBus)
                    <div class="flex flex-col gap-3">
                        <div class="flex justify-between items-start">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <span class="text-sm font-mono font-bold text-slate-800">{{ $nearestBus->plate_number }}</span>
                                    @if($nearestBus->is_simulated)
                                        <span class="px-1.5 py-0.5 text-[8.5px] font-extrabold bg-blue-50 text-[#1D4ED8] border border-blue-100 rounded-full flex items-center gap-0.5" title="Estimated Location (No GPS Signal)">
                                            <span class="w-1 h-1 rounded-full bg-blue-500 animate-pulse"></span>
                                            Estimated
                                        </span>
                                    @endif
                                    @php
                                        $nearestFreshnessState = $nearestBus->gps_freshness_state ?? 'UNKNOWN';
                                        $nearestFreshnessAge = $nearestBus->gps_freshness_age_seconds ?? null;
                                    @endphp
                                    @if($nearestFreshnessState === 'LIVE')
                                        <span class="px-1.5 py-0.5 text-[8.5px] font-extrabold bg-emerald-50 text-[#0F6E56] border border-emerald-100 rounded-full flex items-center gap-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                            LIVE
                                        </span>
                                    @elseif($nearestFreshnessState === 'STALE')
                                        <span class="px-1.5 py-0.5 text-[8.5px] font-extrabold bg-amber-50 text-[#854F0B] border border-amber-100 rounded-full flex items-center gap-0.5" title="Bus signal temporarily lost - last known position shown">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                            STALE ({{ $nearestFreshnessAge }}s)
                                        </span>
                                    @elseif($nearestFreshnessState === 'OFFLINE')
                                        <span class="px-1.5 py-0.5 text-[8.5px] font-extrabold bg-rose-50 text-[#A32D2D] border border-rose-100 rounded-full flex items-center gap-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                            OFFLINE
                                        </span>
                                    @else
                                        <span class="px-1.5 py-0.5 text-[8.5px] font-extrabold bg-slate-50 text-slate-500 border border-slate-200 rounded-full flex items-center gap-0.5">
                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                            UNKNOWN
                                        </span>
                                    @endif
                                </div>
                                <div class="flex items-center gap-1.5 text-xs text-slate-500 font-bold">
                                    <span class="h-2 w-2 rounded-full" style="background-color: {{ $nearestBus->route_color }};"></span>
                                    <span>{{ $nearestBus->route_name }}</span>
                                </div>
                                <span class="text-xs text-slate-400 font-bold mt-0.5">{{ $nearestBus->distance_km }} km away</span>
                            </div>
                            <div class="text-right">
                                <span class="text-lg font-black text-[#003F87] block leading-none">{{ $nearestBus->eta_label }}</span>
                                @if(($nearestBus->passenger_count / $nearestBus->capacity) > 0.8)
                                    <div class="flex items-center gap-1 text-[11px] font-extrabold text-[#A32D2D] justify-end mt-1.5">
                                        <i class="ti ti-users"></i> Nearly Full
                                    </div>
                                @endif
                            </div>
                        </div>
                        <button onclick="requestAlertPermission('{{ $nearestBus->plate_number }}', {{ $nearestBus->eta_minutes ?? 'null' }})"
                                class="w-full h-[38px] bg-[#003F87] text-white font-bold text-xs rounded-xl shadow-sm hover:bg-[#002f66] active:scale-95 transition-all flex items-center justify-center gap-1.5">
                            <i class="ti ti-bell text-[14px]"></i> Set Alert
                        </button>
                    </div>
                @endif
            </div>

            <div x-show="!hasLocation || @json(is_null($nearestBus))" class="flex flex-col items-center justify-center py-2 gap-2 text-center">
                <p class="text-xs font-semibold text-slate-500">Enable location access to discover nearest bus metrics.</p>
                <button @click="requestLocation()" class="border border-[#003F87] text-[#003F87] font-bold text-xs rounded-xl py-2 px-5 active:scale-95 transition-transform w-full">
                    Enable Location
                </button>
            </div>
        </div>
    </div>

    <!-- SECTION 6 — ACTIVE BUS LIST -->
    <div class="flex flex-col gap-3 px-4 pt-5 select-none pb-20">
        <div class="flex justify-between items-center px-0.5">
            <h3 class="text-sm font-bold text-slate-800 tracking-tight flex items-center gap-1.5">
                Active buses
                <span class="bg-slate-200/80 text-slate-600 text-[10px] font-extrabold rounded-full px-2.5 py-0.5 leading-none">{{ $activeBuses->count() }} buses</span>
            </h3>
            <span class="text-[11px] font-bold text-slate-400">Updated 5s ago</span>
        </div>

        <!-- Stacked Cards list -->
        <div class="flex flex-col gap-3">
            @forelse($activeBuses as $bus)
                @php
                    $fillRatio = $bus->capacity > 0 ? $bus->passenger_count / $bus->capacity : 0;
                    $isFull = $fillRatio > 0.8;
                @endphp
                <div @click="focusBusOnMap({{ $bus->bus_id }}, {{ $bus->lat }}, {{ $bus->lng }}, '{{ $bus->plate_number }}')" 
                     class="bg-white border border-slate-100 rounded-2xl p-4 flex flex-col gap-3 cursor-pointer hover:border-slate-200 active:scale-[0.98] transition-all select-none shadow-[0_4px_24px_rgba(15,23,42,0.01)]">
                    
                    <!-- Card row 1: Plate & Status -->
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-1.5">
                            <span class="bg-slate-50 border border-slate-200/80 px-2 py-0.5 rounded text-[11px] font-mono font-bold text-slate-700 tracking-tight">{{ $bus->plate_number }}</span>
                            @if($bus->is_simulated)
                                <span class="px-1.5 py-0.5 text-[9px] font-extrabold bg-blue-50 text-[#1D4ED8] border border-blue-100 rounded-full flex items-center gap-1" title="Estimated Location (No GPS Signal)">
                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-500 animate-pulse"></span>
                                    Estimated
                                </span>
                            @endif
                        </div>
                        <div class="flex items-center gap-1">
                            @if($bus->status === 'active')
                                <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-[#E1F5EE] text-[#0F6E56] border border-emerald-100 rounded-full">{{ \App\Models\SystemSetting::get('label_bus_status_ontime', 'On-time') }}</span>
                            @elseif($bus->status === 'delayed')
                                <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-[#FAEEDA] text-[#854F0B] border border-amber-100 rounded-full">{{ \App\Models\SystemSetting::get('label_bus_status_delayed', 'Delayed') }}</span>
                            @elseif($bus->status === 'idle')
                                <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-[#F1EFE8] text-[#5F5E5A] border border-slate-200/80 rounded-full">{{ \App\Models\SystemSetting::get('label_bus_status_idle', 'Idle') }}</span>
                            @else
                                <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-[#FCEBEB] text-[#A32D2D] border border-rose-100 rounded-full">{{ \App\Models\SystemSetting::get('label_bus_status_breakdown', 'Breakdown') }}</span>
                            @endif
                            @php
                                $freshnessState = $bus->gps_freshness_state ?? 'UNKNOWN';
                                $freshnessAge = $bus->gps_freshness_age_seconds ?? null;
                            @endphp

                            @if($freshnessState === 'LIVE')
                                <span class="px-1.5 py-0.5 text-[9px] font-extrabold bg-emerald-50 text-[#0F6E56] border border-emerald-100 rounded-full flex items-center gap-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                    LIVE
                                </span>
                            @elseif($freshnessState === 'STALE')
                                <span class="px-1.5 py-0.5 text-[9px] font-extrabold bg-amber-50 text-[#854F0B] border border-amber-100 rounded-full flex items-center gap-0.5" title="Bus signal temporarily lost - last known position shown">
                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                                    STALE ({{ $freshnessAge }}s)
                                </span>
                            @elseif($freshnessState === 'OFFLINE')
                                <span class="px-1.5 py-0.5 text-[9px] font-extrabold bg-rose-50 text-[#A32D2D] border border-rose-100 rounded-full flex items-center gap-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500"></span>
                                    OFFLINE
                                </span>
                            @else
                                <span class="px-1.5 py-0.5 text-[9px] font-extrabold bg-slate-50 text-slate-500 border border-slate-200 rounded-full flex items-center gap-0.5">
                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                    UNKNOWN
                                </span>
                            @endif
                        </div>
                    </div>

                    <!-- Card row 2: Route details -->
                    <div class="flex items-center gap-1.5 text-xs text-slate-500 font-bold pl-0.5">
                        <i class="ti ti-route text-[13px] text-slate-400"></i>
                        <span>{{ $bus->route_name }}</span>
                    </div>

                    <!-- Card row 3: ETA Details -->
                    <div class="flex items-center gap-1.5 text-xs font-bold text-[#003F87] pl-0.5">
                        <i class="ti ti-clock text-[13px]"></i>
                        <span><strong class="font-extrabold text-slate-700">{{ $bus->next_stop_name }}</strong> - <strong class="text-[#003F87] font-black">{{ $bus->eta_label }}</strong></span>
                    </div>

                    <!-- Card row 4: Passengers load -->
                    <div class="flex flex-col gap-1 pb-1 border-t border-slate-50 pt-2 pl-0.5">
                        <div class="flex items-center justify-between text-[11.5px] font-bold {{ $isFull ? 'text-[#A32D2D]' : 'text-slate-400' }}">
                            <span>{{ $bus->passenger_count }} / {{ $bus->capacity }} riders</span>
                            <span>{{ round($fillRatio * 100) }}%</span>
                        </div>
                        <div class="w-full bg-[#E6F1FB] rounded-full h-1.5 overflow-hidden mt-1.5">
                            <div class="h-full rounded-full transition-all duration-500" 
                                 style="width: {{ min(100, $fillRatio * 100) }}%; background-color: {{ $isFull ? '#E24B4A' : '#003F87' }};">
                            </div>
                        </div>
                    </div>

                    <!-- Card row 5: Link -->
                    <div class="text-[12px] font-bold text-[#003F87] flex items-center gap-1 active:underline pl-0.5">
                        <i class="ti ti-map-pin"></i> Track this bus
                    </div>

                </div>
            @empty
                <!-- Empty State -->
                <div class="w-full py-12 flex flex-col items-center justify-center bg-white border border-slate-100 rounded-2xl px-4 text-center">
                    <i class="ti ti-bus-off text-slate-300 text-5xl mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-800">No active buses right now</h4>
                    <p class="text-xs font-semibold text-slate-400 mt-1 max-w-[200px] mx-auto">Check back during service hours: 5:00 AM – 9:00 PM</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- GLOBAL SERVICE ALERTS SLIDE-IN DRAWER (Overlay Drawer) -->
    <div class="fixed inset-0 z-50 flex justify-end transition-opacity select-none" 
         x-show="showDrawer" x-cloak
         x-transition:enter="transition-opacity ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showDrawer = false"></div>

        <!-- Drawer Content -->
        <div class="relative w-full max-w-[320px] h-full bg-white shadow-2xl flex flex-col p-5 overflow-y-auto"
             x-show="showDrawer"
             x-transition:enter="transition-transform ease-out duration-300 transform" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
             x-transition:leave="transition-transform ease-in duration-200 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
            
            <!-- Drawer Header -->
            <div class="flex justify-between items-center border-b border-slate-100 pb-3 mb-4 flex-shrink-0">
                <h3 class="text-[15px] font-bold text-slate-800 flex items-center gap-1.5">
                    <i class="ti ti-speakerphone text-slate-600"></i> Active Service Alerts
                </h3>
                <button @click="showDrawer = false" class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:text-slate-600 active:scale-90 transition-transform">
                    <i class="ti ti-x text-sm"></i>
                </button>
            </div>

            <!-- Drawer Alerts List -->
            <div class="flex flex-col gap-3.5 flex-grow overflow-y-auto">
                @forelse($activeAlerts as $alert)
                    @php
                        $border = '#378ADD';
                        $bg = 'bg-sky-50/50 border-sky-100';
                        $icon = 'ti-info-circle';
                        $iconColor = 'text-sky-600';

                        if ($alert->type === 'delay') {
                            $border = '#D97706';
                            $bg = 'bg-amber-50/50 border-amber-100';
                            $icon = 'ti-clock';
                            $iconColor = 'text-amber-600';
                        } elseif ($alert->type === 'suspension') {
                            $border = '#E11D48';
                            $bg = 'bg-rose-50/50 border-rose-100';
                            $icon = 'ti-ban';
                            $iconColor = 'text-rose-600';
                        } elseif ($alert->type === 'maintenance') {
                            $border = '#B45309';
                            $bg = 'bg-orange-50/50 border-orange-100';
                            $icon = 'ti-tool';
                            $iconColor = 'text-orange-600';
                        }
                    @endphp
                    <div class="rounded-xl border-l-[3px] border border-t-slate-100 border-r-slate-100 border-b-slate-100 p-3.5 flex flex-col gap-1.5 {{ $bg }}"
                         style="border-left-color: {{ $border }};">
                        
                        <div class="flex justify-between items-start gap-1">
                            <span class="text-xs font-bold text-slate-800 leading-tight">{{ $alert->title }}</span>
                            <span class="text-[9px] font-bold text-slate-400 uppercase tracking-wider whitespace-nowrap">{{ $alert->created_at->diffForHumans() }}</span>
                        </div>
                        <p class="text-xs font-semibold text-slate-600 leading-normal mt-0.5">
                            {{ $alert->message }}
                        </p>
                        <span class="text-[10px] font-bold text-slate-400 mt-1">Affected: {{ $alert->affected_routes ?: 'All Routes' }}</span>

                    </div>
                @empty
                    <div class="flex flex-col items-center justify-center py-12 text-center">
                        <i class="ti ti-circle-check text-slate-300 text-5xl mb-2"></i>
                        <h4 class="text-xs font-bold text-slate-500">All routes are operating normally</h4>
                    </div>
                @endforelse
            </div>
            
        </div>
    </div>

</div>





