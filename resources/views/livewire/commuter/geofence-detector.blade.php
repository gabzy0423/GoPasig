<div x-data="geofenceDetector()" 
     x-init="initComponent()"
     @geofence-entered.window="handleGeofenceEntered($event.detail)"
     @geofence-exited.window="handleGeofenceExited($event.detail)"
     class="px-4 py-3 select-none">
     
    <!-- Custom CSS styles for high-fidelity animations -->
    <style>
        @keyframes radar-pulse {
            0% { transform: scale(0.6); opacity: 0.9; }
            50% { opacity: 0.4; }
            100% { transform: scale(2.4); opacity: 0; }
        }
        .radar-glow {
            box-shadow: 0 0 20px rgba(99, 102, 241, 0.2);
        }
        .radar-glow-active {
            box-shadow: 0 0 25px rgba(16, 185, 129, 0.4);
            animation: emerald-glow-pulse 2s infinite alternate;
        }
        .animate-radar-1 {
            animation: radar-pulse 2.5s cubic-bezier(0.1, 0.8, 0.3, 1) infinite;
        }
        .animate-radar-2 {
            animation: radar-pulse 2.5s cubic-bezier(0.1, 0.8, 0.3, 1) 1.25s infinite;
        }
        @keyframes emerald-glow-pulse {
            0% { box-shadow: 0 0 15px rgba(16, 185, 129, 0.3); }
            100% { box-shadow: 0 0 30px rgba(16, 185, 129, 0.6); }
        }
        /* Custom progress bar styles */
        .distance-track {
            background: linear-gradient(90deg, #E2E8F0 0%, #CBD5E1 100%);
        }
        .distance-fill {
            background: linear-gradient(90deg, #6366F1 0%, #4F46E5 100%);
            transition: width 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .distance-fill-near {
            background: linear-gradient(90deg, #10B981 0%, #059669 100%);
        }
    </style>

    <!-- Main Card Container -->
    <div :class="activeStop ? 'border-emerald-200 bg-white shadow-[0_12px_40px_rgba(16,185,129,0.08)] radar-glow-active' : 'border-slate-100 bg-white shadow-[0_12px_32px_rgba(15,23,42,0.04)]'"
         class="relative w-full rounded-[28px] border p-5 flex flex-col gap-4 overflow-hidden transition-all duration-500 ease-out">
        
        <!-- Background Ambient Design Elements -->
        <div class="absolute -top-12 -right-12 w-32 h-32 rounded-full opacity-10 blur-2xl transition-colors duration-500"
             :class="activeStop ? 'bg-emerald-500' : 'bg-indigo-500'"></div>
        
        <!-- HEADER ROW: Status Indicators -->
        <div class="flex justify-between items-center z-10">
            <div class="flex items-center gap-2">
                <div class="w-8 h-8 rounded-2xl flex items-center justify-center transition-colors duration-300"
                     :class="activeStop ? 'bg-emerald-50/80 text-emerald-600' : 'bg-indigo-50/80 text-indigo-600'">
                    <i class="ti text-lg" :class="activeStop ? 'ti-circle-check-filled' : 'ti-radar'"></i>
                </div>
                <div class="flex flex-col">
                    <span class="text-[14px] font-extrabold text-slate-800 leading-none">Smart Stop Detector</span>
                    <span class="text-[9.5px] font-bold text-slate-400 mt-0.5 tracking-wider uppercase" x-text="gpsModeText"></span>
                </div>
            </div>
            
            <!-- Dynamic Status Badge -->
            <div class="flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold tracking-wide uppercase transition-all duration-300"
                 :class="activeStop 
                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-100' 
                    : (isTracking ? 'bg-indigo-50 text-indigo-700 border border-indigo-100' : 'bg-slate-100 text-slate-500')">
                <span class="w-1.5 h-1.5 rounded-full" 
                      :class="activeStop ? 'bg-emerald-500 animate-pulse' : (isTracking ? 'bg-indigo-500 animate-pulse' : 'bg-slate-400')"></span>
                <span x-text="activeStop ? 'Nasa Geofence' : (isTracking ? 'Aktibo' : 'Naka-off')"></span>
            </div>
        </div>

        <!-- STATE 1: WAITING FOR ACTIVATION (No location fetched yet) -->
        <template x-if="!hasLocation">
            <div class="flex flex-col items-center justify-center py-6 px-2 text-center gap-4 z-10">
                <!-- Large Pulsing Icon -->
                <div class="relative w-20 h-20 flex items-center justify-center">
                    <div class="absolute inset-0 rounded-full bg-indigo-100/50 scale-110"></div>
                    <div class="w-14 h-14 rounded-full bg-indigo-500 text-white flex items-center justify-center shadow-lg shadow-indigo-500/20 active:scale-95 transition-transform duration-200">
                        <i class="ti ti-map-pin-cog text-2xl"></i>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <h3 class="text-[15px] font-extrabold text-slate-800 tracking-tight">I-activate ang Smart stop Detection</h3>
                    <p class="text-[12px] font-semibold text-slate-400 leading-relaxed max-w-[280px] mx-auto">
                        Awtomatikong alamin kung nasa loob ka ng kahit aling terminal o hintuan ng bus upang makita ang iskedyul.
                    </p>
                </div>

                <button @click="startTracking()" 
                        class="w-full max-w-[220px] bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 text-white text-[13px] font-extrabold py-3 px-4 rounded-2xl flex items-center justify-center gap-2 shadow-md shadow-indigo-600/10 active:scale-[0.98] transition-all duration-150">
                    <i class="ti ti-crosshair text-[15px]"></i>
                    Simulan ang Pagsubaybay
                </button>
            </div>
        </template>

        <!-- STATE 2: ACTIVE SCANNING STATE (GPS enabled, but NOT inside any stop) -->
        <template x-if="hasLocation && !activeStop">
            <div class="flex flex-col gap-4 z-10">
                <!-- Radar Circle Visual and Info -->
                <div class="flex items-center gap-4 bg-slate-50/70 border border-slate-100 p-3.5 rounded-2xl">
                    
                    <!-- Pulsing Radar Circles -->
                    <div class="relative w-14 h-14 rounded-full bg-indigo-50 flex-shrink-0 flex items-center justify-center overflow-hidden">
                        <div class="absolute w-12 h-12 rounded-full border border-indigo-300/40 animate-radar-1"></div>
                        <div class="absolute w-12 h-12 rounded-full border border-indigo-300/40 animate-radar-2"></div>
                        <div class="w-6 h-6 rounded-full bg-indigo-600 text-white flex items-center justify-center shadow-md">
                            <i class="ti ti-navigation text-[11px] animate-pulse"></i>
                        </div>
                    </div>

                    <!-- Scanning message -->
                    <div class="flex flex-col min-w-0">
                        <span class="text-[11px] font-extrabold text-indigo-600 tracking-wide uppercase">Kasalukuyang Nag-iiscan</span>
                        <span class="text-[13px] font-bold text-slate-800 mt-0.5 leading-snug">Hinahanap ang pinakamalapit na hintuan...</span>
                    </div>

                </div>

                <!-- Distance and Nearest Stop Section -->
                <div class="flex flex-col gap-2.5" x-show="nearestStop">
                    <div class="flex justify-between items-end">
                        <div class="flex flex-col">
                            <span class="text-[9.5px] font-extrabold text-slate-400 uppercase tracking-wider">Pinakamalapit na Stop</span>
                            <span class="text-[14px] font-extrabold text-slate-800 tracking-tight mt-0.5" x-text="nearestStop?.name"></span>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="text-[10px] font-extrabold text-indigo-600 leading-none">Distansya</span>
                            <span class="text-[16px] font-black font-mono text-slate-800 mt-0.5" x-text="(distanceToNearest || 0) + 'm'"></span>
                        </div>
                    </div>

                    <!-- Distance visual bar indicator -->
                    <div class="flex flex-col gap-1.5">
                        <div class="w-full h-2 rounded-full bg-slate-100 overflow-hidden relative">
                            <!-- Target Geofence marker line (150m) -->
                            <div class="absolute top-0 bottom-0 left-[30%] w-[2px] bg-slate-300 z-10" title="150m Geofence border"></div>
                            
                            <!-- Filled distance progress track -->
                            <div class="h-full rounded-full distance-fill" 
                                 :class="distanceToNearest <= 300 ? 'distance-fill-near' : ''"
                                 :style="'width: ' + Math.max(5, Math.min(100, (1 - ((distanceToNearest - 150) / 1000)) * 100)) + '%'"></div>
                        </div>
                        <div class="flex justify-between text-[9px] font-extrabold text-slate-400">
                            <span>Malayo ka pa (Geofence: 150m)</span>
                            <span x-text="distanceToNearest ? 'Kailangan ng ' + Math.max(0, distanceToNearest - 150) + 'm pa' : ''"></span>
                        </div>
                    </div>
                </div>

                <!-- Info Hint -->
                <div class="bg-indigo-50/50 border border-indigo-100/50 rounded-2xl p-3 flex gap-2.5 items-start">
                    <i class="ti ti-info-circle-filled text-[15px] text-indigo-500 mt-0.5 flex-shrink-0"></i>
                    <p class="text-[11.5px] text-indigo-950 font-medium leading-normal">
                        Maglakad papalapit sa <strong class="font-bold text-indigo-900" x-text="nearestStop?.name || 'hintuan'"></strong> upang awtomatikong pumasok sa geofence at makuha ang mga Libreng Sakay timetables.
                    </p>
                </div>
            </div>
        </template>

        <!-- STATE 3: GEOFENCE ENTERED STATE (Inside an active stop) -->
        <template x-if="hasLocation && activeStop">
            <div class="flex flex-col gap-4.5 z-10" x-transition:enter="transition ease-out duration-500" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
                
                <!-- Glowing Welcome Header Banner -->
                <div class="bg-gradient-to-r from-emerald-500 to-teal-600 rounded-2xl p-4 text-white flex justify-between items-center shadow-lg shadow-emerald-500/10">
                    <div class="flex flex-col gap-0.5">
                        <span class="text-[10px] font-extrabold text-emerald-100 uppercase tracking-widest leading-none">Nasa Loob Ka Na Ng Geofence 🌟</span>
                        <h2 class="text-[16px] font-extrabold tracking-tight mt-1 leading-snug" x-text="activeStop?.name"></h2>
                    </div>
                    <!-- Ambient audio check badge -->
                    <div class="w-10 h-10 rounded-full bg-white/15 flex items-center justify-center flex-shrink-0 backdrop-blur-sm active:scale-90 transition-transform cursor-pointer" 
                         @click="triggerChime()" title="Patugtugin muli ang chime">
                        <i class="ti ti-volume text-[17px] text-white"></i>
                    </div>
                </div>

                <!-- Live bus schedules at this stop -->
                <div class="flex flex-col gap-2.5">
                    <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider pl-0.5">Mga Parating na Libreng Sakay</span>
                    
                    <div class="bg-emerald-50/30 border border-emerald-100/50 rounded-2xl p-4 flex flex-col gap-3">
                        <div class="flex gap-2.5 items-start">
                            <div class="w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center flex-shrink-0 mt-0.5">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                            </div>
                            <div class="flex-grow min-w-0">
                                <p class="text-[12.5px] font-extrabold text-slate-700 leading-snug" x-text="activeStop?.schedule"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Stop Amenities chips -->
                <div class="flex flex-col gap-2">
                    <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-wider pl-0.5">Mga Pasilidad sa Hintuang Ito</span>
                    
                    <div class="flex flex-wrap gap-1.5 pl-0.5" x-show="activeStop?.amenities">
                        <template x-for="amenity in (activeStop?.amenities ? activeStop.amenities.split(', ') : [])" :key="amenity">
                            <span class="px-3 py-1 rounded-xl bg-slate-50 border border-slate-100 text-[10.5px] font-bold text-slate-600 flex items-center gap-1 shadow-sm">
                                <i class="ti text-[12px] text-slate-400"
                                   :class="amenity.includes('Wi-Fi') || amenity.includes('Charging') ? 'ti-wifi' : 
                                           (amenity.includes('CCTV') || amenity.includes('Security') ? 'ti-shield-check' : 'ti-home-check')"></i>
                                <span x-text="amenity"></span>
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <!-- DEVELOPER SIMULATION EXPANDABLE TRAY (Always compiled, premium tool) -->
        <div class="border-t border-slate-50 pt-3 mt-1.5">
            <button @click="showSim = !showSim" 
                    class="w-full flex justify-between items-center text-[10.5px] font-bold text-slate-400 hover:text-slate-600 transition-colors py-1.5 px-0.5 active:scale-98">
                <span class="flex items-center gap-1.5">
                    <i class="ti ti-device-laptop text-[12px]"></i>
                    Developer Simulation Tools (Gamitin sa Desktop)
                </span>
                <i class="ti text-[11px] transition-transform duration-300" 
                   :class="showSim ? 'ti-chevron-up rotate-180' : 'ti-chevron-down'"></i>
            </button>

            <!-- Expanded simulation tool tray -->
            <div x-show="showSim" x-collapse x-cloak class="pt-3 pb-1 flex flex-col gap-3.5">
                
                <!-- Simulated Location Selector -->
                <div class="flex flex-col gap-2">
                    <span class="text-[9.5px] font-extrabold text-slate-400 uppercase tracking-wider pl-0.5">Pumili ng Lokasyon na I-geogenerate:</span>
                    
                    <div class="grid grid-cols-2 gap-1.5">
                        @foreach($stops->unique('name')->take(4) as $simStop)
                            <button @click="simulateStop({{ $simStop->id }}, {{ $simStop->lat }}, {{ $simStop->lng }}, '{{ addslashes($simStop->name) }}')"
                                    :class="simulatedStopId === {{ $simStop->id }} ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm shadow-indigo-600/10' : 'bg-slate-50 border-slate-100 text-slate-700 hover:bg-slate-100'"
                                    class="border rounded-xl py-2 px-3 text-[11px] font-bold text-left truncate transition-all duration-150 active:scale-97"
                                    title="{{ $simStop->name }}">
                                📍 {{ Str::limit($simStop->name, 20) }}
                            </button>
                        @endforeach
                        <!-- Near Kapitolyo but outside geofence (280m distance) -->
                        <button @click="simulateOutside({{ $simNearLat }}, {{ $simNearLng }}, '{{ addslashes($simNearLabel) }}')"
                                :class="simulatedStopId === 'outside-near' ? 'bg-indigo-600 border-indigo-600 text-white shadow-sm shadow-indigo-600/10' : 'bg-slate-50 border-slate-100 text-slate-700 hover:bg-slate-100'"
                                class="border rounded-xl py-2 px-3 text-[11px] font-bold text-left truncate transition-all duration-150 active:scale-97">
                            🚶 {{ $simNearLabel }}
                        </button>
                        <!-- Out of Range entirely (Masyadong malayo) -->
                        <button @click="simulateOutside({{ $simFarLat }}, {{ $simFarLng }}, '{{ addslashes($simFarLabel) }}')"
                                :class="simulatedStopId === 'outside-far' ? 'bg-indigo-600 border-indigo-600 text-white' : 'bg-slate-50 border-slate-100 text-slate-700 hover:bg-slate-100'"
                                class="border rounded-xl py-2 px-3 text-[11px] font-bold text-left truncate transition-all duration-150 active:scale-97">
                            ❌ {{ $simFarLabel }}
                        </button>
                        <!-- Real Device GPS Mode -->
                        <button @click="switchToRealGPS()"
                                :class="simulatedStopId === 'real' ? 'bg-emerald-600 border-emerald-600 text-white shadow-sm shadow-emerald-600/10' : 'bg-slate-50 border-slate-100 text-slate-700 hover:bg-slate-100'"
                                class="border rounded-xl py-2 px-3 text-[11px] font-bold text-left truncate transition-all duration-150 active:scale-97">
                            📡 Gamitin ang Real GPS
                        </button>
                    </div>
                </div>

                <!-- Simulation Active Alert notification -->
                <div class="bg-amber-50/60 border border-amber-100 rounded-2xl p-3 flex gap-2.5 items-start">
                    <i class="ti ti-alert-triangle-filled text-[14px] text-amber-500 mt-0.5 flex-shrink-0"></i>
                    <p class="text-[10px] text-amber-950 font-bold leading-normal">
                        Naka-on ang simulator. Pinapadala nito ang mga huwad na latitude at longitude sa Livewire component upang makalkula ang Geofence sa localhost nang walang tunay na GPS.
                    </p>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Scripts for client-side geolocation watcher & dynamic Web Audio API Chime Synth -->
<script>
    function geofenceDetector() {
        return {
            showSim: false,
            simulatedStopId: null,
            isTracking: false,
            watchId: null,
            gpsMode: 'idle', // idle, simulated, watching, error
            
            get hasLocation() {
                // Check if $wire coordinates are set
                return this.$wire.lat !== null && this.$wire.lng !== null;
            },
            
            get activeStop() {
                return this.$wire.activeStop;
            },
            
            get nearestStop() {
                return this.$wire.nearestStop;
            },
            
            get distanceToNearest() {
                return this.$wire.distanceToNearest;
            },

            get gpsModeText() {
                if (this.simulatedStopId && this.simulatedStopId !== 'real') {
                    return '🔴 Simulated GPS';
                }
                if (this.isTracking) {
                    return '📡 Live Device GPS';
                }
                return '⚪ Naka-off';
            },

            initComponent() {
                console.log('Geofence Detector Frontend loaded.');
            },

            // Web Audio API Synthesized double chime notification
            // Standard premium UI/UX chime - self-contained and clean
            triggerChime() {
                try {
                    const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                    const now = audioCtx.currentTime;

                    // Unang note
                    const osc1 = audioCtx.createOscillator();
                    const gain1 = audioCtx.createGain();
                    osc1.type = 'sine';
                    osc1.frequency.setValueAtTime({{ $chimeFreq1 }}, now); // Frequency
                    gain1.gain.setValueAtTime(0.12, now);
                    gain1.gain.exponentialRampToValueAtTime(0.0001, now + 0.65);
                    osc1.connect(gain1);
                    gain1.connect(audioCtx.destination);

                    // Pangalawang note - tutunog pagkatapos ng delay
                    const osc2 = audioCtx.createOscillator();
                    const gain2 = audioCtx.createGain();
                    osc2.type = 'sine';
                    osc2.frequency.setValueAtTime({{ $chimeFreq2 }}, now + {{ $chimeDelay }}); // Frequency
                    gain2.gain.setValueAtTime(0.12, now + {{ $chimeDelay }});
                    gain2.gain.exponentialRampToValueAtTime(0.0001, now + 0.8);
                    osc2.connect(gain2);
                    gain2.connect(audioCtx.destination);

                    osc1.start(now);
                    osc1.stop(now + 0.75);
                    
                    osc2.start(now + 0.12);
                    osc2.stop(now + 0.9);

                    console.log('Geofence Welcome chime played!');
                } catch (err) {
                    console.log('Hindi pinayagan ng browser ang audio chime playback: ', err);
                }
            },

            // Handles event geofenceEntered dispatched by Livewire backend
            handleGeofenceEntered(detail) {
                console.log('Nakapasok sa Geofence: ', detail);
                this.triggerChime();
                
                // Pwede ring magdagdag ng native device vibration kung sinusuportahan ng mobile browser
                if ('vibrate' in navigator) {
                    navigator.vibrate([150, 100, 150]);
                }
            },

            handleGeofenceExited(detail) {
                console.log('Lumabas sa Geofence: ', detail);
                
                if ('vibrate' in navigator) {
                    navigator.vibrate(200);
                }
            },

            // Starts browser tracking via watchPosition
            startTracking() {
                if (this.isTracking) return;

                if (!navigator.geolocation) {
                    alert('Hindi sinusuportahan ng iyong browser ang Geolocation.');
                    return;
                }

                this.isTracking = true;
                this.simulatedStopId = 'real';

                this.watchId = navigator.geolocation.watchPosition(
                    (position) => {
                        // Skip if simulated is active
                        if (this.simulatedStopId && this.simulatedStopId !== 'real') return;
                        
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        
                        console.log(`Live GPS coords: Lat ${lat}, Lng ${lng}`);
                        
                        // Send location to Livewire component
                        this.$wire.call('updateLocation', lat, lng);
                    },
                    (error) => {
                        console.error('Error fetching GPS coordinate: ', error);
                        this.gpsMode = 'error';
                        this.isTracking = false;
                        alert('Hindi nakuha ang iyong lokasyon. Mangyaring payagan ang location permissions o gamitin ang Simulator.');
                    },
                    {
                        enableHighAccuracy: true,
                        maximumAge: 10000,
                        timeout: 5000
                    }
                );
            },

            // Switch back to device real GPS
            switchToRealGPS() {
                this.simulatedStopId = 'real';
                this.startTracking();
            },

            // Simulates coordinate submission for a designated stop
            simulateStop(id, lat, lng, name) {
                this.simulatedStopId = id;
                console.log(`Simulating Kapitolyo/City Hall Stop at: Lat ${lat}, Lng ${lng}`);
                
                // Direct call to Livewire component method
                this.$wire.call('updateLocation', lat, lng);
            },

            // Simulates coordinate submission for a point outside stops
            simulateOutside(lat, lng, label) {
                this.simulatedStopId = lat === {{ $simFarLat }} ? 'outside-far' : 'outside-near';
                console.log(`Simulating outside at: Lat ${lat}, Lng ${lng} (${label})`);
                
                this.$wire.call('updateLocation', lat, lng);
            }
        };
    }
</script>
