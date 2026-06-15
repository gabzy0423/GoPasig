<section id="screen-dispatch-intelligence" class="hidden animate-fade-in" style="display: none;">
<div class="space-y-6">

    <!-- Page Header -->
    <div class="flex flex-col gap-1 border-b border-slate-100 pb-3 mb-6 shrink-0">
        <h1 class="text-xl font-bold text-slate-900">Dispatch Intelligence</h1>
        <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
            <span>Dashboard</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span>Operations</span>
            <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
            <span class="text-slate-600 font-bold">Dispatch Intelligence</span>
        </div>
    </div>
    <!-- Success Alert Box -->
    <div id="dispatch-alert-success" class="hidden p-4 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-xl text-xs font-semibold flex items-center justify-between shadow-sm animate-fade-in-up">
        <div class="flex items-center gap-2">
            <i class="ti ti-circle-check text-base"></i>
            <span></span>
        </div>
        <button onclick="document.getElementById('dispatch-alert-success').classList.add('hidden')" class="text-[#3B6D11] hover:opacity-80"><i class="ti ti-x"></i></button>
    </div>
    
    <!-- Error Alert Box -->
    <div id="dispatch-alert-error" class="hidden p-4 bg-[#FCEBEB] border border-[#A32D2D] text-[#A32D2D] rounded-xl text-xs font-semibold flex items-center justify-between shadow-sm animate-fade-in-up">
        <div class="flex items-center gap-2">
            <i class="ti ti-alert-triangle text-base"></i>
            <span></span>
        </div>
        <button onclick="document.getElementById('dispatch-alert-error').classList.add('hidden')" class="text-[#A32D2D] hover:opacity-80"><i class="ti ti-x"></i></button>
    </div>

    <!-- ==================== CONTROLS SECTION ==================== -->
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-end shrink-0">

        <!-- Simulation Phase Pill Selector -->
        <div class="flex items-center bg-slate-100 p-1.5 rounded-xl border border-black/5" data-active-phase="{{ $selectedPhase }}">
            <button class="px-4 py-2 rounded-lg text-xs font-extrabold uppercase tracking-wider transition-all {{ $selectedPhase == 1 ? 'bg-[#003F87] text-white shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                Phase 1: Reactive
            </button>
            <button class="px-4 py-2 rounded-lg text-xs font-extrabold uppercase tracking-wider transition-all {{ $selectedPhase == 2 ? 'bg-[#003F87] text-white shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                Phase 2: Predictive
            </button>
            <button class="px-4 py-2 rounded-lg text-xs font-extrabold uppercase tracking-wider transition-all {{ $selectedPhase == 3 ? 'bg-[#003F87] text-white shadow-sm' : 'text-slate-500 hover:text-slate-800' }}">
                Phase 3: Self-Improving
            </button>
        </div>
    </div>

    <!-- ==================== SYSTEM STATUS ALERTS ==================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- ALERTS COLUMN (7 cols) -->
        <div class="lg:col-span-7 space-y-4">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-bell-ringing text-lg text-[#003F87]"></i>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Active Dispatch Alerts</h3>
                    </div>
                    <span id="dispatch-alerts-count" class="px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide uppercase bg-slate-100 text-slate-500">
                        0 Alerts
                    </span>
                </div>

                <div id="dispatch-alerts-feed" class="space-y-3 max-h-[300px] overflow-y-auto pr-1">
                    <div class="h-[200px] flex flex-col items-center justify-center text-center space-y-3">
                        <div class="h-12 w-12 rounded-full bg-[#F3F9EA] text-[#639922] flex items-center justify-center shadow-inner">
                            <i class="ti ti-circle-check text-2xl"></i>
                        </div>
                        <div>
                            <p class="text-xs font-bold text-slate-800">Operational Stability Achieved</p>
                            <p class="text-[11.5px] text-slate-400 font-semibold mt-0.5">No critical passenger surges or threshold overrides active.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SIMULATOR CONTROL PANEL COLUMN (5 cols) -->
        <div class="lg:col-span-5">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-device-gamepad-2 text-lg text-[#003F87]"></i>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Real-time Activity Simulator</h3>
                    </div>
                </div>

                <!-- Simulator Variables -->
                <div class="grid grid-cols-2 gap-3 p-3 bg-slate-50 border border-slate-100 rounded-xl">
                    <div class="space-y-1">
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-400 block font-semibold">Simulated Day</label>
                        <select id="simulatedDay" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-bold text-slate-700 outline-none cursor-pointer">
                            <option value="Monday" {{ $simulatedDay === 'Monday' ? 'selected' : '' }}>Monday</option>
                            <option value="Tuesday" {{ $simulatedDay === 'Tuesday' ? 'selected' : '' }}>Tuesday</option>
                            <option value="Wednesday" {{ $simulatedDay === 'Wednesday' ? 'selected' : '' }}>Wednesday</option>
                            <option value="Thursday" {{ $simulatedDay === 'Thursday' ? 'selected' : '' }}>Thursday</option>
                            <option value="Friday" {{ $simulatedDay === 'Friday' ? 'selected' : '' }}>Friday</option>
                            <option value="Saturday" {{ $simulatedDay === 'Saturday' ? 'selected' : '' }}>Saturday</option>
                            <option value="Sunday" {{ $simulatedDay === 'Sunday' ? 'selected' : '' }}>Sunday</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-400 block font-semibold">Simulated Time Slot</label>
                        <select id="simulatedTimeSlot" class="w-full rounded-lg border border-slate-200 bg-white px-2 py-1.5 text-xs font-bold text-slate-700 outline-none cursor-pointer">
                            <option value="06:00-08:00" {{ $simulatedTimeSlot === '06:00-08:00' ? 'selected' : '' }}>06:00-08:00 (AM Peak)</option>
                            <option value="08:00-10:00" {{ $simulatedTimeSlot === '08:00-10:00' ? 'selected' : '' }}>08:00-10:00 (AM Rush)</option>
                            <option value="12:00-14:00" {{ $simulatedTimeSlot === '12:00-14:00' ? 'selected' : '' }}>12:00-14:00 (Off Peak)</option>
                            <option value="16:00-18:00" {{ $simulatedTimeSlot === '16:00-18:00' ? 'selected' : '' }}>16:00-18:00 (PM Peak)</option>
                            <option value="18:00-20:00" {{ $simulatedTimeSlot === '18:00-20:00' ? 'selected' : '' }}>18:00-20:00 (PM Rush)</option>
                        </select>
                    </div>
                </div>

                <!-- Simulation triggers per route -->
                <div class="space-y-2.5">
                    <span class="text-[11px] font-black uppercase tracking-wider text-slate-400 block font-semibold">Trigger Commuters per Route</span>
                    
                    @foreach($routesData as $r)
                        <div class="p-2.5 bg-slate-50 border border-slate-100 rounded-xl flex items-center justify-between gap-2">
                            <div class="min-w-0">
                                <span class="text-xs font-extrabold text-[#001F44] block truncate">Route {{ $r->id }}</span>
                                <span class="text-[10px] text-slate-400 font-semibold block truncate">{{ $r->description }}</span>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <button onclick="addCommuterAction({{ $r->id }})" class="h-6 px-2 text-[10px] font-bold text-[#003F87] bg-white border border-[#003F87]/20 hover:bg-[#E6F1FB] rounded transition cursor-pointer" title="Add 1 app passenger check-in">
                                    +1 App
                                </button>
                                <button onclick="addManualTickerAction({{ $r->id }})" class="h-6 px-2 text-[10px] font-bold text-[#3B6D11] bg-white border border-[#3B6D11]/20 hover:bg-[#F3F9EA] rounded transition cursor-pointer" title="Add 1 driver manual ticket count">
                                    +1 Driver
                                </button>
                                <button onclick="simulateRushSpurtAction({{ $r->id }})" class="h-6 px-2 text-[10px] font-bold text-[#A32D2D] bg-white border border-[#A32D2D]/20 hover:bg-[#FCEBEB] rounded transition cursor-pointer" title="Simulate rush hour spurt count">
                                    Spurt
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-end">
                    <button onclick="clearSimulatorDataAction()" class="h-8 px-4 text-xs font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition uppercase tracking-wider cursor-pointer">
                        Clear Simulator Data
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== MAIN DEMAND MONITORING BOARD ==================== -->
    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div class="flex items-center gap-2">
                <i class="ti ti-route text-lg text-[#003F87]"></i>
                <h3 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Active Commuter Demand Board</h3>
            </div>
            <div class="flex items-center gap-1.5 text-xs text-slate-400 font-semibold">
                <span>Phase: </span>
                <strong id="phase-label-display" class="text-[#003F87] uppercase">Reactive (Thresholds)</strong>
            </div>
        </div>

        <div id="demand-board-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            @foreach($routesData as $r)
                @php
                    $borderClass = $r->status === 'red' ? 'border-[#E24B4A] border-t-[4px]' : ($r->status === 'yellow' ? 'border-[#BA7517] border-t-[4px]' : 'border-slate-200 border-t-[4px] border-t-[#003F87]');
                    $badgeClass = $r->status === 'red' ? 'bg-[#FCEBEB] text-[#A32D2D]' : ($r->status === 'yellow' ? 'bg-[#FAEEDA] text-[#854F0B]' : 'bg-[#EAF3DE] text-[#3B6D11]');
                    $badgeText = $r->status === 'red' ? '🔴 Dispatch Now' : ($r->status === 'yellow' ? '🟡 Standby (High)' : '🟢 Normal');
                    $loadPercent = $r->threshold > 0 ? min(100, round(($r->total / $r->threshold) * 100)) : 0;
                    $progressBarColor = $r->status === 'red' ? 'bg-[#E24B4A]' : ($r->status === 'yellow' ? 'bg-[#BA7517]' : 'bg-[#003F87]');
                @endphp
                <div class="bg-white border rounded-2xl p-4 shadow-sm flex flex-col justify-between space-y-4 transition hover:shadow-md {{ $borderClass }}">
                    <div class="space-y-1">
                        <div class="flex justify-between items-start">
                            <h4 class="text-sm font-extrabold text-[#001F44]">Route {{ $r->id }}</h4>
                            <span class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ $badgeClass }}">{{ $badgeText }}</span>
                        </div>
                        <p class="text-[11px] text-slate-400 font-semibold leading-tight line-clamp-2 h-[32px]">{{ $r->description }}</p>
                    </div>

                    <!-- Statistics counts breakdown -->
                    <div class="grid grid-cols-3 gap-1 bg-slate-50 border border-slate-100 p-2 rounded-xl text-center">
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold block uppercase tracking-wider">App</span>
                            <span class="text-sm font-bold text-slate-800 font-mono">{{ $r->auto_count }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold block uppercase tracking-wider">Manual</span>
                            <span class="text-sm font-bold text-slate-800 font-mono">{{ $r->manual_count }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] text-slate-400 font-bold block uppercase tracking-wider">Total</span>
                            <span class="text-sm font-bold text-slate-800 font-mono">{{ $r->total }}</span>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div class="space-y-1">
                        <div class="flex justify-between text-[11px] font-semibold text-slate-500">
                            <span>Waiting Load: {{ $loadPercent }}%</span>
                            <span>Threshold: {{ $r->threshold }} pax</span>
                        </div>
                        <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden border border-black/5 shadow-inner">
                            <div class="h-full rounded-full {{ $progressBarColor }} transition-all" style="width: {{ $loadPercent }}%"></div>
                        </div>
                    </div>

                    @if($selectedPhase >= 2)
                        <div class="p-2 bg-[#E6F1FB] border border-[#003F87]/15 rounded-xl text-[11px] text-[#0C447C] font-semibold flex items-center justify-between">
                            <span>Expected Peak:</span>
                            <strong class="font-mono">{{ $r->historical_avg }} pax</strong>
                        </div>
                    @endif

                    <button onclick="dispatchNowAction({{ $r->id }})" class="w-full h-9 flex items-center justify-center gap-1 bg-[#003F87] hover:bg-[#002D62] text-white text-xs font-extrabold uppercase tracking-wider rounded-xl transition shadow-sm cursor-pointer">
                        <i class="ti ti-bus-stop text-base"></i>
                        <span>Dispatch Bus</span>
                    </button>
                </div>
            @endforeach
        </div>
    </div>

    <!-- ==================== LOWER MODULE ROW: THRESHOLD OVERRIDES & PERFORMANCE / TIMELINE ==================== -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- LEFT: Settings & History (7 cols) -->
        <div class="lg:col-span-7 space-y-5">
            <!-- Settings Card -->
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-settings-automation text-lg text-[#003F87]"></i>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Threshold Limits Overrides</h3>
                    </div>
                </div>

                <form id="threshold-override-form" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                    <div class="space-y-1">
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-400 block font-semibold">Select Route</label>
                        <select id="selectedRouteId" class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 outline-none cursor-pointer">
                            <option value="1" {{ $selectedRouteId == 1 ? 'selected' : '' }}>Route 1 (SPED - City Hall)</option>
                            <option value="2" {{ $selectedRouteId == 2 ? 'selected' : '' }}>Route 2 (SPED - Ligaya)</option>
                            <option value="3" {{ $selectedRouteId == 3 ? 'selected' : '' }}>Route 3 (SPED - One San Miguel)</option>
                            <option value="4" {{ $selectedRouteId == 4 ? 'selected' : '' }}>Route 4 (SPED - Nagpayong)</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[10px] font-black uppercase tracking-wider text-slate-400 block font-semibold">Max Waiting Limit (pax)</label>
                        <input type="number" id="customThreshold" value="{{ $customThreshold }}" 
                               min="{{ \App\Models\SystemSetting::get('threshold_min_value', 5) }}" 
                               max="{{ \App\Models\SystemSetting::get('threshold_max_value', 100) }}"
                               class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-bold text-slate-700 outline-none focus:bg-white focus:border-[#003F87]">
                    </div>

                    <button type="submit" class="h-9 w-full bg-[#003F87] hover:bg-[#002D62] text-white text-xs font-extrabold uppercase tracking-wider rounded-lg transition shadow-sm cursor-pointer">
                        Apply Limit
                    </button>
                </form>
            </div>

            <!-- Dynamic Accuracy ML/Historical Tracker container -->
            <div id="accuracy-or-patterns-container" class="space-y-4">
                <!-- Fallback Historical Patterns block -->
                <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-history text-lg text-[#003F87]"></i>
                            <h3 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Recorded Peak Demand Patterns</h3>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        @foreach($historicalPatterns->take(4) as $pattern)
                            <div class="p-3 bg-slate-50 border border-slate-100 rounded-xl text-center space-y-1">
                                <span class="text-[10px] text-slate-400 font-extrabold uppercase block tracking-wider">{{ $pattern->day_of_week }}</span>
                                <span class="text-[11px] font-bold text-slate-700 block truncate">Route {{ $pattern->route_id }}</span>
                                <strong class="text-lg font-black text-[#003F87] font-mono block">{{ $pattern->total_commuters }} pax</strong>
                                <span class="text-[9px] text-slate-400 font-bold block">{{ $pattern->time_slot }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Dispatch Timeline log (5 cols) -->
        <div class="lg:col-span-5">
            <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-5 space-y-4 flex flex-col h-full">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-timeline text-lg text-[#003F87]"></i>
                        <h3 class="text-sm font-bold text-slate-800 uppercase tracking-tight">Recent Dispatch Logs</h3>
                    </div>
                </div>

                <!-- Vertical timeline log list -->
                <div id="recent-dispatches-list" class="flex-grow overflow-y-auto space-y-4 max-h-[300px] pr-1 pl-4 border-l border-slate-100 mt-2 relative">
                    @forelse($recentDispatches as $log)
                        @php
                            $routeColor = match($log->trip->route_id) {
                                1 => '#003F87',
                                2 => '#BA7517',
                                3 => '#639922',
                                4 => '#E24B4A',
                                default => '#888780'
                            };
                        @endphp
                        <div class="relative py-1 group flex flex-col gap-1 transition-all rounded hover:bg-slate-50 p-2 border border-transparent hover:border-slate-100">
                            <!-- Timeline node circle dot -->
                            <span class="absolute h-2.5 w-2.5 rounded-full border-2 border-white shadow-sm -left-[22px] top-4 z-10" style="background-color: {{ $routeColor }}"></span>
                            
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-black text-[#001F44] truncate">Route {{ $log->trip->route_id }} ({{ $log->trip->route->name ?? 'Route' }})</span>
                                <span class="text-[10px] text-slate-400 font-bold font-mono">{{ $log->dispatched_at->diffForHumans() }}</span>
                            </div>
                            
                            <div class="text-[11px] text-slate-500 font-semibold space-y-0.5">
                                <div>Bus: <strong class="text-slate-700 font-mono">{{ $log->trip->bus->plate_number ?? '—' }}</strong> · Driver: <strong class="text-slate-700">{{ $log->trip->driver ? ($log->trip->driver->first_name . ' ' . $log->trip->driver->last_name) : '—' }}</strong></div>
                                <div class="italic text-[10px] opacity-90 mt-0.5">{{ $log->notes }}</div>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-slate-400 text-xs font-bold">
                            No dispatch actions recorded in this session.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>


</section>

