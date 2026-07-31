<div wire:poll.5s class="w-full flex flex-col gap-0 select-none pb-6"
     x-data="{ 
         showDrawer: @entangle('selectedStopId'),
         lat: @entangle('lat'), 
         lng: @entangle('lng'),
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

    <!-- SECTION 1 — SEARCH BOX HEADER -->
    <div class="w-full bg-white border-b border-slate-100 py-3.5 px-4 flex-shrink-0 shadow-sm z-20 flex flex-col gap-3">
        <div class="flex items-center gap-2">
            <div class="flex-grow relative flex items-center bg-slate-50 border border-slate-200 rounded-xl px-3.5 py-2.5">
                <i class="ti ti-search text-slate-400 text-base flex-shrink-0 mr-2.5"></i>
                <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search stop name or landmarks…" class="bg-transparent outline-none w-full text-slate-800 placeholder-slate-400 text-xs font-semibold">
            </div>
            
            <button @click="requestLocation()" class="w-[42px] h-[42px] bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-center text-[#003F87] active:scale-95 transition-transform" title="Request Proximity Location">
                <i x-show="!hasLocation" class="ti ti-current-location text-lg"></i>
                <i x-show="hasLocation" x-cloak class="ti ti-check text-lg"></i>
            </button>
        </div>
        
        <div class="flex items-center justify-between text-[11px] font-bold text-slate-400 pl-0.5">
            <span>Seeded Stops: {{ $stops->count() }} locations</span>
            <div x-show="hasLocation" class="flex items-center gap-1 text-emerald-600">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Proximity sorting active</span>
            </div>
            <div x-show="!hasLocation" class="text-slate-400">Location disabled</div>
        </div>
    </div>

    <!-- SECTION 2 — GOOGLE MAP CANVAS -->
    <div class="w-full relative h-[280px] bg-slate-100 flex-shrink-0 border-b border-slate-200 shadow-inner">
        <div id="map" class="w-full h-full" wire:ignore></div>
        
        <!-- MAP CONTROLS OVERLAY -->
        <div class="absolute bottom-4 right-4 z-30 flex flex-col gap-1.5 select-none">
            <button id="zoom-in" class="w-9 h-9 bg-white border border-slate-200 rounded-lg flex items-center justify-center shadow-md active:scale-90 text-slate-700 transition-transform">
                <i class="ti ti-plus text-lg"></i>
            </button>
            <button id="zoom-out" class="w-9 h-9 bg-white border border-slate-200 rounded-lg flex items-center justify-center shadow-md active:scale-90 text-slate-700 transition-transform">
                <i class="ti ti-minus text-lg"></i>
            </button>
        </div>
    </div>

    <!-- SECTION 3 — STOPS ROSTER/LIST -->
    <div class="flex flex-col gap-3 px-4 pt-5 select-none pb-20">
        <h3 class="text-sm font-bold text-slate-800 tracking-tight pl-0.5">Stops Ordered by Proximity</h3>

        <div class="flex flex-col gap-3" id="stops-list-container">
            @forelse($stops as $stop)
                @php
                    $dotColor = $stop->route?->color ?: '#003F87';
                @endphp
                <div wire:click="selectStop({{ $stop->id }})"
                     wire:loading.class="opacity-60 pointer-events-none"
                     wire:target="selectStop"
                     onclick="focusStopOnMap({{ $stop->id }}, {{ $stop->lat }}, {{ $stop->lng }}, '{{ addslashes($stop->name) }}')"
                     class="bg-white border {{ $selectedStopId === $stop->id ? 'border-[#003F87]' : 'border-slate-100' }} rounded-2xl p-4 flex items-center justify-between cursor-pointer hover:border-slate-200 active:scale-[0.98] transition-all select-none shadow-[0_4px_24px_rgba(15,23,42,0.01)]">
                    
                    <div class="flex items-center gap-3.5 min-w-0">
                        <!-- Route Colored Sequence circle -->
                        <div class="h-9 w-9 rounded-full font-black text-xs flex items-center justify-center uppercase shrink-0 text-white shadow-sm" style="background-color: {{ $dotColor }};">
                            #{{ $stop->sequence }}
                        </div>
                        <div class="min-w-0">
                            <span class="text-[13.5px] font-bold text-slate-800 truncate block leading-tight">{{ $stop->name }}</span>
                            <div class="flex items-center gap-1.5 text-[11px] text-slate-500 font-bold mt-1 leading-none">
                                <span class="h-1.5 w-1.5 rounded-full shrink-0" style="background-color: {{ $dotColor }}"></span>
                                <span>{{ $stop->route->name }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Proximity Tag -->
                    <div class="text-right shrink-0">
                        @if($stop->distance !== null)
                            <span class="text-xs font-black text-[#003F87] block">{{ $stop->distance }} km</span>
                            <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider block mt-0.5">away</span>
                        @else
                            <i wire:loading.remove wire:target="selectStop({{ $stop->id }})" class="ti ti-map-pin text-slate-300 text-sm block"></i>
                        @endif
                        <i wire:loading wire:target="selectStop({{ $stop->id }})" class="ti ti-loader-2 text-[#003F87] text-sm block animate-spin"></i>
                    </div>

                </div>
            @empty
                <!-- Empty State -->
                <div class="w-full py-12 flex flex-col items-center justify-center bg-white border border-slate-100 rounded-2xl px-4 text-center">
                    <i class="ti ti-building-fortress text-slate-300 text-5xl mb-2"></i>
                    <h4 class="text-sm font-bold text-slate-800">No stops match your query</h4>
                    <p class="text-xs font-semibold text-slate-400 mt-1 max-w-[220px] mx-auto">Try refining your search text or landmarks.</p>
                </div>
            @endforelse
        </div>
    </div>

    <!-- SECTION 4 — INTERACTIVE DETAILED SLIDE-UP SHEET DRAWER -->
    <div class="fixed inset-0 z-50 flex justify-end transition-opacity select-none" 
         x-show="showDrawer" x-cloak
         x-transition:enter="transition-opacity ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
         x-transition:leave="transition-opacity ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">
        
        <!-- Backdrop -->
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" @click="showDrawer = false; $wire.closeDrawer()"></div>

        <!-- Drawer Content -->
        @if($selectedStop)
            <div class="relative w-full max-w-[360px] h-full bg-white shadow-2xl flex flex-col p-5 overflow-y-auto"
                 x-show="showDrawer"
                 x-transition:enter="transition-transform ease-out duration-300 transform" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition-transform ease-in duration-200 transform" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full">
                
                <!-- Drawer Header -->
                <div class="flex justify-between items-start border-b border-slate-100 pb-3 mb-4 flex-shrink-0 gap-2">
                    <div class="min-w-0">
                        <span class="text-[10px] font-extrabold text-[#003F87] uppercase tracking-widest leading-none">Selected Stop Landmark</span>
                        <h3 class="text-[15.5px] font-black text-slate-800 leading-snug mt-1.5">{{ $selectedStop->name }}</h3>
                    </div>
                    <button @click="showDrawer = false; $wire.closeDrawer()"
                            wire:loading.attr="disabled"
                            wire:target="closeDrawer"
                            class="w-8 h-8 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:text-slate-600 active:scale-90 transition-transform flex-shrink-0 disabled:opacity-60 disabled:pointer-events-none">
                        <i wire:loading.remove wire:target="closeDrawer" class="ti ti-x text-sm"></i>
                        <i wire:loading wire:target="closeDrawer" class="ti ti-loader-2 text-sm animate-spin"></i>
                    </button>
                </div>

                <!-- Drawer Stop Body details -->
                <div class="flex flex-col gap-5 flex-grow">
                    
                    <!-- Proximity status strip -->
                    <div class="flex items-center justify-between p-3.5 bg-slate-50 border border-slate-150 rounded-xl select-none leading-none shrink-0">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider">Distance to Stop</span>
                        @if($selectedStop->distance !== null)
                            <span class="text-sm font-black text-[#003F87]">{{ $selectedStop->distance }} km away</span>
                        @else
                            <span class="text-xs font-extrabold text-slate-400">Location Disabled</span>
                        @endif
                    </div>

                    <!-- Servicing Routes -->
                    <div class="space-y-2">
                        <h4 class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Servicing Routes</h4>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach($servicingRoutes as $route)
                                @php
                                    $color = $route->color ?: '#003F87';
                                @endphp
                                <span class="inline-flex items-center gap-1.5 rounded-lg border border-slate-100 bg-slate-50/50 px-3 py-2 text-xs font-bold text-slate-700 shadow-sm leading-none select-none">
                                    <span class="h-2 w-2 rounded-full flex-shrink-0" style="background-color: {{ $color }}"></span>
                                    <span>{{ $route->name }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>

                    <!-- Next Arriving Bus ETA stub -->
                    <div class="space-y-2.5 flex-grow">
                        <h4 class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Next Arriving Bus</h4>
                        
                        @if($nextBus)
                            <div class="bg-[#F8FBFF] border border-[#003F87]/15 rounded-2xl p-4 flex flex-col gap-3 shadow-sm select-none">
                                <div class="flex justify-between items-center">
                                    <span class="bg-white border border-[#003F87]/10 px-2 py-0.5 rounded text-[11px] font-mono font-bold text-slate-700 tracking-tight shadow-sm">{{ $nextBus->plate_number }}</span>
                                    
                                    @if($nextBus->eta !== null && $nextBus->eta <= 5)
                                        <span class="px-2.5 py-0.5 text-[9.5px] font-extrabold bg-[#EAF3DE] text-[#3B6D11] border border-emerald-100 rounded-full leading-none">Arriving soon</span>
                                    @else
                                        <span class="px-2.5 py-0.5 text-[9.5px] font-extrabold bg-[#E6F1FB] text-[#0C447C] border border-sky-100 rounded-full leading-none">En route</span>
                                    @endif
                                </div>
                                
                                <div class="flex flex-col leading-tight mt-1 text-slate-700">
                                    <span class="text-xs font-bold text-slate-450 leading-none">ETA</span>
                                    <span class="text-xl font-black text-[#003F87] mt-1">{{ $nextBus->eta_label }}</span>
                                    <span class="text-[10px] font-bold text-slate-400 mt-1">{{ $nextBus->eta_description }}</span>
                                </div>
                                
                                <div class="flex items-center gap-1.5 text-xs font-bold text-slate-500 pl-0.5 border-t border-slate-100 pt-2.5">
                                    <i class="ti ti-users text-[13px]"></i>
                                    <span>{{ $nextBus->passengers }} / {{ $nextBus->capacity }} passengers inside</span>
                                </div>
                            </div>
                        @else
                            <div class="bg-slate-50 border border-slate-150 rounded-2xl p-5 text-center flex flex-col items-center justify-center gap-1.5 h-[130px]">
                                <i class="ti ti-bus-off text-slate-350 text-3xl"></i>
                                <span class="text-xs font-bold text-slate-700">No active bus on this route</span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase">Check back during service hours</span>
                            </div>
                        @endif
                    </div>

                    <!-- Tracker redirect link -->
                    <div class="pt-2 border-t border-slate-100 shrink-0">
                        <a href="{{ route('commuter.tracker') }}?stop={{ urlencode($selectedStop->name) }}" class="w-full h-10 bg-[#003F87] text-white font-bold text-xs rounded-xl shadow-sm hover:bg-[#002f66] active:scale-95 transition-all flex items-center justify-center gap-1.5 uppercase tracking-wider">
                            <i class="ti ti-map-pin text-[14px]"></i> Track on Live Map
                        </a>
                    </div>

                </div>
                
            </div>
        @endif
    </div>

</div>

