@extends('layouts.driver')

@section('title', 'GoPasig - Active Trip driving panel')

@section('content')
<div x-data="{ 
    showModal: false, 
    selectedType: '{{ \App\Models\Incident::TYPES[0] ?? "Breakdown" }}', 
    description: '', 
    isSubmitting: false,
    toast: { show: false, message: '', type: 'success' },
    triggerToast(msg, type = 'success') {
        this.toast.message = msg;
        this.toast.type = type;
        this.toast.show = true;
        setTimeout(() => { this.toast.show = false; }, 4000);
    },
    submitIncident() {
        if (this.isSubmitting) return;
        this.isSubmitting = true;
        
        fetch('{{ route('driver.trip.incident') }}', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=&quot;csrf-token&quot;]').getAttribute('content')
            },
            body: JSON.stringify({
                type: this.selectedType,
                description: this.description
            })
        })
        .then(response => response.json())
        .then(data => {
            this.isSubmitting = false;
            if (data.success) {
                this.showModal = false;
                this.triggerToast(data.message || 'Incident reported to Dispatch!');
                
                // If it is a breakdown, automatically update UI and turn off live trip session
                if (this.selectedType === 'Breakdown') {
                    const layoutDot = document.querySelector('.status-indicator-dot');
                    const layoutText = document.querySelector('.status-indicator-text');
                    const btn = document.getElementById('btn-toggle-tracking');
                    const desc = document.getElementById('tracking-desc-text');
                    
                    if (layoutDot) {
                        layoutDot.className = 'relative inline-flex rounded-full h-2 w-2 bg-rose-500 status-indicator-dot';
                        layoutText.innerText = 'BREAKDOWN';
                        layoutText.className = 'status-indicator-text text-rose-500 font-bold';
                    }
                    
                    if (typeof stopSimulation === 'function') {
                        stopSimulation();
                    }
                    
                    isTrackingActive = false;
                    if (btn) {
                        btn.innerText = 'START LIVE TRIP SESSION';
                        btn.className = 'w-full py-4 rounded-xl font-black text-[15px] tracking-wide shadow-md border premium-transition active:scale-[0.98] bg-[#003F87] hover:bg-[#0050a3] text-white border-[#003F87]/15 shadow-[0_4px_16px_rgba(0,63,135,0.15)]';
                    }
                    if (desc) {
                        desc.innerText = 'Offline — Breakdown declared.';
                        desc.className = 'text-[11px] text-rose-500 font-black';
                    }
                }
                
                this.description = '';
            } else {
                this.triggerToast(data.message || 'An error occurred.', 'error');
            }
        })
        .catch(err => {
            this.isSubmitting = false;
            this.triggerToast('Failed to report incident. Try again.', 'error');
            console.error(err);
        });
    }
}" class="flex flex-col gap-5 px-4 pt-4 pb-8 select-none relative">
    
    <!-- CSRF Token for JavaScript Requests -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- HEADER TITLE & SUB -->
    <div class="flex justify-between items-center">
        <div class="flex flex-col gap-0.5">
            <span class="text-[10px] font-extrabold text-[#003F87] uppercase tracking-widest">Active Drive</span>
            <h1 class="text-xl font-black text-slate-800 tracking-tight leading-none">GPS Trip Control</h1>
        </div>
        
        <!-- Live status ping -->
        <div id="live-trip-ping" class="hidden flex items-center gap-1.5 px-2.5 py-0.5 bg-rose-50 text-rose-600 border border-rose-100 rounded-md text-[10px] font-extrabold uppercase tracking-wide">
            <span class="relative flex h-1.5 w-1.5">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-500 opacity-75"></span>
                <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-rose-500"></span>
            </span>
            Transmitting GPS
        </div>
    </div>

    @if($driver && $driver->assigned_bus && $bus)
        <!-- TRIP CONTROL TOGGLE BUTTON -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] flex flex-col gap-4">
            <div class="flex justify-between items-start">
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Tracking Session</span>
                    <span class="text-[11px] text-slate-450 font-semibold" id="tracking-desc-text">Offline — coordinates are frozen.</span>
                </div>
                <div class="w-8 h-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-450">
                    <i class="ti ti-satellite text-lg" id="satellite-icon"></i>
                </div>
            </div>

            <button id="btn-toggle-tracking" onclick="toggleTracking()" class="w-full py-4 rounded-xl font-black text-[15px] tracking-wide shadow-md border premium-transition active:scale-[0.98]
                {{ $bus->status === 'active' ? 'bg-rose-600 hover:bg-rose-500 text-white border-rose-500/20 shadow-[0_4px_16px_rgba(225,29,72,0.2)]' : 'bg-[#003F87] hover:bg-[#0050a3] text-white border-[#003F87]/15 shadow-[0_4px_16px_rgba(0,63,135,0.15)]' }}">
                {{ $bus->status === 'active' ? 'STOP LIVE TRIP SESSION' : 'START LIVE TRIP SESSION' }}
            </button>
        </div>

        <!-- TWO COLUMN INTERACTIVE PANEL: SPEED & PASSENGERS -->
        <div class="grid grid-cols-2 gap-3.5">
            
            <!-- Left: Speedometer Widget -->
            <div class="bg-white border border-slate-100 rounded-2xl p-4.5 flex flex-col items-center justify-center gap-2 shadow-[0_4px_24px_rgba(15,23,42,0.02)] relative overflow-hidden">
                <span class="text-[9.5px] font-extrabold text-slate-400 uppercase tracking-widest text-center">Live Speed</span>
                
                <!-- Speed Radial UI -->
                <div class="relative w-24 h-24 flex flex-col items-center justify-center mt-1">
                    <!-- Outer Dashed Ring -->
                    <div class="absolute inset-0 rounded-full border-4 border-slate-200 border-dashed animate-[spin_40s_linear_infinite]"></div>
                    <div class="absolute inset-2 rounded-full border border-[#003F87]/10"></div>
                    
                    <div class="flex flex-col items-center leading-none z-10">
                        <span class="text-3xl font-black font-mono text-slate-800" id="speed-display">0</span>
                        <span class="text-[9.5px] font-bold text-slate-400 uppercase mt-0.5 font-mono">km/h</span>
                    </div>
                </div>

                <div class="text-[10px] font-semibold text-slate-450 mt-0.5" id="speed-status-text">SHUTTLE IDLE</div>
            </div>

            <!-- Right: Interactive Passenger Occupancy Counter -->
            <div class="bg-white border border-slate-100 rounded-2xl p-4.5 flex flex-col justify-between gap-3 shadow-[0_4px_24px_rgba(15,23,42,0.02)]">
                <div class="flex flex-col gap-0.5">
                    <span class="text-[9.5px] font-extrabold text-slate-400 uppercase tracking-widest">Passenger Load</span>
                    <div class="flex items-baseline gap-1 mt-1 leading-none">
                        <span class="text-3xl font-black font-mono text-slate-800" id="pax-count">{{ $bus->passengers }}</span>
                        <span class="text-xs text-slate-450 font-bold">/ <span id="pax-cap">{{ $bus->capacity }}</span></span>
                    </div>
                </div>

                <!-- Progress occupancy bar -->
                @php
                    $paxPercent = $bus->capacity > 0 ? ($bus->passengers / $bus->capacity) * 100 : 0;
                    $warningLimit = \App\Models\Bus::OCCUPANCY_WARNING_THRESHOLD;
                    $criticalLimit = \App\Models\Bus::OCCUPANCY_CRITICAL_THRESHOLD;
                    
                    $paxColor = 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.25)]';
                    if($paxPercent >= $warningLimit && $paxPercent < $criticalLimit) {
                        $paxColor = 'bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.25)]';
                    } elseif ($paxPercent >= $criticalLimit) {
                        $paxColor = 'bg-rose-500 shadow-[0_0_8px_rgba(239,68,68,0.25)]';
                    }
                @endphp
                <div class="w-full bg-slate-100 rounded-full h-[6px] overflow-hidden">
                    <div class="h-full rounded-full transition-all duration-300 {{ $paxColor }}" id="pax-bar" style="width: {{ $paxPercent }}%;"></div>
                </div>

                <!-- Large Big Action Buttons -->
                <div class="flex gap-2">
                    <button onclick="changePassengers(-1)" class="flex-grow py-2 rounded-xl bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-700 font-black text-lg active:scale-90 transition-transform">
                        -
                    </button>
                    <button onclick="changePassengers(1)" class="flex-grow py-2 rounded-xl bg-[#003F87] text-white font-black text-lg hover:bg-[#0050a3] active:scale-90 transition-transform">
                        +
                    </button>
                </div>
            </div>

        </div>

        <!-- INTERACTIVE STOP ARRIVAL CONTROL -->
        @if($route)
            <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] flex flex-col gap-4">
                <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                    <i class="ti ti-map-pin text-[#003F87] text-[18px]"></i>
                    <span class="text-xs font-black text-slate-700 uppercase tracking-widest">Next Stop Dispatch</span>
                </div>

                <!-- Active Stop indicator -->
                <div class="flex flex-col gap-1 px-3 py-2.5 bg-slate-50 border border-slate-100 rounded-xl">
                    <span class="text-[9.5px] font-extrabold text-slate-450 uppercase tracking-widest leading-none">Arriving Next Stop</span>
                    <span class="text-[15px] font-black text-[#003F87] leading-tight mt-1" id="active-stop-label">
                        {{ $bus->next_stop ?: 'Not Dispatched yet' }}
                    </span>
                    @php
                        $defaultEta = 5;
                        if ($route && $route->stops->count() > 1) {
                            $defaultEta = (int) round(($route->travel_time_minutes ?? 30) / ($route->stops->count() - 1));
                        }
                        $defaultEta = max(1, $defaultEta);
                    @endphp
                    <div class="flex justify-between items-center mt-2.5 pt-2 border-t border-slate-200 text-[11px] font-bold text-slate-500">
                        <span>ETA to Stop</span>
                        <div class="flex items-center gap-1.5">
                            <input type="number" id="eta-input" min="1" max="60" value="{{ $bus->eta ?: $defaultEta }}" onchange="updateNextStop()" class="w-12 h-6 px-1 text-center bg-white border border-slate-250 rounded-md text-slate-800 font-mono font-bold focus:outline-none focus:border-[#003F87]">
                            <span>mins</span>
                        </div>
                    </div>
                </div>

                <!-- Stops Stack Scroll (Radio Picker) -->
                <div class="flex flex-col gap-2.5 max-h-[175px] overflow-y-auto pr-1 no-scrollbar">
                    @forelse($route->stops->sortBy('sequence') as $stop)
                        <label class="flex items-center justify-between p-3 bg-slate-50/40 hover:bg-slate-50 rounded-xl border border-slate-100 cursor-pointer active:scale-[0.99] transition-transform select-none">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="next_stop_radio" value="{{ $stop->name }}" 
                                    {{ $bus->next_stop === $stop->name ? 'checked' : '' }} 
                                    onchange="selectNextStop('{{ $stop->name }}')"
                                    class="w-4 h-4 text-[#003F87] bg-white border-slate-250 focus:ring-[#003F87]/20 focus:ring-2">
                                <div class="flex flex-col">
                                    <span class="text-xs font-bold text-slate-700">{{ $stop->name }}</span>
                                    <span class="text-[9px] font-semibold text-slate-450">Stop Order #{{ $stop->sequence }}</span>
                                </div>
                            </div>
                            <span class="text-[10px] font-extrabold text-slate-400 font-mono">STOP</span>
                        </label>
                    @empty
                        <div class="py-4 text-center text-xs text-slate-450 font-bold">No stops found along this route.</div>
                    @endforelse
                </div>
            </div>
        @endif

        <!-- QUICK INCIDENT / SERVICE DISRUPTION REPORT CARD -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] flex flex-col gap-3">
            <div class="flex items-center gap-2 pb-2 border-b border-slate-100">
                <i class="ti ti-alert-triangle text-rose-500 text-[18px]"></i>
                <span class="text-xs font-black text-slate-700 uppercase tracking-widest">Report Service Incident</span>
            </div>
            
            <p class="text-[11.5px] text-slate-450 font-semibold leading-relaxed">
                Encountered a breakdown, traffic delay, accident, or detour? Report it instantly to notify the dispatch live tracker map.
            </p>

            <button @click="showModal = true" class="w-full py-3.5 rounded-xl bg-slate-50 border border-slate-200 text-rose-600 hover:bg-rose-50 hover:border-rose-100 hover:text-rose-700 font-black text-xs tracking-wider uppercase transition-all duration-200 active:scale-[0.98] flex items-center justify-center gap-2 cursor-pointer">
                <i class="ti ti-alert-circle text-base"></i>
                <span>Quick Report Disruption</span>
            </button>
        </div>
    @else
        <!-- No assigned bus -->
        <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-md text-center py-10 flex flex-col items-center justify-center">
            <i class="ti ti-lock-square-rounded text-slate-300 text-5xl mb-3"></i>
            <h2 class="text-base font-black text-slate-800 leading-tight">Shuttle Locked</h2>
            <p class="text-xs text-slate-450 font-semibold mt-1 px-4 text-center">
                You do not have a bus assigned for active tracking today. Contact dispatcher {{ $dispatcherName }} at fleet operations to assign a shuttle.
            </p>
        </div>
    @endif

    <!-- MOBILE BOTTOM SLIDE-UP SHEET OVERLAY -->
    <div x-show="showModal" 
         class="absolute inset-0 z-50 flex flex-col justify-end" 
         style="display: none;">
        
        <!-- Semi-transparent dark glass backdrop -->
        <div x-show="showModal"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="showModal = false"
             class="absolute inset-0 bg-slate-900/60 backdrop-blur-xs"></div>

        <!-- Slide-up Content Container -->
        <div x-show="showModal"
             x-transition:enter="transition ease-out duration-300 transform"
             x-transition:enter-start="translate-y-full"
             x-transition:enter-end="translate-y-0"
             x-transition:leave="transition ease-in duration-200 transform"
             x-transition:leave-start="translate-y-0"
             x-transition:leave-end="translate-y-full"
             class="relative bg-white rounded-t-3xl p-5 pb-8 shadow-[0_-8px_30px_rgba(15,23,42,0.15)] flex flex-col gap-4 max-h-[90%] z-10 select-none">
            
            <!-- iOS Drag Notch -->
            <div class="w-12 h-1 bg-slate-200 rounded-full mx-auto mb-1 shrink-0"></div>

            <!-- Header -->
            <div class="flex justify-between items-center shrink-0">
                <div class="flex flex-col gap-0.5">
                    <span class="text-[10px] font-extrabold text-rose-500 uppercase tracking-widest">Report Alert</span>
                    <h3 class="text-lg font-black text-slate-800 tracking-tight leading-none">Declare Disruption</h3>
                </div>
                <button @click="showModal = false" class="w-8 h-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-450 hover:bg-slate-100 hover:text-slate-700 active:scale-95 transition-transform">
                    <i class="ti ti-x text-lg"></i>
                </button>
            </div>

            <!-- Interactive Grid Selector for Incident Type -->
            <div class="flex flex-col gap-1.5 mt-1 shrink-0">
                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Select Incident Type</span>
                <div class="grid grid-cols-2 gap-2.5">
                    @foreach(\App\Models\Incident::TYPES_METADATA as $typeName => $meta)
                        <button type="button" @click="selectedType = '{{ $typeName }}'" 
                                class="p-3.5 rounded-xl border-2 flex flex-col items-center justify-center gap-1.5 transition-all duration-200 active:scale-95 text-center cursor-pointer font-sans"
                                :class="selectedType === '{{ $typeName }}' ? '{{ $meta['active_class'] }} shadow-sm' : 'bg-slate-55/50 border-slate-100 text-slate-500 hover:bg-slate-50 hover:border-slate-200'">
                            <i class="ti {{ $meta['icon'] }} text-2xl" :class="selectedType === '{{ $typeName }}' ? '{{ $meta['icon_active'] }}' : 'text-slate-400'"></i>
                            <span class="text-xs font-black uppercase tracking-wide">
                                {{ $typeName === 'Heavy Traffic Delay' ? 'Traffic Delay' : ($typeName === 'Passenger Concern' ? 'Concern' : $typeName) }}
                            </span>
                        </button>
                    @endforeach
                </div>
            </div>

            <!-- Optional context / description input -->
            <div class="flex flex-col gap-1.5 shrink-0">
                <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest font-sans">Incident Description</span>
                <textarea x-model="description" 
                          placeholder="Provide details or current location... (e.g. Engine radiator overheating near Shaw Blvd)" 
                          class="w-full px-3.5 py-3 rounded-xl border border-slate-200 bg-slate-50 text-xs font-bold text-slate-800 focus:bg-white focus:outline-none focus:border-[#003F87] placeholder-slate-400 no-scrollbar transition-all duration-200 font-sans" 
                          rows="3"></textarea>
            </div>

            <!-- Action buttons -->
            <div class="flex gap-3 mt-2 shrink-0">
                <button type="button" @click="showModal = false" 
                        class="flex-1 py-3.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 font-black text-xs tracking-wider uppercase transition-colors active:scale-95 cursor-pointer font-sans">
                    Cancel
                </button>
                
                <button type="button" @click="submitIncident()" :disabled="isSubmitting" 
                        class="flex-1 py-3.5 rounded-xl bg-rose-600 hover:bg-rose-500 disabled:bg-rose-400 text-white border border-rose-600/10 font-black text-xs tracking-wider uppercase shadow-[0_4px_16px_rgba(225,29,72,0.2)] transition-all active:scale-95 flex items-center justify-center gap-1.5 disabled:opacity-50 cursor-pointer font-sans">
                    <span x-show="!isSubmitting" class="flex items-center gap-1.5">
                        <i class="ti ti-send text-sm"></i>
                        <span>Transmit Alert</span>
                    </span>
                    <span x-show="isSubmitting" class="flex items-center gap-1.5 animate-pulse">
                        <svg class="animate-spin h-3.5 w-3.5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Transmitting...</span>
                    </span>
                </button>
            </div>

        </div>
    </div>

    <!-- Floating Success/Error Toast -->
    <div x-show="toast.show" 
         x-transition:enter="transition ease-out duration-300 transform"
         x-transition:enter-start="-translate-y-12 opacity-0"
         x-transition:enter-end="translate-y-0 opacity-100"
         x-transition:leave="transition ease-in duration-200 transform"
         x-transition:leave-start="translate-y-0 opacity-100"
         x-transition:leave-end="-translate-y-12 opacity-0"
         class="absolute top-4 left-4 right-4 z-50 pointer-events-none"
         style="display: none;">
        <div class="flex items-center gap-2.5 px-4 py-3 rounded-xl border shadow-lg backdrop-blur-md"
             :class="toast.type === 'success' ? 'bg-emerald-500/90 text-white border-emerald-500/10' : 'bg-rose-500/90 text-white border-rose-500/10'">
            <i class="text-lg" :class="toast.type === 'success' ? 'ti ti-circle-check' : 'ti ti-alert-circle'"></i>
            <span class="text-xs font-black tracking-wide uppercase" x-text="toast.message"></span>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    // Dynamic config constants from database models
    const warningLimit = {{ \App\Models\Bus::OCCUPANCY_WARNING_THRESHOLD }};
    const criticalLimit = {{ \App\Models\Bus::OCCUPANCY_CRITICAL_THRESHOLD }};
    const fastSpeedThreshold = {{ \App\Models\Bus::SPEED_FAST_THRESHOLD }};
    const gpsSyncInterval = {{ \App\Models\Bus::GPS_SYNC_INTERVAL_MS }};
    const speedSimInterval = {{ \App\Models\Bus::SPEED_SIMULATION_INTERVAL_MS }};
    const simSpeedMin = {{ \App\Models\Bus::SIM_SPEED_MIN }};
    const simSpeedMax = {{ \App\Models\Bus::SIM_SPEED_MAX }};
    
    @php
        $defaultStop = \App\Models\Stop::first();
        $fallbackLat = $defaultStop ? $defaultStop->lat : 14.5593;
        $fallbackLng = $defaultStop ? $defaultStop->lng : 121.0805;
    @endphp
    const fallbackLat = {{ $fallbackLat }};
    const fallbackLng = {{ $fallbackLng }};

    // State indicators
    let isTrackingActive = "{{ isset($bus) && $bus->status === 'active' ? 'true' : 'false' }}" === 'true';
    let simulationTimer = null;
    let speedTimer = null;
    let currentSpeed = 0;
    let targetSpeed = 0;
    
    // HTML5 Geolocation API state
    let geoWatchId = null;
    let isRealSpeedActive = false;
    let lastDeviceLat = null;
    let lastDeviceLng = null;

    // Dynamic Coordinates Path array from database (with fallback integrated)
    const mockRouteCoords = @json($gpsCoords);
    let mockCoordIndex = 0;

    document.addEventListener("DOMContentLoaded", function() {
        if (isTrackingActive) {
            startSimulation();
        }
    });

    // Interactive Passenger Occupancy Counter
    function changePassengers(change) {
        const paxCountEl = document.getElementById('pax-count');
        const paxCapEl = document.getElementById('pax-cap');
        const paxBarEl = document.getElementById('pax-bar');
        if (!paxCountEl || !paxCapEl) return;

        const currentPax = parseInt(paxCountEl.innerText) || 0;
        const capacity = parseInt(paxCapEl.innerText) || 0;

        const targetPax = currentPax + change;
        if (targetPax < 0 || targetPax > capacity) {
            return;
        }

        fetch("{{ route('driver.trip.pax') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ change: change })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                paxCountEl.innerText = data.passengers;
                const percent = capacity > 0 ? (data.passengers / capacity) * 100 : 0;
                if (paxBarEl) {
                    paxBarEl.style.width = percent + '%';
                    paxBarEl.className = 'h-full rounded-full transition-all duration-300';
                    if (percent >= warningLimit && percent < criticalLimit) {
                        paxBarEl.classList.add('bg-amber-500', 'shadow-[0_0_8px_rgba(245,158,11,0.25)]');
                    } else if (percent >= criticalLimit) {
                        paxBarEl.classList.add('bg-rose-500', 'shadow-[0_0_8px_rgba(239,68,68,0.25)]');
                    } else {
                        paxBarEl.classList.add('bg-emerald-500', 'shadow-[0_0_8px_rgba(16,185,129,0.25)]');
                    }
                }
            }
        })
        .catch(err => console.error("Error updating passenger count:", err));
    }

    // Interactive Stop & ETA updates
    function selectNextStop(name) {
        const activeStopLabel = document.getElementById('active-stop-label');
        if (activeStopLabel) {
            activeStopLabel.innerText = name;
        }
        updateNextStop();
    }

    function updateNextStop() {
        const activeStopLabel = document.getElementById('active-stop-label');
        const etaInput = document.getElementById('eta-input');
        if (!activeStopLabel || !etaInput) return;

        fetch("{{ route('driver.trip.stop') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                next_stop: activeStopLabel.innerText,
                eta: etaInput.value
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log("Stop and ETA updated successfully:", data);
            }
        })
        .catch(err => console.error("Error updating next stop:", err));
    }

    function startGPSWatch() {
        if ("geolocation" in navigator) {
            geoWatchId = navigator.geolocation.watchPosition(
                (position) => {
                    lastDeviceLat = position.coords.latitude;
                    lastDeviceLng = position.coords.longitude;
                    
                    let speedMps = position.coords.speed;
                    if (speedMps !== null && speedMps >= 0) {
                        isRealSpeedActive = true;
                        currentSpeed = Math.round(speedMps * 3.6);
                        document.getElementById('speed-display').innerText = currentSpeed;
                        
                        const speedStatus = document.getElementById('speed-status-text');
                        if (currentSpeed === 0) {
                            speedStatus.innerText = "SHUTTLE IDLE";
                            speedStatus.className = "text-[10px] font-semibold text-slate-400 mt-0.5";
                        } else if (currentSpeed > fastSpeedThreshold) {
                            speedStatus.innerText = "CRUISING FAST";
                            speedStatus.className = "text-[10px] font-semibold text-amber-600 mt-0.5";
                        } else {
                            speedStatus.innerText = "DRIVING SLOW";
                            speedStatus.className = "text-[10px] font-semibold text-emerald-600 mt-0.5";
                        }
                    }
                },
                (error) => {
                    console.warn("Geolocation watch warning: ", error);
                    isRealSpeedActive = false;
                },
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 0 }
            );
        }
    }

    function stopGPSWatch() {
        if (geoWatchId) {
            navigator.geolocation.clearWatch(geoWatchId);
            geoWatchId = null;
        }
        isRealSpeedActive = false;
        lastDeviceLat = null;
        lastDeviceLng = null;
    }

    function toggleTracking() {
        const nextStatus = isTrackingActive ? 'inactive' : 'active';
        
        fetch("{{ route('driver.trip.toggle') }}", {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({ status: nextStatus })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                isTrackingActive = (nextStatus === 'active');
                
                // Update UI Buttons
                const btn = document.getElementById('btn-toggle-tracking');
                const desc = document.getElementById('tracking-desc-text');
                const ping = document.getElementById('live-trip-ping');
                const sat = document.getElementById('satellite-icon');
                
                // Update header live indicator dot dynamically (layout global status)
                const layoutBadge = document.getElementById('driver-status-badge');
                const layoutDot = document.querySelector('.status-indicator-dot');
                const layoutText = document.querySelector('.status-indicator-text');
                const statsTrips = document.getElementById('stats-trips');
                
                if (isTrackingActive) {
                    btn.innerText = 'STOP LIVE TRIP SESSION';
                    btn.className = "w-full py-4 rounded-xl font-black text-[15px] tracking-wide shadow-md border premium-transition active:scale-[0.98] bg-rose-600 hover:bg-rose-500 text-white border-rose-500/20 shadow-[0_4px_16px_rgba(225,29,72,0.2)]";
                    desc.innerText = "LIVE — GPS coordinates are actively transmitting.";
                    ping.classList.remove('hidden');
                    sat.classList.add('text-[#003F87]');
                    sat.classList.add('animate-pulse');
                    
                    if (layoutDot) {
                        layoutDot.className = "relative inline-flex rounded-full h-2 w-2 bg-rose-500 status-indicator-dot";
                        layoutText.innerText = "LIVE";
                    }
                    if (statsTrips && data.trips_today) {
                        statsTrips.innerText = data.trips_today;
                    }
                    startSimulation();
                } else {
                    btn.innerText = 'START LIVE TRIP SESSION';
                    btn.className = "w-full py-4 rounded-xl font-black text-[15px] tracking-wide shadow-md border premium-transition active:scale-[0.98] bg-[#003F87] hover:bg-[#0050a3] text-white border-[#003F87]/15 shadow-[0_4px_16px_rgba(0,63,135,0.15)]";
                    desc.innerText = "Offline — coordinates are frozen.";
                    ping.classList.add('hidden');
                    sat.classList.remove('text-[#003F87]');
                    sat.classList.remove('animate-pulse');
                    
                    if (layoutDot) {
                        layoutDot.className = "relative inline-flex rounded-full h-2 w-2 bg-slate-400 status-indicator-dot";
                        layoutText.innerText = "OFFLINE";
                    }
                    stopSimulation();
                }
            }
        });
    }

    // Coordinates Simulation Loop & Mock Speed Updates
    function startSimulation() {
        startGPSWatch();

        speedTimer = setInterval(() => {
            if (isRealSpeedActive) return; // Skip simulated speed if real telemetry is active
            
            if (Math.random() > 0.7) {
                targetSpeed = Math.floor(Math.random() * 8);
            } else {
                targetSpeed = simSpeedMin + Math.floor(Math.random() * (simSpeedMax - simSpeedMin));
            }
            
            let speedDiff = targetSpeed - currentSpeed;
            currentSpeed += Math.sign(speedDiff) * Math.min(Math.abs(speedDiff), 5);
            
            document.getElementById('speed-display').innerText = currentSpeed;
            
            const speedStatus = document.getElementById('speed-status-text');
            if (currentSpeed === 0) {
                speedStatus.innerText = "SHUTTLE IDLE";
                speedStatus.className = "text-[10px] font-semibold text-slate-400 mt-0.5";
            } else if (currentSpeed > fastSpeedThreshold) {
                speedStatus.innerText = "CRUISING FAST";
                speedStatus.className = "text-[10px] font-semibold text-amber-600 mt-0.5";
            } else {
                speedStatus.innerText = "DRIVING SLOW";
                speedStatus.className = "text-[10px] font-semibold text-emerald-600 mt-0.5";
            }
        }, speedSimInterval);

        simulationTimer = setInterval(() => {
            if (!isTrackingActive) return;
            
            let coord;
            // Use real device GPS coords if available, otherwise fall back to route simulation path
            if (lastDeviceLat !== null && lastDeviceLng !== null) {
                coord = { lat: lastDeviceLat, lng: lastDeviceLng };
                console.log(`Real GPS Transmit: Lat: ${coord.lat}, Lng: ${coord.lng}`);
            } else {
                // Ensure array has values
                if (mockRouteCoords.length > 0) {
                    const currentPoint = mockRouteCoords[mockCoordIndex];
                    coord = {
                        lat: Array.isArray(currentPoint) ? currentPoint[0] : currentPoint.lat,
                        lng: Array.isArray(currentPoint) ? currentPoint[1] : currentPoint.lng
                    };
                    mockCoordIndex = (mockCoordIndex + 1) % mockRouteCoords.length;
                    console.log(`Simulated GPS Transmit: Lat: ${coord.lat}, Lng: ${coord.lng}`);
                } else {
                    coord = { lat: fallbackLat, lng: fallbackLng }; // Default fallback
                }
            }

            // Perform real-time GPS telemetry updates to the Laravel server MySQL database
            fetch("{{ route('driver.trip.gps') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    lat: coord.lat,
                    lng: coord.lng,
                    speed: currentSpeed
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    console.log('Database GPS Sync Success:', data);
                    // Update current stop display dynamically if updated by backend progression
                    if (data.next_stop) {
                        const stopLabel = document.getElementById('active-stop-label');
                        if (stopLabel) {
                            stopLabel.innerText = data.next_stop;
                        }
                    }
                }
            })
            .catch(err => console.error('Database GPS Sync Error:', err));
        }, gpsSyncInterval);
        
        document.getElementById('live-trip-ping').classList.remove('hidden');
        const sat = document.getElementById('satellite-icon');
        if (sat) {
            sat.classList.add('text-[#003F87]');
            sat.classList.add('animate-pulse');
        }
    }

    function stopSimulation() {
        stopGPSWatch();
        clearInterval(simulationTimer);
        clearInterval(speedTimer);
        currentSpeed = 0;
        document.getElementById('speed-display').innerText = 0;
        document.getElementById('speed-status-text').innerText = "SHUTTLE IDLE";
        document.getElementById('speed-status-text').className = "text-[10px] font-semibold text-slate-400 mt-0.5";
        
        document.getElementById('live-trip-ping').classList.add('hidden');
        const sat = document.getElementById('satellite-icon');
        if (sat) {
            sat.classList.remove('text-[#003F87]');
            sat.classList.remove('animate-pulse');
        }
    }
</script>
@endsection
