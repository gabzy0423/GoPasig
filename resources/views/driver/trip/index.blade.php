@extends('layouts.driver')

@section('title', 'GoPasig - Active Trip driving panel')

@section('content')
<div x-data="{ 
    showModal: false, 
    selectedType: '{{ \App\Models\Incident::getTypes()[0] ?? "Breakdown" }}', 
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
                    
                    if (typeof stopTelemetry === 'function') {
                        stopTelemetry();
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

    <!-- GPS Weak Signal Warning Banner -->
    <div id="gps-signal-weak-alert" class="hidden bg-amber-50 border border-amber-200 rounded-2xl p-4 flex items-start gap-3 z-10 animate-pulse">
        <i class="ti ti-wifi-off text-amber-500 text-lg flex-shrink-0 mt-0.5"></i>
        <div class="flex flex-col gap-0.5">
            <span class="text-xs font-bold text-amber-700 leading-snug">GPS signal weak — please check your connection</span>
        </div>
    </div>

    <!-- Temporary GPS Debug Panel -->
    <div id="gps-debug-panel" class="bg-slate-900 text-slate-100 rounded-2xl p-4 flex flex-col gap-1.5 text-xs font-mono select-all">
        <div class="flex justify-between items-center border-b border-slate-700 pb-1.5 mb-1.5">
            <span class="font-extrabold text-[#003F87] tracking-widest text-[10px]">GPS DEBUG PANEL</span>
            <span class="text-[9px] text-slate-400">Temporary Debug Panel</span>
        </div>
        <div>Protocol: <span id="gps-debug-protocol" class="text-emerald-400 font-bold">Checking...</span></div>
        <div>Hostname: <span id="gps-debug-hostname" class="text-emerald-400 font-bold">Checking...</span></div>
        <div>Permission: <span id="gps-debug-permission" class="text-blue-400 font-bold">Checking...</span></div>
        <div>GPS Status: <span id="gps-debug-status" class="text-amber-400 font-bold">Waiting...</span></div>
        <div>Last Device Lat/Lng: <span id="gps-debug-coords" class="text-slate-350 font-bold">None</span></div>
        <div>GPS State: <span id="gps-debug-state" class="text-amber-400 font-bold">Waiting</span></div>
        <div>Last GPS Success: <span id="gps-debug-last-success" class="text-emerald-400 font-bold">None</span></div>
        <div>Telemetry Heartbeat: <span id="gps-debug-heartbeat" class="text-emerald-400 font-bold">None</span></div>
        <div>Packet Type: <span id="gps-debug-packet-type" class="text-violet-400 font-bold">None</span></div>
        <div>GPS Fix Timestamp: <span id="gps-debug-fix-timestamp" class="text-emerald-400 font-bold">None</span></div>
        <div>GPS Fix Age: <span id="gps-debug-fix-age" class="text-amber-400 font-bold">None</span></div>
        <div>Speed Source: <span id="gps-debug-speed-source" class="text-violet-400 font-bold">None</span></div>
        <div>Last Known Coordinate Age: <span id="gps-debug-coordinate-age" class="text-amber-400 font-bold">None</span></div>
        <div>Last POST Status: <span id="gps-debug-post-status" class="text-blue-400 font-bold">None</span></div>
        <div>Current Accuracy Variable: <span id="gps-debug-current-accuracy" class="text-cyan-400 font-bold">None</span></div>
        <div>Payload Accuracy: <span id="gps-debug-payload-accuracy" class="text-cyan-400 font-bold">None</span></div>
        <div>Accuracy Trace Build: <span id="gps-debug-accuracy-build" class="text-violet-400 font-bold">accuracy-trace-mobile-v1</span></div>
        <div>Last Error: <span id="gps-debug-error" class="text-rose-450 font-bold">None</span></div>
        <div>Watch ID: <span id="gps-debug-watchid" class="text-slate-450 font-bold">None</span></div>
    </div>

    @if($driver && $driver->assigned_bus && $bus)
        <!-- TRIP CONTROL TOGGLE BUTTON -->
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] flex flex-col gap-4">
            <div class="flex justify-between items-start">
                <div class="flex flex-col gap-0.5">
                    <span class="text-xs font-black text-slate-700 uppercase tracking-wider">Tracking Session</span>
                    <span class="text-[11px] text-slate-450 font-semibold" id="tracking-desc-text">
                        {{ $bus->status === 'operating' ? 'LIVE — GPS coordinates are actively transmitting.' : 'Offline — coordinates are frozen.' }}
                    </span>
                </div>
                <div class="flex flex-col items-end gap-1">
                    <div class="w-8 h-8 rounded-full bg-slate-50 border border-slate-100 flex items-center justify-center text-slate-450">
                        <i class="ti ti-satellite text-lg" id="satellite-icon"></i>
                    </div>
                    {{-- GPS source indicator: updated dynamically by updateGpsSourceBadge() JS --}}
                    <span id="gps-source-badge" class="text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded-full bg-slate-100 text-slate-400 border border-slate-200">
                        ◌ WAITING
                    </span>
                </div>
            </div>

            <button id="btn-toggle-tracking" onclick="toggleTracking()" class="w-full py-4 rounded-xl font-black text-[15px] tracking-wide shadow-md border premium-transition active:scale-[0.98]
                {{ $bus->status === 'operating' ? 'bg-rose-600 hover:bg-rose-500 text-white border-rose-500/20 shadow-[0_4px_16px_rgba(225,29,72,0.2)]' : 'bg-[#003F87] hover:bg-[#0050a3] text-white border-[#003F87]/15 shadow-[0_4px_16px_rgba(0,63,135,0.15)]' }}">
                @if($bus->status === 'operating')
                    STOP LIVE TRIP SESSION
                @elseif($bus->status === 'ready')
                    START LIVE TRIP SESSION
                @else
                    START LIVE TRIP SESSION
                @endif
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
                    $warningLimit = \App\Models\Bus::getOccupancyWarningThreshold();
                    $criticalLimit = \App\Models\Bus::getOccupancyCriticalThreshold();
                    
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
                            $routeStops = $route->stops->sortBy('sequence')->values();
                            $offsets = \App\Models\Stop::getDistanceWeightedOffsets($routeStops, $route->travel_time_minutes ?? 30);
                            
                            $nextStopName = $bus->next_stop;
                            $nextStopIndex = $routeStops->search(function ($s) use ($nextStopName) {
                                return stripos($s->name, (string)$nextStopName) !== false || stripos((string)$nextStopName, $s->name) !== false;
                            });
                            
                            if ($nextStopIndex !== false && $nextStopIndex > 0) {
                                $defaultEta = (int) round($offsets[$nextStopIndex] - $offsets[$nextStopIndex - 1]);
                            } else {
                                $defaultEta = (int) round($offsets[1] ?? 5);
                            }
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
                @php
                    $nextStopSeq = 0;
                    if ($bus->next_stop && $route) {
                        $foundStop = $route->stops->first(function ($s) use ($bus) {
                            return stripos($s->name, (string)$bus->next_stop) !== false || stripos((string)$bus->next_stop, $s->name) !== false;
                        });
                        if ($foundStop) {
                            $nextStopSeq = $foundStop->sequence;
                        }
                    }
                @endphp
                <div class="flex flex-col gap-2.5 max-h-[175px] overflow-y-auto pr-1 no-scrollbar">
                    @forelse($route->stops->sortBy('sequence') as $stop)
                        @php
                            $isPassed = $stop->sequence < $nextStopSeq;
                            $isCurrent = $bus->next_stop === $stop->name;
                        @endphp
                        <label data-stop-seq="{{ $stop->sequence }}" data-stop-name="{{ $stop->name }}" 
                            class="stop-label-item flex items-center justify-between p-3 rounded-xl border cursor-pointer active:scale-[0.99] transition-all select-none
                            {{ $isPassed ? 'opacity-50 bg-slate-100/70 border-slate-150' : 'bg-slate-50/40 border-slate-100 hover:bg-slate-50' }}
                            {{ $isCurrent ? 'border-[#003F87]/30 bg-[#003F87]/5' : '' }}">
                            <div class="flex items-center gap-3">
                                <input type="radio" name="next_stop_radio" value="{{ $stop->name }}" 
                                    {{ $isCurrent ? 'checked' : '' }} 
                                    onchange="selectNextStop('{{ $stop->name }}')"
                                    class="w-4 h-4 text-[#003F87] bg-white border-slate-250 focus:ring-[#003F87]/20 focus:ring-2">
                                <div class="flex flex-col">
                                    <span class="stop-name-text text-xs font-bold {{ $isPassed ? 'line-through text-slate-400' : 'text-slate-700' }}">{{ $stop->name }}</span>
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
                    @foreach(\App\Models\Incident::getTypesMetadata() as $typeName => $meta)
                        <button type="button" @click="selectedType = '{{ $typeName }}'" 
                                class="p-3.5 rounded-xl border-2 flex flex-col items-center justify-center gap-1.5 transition-all duration-200 active:scale-95 text-center cursor-pointer font-sans"
                                :class="selectedType === '{{ $typeName }}' ? '{{ $meta['active_class'] }} shadow-sm' : 'bg-slate-55/50 border-slate-100 text-slate-500 hover:bg-slate-50 hover:border-slate-200'">
                            <i class="ti {{ $meta['icon'] }} text-2xl" :class="selectedType === '{{ $typeName }}' ? '{{ $meta['icon_active'] }}' : 'text-slate-400'"></i>
                            <span class="text-xs font-black uppercase tracking-wide">
                                {{ \App\Models\Incident::isTrafficDelay($typeName) ? 'Traffic Delay' : (\App\Models\Incident::isPassengerConcern($typeName) ? 'Concern' : $typeName) }}
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
    /**
     * TECHNICAL DEBT NOTE — Driver Dashboard JavaScript
     * ---------------------------------------------------
     * All GPS telemetry logic, UI state management, and tracking business rules
     * currently reside in this Blade template as inline JavaScript.
     *
     * Future refactor target (when complexity grows beyond ~1500 lines):
     *   - resources/js/driver/gps-service.js       — GPS acquisition, watchPosition, retry logic
     *   - resources/js/driver/telemetry-service.js — Transmission loop, hold logic, coord validation
     *   - resources/js/driver/driver-ui.js         — Badge updates, stop UI, speed display
     *
     * This keeps concerns separated and makes each module independently testable.
     */

    // Dynamic config constants from database models
    const warningLimit = {{ \App\Models\Bus::getOccupancyWarningThreshold() }};
    const criticalLimit = {{ \App\Models\Bus::getOccupancyCriticalThreshold() }};
    const fastSpeedThreshold = {{ \App\Models\Bus::getSpeedFastThreshold() }};
    const gpsSyncInterval = {{ \App\Models\Bus::getGpsSyncIntervalMs() }};
    const gpsExtendedStaleThresholdMs = {{ (int) \App\Models\SystemSetting::get('gps_extended_stale_threshold_ms', 600000) }};
    const speedSimInterval = {{ \App\Models\Bus::getSpeedSimulationIntervalMs() }};
    const simSpeedMin = {{ $bus ? $bus->getMinSpeed() : \App\Models\Bus::getSimSpeedMin() }};
    const simSpeedMax = {{ $bus ? $bus->getMaxSpeed() : \App\Models\Bus::getSimSpeedMax() }};
    
    @php
        $defaultStop = \App\Models\Stop::first();
        $fallbackLat = $defaultStop ? $defaultStop->lat : (float) \App\Models\SystemSetting::get('default_route_start_lat', 14.5593);
        $fallbackLng = $defaultStop ? $defaultStop->lng : (float) \App\Models\SystemSetting::get('default_route_start_lng', 121.0805);
    @endphp
    const fallbackLat = {{ $fallbackLat }};
    const fallbackLng = {{ $fallbackLng }};

    // State indicators
    let isTrackingActive = "{{ isset($bus) && $bus->status === 'operating' ? 'true' : 'false' }}" === 'true';
    let telemetryTimer = null;
    let speedTimer = null;
    let currentSpeed = 0;
    let targetSpeed = 0;
    
    // HTML5 Geolocation API state
    let geoWatchId = null;
    let isRealSpeedActive = false;
    let lastDeviceLat = null;
    let lastDeviceLng = null;
    let lastDeviceAccuracy = null;
    let lastDeviceSpeedMps = 0;
    let lastDeviceHeading = null;
    let lastGpsSuccessAt = null;
    let lastGpsFixTimestamp = null;
    let lastGpsFixSequence = 0;
    let lastSentGpsFixSequence = null;
    let lastSentGpsFixTimestamp = null;
    let lastDeviceSpeedSource = 'native';
    let lastPacketType = 'None';
    let lastPacketSpeedSource = 'None';
    let lastPacketGpsFixAgeMs = null;
    let telemetryHeartbeatAt = null;
    let gpsState = 'GPS ACQUIRING';
    let gpsWatchFailureCount = 0;
    let lastPostStatus = 'None';
    let prevLat = null;
    let prevLng = null;
    let lastCoordTime = null;
    let gpsRetryTimeout = null;

    // GPS acquisition state — prevents simulation fallback during GPS hardware cold-start.
    // Set to false on start; becomes true only after the FIRST real position fix arrives.
    // While false (and permission not denied), the simulation timer HOLDS and does not
    // transmit fake route coordinates.
    let gpsAcquired = false;
    let gpsPermissionDenied = false;
    // Maximum ms to wait for first GPS fix before allowing simulation fallback
    const GPS_WARMUP_TIMEOUT_MS = 20000;

    function getPositionTimestamp(position) {
        const timestamp = Number(position && position.timestamp);
        return Number.isFinite(timestamp) && timestamp > 0 ? timestamp : Date.now();
    }

    function formatTelemetryAgeMs(ageMs) {
        return Number.isFinite(ageMs) ? Math.max(0, Math.round(ageMs / 1000)) + 's' : 'None';
    }

    function formatTelemetryTime(timestamp) {
        return timestamp ? new Date(timestamp).toLocaleTimeString() : 'None';
    }

    function formatTelemetryAge(timestamp) {
        if (!timestamp) return 'None';
        const ageSeconds = Math.max(0, Math.round((Date.now() - timestamp) / 1000));
        return ageSeconds + 's';
    }

    function updateTelemetryDebug() {
        const stateEl = document.getElementById('gps-debug-state');
        const successEl = document.getElementById('gps-debug-last-success');
        const heartbeatEl = document.getElementById('gps-debug-heartbeat');
        const ageEl = document.getElementById('gps-debug-coordinate-age');
        const postEl = document.getElementById('gps-debug-post-status');
        const packetTypeEl = document.getElementById('gps-debug-packet-type');
        const fixTimestampEl = document.getElementById('gps-debug-fix-timestamp');
        const fixAgeEl = document.getElementById('gps-debug-fix-age');
        const speedSourceEl = document.getElementById('gps-debug-speed-source');

        if (stateEl) stateEl.innerText = gpsState;
        if (successEl) successEl.innerText = formatTelemetryTime(lastGpsSuccessAt);
        if (heartbeatEl) heartbeatEl.innerText = formatTelemetryTime(telemetryHeartbeatAt);
        if (ageEl) ageEl.innerText = formatTelemetryAge(lastGpsSuccessAt);
        if (postEl) postEl.innerText = lastPostStatus;
        if (packetTypeEl) packetTypeEl.innerText = lastPacketType;
        if (fixTimestampEl) fixTimestampEl.innerText = formatTelemetryTime(lastGpsFixTimestamp);
        if (fixAgeEl) fixAgeEl.innerText = formatTelemetryAgeMs(lastPacketGpsFixAgeMs);
        if (speedSourceEl) speedSourceEl.innerText = lastPacketSpeedSource;
    }

    function updateAccuracyDebug(currentAccuracy, payloadAccuracy = null) {
        const currentEl = document.getElementById('gps-debug-current-accuracy');
        const payloadEl = document.getElementById('gps-debug-payload-accuracy');
        if (currentEl) currentEl.innerText = Number.isFinite(currentAccuracy) ? currentAccuracy + 'm' : 'null';
        if (payloadEl) payloadEl.innerText = Number.isFinite(payloadAccuracy) ? payloadAccuracy + 'm' : 'null';
        updateTelemetryDebug();
    }
    // Dynamic Coordinates Path array from database (with fallback integrated)
    const mockRouteCoords = @json($gpsCoords);
    let mockCoordIndex = 0;

    document.addEventListener("DOMContentLoaded", function() {
        if (isTrackingActive) {
            startTelemetry();
            const btn = document.getElementById('btn-toggle-tracking');
            if (btn) btn.innerText = 'STOP LIVE TRIP SESSION';
        } else if ("{{ $bus ? $bus->status : '' }}" === 'operating') {
            const btn = document.getElementById('btn-toggle-tracking');
            if (btn) {
                btn.innerText = 'RESUME TRACKING';
                btn.className = "w-full py-4 rounded-xl font-black text-[15px] tracking-wide shadow-md border premium-transition active:scale-[0.98] bg-[#003F87] hover:bg-[#0050a3] text-white border-[#003F87]/15 shadow-[0_4px_16px_rgba(0,63,135,0.15)]";
            }
        }
        // Initialize passed stops styling on load
        const initialNextStop = document.getElementById('active-stop-label')?.innerText?.trim();
        if (initialNextStop) {
            updatePassedStopsUI(initialNextStop);
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
        updatePassedStopsUI(name);
        updateNextStop();
    }

    function updatePassedStopsUI(nextStopName) {
        const targetLabel = document.querySelector(`.stop-label-item[data-stop-name="${nextStopName}"]`);
        if (!targetLabel) return;
        
        const nextStopSeq = parseInt(targetLabel.getAttribute('data-stop-seq')) || 0;
        
        const labels = document.querySelectorAll('.stop-label-item');
        labels.forEach(label => {
            const seq = parseInt(label.getAttribute('data-stop-seq')) || 0;
            const stopName = label.getAttribute('data-stop-name');
            const nameText = label.querySelector('.stop-name-text');
            
            // Remove previous statuses
            label.classList.remove('opacity-50', 'bg-slate-100/70', 'border-slate-150', 'border-[#003F87]/30', 'bg-[#003F87]/5');
            label.classList.add('bg-slate-50/40', 'border-slate-100');
            if (nameText) {
                nameText.classList.remove('line-through', 'text-slate-400');
                nameText.classList.add('text-slate-700');
            }
            
            if (seq < nextStopSeq) {
                // Passed stop
                label.classList.add('opacity-50', 'bg-slate-100/70', 'border-slate-150');
                label.classList.remove('bg-slate-50/40', 'border-slate-100');
                if (nameText) {
                    nameText.classList.add('line-through', 'text-slate-400');
                    nameText.classList.remove('text-slate-700');
                }
            } else if (stopName === nextStopName) {
                // Current stop
                label.classList.add('border-[#003F87]/30', 'bg-[#003F87]/5');
            }
        });
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

    function calculateDistanceInMeters(lat1, lon1, lat2, lon2) {
        const R = 6371e3; // Earth's radius in meters
        const phi1 = lat1 * Math.PI / 180;
        const phi2 = lat2 * Math.PI / 180;
        const deltaPhi = (lat2 - lat1) * Math.PI / 180;
        const deltaLambda = (lon2 - lon1) * Math.PI / 180;

        const a = Math.sin(deltaPhi / 2) * Math.sin(deltaPhi / 2) +
                  Math.cos(phi1) * Math.cos(phi2) *
                  Math.sin(deltaLambda / 2) * Math.sin(deltaLambda / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

        return R * c; // in meters
    }

    function normalizeHeading(heading) {
        return Number.isFinite(heading) && heading >= 0 && heading <= 360 ? heading : null;
    }

    function updateSpeedDisplay(speedMps) {
        const speedKmh = Math.max(0, speedMps) * 3.6;
        currentSpeed = speedKmh < 3 ? 0 : Math.round(speedKmh);

        const display = document.getElementById('speed-display');
        if (display) {
            display.innerText = currentSpeed;
        }

        const speedStatus = document.getElementById('speed-status-text');
        if (!speedStatus) return;

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

    // Update the GPS source badge shown to the driver
    function updateGpsSourceBadge(state, accuracy) {
        gpsState = state || gpsState;
        updateTelemetryDebug();
        const badge = document.getElementById('gps-source-badge');
        if (!badge) return;
        
        const accuracyText = (accuracy !== undefined && accuracy !== null) ? ` · ${Math.round(accuracy)}m` : '';

        if (state === 'REAL GPS') {
            badge.textContent = `REAL GPS${accuracyText}`;
            badge.className = 'text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded-full bg-emerald-500/15 text-emerald-600 border border-emerald-500/20';
        } else if (state === 'GPS ACQUIRING...') {
            badge.textContent = 'ACQUIRING...';
            badge.className = 'text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded-full bg-blue-500/15 text-blue-600 border border-blue-500/20 animate-pulse';
        } else if (state === 'GPS WEAK') {
            badge.textContent = `GPS WEAK${accuracyText}`;
            badge.className = 'text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded-full bg-rose-500/15 text-rose-600 border border-rose-500/20';
        } else if (state === 'GPS DEGRADED') {
            badge.textContent = `GPS DEGRADED${accuracyText}`;
            badge.className = 'text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded-full bg-amber-500/15 text-amber-700 border border-amber-500/20';
        } else if (state === 'GPS STALE') {
            badge.textContent = 'GPS STALE';
            badge.className = 'text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded-full bg-rose-600/20 text-rose-700 border border-rose-600/30';
        } else if (state === 'GPS LOST') {
            badge.textContent = 'GPS LOST';
            badge.className = 'text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded-full bg-rose-600/20 text-rose-700 border border-rose-600/30';
        } else if (state === 'GPS BLOCKED') {
            badge.textContent = 'GPS BLOCKED';
            badge.className = 'text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded-full bg-slate-500/15 text-slate-600 border border-slate-500/20';
        } else {
            badge.textContent = state || 'UNKNOWN';
            badge.className = 'text-[9px] font-extrabold uppercase tracking-widest px-2 py-0.5 rounded-full bg-slate-100 text-slate-500 border border-slate-200';
        }
    }

    let gpsRetryCount = 0;
    const GPS_MAX_RETRIES = 5;

    function acquireGPS() {
        // Update protocol and hostname in debug panel
        const protoEl = document.getElementById('gps-debug-protocol');
        if (protoEl) protoEl.innerText = window.location.protocol;
        const hostEl = document.getElementById('gps-debug-hostname');
        if (hostEl) hostEl.innerText = window.location.hostname;

        // Check Permissions API
        if (navigator.permissions && navigator.permissions.query) {
            navigator.permissions.query({name: 'geolocation'}).then(function(result) {
                const pEl = document.getElementById('gps-debug-permission');
                if (pEl) pEl.innerText = result.state;
                result.onchange = function() {
                    if (pEl) pEl.innerText = result.state;
                };
            }).catch(function(err) {
                const pEl = document.getElementById('gps-debug-permission');
                if (pEl) pEl.innerText = 'Error: ' + err.message;
            });
        } else {
            const pEl = document.getElementById('gps-debug-permission');
            if (pEl) pEl.innerText = 'Not Supported';
        }

        const statusEl = document.getElementById('gps-debug-status');

        if ("geolocation" in navigator) {
            if (statusEl) statusEl.innerText = "Acquiring initial lock (Attempt " + (gpsRetryCount + 1) + ")...";
            updateGpsSourceBadge('GPS ACQUIRING...');

            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const currentLat = position.coords.latitude;
                    const currentLng = position.coords.longitude;
                    const accuracy = position.coords.accuracy;
                    const fixTimestamp = getPositionTimestamp(position);
                    const nativeSpeedMps = position.coords.speed;
                    const hasNativeSpeed = Number.isFinite(nativeSpeedMps) && nativeSpeedMps >= 0;
                    console.log('GPS acquire getCurrentPosition SUCCESS:', currentLat, currentLng);
                    
                    gpsAcquired = true;
                    lastDeviceLat = currentLat;
                    lastDeviceLng = currentLng;
                    lastDeviceAccuracy = Number.isFinite(accuracy) ? accuracy : null;
                    lastDeviceSpeedMps = hasNativeSpeed ? nativeSpeedMps : 0;
                    lastDeviceSpeedSource = hasNativeSpeed ? 'native' : 'calculated';
                    lastDeviceHeading = normalizeHeading(position.coords.heading);
                    lastGpsFixTimestamp = fixTimestamp;
                    lastGpsSuccessAt = fixTimestamp;
                    lastGpsFixSequence++;
                    gpsWatchFailureCount = 0;
                    gpsPermissionDenied = false;
                    updateSpeedDisplay(lastDeviceSpeedMps);
                    updateAccuracyDebug(lastDeviceAccuracy);
                    updateGpsSourceBadge('REAL GPS', accuracy);
                    
                    const coordsEl = document.getElementById('gps-debug-coords');
                    if (coordsEl) coordsEl.innerText = currentLat.toFixed(6) + ', ' + currentLng.toFixed(6) + ' (Acc: ' + accuracy.toFixed(1) + 'm)';
                    if (statusEl) statusEl.innerText = "Initial Lock Acquired. Starting watch...";
                    
                    startContinuousWatch();
                },
                function(error) {
                    console.warn("GPS acquire getCurrentPosition ERROR code:", error.code, "message:", error.message);
                    
                    const errEl = document.getElementById('gps-debug-error');
                    if (errEl) errEl.innerText = "Attempt " + (gpsRetryCount + 1) + " error: Code " + error.code + " - " + error.message;

                    if (error.code === error.PERMISSION_DENIED) {
                        gpsPermissionDenied = true;
                        updateGpsSourceBadge('GPS BLOCKED');
                        if (statusEl) statusEl.innerText = "Permission Denied — GPS access blocked. Telemetry on hold.";
                    } else {
                        gpsRetryCount++;
                        if (gpsRetryCount <= GPS_MAX_RETRIES) {
                            const delay = gpsRetryCount * 3000; // 3s, 6s, 9s, 12s, 15s
                            console.log('GPS timeout/error — retry', gpsRetryCount, 'in', delay, 'ms');
                            if (statusEl) statusEl.innerText = "Retry " + gpsRetryCount + " in " + (delay / 1000) + "s...";
                            setTimeout(acquireGPS, delay);
                        } else {
                            // All retries exhausted — hold transmission, show GPS LOST
                            console.log('GPS failed after', GPS_MAX_RETRIES, 'retries. Holding telemetry.');
                            updateGpsSourceBadge('GPS LOST');
                            if (statusEl) statusEl.innerText = "GPS signal lost. Telemetry on hold. Retrying on next fix.";
                        }
                    }
                },
                {
                    enableHighAccuracy: true,
                    timeout: 20000, // 20 seconds per attempt
                    maximumAge: 0
                }
            );
        } else {
            gpsAcquired = false;
            gpsPermissionDenied = true;
            updateGpsSourceBadge('GPS BLOCKED');
            if (statusEl) statusEl.innerText = "Geolocation NOT supported by browser. Telemetry on hold.";
        }
    }

    function startContinuousWatch() {
        const statusEl = document.getElementById('gps-debug-status');
        if (statusEl) statusEl.innerText = "Starting watchPosition...";

        geoWatchId = navigator.geolocation.watchPosition(
            function(position) {
                const currentLat = position.coords.latitude;
                const currentLng = position.coords.longitude;
                const accuracy = position.coords.accuracy;
                const nowTime = Date.now();
                const fixTimestamp = getPositionTimestamp(position);
                const maxAccuracy = 50; // Accept readings under 50m accuracy

                const coordsEl = document.getElementById('gps-debug-coords');
                if (coordsEl) coordsEl.innerText = currentLat.toFixed(6) + ', ' + currentLng.toFixed(6) + ' (Acc: ' + accuracy.toFixed(1) + 'm)';

                if (accuracy <= maxAccuracy) {
                    lastDeviceLat = currentLat;
                    lastDeviceLng = currentLng;
                    lastDeviceAccuracy = Number.isFinite(accuracy) ? accuracy : null;
                    lastDeviceSpeedMps = Number.isFinite(position.coords.speed) && position.coords.speed >= 0 ? position.coords.speed : 0;
                    lastDeviceHeading = normalizeHeading(position.coords.heading);
                    lastGpsFixTimestamp = fixTimestamp;
                    lastGpsSuccessAt = fixTimestamp;
                    lastGpsFixSequence++;
                    gpsWatchFailureCount = 0;
                    gpsPermissionDenied = false;
                    updateSpeedDisplay(lastDeviceSpeedMps);
                    updateAccuracyDebug(lastDeviceAccuracy);
                    gpsAcquired = true; // double-ensure
                    updateGpsSourceBadge('REAL GPS', accuracy);
                    if (statusEl) statusEl.innerText = "Receiving Live Updates";
                } else {
                    // Poor accuracy — keep last good reading
                    updateGpsSourceBadge('GPS WEAK', accuracy);
                    if (statusEl) statusEl.innerText = "Skipped low accuracy: " + Math.round(accuracy) + "m";
                    console.log('Skipping low accuracy reading:', accuracy, 'm');
                    return; // Skip update but keep watch alive
                }

                // Hide the alert banner if visible
                const weakAlert = document.getElementById('gps-signal-weak-alert');
                if (weakAlert) {
                    weakAlert.classList.add('hidden');
                }

                let speedMps = position.coords.speed;
                let speedSource = 'native';

                if (Number.isFinite(speedMps) && speedMps >= 0) {
                    isRealSpeedActive = true;
                } else {
                    speedSource = 'calculated';
                    speedMps = 0;
                    // Fallback: Compute speed from coordinates difference
                    if (prevLat !== null && prevLng !== null && lastCoordTime !== null) {
                        let distanceMeters = calculateDistanceInMeters(prevLat, prevLng, currentLat, currentLng);
                        if (distanceMeters < 2.0) {
                            distanceMeters = 0;
                        }
                        const timeSeconds = (nowTime - lastCoordTime) / 1000;
                        if (timeSeconds > 0.5) {
                            const computedSpeedMps = distanceMeters / timeSeconds;
                            if (computedSpeedMps * 3.6 < 120) {
                                speedMps = computedSpeedMps;
                            }
                        }
                    }
                    isRealSpeedActive = true;
                }

                prevLat = currentLat;
                prevLng = currentLng;
                lastCoordTime = nowTime;

                lastDeviceSpeedMps = Math.max(0, speedMps);
                lastDeviceSpeedSource = speedSource;
                lastDeviceHeading = normalizeHeading(position.coords.heading);
                updateSpeedDisplay(lastDeviceSpeedMps);
            },
            function(error) {
                console.log('GPS watchPosition signal lost temporarily:', error.code, error.message);
                
                const errEl = document.getElementById('gps-debug-error');
                if (errEl) errEl.innerText = "Watch error: Code " + error.code + " - " + error.message;

                if (error.code === error.PERMISSION_DENIED) {
                    gpsPermissionDenied = true;
                    lastPostStatus = 'GPS permission blocked';
                    updateGpsSourceBadge('GPS BLOCKED');
                    if (statusEl) statusEl.innerText = "Permission denied. Telemetry on hold.";
                } else {
                    gpsWatchFailureCount++;
                    // Transient watch errors can happen while stationary. Keep the last real fix
                    // and continue heartbeat telemetry unless failures remain extended.
                    updateGpsSourceBadge('GPS DEGRADED', lastDeviceAccuracy);
                    if (statusEl) statusEl.innerText = "GPS signal degraded. Continuing heartbeat from last real fix.";
                }

                const weakAlert = document.getElementById('gps-signal-weak-alert');
                if (weakAlert) {
                    weakAlert.classList.remove('hidden');
                }
                isRealSpeedActive = false;
            },
            {
                enableHighAccuracy: true,
                timeout: 30000,
                maximumAge: 5000  // Accept 5s old fix
            }
        );

        const watchIdEl = document.getElementById('gps-debug-watchid');
        if (watchIdEl) watchIdEl.innerText = geoWatchId;
    }

    function stopGPSWatch() {
        if (geoWatchId) {
            navigator.geolocation.clearWatch(geoWatchId);
            geoWatchId = null;
        }
        isRealSpeedActive = false;
        gpsAcquired = false;
        gpsPermissionDenied = false;
        gpsRetryCount = 0;
        lastDeviceLat = null;
        lastDeviceLng = null;
        lastDeviceAccuracy = null;
        lastDeviceSpeedMps = 0;
        lastDeviceHeading = null;
        lastGpsSuccessAt = null;
        lastGpsFixTimestamp = null;
        lastGpsFixSequence = 0;
        lastSentGpsFixSequence = null;
        lastSentGpsFixTimestamp = null;
        lastDeviceSpeedSource = 'native';
        lastPacketType = 'None';
        lastPacketSpeedSource = 'None';
        lastPacketGpsFixAgeMs = null;
        telemetryHeartbeatAt = null;
        gpsState = 'GPS ACQUIRING';
        gpsWatchFailureCount = 0;
        lastPostStatus = 'None';
        prevLat = null;
        prevLng = null;
        lastCoordTime = null;

        const weakAlert = document.getElementById('gps-signal-weak-alert');
        if (weakAlert) {
            weakAlert.classList.add('hidden');
        }

        const statusEl = document.getElementById('gps-debug-status');
        if (statusEl) statusEl.innerText = "Stopped";
        const coordsEl = document.getElementById('gps-debug-coords');
        if (coordsEl) coordsEl.innerText = "None";
        const watchIdEl = document.getElementById('gps-debug-watchid');
        if (watchIdEl) watchIdEl.innerText = "None";
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
                    startTelemetry();
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
                    stopTelemetry();
                }
            } else {
                // Surface the backend error message to the driver
                const msg = data.message || 'Failed to update trip session. Please try again.';
                // Use Alpine toast if available, otherwise fallback to native alert
                const toastEl = document.querySelector('[x-data]')?.__x;
                if (toastEl) {
                    toastEl.$data.triggerToast(msg, 'error');
                } else {
                    alert(msg);
                }
            }
        })
        .catch(err => {
            console.error('toggleTracking error:', err);
            alert('Connection error — could not update trip session. Please check your internet connection.');
        });
    }

    // GPS Telemetry Transmission Loop
    function startTelemetry() {
        // Clear any existing intervals/watches to prevent duplicates
        stopTelemetry();

        acquireGPS();


        telemetryTimer = setInterval(() => {
            if (!isTrackingActive) return;

            // ── Only transmit if real device GPS coordinates are available ────────
            // If GPS is still acquiring, blocked, or lost: hold transmission.
            // Never transmit mock route coordinates or stale fallback coordinates.
            if (lastDeviceLat === null || lastDeviceLng === null) {
                // GPS not yet acquired, lost, or blocked — hold this tick silently
                lastPostStatus = 'Held - no real GPS fix';
                updateTelemetryDebug();
                console.log('[GPS] Holding telemetry — no real fix available.');
                return;
            }

            if (gpsPermissionDenied) {
                lastPostStatus = 'Held - GPS permission blocked';
                updateGpsSourceBadge('GPS BLOCKED');
                console.log('[GPS] Holding telemetry - GPS permission blocked.');
                updateTelemetryDebug();
                return;
            }

            const gpsAgeMs = lastGpsSuccessAt !== null ? Date.now() - lastGpsSuccessAt : Number.POSITIVE_INFINITY;
            const extendedFailure = gpsWatchFailureCount >= GPS_MAX_RETRIES && gpsAgeMs > gpsExtendedStaleThresholdMs;
            if (extendedFailure) {
                lastPostStatus = 'Held - extended GPS failure';
                updateGpsSourceBadge('GPS STALE');
                const statusEl = document.getElementById('gps-debug-status');
                if (statusEl) statusEl.innerText = "Extended GPS failure. Telemetry on hold until a new real fix arrives.";
                console.log('[GPS] Holding telemetry - extended GPS failure.', {
                    gps_age_ms: gpsAgeMs,
                    stale_threshold_ms: gpsExtendedStaleThresholdMs,
                    failure_count: gpsWatchFailureCount
                });
                updateTelemetryDebug();
                return;
            }

            const sendTimeMs = Date.now();
            const hasUnsentGpsCallback = lastSentGpsFixSequence !== lastGpsFixSequence
                || lastSentGpsFixTimestamp !== lastGpsFixTimestamp;
            const isCachedFix = !hasUnsentGpsCallback;
            const gpsFixAgeMs = lastGpsFixTimestamp !== null ? Math.max(0, Math.round(sendTimeMs - lastGpsFixTimestamp)) : null;
            const packetSpeedSource = isCachedFix ? 'cached' : lastDeviceSpeedSource;
            const payloadGpsFixSequence = lastGpsFixSequence;
            const payloadGpsFixTimestamp = lastGpsFixTimestamp;

            lastPacketType = isCachedFix ? 'CACHED HEARTBEAT' : 'FRESH FIX';
            lastPacketSpeedSource = packetSpeedSource;
            lastPacketGpsFixAgeMs = gpsFixAgeMs;
            updateTelemetryDebug();

            const coord = { lat: lastDeviceLat, lng: lastDeviceLng };
            const telemetryPayload = {
                lat: coord.lat,
                lng: coord.lng,
                speed: lastDeviceSpeedMps,
                heading: lastDeviceHeading,
                is_simulated: false,
                accuracy: lastDeviceAccuracy,
                gps_fix_timestamp: lastGpsFixTimestamp !== null ? new Date(lastGpsFixTimestamp).toISOString() : null,
                gps_fix_age_ms: gpsFixAgeMs,
                is_cached_fix: isCachedFix,
                speed_source: packetSpeedSource
            };
            console.log(`[GPS] REAL: ${coord.lat}, ${coord.lng}`);
            console.log('[GPS_ACCURACY_TRACE] browser_accuracy', {
                position_accuracy: lastDeviceAccuracy,
                last_device_lat: lastDeviceLat,
                last_device_lng: lastDeviceLng
            });
            updateAccuracyDebug(lastDeviceAccuracy, telemetryPayload.accuracy);
            console.log('[GPS_ACCURACY_TRACE] final_fetch_payload', telemetryPayload);
            console.log('[GPS_ACCURACY_TRACE] mobile_before_fetch', {
                positionAccuracy: lastDeviceAccuracy,
                lastDeviceAccuracy: lastDeviceAccuracy,
                payloadAccuracy: telemetryPayload.accuracy
            });

            // Transmit coordinates to the server
            fetch("{{ route('driver.trip.gps') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(telemetryPayload)
            })
            .then(async response => {
                const contentType = response.headers.get('content-type') || 'none';
                const rawBody = await response.text();
                const truncatedBody = rawBody.length > 500 ? rawBody.slice(0, 500) + '...' : rawBody;
                const responseDiagnostics = {
                    status: response.status,
                    statusText: response.statusText,
                    contentType: contentType,
                    body: truncatedBody
                };
                console.log('[GPS] DB Sync HTTP response:', responseDiagnostics);

                if (!contentType.toLowerCase().includes('application/json')) {
                    lastPostStatus = `HTTP ${response.status} ${response.statusText} non-JSON ${contentType}: ${truncatedBody.slice(0, 120)}`;
                    updateTelemetryDebug();
                    return null;
                }

                let data;
                try {
                    data = JSON.parse(rawBody);
                } catch (parseError) {
                    lastPostStatus = `HTTP ${response.status} JSON parse error: ${parseError.message}`;
                    updateTelemetryDebug();
                    console.error('[GPS] DB Sync JSON parse error:', {
                        ...responseDiagnostics,
                        error: parseError.message
                    });
                    return null;
                }

                if (!response.ok) {
                    lastPostStatus = `HTTP ${response.status} ${response.statusText}: ${data.message || data.error || 'request failed'}`;
                    updateTelemetryDebug();
                    console.warn('[GPS] DB Sync HTTP error:', {
                        ...responseDiagnostics,
                        json: data
                    });
                    return null;
                }

                return data;
            })
            .then(data => {
                if (!data) return;

                if (data.success) {
                    telemetryHeartbeatAt = Date.now();
                    lastSentGpsFixSequence = payloadGpsFixSequence;
                    lastSentGpsFixTimestamp = payloadGpsFixTimestamp;
                    lastPostStatus = 'OK - ' + formatTelemetryTime(telemetryHeartbeatAt);
                    updateTelemetryDebug();
                    console.log('[GPS] DB Sync OK:', data.lat, data.lng);
                    if (data.next_stop) {
                        const stopLabel = document.getElementById('active-stop-label');
                        if (stopLabel) {
                            stopLabel.innerText = data.next_stop;
                            updatePassedStopsUI(data.next_stop);
                        }
                    }
                } else {
                    lastPostStatus = 'Rejected - ' + (data.message || 'unknown');
                    updateTelemetryDebug();
                }
            })
            .catch(err => {
                lastPostStatus = 'Fetch error - ' + err.message;
                updateTelemetryDebug();
                console.error('[GPS] DB Sync Fetch Error:', err);
            });
        }, gpsSyncInterval);
        
        const livePing = document.getElementById('live-trip-ping');
        if (livePing) {
            livePing.classList.remove('hidden');
        }
        const sat = document.getElementById('satellite-icon');
        if (sat) {
            sat.classList.add('text-[#003F87]');
            sat.classList.add('animate-pulse');
        }
    }

    function stopTelemetry() {
        stopGPSWatch();
        if (telemetryTimer) {
            clearInterval(telemetryTimer);
            telemetryTimer = null;
        }
        if (speedTimer) {
            clearInterval(speedTimer);
            speedTimer = null;
        }
        currentSpeed = 0;
        const display = document.getElementById('speed-display');
        if (display) {
            display.innerText = 0;
        }
        const speedStatus = document.getElementById('speed-status-text');
        if (speedStatus) {
            speedStatus.innerText = "SHUTTLE IDLE";
            speedStatus.className = "text-[10px] font-semibold text-slate-400 mt-0.5";
        }
        
        const livePing = document.getElementById('live-trip-ping');
        if (livePing) {
            livePing.classList.add('hidden');
        }
        const sat = document.getElementById('satellite-icon');
        if (sat) {
            sat.classList.remove('text-[#003F87]');
            sat.classList.remove('animate-pulse');
        }
    }

    // Stop intervals and GPS watch on beforeunload/pagehide to prevent memory leaks or running background tasks
    window.addEventListener('beforeunload', () => {
        stopTelemetry();
    });
    window.addEventListener('pagehide', () => {
        stopTelemetry();
    });
</script>
@endsection













