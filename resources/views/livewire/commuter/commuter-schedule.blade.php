<div wire:poll.60s 
     class="max-w-[768px] mx-auto w-full min-h-screen bg-slate-50 flex flex-col pb-24 relative select-none px-4 pt-4"
>
    <!-- SECTION 1 — SECTION HEADER + ROUTE FILTER -->
    <div class="flex flex-col gap-3.5 mb-4 flex-shrink-0">
        <div class="flex flex-col gap-0.5">
            <h2 class="text-[18px] font-medium text-slate-800 leading-tight">Schedule</h2>
            <p class="text-[13px] text-slate-400 font-medium">Libreng Sakay departure times</p>
        </div>

        <!-- Horizontal scrollable filter chips row -->
        <div class="w-full flex items-center gap-2 overflow-x-auto no-scrollbar scroll-smooth py-1">
            <!-- All Routes -->
            <button 
                wire:click="filterByRoute(null)" 
                class="flex-shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-medium transition-all active:scale-95 shadow-2xs
                       {{ is_null($activeRouteFilter) ? 'bg-[#003F87] text-white border-none' : 'bg-white text-[#003F87] border border-[#003F87]' }}"
            >
                All Routes
            </button>

            <!-- Dynamic Route Pills -->
            @foreach($routes as $route)
                <button 
                    wire:click="filterByRoute({{ $route['id'] }})" 
                    class="flex-shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-medium transition-all active:scale-95 shadow-2xs
                           {{ $activeRouteFilter === $route['id'] ? 'bg-[#003F87] text-white border-none' : 'bg-white text-[#003F87] border border-[#003F87]' }}"
                >
                    Route {{ $route['id'] }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- SECTION 2 — SCHEDULE TIMETABLE LIST -->
    <div class="flex-grow flex flex-col gap-4 overflow-y-auto no-scrollbar pb-6">
        @forelse($groupedSchedules as $band => $trips)
            <!-- Sticky Band Group Header -->
            <div class="sticky top-0 z-10 bg-slate-50 py-2 border-b border-slate-100 flex items-center">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-[0.06em]">
                    {{ $band }}
                </span>
            </div>

            <!-- List of rows under this band -->
            <div class="flex flex-col gap-3">
                @foreach($trips as $trip)
                    @php
                        $isSelected = $selectedTripId === $trip['trip_id'];
                    @endphp
                    <div 
                        wire:click="selectTrip({{ $trip['trip_id'] }})"
                        class="cursor-pointer transition-all duration-200 border-l-[3.5px] rounded-lg p-3.5 flex gap-3 shadow-2xs border border-slate-200/90
                               {{ $isSelected ? 'border-l-[#003F87] bg-[#F0F6FF] border-slate-200' : 'border-l-transparent bg-white hover:border-slate-300' }}"
                    >
                        <!-- Left Column: Departure Time -->
                        <div class="w-[20%] flex flex-col justify-center">
                            <span class="text-[15px] font-bold text-[#003F87] leading-tight">
                                {{ $trip['departure_time'] }}
                            </span>
                        </div>

                        <!-- Center Column: Route & Status -->
                        <div class="flex-1 flex flex-col gap-1 pr-2">
                            <span class="text-[13px] font-semibold text-slate-800 leading-snug">
                                {{ $trip['route_name'] }}
                            </span>
                            <span class="text-[12px] text-slate-400 font-medium leading-none">
                                {{ $trip['stop_count'] }} stops &bull; Est. travel time {{ $trip['estimated_duration_minutes'] }} min
                            </span>
                            
                            <!-- Status Indicator -->
                            <div class="flex items-center gap-1 mt-1 text-[12px] font-semibold">
                                @if($trip['status'] === 'on_time')
                                    <span class="flex items-center gap-1 text-[#0F6E56] bg-[#E1F5EE] px-2 py-0.5 rounded-full text-[11px]">
                                        <i class="ti ti-circle-check"></i> On time
                                    </span>
                                @elseif($trip['status'] === 'delayed')
                                    <span class="flex items-center gap-1 text-[#854F0B] bg-[#FAEEDA] px-2 py-0.5 rounded-full text-[11px]">
                                        <i class="ti ti-clock-exclamation"></i> Delayed &bull; ~{{ $trip['delay_minutes'] }} min
                                    </span>
                                @else
                                    <span class="flex items-center gap-1 text-[#A32D2D] bg-[#FCEBEB] px-2 py-0.5 rounded-full text-[11px]">
                                        <i class="ti ti-circle-x"></i> Cancelled
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Right Column: Arrival Time & Chevron -->
                        <div class="w-[22%] flex flex-col items-end justify-center gap-1.5 flex-shrink-0">
                            <span class="text-[12.5px] font-semibold text-slate-400 whitespace-nowrap leading-none">
                                Est. {{ $trip['estimated_arrival_time'] }}
                            </span>
                            <i class="ti ti-chevron-right text-[14px] text-slate-400"></i>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <!-- Empty timetable fallback -->
            <div class="w-full py-16 flex flex-col items-center justify-center text-center bg-white border border-slate-200 rounded-xl shadow-2xs">
                <i class="ti ti-calendar-off text-[48px] text-slate-300 mb-3"></i>
                <h3 class="text-[15px] font-semibold text-slate-800">No trips scheduled</h3>
                <p class="text-[13px] text-slate-400 mt-1 max-w-xs">There are no operational departures matching this filter right now.</p>
            </div>
        @endforelse
    </div>

    <!-- SECTION 3 — SELECTED TRIP DETAIL CARD -->
    @if($selectedTrip)
        <div 
            class="bg-white border border-slate-200 rounded-xl p-4 flex flex-col gap-4 shadow-sm mt-3 animate-fade-in"
            style="transition: max-height 250ms ease-out;"
        >
            <!-- Card Header Row -->
            <div class="flex justify-between items-center border-b border-slate-100 pb-2.5">
                <span class="text-[15px] font-bold text-slate-800 flex items-center gap-1.5">
                    <i class="ti ti-info-circle text-[#003F87]"></i> Trip details
                </span>
                <button 
                    wire:click="selectTrip(null)" 
                    class="w-6.5 h-6.5 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:text-slate-600 active:scale-90 transition-transform"
                >
                    <i class="ti ti-x text-[14px]"></i>
                </button>
            </div>

            <!-- Content Grid (Side-by-side on desktop md+, stacked on sm) -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                
                <!-- LEFT SUB-SECTION — Stop Timeline -->
                <div class="flex flex-col gap-3">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-[0.05em]">Stop timeline</span>
                    
                    <div class="flex flex-col pl-1">
                        @php
                            $timelineStops = collect($selectedTrip['stops'])->take(6);
                            $totalStops = count($selectedTrip['stops']);
                        @endphp
                        
                        @foreach($timelineStops as $index => $stop)
                            <div class="flex gap-3.5 items-stretch min-h-[44px]">
                                <!-- Left Circle & Line Indicator -->
                                <div class="flex flex-col items-center justify-start w-6 relative flex-shrink-0">
                                    <!-- Dashed Line -->
                                    @if($index < count($timelineStops) - 1)
                                        <div class="absolute top-3.5 bottom-0 w-[1.5px] border-l-2 border-dashed border-[#CBD5E1]"></div>
                                    @endif

                                    @if($stop['stop_status'] === 'departed')
                                        <!-- Departed Stop: Filled circle -->
                                        <div class="h-2.5 w-2.5 rounded-full bg-[#003F87] z-10 my-1.5 shadow-sm"></div>
                                    @elseif($stop['stop_status'] === 'current')
                                        <!-- Current Stop: Pulsing circle -->
                                        <div class="relative flex h-3.5 w-3.5 items-center justify-center z-10 my-1">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#378ADD] opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-3 w-3 bg-[#378ADD] border border-white"></span>
                                        </div>
                                    @else
                                        <!-- Upcoming Stop: Hollow circle -->
                                        <div class="h-2.5 w-2.5 rounded-full border-2 border-[#CBD5E1] bg-white z-10 my-1.5"></div>
                                    @endif
                                </div>

                                <!-- Right Stop details -->
                                <div class="flex-1 pb-3 flex flex-col justify-start">
                                    <span class="text-[13px] font-semibold text-slate-800 leading-none">{{ $stop['stop_name'] }}</span>
                                    <span class="text-[11.5px] text-slate-400 font-semibold mt-1">Est. {{ $stop['estimated_time'] }}</span>
                                </div>
                            </div>
                        @endforeach

                        @if($totalStops > 6)
                            <div class="pl-9 pt-1.5">
                                <a href="#" class="text-[13px] font-bold text-[#003F87] hover:underline">
                                    View all {{ $totalStops }} stops
                                </a>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- RIGHT SUB-SECTION — Trip Info -->
                <div class="flex flex-col gap-3.5">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-[0.05em]">Trip info</span>

                    <div class="flex flex-col gap-3 bg-slate-50/50 border border-slate-100 rounded-xl p-3.5">
                        <!-- Route -->
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.05em] leading-none">Route</span>
                            <div class="flex items-center gap-1.5 mt-0.5">
                                <span class="h-2 w-2 rounded-full flex-shrink-0" style="background-color: {{ $selectedTrip['route_color'] }};"></span>
                                <span class="text-[13px] font-semibold text-slate-700">{{ $selectedTrip['route_name'] }}</span>
                            </div>
                        </div>

                        <!-- Departure -->
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.05em] leading-none">Departure</span>
                            <span class="text-[13px] font-semibold text-slate-700 mt-0.5">
                                {{ $selectedTrip['departure_time'] }} from Terminal
                            </span>
                        </div>

                        <!-- Est. Arrival -->
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.05em] leading-none">Est. Arrival</span>
                            <span class="text-[13px] font-semibold text-slate-700 mt-0.5">
                                {{ $selectedTrip['estimated_arrival_time'] }} at Destination
                            </span>
                        </div>

                        <!-- Duration -->
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.05em] leading-none">Duration</span>
                            <span class="text-[13px] font-semibold text-slate-700 mt-0.5">
                                {{ $selectedTrip['estimated_duration_minutes'] }} minutes
                            </span>
                        </div>

                        <!-- Status -->
                        <div class="flex flex-col gap-1">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.05em] leading-none">Status</span>
                            <div class="mt-0.5">
                                @if($selectedTrip['status'] === 'on_time')
                                    <span class="inline-flex items-center gap-1 text-[#0F6E56] bg-[#E1F5EE] px-2.5 py-0.5 rounded-full text-[11px] font-bold border border-emerald-100">
                                        <i class="ti ti-circle-check"></i> On time
                                    </span>
                                @elseif($selectedTrip['status'] === 'delayed')
                                    <span class="inline-flex items-center gap-1 text-[#854F0B] bg-[#FAEEDA] px-2.5 py-0.5 rounded-full text-[11px] font-bold border border-amber-100">
                                        <i class="ti ti-clock-exclamation"></i> Delayed
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[#A32D2D] bg-[#FCEBEB] px-2.5 py-0.5 rounded-full text-[11px] font-bold border border-rose-100">
                                        <i class="ti ti-circle-x"></i> Cancelled
                                    </span>
                                @endif
                            </div>
                        </div>

                        <!-- Delay Info (Conditional) -->
                        @if($selectedTrip['status'] === 'delayed')
                            <div class="flex items-center gap-1 text-[12px] font-bold text-[#854F0B]">
                                <i class="ti ti-clock text-sm"></i>
                                <span>Approx. {{ $selectedTrip['delay_minutes'] }} min delay</span>
                            </div>
                        @endif

                        <!-- Bus Assigned -->
                        <div class="flex flex-col gap-0.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-[0.05em] leading-none">Bus assigned</span>
                            @if($selectedTrip['bus_plate'])
                                <span class="text-[13px] font-mono font-bold text-slate-700 mt-0.5">
                                    {{ $selectedTrip['bus_plate'] }}
                                </span>
                            @else
                                <span class="text-[13px] text-slate-400 font-medium italic mt-0.5">
                                    Not yet assigned
                                </span>
                            @endif
                        </div>
                    </div>
                </div>

            </div>

            <!-- Bottom Row: Alert Button -->
            <div class="mt-2 border-t border-slate-100 pt-3 flex justify-end">
                @php
                    $isAlertSet = in_array($selectedTrip['trip_id'], $setAlerts);
                @endphp
                
                @if($isAlertSet)
                    <button 
                        disabled
                        class="w-full md:w-auto px-5 py-2.5 bg-emerald-50 border border-emerald-300 text-[#0F6E56] font-bold text-[13px] rounded-lg flex items-center justify-center gap-1.5 cursor-not-allowed"
                    >
                        <i class="ti ti-bell-check text-base"></i> Alert set
                    </button>
                @else
                    <button 
                        wire:click="setAlert({{ $selectedTrip['trip_id'] }})"
                        class="w-full md:w-auto px-5 py-2.5 border border-[#003F87] text-[#003F87] bg-white hover:bg-[#003F87]/5 active:scale-95 transition-all font-bold text-[13px] rounded-lg flex items-center justify-center gap-1.5 shadow-2xs"
                    >
                        <i class="ti ti-bell text-base"></i> Set arrival alert
                    </button>
                @endif
            </div>

        </div>
    @endif
</div>
