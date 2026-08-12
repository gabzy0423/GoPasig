@extends('layouts.commuter')

@section('title', 'GoPasig - Commuter Dashboard')

@section('content')
<div class="flex flex-col gap-0 select-none pb-8">

    <!-- SECTION 1: WELCOME HERO BANNER WITH AMBIENT GLOW -->
    <section class="relative w-full bg-slate-50/50 py-3.5 px-4 flex items-center justify-center overflow-hidden border-b border-slate-100">
        <!-- Ambient Glow (Blurred duplicate behind the main banner) -->
        <div class="absolute inset-x-6 top-4 bottom-2 bg-cover bg-center rounded-xl opacity-35 blur-xl scale-95 pointer-events-none select-none" style="background-image: url('{{ asset('images/banner.png') }}');"></div>

        <!-- Main Timetable Banner Card -->
        <div class="relative w-full rounded-xl overflow-hidden border border-slate-100 shadow-[0_8px_30px_rgba(15,23,42,0.06)] bg-white z-10">
            <img src="{{ asset('images/banner.png') }}" alt="GoPasig Libreng Sakay Official Timetable" class="w-full h-auto select-none block">
        </div>
    </section>

    <!-- SMART GEOFENCE DETECTOR (Livewire Component) -->
    <livewire:commuter.geofence-detector />

    <!-- SECTION 2: QUICK STATS STRIP (Livewire Component) -->
    <livewire:commuter.quick-stats />

    <!-- SECTION 3: NEAREST BUSES (Livewire Component) -->
    <livewire:commuter.nearest-buses />

    <!-- SECTION 4: ACTIVE ROUTES -->
    <section class="px-4 pt-5 flex flex-col gap-3.5 select-none">
        <!-- Header -->
        <div class="flex justify-between items-center px-0.5">
            <h2 class="text-[16px] font-bold text-slate-800 tracking-tight">Active routes</h2>
            <a href="{{ route('commuter.routes') }}" class="text-[13px] font-bold text-[#003F87] active:opacity-75 flex items-center gap-0.5">
                View all <i class="ti ti-chevron-right text-xs"></i>
            </a>
        </div>

        <!-- Routes Stacked List -->
        <div class="flex flex-col gap-3">
            @forelse($activeRoutes as $route)
                <a href="{{ route('commuter.routes') }}?route={{ urlencode($route->route_name) }}" class="bg-white rounded-2xl border border-slate-100 p-4 flex flex-col gap-2.5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] active:scale-[0.97] active:bg-slate-50 transition-all duration-200">
                    
                    <!-- Row 1: Colored Dot + Name + Health Chip -->
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-2">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $route->route_color }}; box-shadow: 0 0 6px {{ $route->route_color }};"></span>
                            <span class="text-[14px] font-bold text-slate-800 tracking-tight">{{ $route->route_name }}</span>
                        </div>
                        
                        <!-- Health Badge -->
                        @if($route->health_status === 'On Track')
                            <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-full shadow-sm">On Track</span>
                        @elseif($route->health_status === 'Minor Delay')
                            <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-amber-50 text-amber-700 border border-amber-100 rounded-full shadow-sm">Minor Delay</span>
                        @else
                            <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-rose-50 text-rose-700 border border-rose-100 rounded-full shadow-sm">Disrupted</span>
                        @endif
                    </div>

                    <!-- Row 2: Buses Count + Next Bus ETA -->
                    <div class="text-[12px] font-bold text-slate-400 flex items-center gap-1">
                        <i class="ti ti-bus text-[13px] text-slate-400"></i>
                        <span>{{ $route->buses_on_route }} buses</span>
                        <span class="text-slate-300">•</span>
                        <i class="ti ti-clock text-[13px] text-slate-400"></i>
                        <span>Next bus: <strong class="text-slate-600 font-bold">{{ $route->next_eta_label }}</strong></span>
                    </div>

                    <!-- Row 3: Trip Progress Bar -->
                    @php
                        $progressPercent = $route->scheduled_trips > 0 ? min(100, ($route->completed_trips / $route->scheduled_trips) * 100) : 0;
                    @endphp
                    <div class="flex flex-col gap-1.5 pt-1.5 border-t border-slate-50">
                        <div class="w-full bg-slate-100 rounded-full h-[4px] overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-500" 
                                 style="width: {{ $progressPercent }}%; background-color: {{ $route->route_color }};">
                            </div>
                        </div>
                        <div class="text-[10px] font-extrabold text-slate-400 text-right">
                            {{ $route->completed_trips }} / {{ $route->scheduled_trips }} trips completed
                        </div>
                    </div>

                </a>
            @empty
                <!-- Empty State -->
                <div class="w-full py-8 flex flex-col items-center justify-center bg-white border border-slate-100 shadow-[0_4px_24px_rgba(15,23,42,0.02)] rounded-2xl px-4">
                    <i class="ti ti-route text-slate-300 text-4xl mb-2"></i>
                    <p class="text-xs font-semibold text-slate-400 text-center">No active routes at this time.</p>
                </div>
            @endforelse
        </div>
    </section>

    <!-- SECTION 5: LATEST SERVICE ALERTS -->
    <section class="px-4 pt-5 flex flex-col gap-3.5 select-none">
        <!-- Header -->
        <div class="flex justify-between items-center px-0.5">
            <h2 class="text-[16px] font-bold text-slate-800 tracking-tight">Service alerts</h2>
            <a href="{{ route('commuter.alerts') }}" class="text-[13px] font-bold text-[#003F87] active:opacity-75 flex items-center gap-0.5">
                See all <i class="ti ti-chevron-right text-xs"></i>
            </a>
        </div>

        <!-- Alerts Stacked List -->
        <div class="flex flex-col gap-2.5">
            @forelse($latestAlerts as $alert)
                @php
                    // Map type styling
                    $border = '#378ADD';
                    $bg = 'bg-sky-50/55 border-sky-100';
                    $icon = 'ti-info-circle';
                    $iconColor = 'text-sky-600';

                    if ($alert->type === 'delay') {
                        $border = '#D97706';
                        $bg = 'bg-amber-50/55 border-amber-100';
                        $icon = 'ti-clock';
                        $iconColor = 'text-amber-600';
                    } elseif ($alert->type === 'suspension') {
                        $border = '#E11D48';
                        $bg = 'bg-rose-50/55 border-rose-100';
                        $icon = 'ti-ban';
                        $iconColor = 'text-rose-600';
                    } elseif ($alert->type === 'maintenance') {
                        $border = '#B45309';
                        $bg = 'bg-orange-50/55 border-orange-100';
                        $icon = 'ti-tool';
                        $iconColor = 'text-orange-600';
                    }
                @endphp
                <div class="rounded-2xl border-l-[4px] border border-t-slate-100 border-r-slate-100 border-b-slate-100 p-4 flex flex-col gap-2 shadow-[0_4px_20px_rgba(15,23,42,0.01)] {{ $bg }}" 
                     style="border-left-color: {{ $border }};">
                    
                    <!-- Row 1: Icon + Title + Timestamp -->
                    <div class="flex justify-between items-start gap-3">
                        <div class="flex items-center gap-2 min-w-0">
                            <div class="w-6 h-6 rounded-full bg-white/80 border border-slate-100 flex items-center justify-center flex-shrink-0">
                                <i class="ti {{ $icon }} text-[14px] {{ $iconColor }}"></i>
                            </div>
                            <span class="text-[13px] font-bold text-slate-800 leading-snug truncate">{{ $alert->title }}</span>
                        </div>
                        <span class="text-[10px] font-extrabold text-slate-400 whitespace-nowrap mt-1 uppercase tracking-wider">{{ $alert->created_at }}</span>
                    </div>

                    <!-- Row 2: Message Body -->
                    <p class="text-[12px] text-slate-600 leading-relaxed font-medium line-clamp-2 pl-0.5">
                        {{ $alert->message }}
                    </p>

                    <!-- Row 3: Affected Routes -->
                    <div class="text-[10px] font-bold text-slate-400 uppercase tracking-wider pl-0.5 mt-0.5">
                        Affected: <span class="text-slate-500 font-extrabold">{{ $alert->affected_routes }}</span>
                    </div>

                </div>
            @empty
                <!-- Inline Note if empty -->
                <div class="w-full bg-emerald-50/60 border border-emerald-100 rounded-2xl py-3.5 px-4 flex items-center justify-center gap-2.5 shadow-sm">
                    <i class="ti ti-circle-check text-emerald-600 text-lg"></i>
                    <span class="text-xs text-emerald-700 font-bold">No active alerts — all routes operating normally ✓</span>
                </div>
            @endforelse
        </div>
    </section>

    <!-- SECTION 6: TODAY'S SCHEDULE PEEK -->
    <section class="px-4 pt-5 flex flex-col gap-3.5 select-none">
        <!-- Header -->
        <div class="flex justify-between items-center px-0.5">
            <h2 class="text-[16px] font-bold text-slate-800 tracking-tight">Today's schedule</h2>
            <a href="{{ route('commuter.schedule') }}" class="text-[13px] font-bold text-[#003F87] active:opacity-75 flex items-center gap-0.5">
                Full schedule <i class="ti ti-chevron-right text-xs"></i>
            </a>
        </div>

        <!-- Horizontal Scroll Row (Premium Airline Stub look) -->
        <div class="flex items-center gap-3.5 overflow-x-auto no-scrollbar pb-3 px-0.5" style="-webkit-overflow-scrolling: touch;">
            @forelse($schedulepeek as $sched)
                <div class="flex-shrink-0 w-[155px] bg-white rounded-2xl border border-slate-100 p-4 flex flex-col gap-3.5 shadow-[0_6px_24px_rgba(15,23,42,0.02)] select-none">
                    <!-- Row 1: Colored dot + name -->
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="w-[8px] h-[8px] rounded-full flex-shrink-0" style="background-color: {{ $sched->route_color }}; box-shadow: 0 0 6px {{ $sched->route_color }};"></span>
                        <span class="text-[12.5px] font-extrabold text-slate-800 truncate">{{ $sched->route_name }}</span>
                    </div>

                    <div class="flex flex-col gap-2.5">
                        <!-- Row 2: First trip label & value -->
                        <div class="flex flex-col gap-0.5 pl-0.5">
                            <span class="text-[9px] font-extrabold text-slate-400 leading-none uppercase tracking-wider">First trip</span>
                            <span class="text-[13.5px] font-bold text-slate-700 mt-0.5">{{ $sched->first_trip }}</span>
                        </div>

                        <!-- Row 3: Last trip label & value -->
                        <div class="flex flex-col gap-0.5 pl-0.5">
                            <span class="text-[9px] font-extrabold text-slate-400 leading-none uppercase tracking-wider">Last trip</span>
                            <span class="text-[13.5px] font-bold text-slate-700 mt-0.5">{{ $sched->last_trip }}</span>
                        </div>
                    </div>

                    <!-- Row 4: Status Note with bullet -->
                    <div class="text-[11px] font-bold mt-0.5 leading-none pl-0.5">
                        @if($sched->service_status === 'In service')
                            <span class="text-emerald-600 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                In service
                            </span>
                        @elseif(str_starts_with($sched->service_status, 'Starts in'))
                            <span class="text-amber-600 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                {{ $sched->service_status }}
                            </span>
                        @else
                            <span class="text-slate-400 flex items-center gap-1">
                                <span class="h-1.5 w-1.5 rounded-full bg-slate-300"></span>
                                {{ $sched->service_status }}
                            </span>
                        @endif
                    </div>

                </div>
            @empty
                <!-- Empty State -->
                <div class="w-full py-8 flex flex-col items-center justify-center bg-white border border-slate-100 shadow-[0_4px_24px_rgba(15,23,42,0.02)] rounded-2xl px-4">
                    <i class="ti ti-calendar-off text-slate-300 text-4xl mb-2"></i>
                    <p class="text-xs font-semibold text-slate-400 text-center">No schedules available today.</p>
                </div>
            @endforelse
        </div>
    </section>

</div>
@endsection

