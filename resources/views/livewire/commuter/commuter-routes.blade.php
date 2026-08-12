<div wire:poll.30s x-data="commuterRoutes()"
    class="max-w-[768px] mx-auto w-full min-h-screen bg-slate-50 flex flex-col pb-24 relative select-none">
    
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

    <!-- SECTION 1 — SEARCH BAR ONLY -->
    <div class="px-4 py-3 bg-white border-b border-slate-200 sticky top-0 z-20">
        <div class="relative w-full">
            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                <i class="ti ti-search text-[16px]"></i>
            </span>
            <input type="text" x-model="searchQuery" placeholder="Search route or stop name..."
                class="w-full pl-10 pr-9 py-2.5 bg-white border border-slate-200 rounded-full text-sm font-normal text-slate-800 placeholder-slate-400 focus:outline-none focus:border-[#003F87] transition-all" />
            <button x-show="searchQuery.length > 0" @click="searchQuery = ''"
                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-400 hover:text-slate-600 active:scale-90 transition-transform">
                <i class="ti ti-x text-[14px]"></i>
            </button>
        </div>
    </div>

    <!-- SECTION 2 — ROUTE FILTER CHIPS -->
    <div class="w-full py-3 bg-slate-50 flex-shrink-0">
        <div class="flex items-center gap-2 px-4 overflow-x-auto no-scrollbar scroll-smooth">
            <!-- All Routes -->
            <button @click="selectedChip = 'All'"
                :class="selectedChip === 'All' ? 'bg-[#003F87] text-white border-none' : 'bg-white text-[#003F87] border border-[#003F87]'"
                class="flex-shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-medium transition-all active:scale-95 shadow-2xs">
                All Routes
            </button>

            <!-- Dynamic Chips -->
            @foreach($routes as $route)
                <button @click="selectedChip = {{ $route['route_id'] }}"
                    :class="selectedChip == {{ $route['route_id'] }} ? 'bg-[#003F87] text-white border-none' : 'bg-white text-[#003F87] border border-[#003F87]'"
                    class="flex-shrink-0 px-3.5 py-1.5 rounded-full text-[13px] font-medium transition-all active:scale-95 shadow-2xs">
                    {{ $route['route_name'] }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- SECTION 3 — ROUTE CARDS LIST -->
    <div class="flex flex-col gap-3 px-4 pb-6 flex-grow">
        @foreach($routes as $route)
            <div x-show="matchesFilter({{ $route['route_id'] }})" x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                class="relative bg-white border border-slate-200 rounded-[14px] p-4 pl-5 shadow-[0_1px_4px_rgba(0,0,0,0.06)] flex flex-col gap-3.5 overflow-hidden transition-all duration-300">
                <!-- Vertical route indicator bar -->
                <div class="absolute left-0 top-0 bottom-0 w-1" style="background-color: {{ $route['route_color'] }};">
                </div>

                <!-- TOP ROW -->
                <div class="flex justify-between items-start">
                    <h2 class="text-[15px] font-medium text-slate-800 leading-snug pr-4">
                        {{ $route['route_name'] }}
                    </h2>
                    <span
                        class="text-[11px] font-semibold text-[#003F87] bg-[#E6F1FB] px-2 py-0.5 rounded-md flex-shrink-0">
                        {{ $route['route_code'] }}
                    </span>
                </div>

                <!-- ROW 2: Origin & Destination -->
                <div class="flex items-center gap-1.5 text-[13px] text-slate-500">
                    <i class="ti ti-map-pin text-[12px] text-slate-400"></i>
                    <span>{{ $route['origin'] }} &rarr; {{ $route['destination'] }}</span>
                </div>

                <!-- ROW 3: Stats -->
                <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-[12px] text-slate-500">
                    <div class="flex items-center gap-1">
                        <i class="ti ti-clock text-slate-400"></i>
                        <span>~{{ $route['est_travel_minutes'] }} min travel time</span>
                    </div>
                    <div class="flex items-center gap-1">
                        <i class="ti ti-map-2 text-slate-400"></i>
                        <span>{{ $route['total_stops'] }} stops</span>
                    </div>
                    <div
                        class="flex items-center gap-1 font-medium {{ $route['active_bus_count'] > 0 ? 'text-[#0F6E56]' : 'text-slate-400' }}">
                        <i class="ti ti-bus"></i>
                        <span>{{ $route['active_bus_count'] }} active
                            {{ Str::plural('bus', $route['active_bus_count']) }}</span>
                    </div>
                </div>

                <!-- ROW 4: Divider -->
                <hr class="border-slate-100" />

                <!-- ROW 5: Next Bus Strip -->
                <div class="flex justify-between items-center text-[12px]">
                    <div class="flex items-center gap-1.5">
                        <span class="text-slate-400">Next bus:</span>
                        @if($route['next_bus_eta_label'])
                            <span class="text-[13px] font-medium text-[#003F87]">{{ $route['next_bus_eta_label'] }}</span>
                        @else
                            <span class="text-[12px] text-slate-400 italic">No active bus</span>
                        @endif
                    </div>

                    <div class="flex items-center gap-1.5">
                        @if($route['active_bus_count'] > 0)
                            <!-- Pulsing Live indicator -->
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-[#0F6E56] opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-[#0F6E56]"></span>
                            </span>
                            <span class="text-[11px] font-medium text-[#0F6E56]">Live</span>
                        @else
                            <span class="h-2 w-2 rounded-full bg-slate-300"></span>
                            <span class="text-[11px] text-slate-400 font-medium">No active bus</span>
                        @endif
                    </div>
                </div>

                <!-- BOTTOM ROW: Details Button -->
                <button wire:click="selectRoute({{ $route['route_id'] }})"
                    wire:loading.attr="disabled"
                    wire:target="selectRoute({{ $route['route_id'] }})"
                    class="w-full mt-1 border border-[#003F87] text-[#003F87] text-[13px] font-medium py-2 px-3 rounded-lg flex items-center justify-between hover:bg-[#003F87]/5 active:scale-[0.98] transition-all disabled:opacity-60 disabled:pointer-events-none">
                    <span wire:loading.remove wire:target="selectRoute({{ $route['route_id'] }})">View route details</span>
                    <span wire:loading wire:target="selectRoute({{ $route['route_id'] }})" class="inline-flex items-center gap-1.5">
                        <i class="ti ti-loader-2 animate-spin"></i>
                        Loading
                    </span>
                    <i wire:loading.remove wire:target="selectRoute({{ $route['route_id'] }})" class="ti ti-chevron-right"></i>
                </button>
            </div>
        @endforeach

        <!-- EMPTY STATE -->
        <div x-show="countVisible() === 0" x-cloak
            class="w-full py-16 px-4 flex flex-col items-center justify-center text-center bg-white border border-slate-200 rounded-[14px] shadow-[0_1px_4px_rgba(0,0,0,0.06)]">
            <i class="ti ti-bus-off text-[48px] text-slate-300 mb-3"></i>
            <h3 class="text-[16px] font-medium text-slate-800">No routes found</h3>
            <p class="text-[14px] text-slate-400 mt-1 max-w-xs">Try a different search or route filter</p>
        </div>
    </div>

    <!-- SECTION 4 — ROUTE DETAIL DRAWER / RIGHT PANEL -->
    <!-- Semi-transparent backdrop -->
    <div x-show="$wire.selectedRouteId" x-cloak @click="$wire.set('selectedRouteId', null)"
        x-transition:enter="transition-opacity ease-out duration-300" x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100" x-transition:leave="transition-opacity ease-in duration-200"
        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
        class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs z-30"></div>

    <!-- Drawer Content Panel -->
    <div x-show="$wire.selectedRouteId" x-cloak x-transition:enter="transition-transform ease-out duration-300"
        x-transition:enter-start="translate-y-full lg:translate-y-0 lg:translate-x-full"
        x-transition:enter-end="translate-y-0 lg:translate-x-0"
        x-transition:leave="transition-transform ease-in duration-200"
        x-transition:leave-start="translate-y-0 lg:translate-x-0"
        x-transition:leave-end="translate-y-full lg:translate-y-0 lg:translate-x-full"
        class="fixed bottom-0 left-0 right-0 lg:left-auto lg:top-0 lg:right-0 lg:w-[400px] lg:h-full max-h-[88vh] lg:max-h-screen bg-white rounded-t-[20px] lg:rounded-t-none lg:rounded-l-[20px] shadow-2xl z-40 flex flex-col premium-transition border border-slate-200">
        <!-- Drag handle (Mobile only) -->
        <div class="w-full flex justify-center py-2.5 lg:hidden flex-shrink-0">
            <div class="w-10 h-1 rounded-full bg-[#D9D6CE]"></div>
        </div>

        @if($selectedRouteId)
            @php
                $selectedRoute = collect($routes)->firstWhere('route_id', $selectedRouteId);

                // Construct Google Maps Static API URL
                $staticMapUrl = '';
                $polylineDb = \App\Models\Route::find($selectedRouteId);
                if ($polylineDb && $polylineDb->polyline_coordinates) {
                    $pathCoords = [];
                    foreach ($polylineDb->polyline_coordinates as $coord) {
                        $pathCoords[] = "{$coord[0]},{$coord[1]}";
                    }
                    $pathString = implode('|', $pathCoords);
                    $colorHex = str_replace('#', '0x', $selectedRoute['route_color']);
                    $staticMapUrl = "https://maps.googleapis.com/maps/api/staticmap?size=600x160&path=color:{$colorHex}|weight:5|{$pathString}&key=" . config('services.google.maps_api_key');
                }
            @endphp

            <!-- DRAWER HEADER -->
            <div class="px-5 py-3 border-b border-slate-100 flex justify-between items-start flex-shrink-0">
                <div class="flex-1 min-w-0 pr-4">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[11px] font-bold text-[#003F87] bg-[#E6F1FB] px-2 py-0.5 rounded-md">
                            {{ $selectedRoute['route_code'] }}
                        </span>
                        <h3 class="text-[16px] font-semibold text-slate-800 truncate">
                            {{ $selectedRoute['route_name'] }}
                        </h3>
                    </div>
                    <p class="text-[13px] text-slate-400 font-medium mt-1">
                        {{ $selectedRoute['origin'] }} &rarr; {{ $selectedRoute['destination'] }}
                    </p>
                </div>
                <button @click="$wire.set('selectedRouteId', null)"
                    wire:loading.attr="disabled"
                    wire:target="selectedRouteId"
                    class="w-7 h-7 rounded-full bg-slate-50 flex items-center justify-center text-slate-400 hover:text-slate-600 active:scale-90 transition-transform disabled:opacity-60 disabled:pointer-events-none">
                    <i wire:loading.remove wire:target="selectedRouteId" class="ti ti-x text-[16px]"></i>
                    <i wire:loading wire:target="selectedRouteId" class="ti ti-loader-2 text-[16px] animate-spin"></i>
                </button>
            </div>

            <!-- DRAWER SCROLLABLE CONTAINER -->
            <div class="flex-1 overflow-y-auto px-5 pb-[100px] pt-3 no-scrollbar flex flex-col gap-5">

                <!-- DRAWER SECTION A — Mini Route Map -->
                <div class="flex flex-col gap-3">
                    <!-- Map Wrapper -->
                    <div class="w-full h-[160px] rounded-lg overflow-hidden border border-slate-200 relative bg-slate-100">
                        <!-- Mobile Static Map Image -->
                        <div class="w-full h-full lg:hidden">
                            @if($staticMapUrl)
                                <img src="{{ $staticMapUrl }}" alt="Static Route Map" class="w-full h-full object-cover" />
                            @else
                                <div class="w-full h-full flex items-center justify-center text-xs text-slate-400 font-medium">
                                    Map unavailable</div>
                            @endif
                        </div>

                        <!-- Desktop Interactive Google Map Canvas -->
                        <div id="drawer-map" class="w-full h-full hidden lg:block" wire:ignore></div>
                    </div>

                    <!-- Row of 3 quick stats -->
                    <div
                        class="flex items-center justify-center gap-4 py-1.5 border border-slate-100 bg-slate-50/50 rounded-lg text-[13px] text-slate-500 font-medium">
                        <span>{{ $selectedRoute['total_stops'] }} stops</span>
                        <div class="w-[1px] h-3 bg-slate-200"></div>
                        <span>~{{ $selectedRoute['est_travel_minutes'] }} min</span>
                        <div class="w-[1px] h-3 bg-slate-200"></div>
                        <span class="{{ $selectedRoute['active_bus_count'] > 0 ? 'text-[#0F6E56] font-semibold' : '' }}">
                            {{ $selectedRoute['active_bus_count'] }} active
                        </span>
                    </div>
                </div>

                <!-- DRAWER SECTION B — Stop Timeline -->
                <div class="flex flex-col gap-2">
                    <h4 class="text-[14px] font-medium text-slate-800">Stops along this route</h4>

                    <div class="flex flex-col gap-0.5 mt-1 pl-1">
                        @foreach($routeStops as $index => $stop)
                            <div class="flex gap-4 items-stretch min-h-[48px]">
                                <!-- Left Indicator Column -->
                                <div class="flex flex-col items-center justify-start w-6 relative flex-shrink-0">
                                    <!-- Connector Line -->
                                    @if($index < count($routeStops) - 1)
                                        <div class="absolute top-3.5 bottom-0 w-[1.5px] bg-[#E0E0E0]"></div>
                                    @endif

                                    @if($index === 0 || $index === count($routeStops) - 1)
                                        <!-- First/Last stop filled circle -->
                                        <div class="h-2.5 w-2.5 rounded-full bg-[#003F87] z-10 my-2"></div>
                                    @else
                                        <!-- Middle stops small filled circle -->
                                        <div class="h-2 w-2 rounded-full bg-[#B0BEC5] z-10 my-2.5"></div>
                                    @endif
                                </div>

                                <!-- Right Stop Info -->
                                <div class="flex-1 pb-4 flex flex-col justify-start">
                                    <span class="text-[14px] font-medium text-slate-800 leading-normal">{{ $stop['stop_name'] }}</span>
                                    <span class="text-[10px] font-extrabold uppercase tracking-wide {{ ($stop['stop_type'] ?? 'designated_stop') === 'pickup_point' ? 'text-[#0F6E56]' : 'text-[#003F87]' }} mt-0.5">
                                        {{ ($stop['stop_type'] ?? 'designated_stop') === 'pickup_point' ? 'Pick-up Point' : 'Designated Stop' }}
                                    </span>
                                    @if($stop['next_bus_eta_label'])
                                        <span class="text-[12px] font-medium text-[#0F6E56] mt-0.5 flex items-center gap-1">
                                            <i class="ti ti-bus text-xs"></i>
                                            <span>{{ $stop['next_bus_eta_label'] }}</span>
                                        </span>
                                    @else
                                        <span class="text-[12px] text-slate-400 italic mt-0.5">No bus approaching</span>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- DRAWER SECTION C — Active Buses on This Route -->
                <div class="flex flex-col gap-2">
                    <h4 class="text-[14px] font-medium text-slate-800">Active buses</h4>

                    <div class="flex items-stretch gap-2.5 overflow-x-auto no-scrollbar py-1">
                        @forelse($activeBuses as $bus)
                            @php
                                $fillRatio = $bus['capacity'] > 0 ? $bus['passengers_onboard'] / $bus['capacity'] : 0;
                                $isOverFull = $fillRatio > 0.8;
                                $barColor = $isOverFull ? 'bg-[#E24B4A]' : 'bg-[#003F87]';
                            @endphp
                            <div
                                class="flex-shrink-0 bg-white border border-slate-200 rounded-lg p-3 w-[170px] flex flex-col gap-2.5 shadow-xs">
                                <!-- Row 1: Bus plate & status chip -->
                                <div class="flex justify-between items-center gap-2">
                                    <span
                                        class="text-[13px] font-mono font-medium text-slate-700">{{ $bus['plate_number'] }}</span>
                                    <span
                                        class="text-[9.5px] font-extrabold px-1.5 py-0.5 rounded-full {{ $bus['status'] === 'On Time' ? 'bg-[#E1F5EE] text-[#0F6E56]' : 'bg-[#FAEEDA] text-[#854F0B]' }}">
                                        {{ $bus['status'] }}
                                    </span>
                                </div>

                                <!-- Row 2: Driver name -->
                                <div class="flex items-center gap-1.5 text-[12px] text-slate-400">
                                    <i class="ti ti-user text-[13px]"></i>
                                    <span class="truncate font-medium text-slate-500">{{ $bus['driver_name'] }}</span>
                                </div>

                                <!-- Row 3: Passenger bar -->
                                <div class="flex flex-col gap-1.5">
                                    <div
                                        class="flex justify-between items-center text-[10.5px] font-semibold {{ $isOverFull ? 'text-[#E24B4A]' : 'text-slate-400' }}">
                                        <span>{{ $bus['passengers_onboard'] }}/{{ $bus['capacity'] }} riders</span>
                                        <span>{{ round($fillRatio * 100) }}%</span>
                                    </div>
                                    <div class="w-full bg-[#E6F1FB] rounded-full h-[4px] overflow-hidden">
                                        <div class="h-full rounded-full transition-all duration-500 ease-out"
                                            style="width: {{ min(100, $fillRatio * 100) }}%;" :class="'{{ $barColor }}'"></div>
                                    </div>
                                </div>

                                <!-- Row 4: Next stop info -->
                                <div class="text-[11px] text-slate-400 font-medium truncate border-t border-slate-50 pt-1.5">
                                    Next: {{ $bus['next_stop_name'] }} &bull; {{ $bus['next_stop_eta_label'] }}
                                </div>
                            </div>
                        @empty
                            <!-- Empty active buses -->
                            <div
                                class="w-full py-5 text-center text-xs text-slate-400 font-medium bg-slate-50 border border-slate-100 rounded-lg flex flex-col items-center justify-center gap-1.5">
                                <i class="ti ti-bus-off text-lg text-slate-300"></i>
                                <span>No active buses on this route</span>
                            </div>
                        @endforelse
                    </div>
                </div>

                <!-- DRAWER SECTION D — Set Arrival Alert -->
                <div x-data="{ expanded: false, alertStopId: '', alertMinutes: '5' }"
                    class="border border-slate-200 rounded-xl p-3.5 bg-slate-50/50 mt-1">
                    <!-- Toggle Row -->
                    <div @click="expanded = !expanded" class="flex justify-between items-center cursor-pointer select-none">
                        <div class="flex items-center gap-2 text-[14px] font-medium text-slate-700">
                            <i class="ti ti-bell text-slate-500 text-[16px]"></i>
                            <span>Set arrival alert</span>
                        </div>
                        <i class="ti ti-chevron-down text-slate-400 transition-transform duration-200"
                            :class="expanded ? 'rotate-180' : ''"></i>
                    </div>

                    <!-- Expanded Content -->
                    <div x-show="expanded" x-transition x-cloak class="mt-3.5 flex flex-col gap-3.5">
                        <label class="text-[13px] text-slate-400 font-medium leading-normal">
                            Notify me when a bus is approaching:
                        </label>

                        <!-- Stop Selector Dropdown -->
                        <select x-model="alertStopId"
                            class="w-full bg-white border border-slate-200 rounded-lg p-2.5 text-xs font-medium text-slate-700 focus:outline-none focus:border-[#003F87] transition-colors">
                            <option value="">Select a stop...</option>
                            @foreach($routeStops as $stop)
                                <option value="{{ is_numeric($stop['stop_id']) ? $stop['stop_id'] : '' }}" @disabled(! is_numeric($stop['stop_id']))>{{ $stop['stop_name'] }} - {{ ($stop['stop_type'] ?? 'designated_stop') === 'pickup_point' ? 'Pick-up Point' : 'Designated Stop' }}</option>
                            @endforeach
                        </select>

                        <!-- Minutes Selector -->
                        <div class="flex flex-col gap-1.5">
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Notify minutes
                                before</span>
                            <div class="flex items-center gap-2">
                                <template x-for="min in ['2', '5', '10']">
                                    <button @click="alertMinutes = min" type="button"
                                        :class="alertMinutes === min ? 'bg-[#003F87] text-white border-none shadow-xs' : 'bg-white text-slate-600 border border-slate-200'"
                                        class="flex-1 py-1.5 rounded-full text-xs font-semibold transition-all active:scale-95"
                                        x-text="min + ' min'"></button>
                                </template>
                            </div>
                        </div>

                        <!-- Set Alert Button -->
                        <button
                            @click="$wire.setArrivalAlert(alertStopId, alertMinutes); alertStopId = ''; expanded = false;"
                            wire:loading.attr="disabled"
                            wire:target="setArrivalAlert"
                            :disabled="!alertStopId"
                            :class="!alertStopId ? 'opacity-40 cursor-not-allowed' : 'active:scale-[0.98]'"
                            class="w-full bg-[#003F87] text-white text-[14px] font-semibold py-2.5 px-4 rounded-lg flex items-center justify-center gap-1.5 shadow-sm transition-all disabled:opacity-60 disabled:pointer-events-none">
                            <i wire:loading.remove wire:target="setArrivalAlert" class="ti ti-bell-plus"></i>
                            <i wire:loading wire:target="setArrivalAlert" class="ti ti-loader-2 animate-spin"></i>
                            <span wire:loading.remove wire:target="setArrivalAlert">Set alert</span>
                            <span wire:loading wire:target="setArrivalAlert">Setting alert</span>
                        </button>
                    </div>
                </div>

            </div>
        @endif
    </div>

    <!-- Scripts Section for Google Maps JS & Notifications -->
    <script>
        function commuterRoutes() {
            return {
                searchQuery: '',
                selectedChip: 'All',
                routes: @json($routes),
                matchesFilter(routeId) {
                    const route = this.routes.find(function (r) { return r.route_id == routeId; });
                    if (!route) return false;

                    const query = this.searchQuery.toLowerCase().trim();
                    const matchesSearch = query === '' ||
                        route.route_name.toLowerCase().includes(query) ||
                        route.route_code.toLowerCase().includes(query) ||
                        route.origin.toLowerCase().includes(query) ||
                        route.destination.toLowerCase().includes(query) ||
                        (route.stop_names && route.stop_names.some(function (name) { return name.toLowerCase().includes(query); }));

                    const matchesChip = this.selectedChip === 'All' || route.route_id == this.selectedChip;
                    return matchesSearch && matchesChip;
                },
                countVisible() {
                    let count = 0;
                    for (let i = 0; i < this.routes.length; i++) {
                        if (this.matchesFilter(this.routes[i].route_id)) {
                            count++;
                        }
                    }
                    return count;
                }
            };
        }

        let drawerMap;
        let drawerPolyline;
        let drawerStopMarkers = [];

        document.addEventListener('livewire:init', () => {
            // Check if google maps script needs loading
            if (typeof google === 'undefined') {
                const script = document.createElement('script');
                script.src = "https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps_api_key') }}";
                script.async = true;
                script.defer = true;
                document.head.appendChild(script);
            }

            // Listen for route details loaded event from Livewire
            Livewire.on('routeDetailsLoaded', (data) => {
                const payload = data[0];

                // Initialize/Update Interactive Map on Desktop only
                setTimeout(() => {
                    const mapEl = document.getElementById('drawer-map');
                    if (!mapEl) return;

                    if (typeof google === 'undefined') return;

                    if (!drawerMap) {
                        drawerMap = new google.maps.Map(mapEl, {
                            center: { lat: 14.5764, lng: 121.0851 },
                            zoom: 12.5,
                            disableDefaultUI: true,
                            styles: [
                                { "featureType": "all", "elementType": "labels.text.fill", "stylers": [{ "color": "#4b5563" }] },
                                { "featureType": "water", "elementType": "geometry.fill", "stylers": [{ "color": "#E0F2FE" }] },
                                { "featureType": "road", "elementType": "geometry.fill", "stylers": [{ "color": "#FFFFFF" }] },
                                { "featureType": "landscape", "elementType": "geometry.fill", "stylers": [{ "color": "#F8FAFC" }] },
                                { "featureType": "poi", "stylers": [{ "visibility": "off" }] }
                            ]
                        });
                    }

                    // Reset previous polyline and markers
                    if (drawerPolyline) drawerPolyline.setMap(null);
                    drawerStopMarkers.forEach(m => m.setMap(null));
                    drawerStopMarkers = [];

                    if (payload.polyline && payload.polyline.length > 0) {
                        const path = payload.polyline.map(c => ({ lat: parseFloat(c[0]), lng: parseFloat(c[1]) }));

                        drawerPolyline = new google.maps.Polyline({
                            path: path,
                            geodesic: true,
                            strokeColor: payload.color,
                            strokeOpacity: 0.95,
                            strokeWeight: 4,
                            map: drawerMap
                        });

                        const bounds = new google.maps.LatLngBounds();
                        payload.stops.forEach((stop, idx) => {
                            const pos = { lat: stop.lat, lng: stop.lng };
                            bounds.extend(pos);

                            const m = new google.maps.Marker({
                                position: pos,
                                map: drawerMap,
                                title: stop.name,
                                icon: {
                                    path: google.maps.SymbolPath.CIRCLE,
                                    scale: idx === 0 || idx === payload.stops.length - 1 ? 5 : 3.5,
                                    fillColor: '#FFFFFF',
                                    fillOpacity: 1,
                                    strokeColor: payload.color,
                                    strokeWeight: 2
                                }
                            });
                            drawerStopMarkers.push(m);
                        });

                        drawerMap.fitBounds(bounds);
                    }
                }, 100);
            });

            // Listen for arrival alert created event
            Livewire.on('alert-created', (data) => {
                const payload = data[0];

                // Local Storage register fallback (for local tracker check if wanted)
                let localAlerts = JSON.parse(localStorage.getItem("gopasig_arrival_alerts") || "[]");
                localAlerts.push({
                    stop: payload.stop_name,
                    minutes: payload.minutes,
                    timestamp: Date.now()
                });
                localStorage.setItem("gopasig_arrival_alerts", JSON.stringify(localAlerts));

                // Send a browser system notification if permissions allowed
                if ("Notification" in window) {
                    Notification.requestPermission().then(permission => {
                        if (permission === "granted") {
                            new Notification("GoPasig Arrival Alert Set!", {
                                body: `We will notify you when a bus is ${payload.minutes} minutes away from ${payload.stop_name}.`,
                                icon: "/images/pasig_logo.png"
                            });
                        } else {
                            GoPasigUI.alert(`GoPasig Alert Set! We will notify you when a bus is ${payload.minutes} minutes away from ${payload.stop_name}.`);
                        }
                    });
                } else {
                    GoPasigUI.alert(`GoPasig Alert Set! We will notify you when a bus is ${payload.minutes} minutes away from ${payload.stop_name}.`);
                }
            });
        });
    </script>
</div>


