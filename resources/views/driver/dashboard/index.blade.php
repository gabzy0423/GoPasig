@extends('layouts.driver')

@section('title', 'GoPasig - Driver Dashboard')

@section('content')
<div class="flex flex-col gap-5 px-4 pt-4 pb-8 select-none">

    <!-- WELCOME HERO HEADER -->
    <div class="flex flex-col gap-1.5 pt-2">
        <span class="text-xs font-extrabold text-[#003F87] uppercase tracking-widest">{{ strtoupper(Auth::user()->role) }} PANEL</span>
        <h1 class="text-2xl font-black text-slate-800 tracking-tight leading-none">
            Magandang araw, <span class="text-[#003F87]">{{ $driver ? $driver->first_name : explode(' ', Auth::user()->name)[0] }}</span>! 👋
        </h1>
        <p class="text-xs text-slate-400 font-semibold">
            {{ $route ? $route->name : (config('app.name', 'Pasig City') . ' Libreng Sakay') }} • {{ now()->format('l, M d, Y') }}
        </p>
    </div>

    <!-- QUICK STATUS ALERT (If license is near expiry) -->
    @if($driver && $driver->license_expiry && $driver->license_expiry->isBefore(now()->addMonths(3)))
        <div class="bg-amber-50 border border-amber-100 rounded-2xl p-4 flex gap-3 shadow-sm">
            <div class="w-10 h-10 rounded-xl bg-amber-500/10 flex items-center justify-center text-amber-700 flex-shrink-0">
                <i class="ti ti-alert-triangle text-xl"></i>
            </div>
            <div class="flex flex-col gap-0.5 min-w-0">
                <span class="text-xs font-black text-amber-700 uppercase tracking-wide">License Renewal Alert</span>
                <p class="text-[11.5px] text-slate-650 font-medium leading-relaxed">
                    Your professional license will expire on <strong class="text-amber-800 font-bold">{{ $driver->license_expiry->format('F d, Y') }}</strong>. Please prepare for renewal.
                </p>
            </div>
        </div>
    @endif

    <!-- QUICK STATS GRID -->
    <div class="grid grid-cols-2 gap-3.5">
        
        <!-- Stats Card 1: Trips Today -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 flex flex-col gap-1.5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] relative overflow-hidden">
            <div class="absolute right-[-10px] bottom-[-10px] text-slate-100 pointer-events-none">
                <i class="ti ti-steering-wheel text-6xl"></i>
            </div>
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Trips Today</span>
            <div class="flex items-baseline gap-1 mt-1 z-10">
                <span class="text-2xl font-black text-slate-800 font-mono" id="stats-trips">{{ $quickStats->trips_today }}</span>
                <span class="text-[11px] font-bold text-slate-450">{{ $quickStats->trips_today == 1 ? 'run' : 'runs' }}</span>
            </div>
        </div>

        <!-- Stats Card 2: Passengers Hauled -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 flex flex-col gap-1.5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] relative overflow-hidden">
            <div class="absolute right-[-10px] bottom-[-10px] text-slate-100 pointer-events-none">
                <i class="ti ti-users text-6xl"></i>
            </div>
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Pax Served</span>
            <div class="flex items-baseline gap-1 mt-1 z-10">
                <span class="text-2xl font-black text-[#003F87] font-mono" id="stats-pax">{{ $quickStats->pax_today }}</span>
                <span class="text-[11px] font-bold text-slate-455">{{ $quickStats->pax_today == 1 ? 'rider' : 'riders' }}</span>
            </div>
        </div>

        <!-- Stats Card 3: Performance Score -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 flex flex-col gap-1.5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] relative overflow-hidden">
            <div class="absolute right-[-10px] bottom-[-10px] text-slate-100 pointer-events-none">
                <i class="ti ti-award text-6xl"></i>
            </div>
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Rating</span>
            <div class="flex items-baseline gap-1 mt-1 z-10">
                <span class="text-2xl font-black text-emerald-600 font-mono">{{ $quickStats->performance_score }}%</span>
                <span class="text-[11px] font-bold text-slate-450">{{ $quickStats->performance_score >= 90 ? 'excellent' : ($quickStats->performance_score >= 75 ? 'good' : 'warning') }}</span>
            </div>
        </div>

        <!-- Stats Card 4: 30-Day Incidents -->
        <div class="bg-white border border-slate-100 rounded-2xl p-4 flex flex-col gap-1.5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] relative overflow-hidden">
            <div class="absolute right-[-10px] bottom-[-10px] text-slate-100 pointer-events-none">
                <i class="ti ti-shield-alert text-6xl"></i>
            </div>
            <span class="text-[10px] font-extrabold text-slate-400 uppercase tracking-widest">Incidents</span>
            <div class="flex items-baseline gap-1 mt-1 z-10">
                <span class="text-2xl font-black font-mono {{ $quickStats->incidents_30 > 0 ? 'text-rose-600' : 'text-slate-700' }}">{{ $quickStats->incidents_30 }}</span>
                <span class="text-[11px] font-bold text-slate-450">{{ $quickStats->incidents_30 == 1 ? 'incident' : 'incidents' }}</span>
            </div>
        </div>

    </div>

    <!-- PRIMARY STEERING BUTTON (STEER COMPONENT CTA) -->
    <a href="{{ route('driver.trip') }}" class="relative group active:scale-[0.98] transition-all duration-200">
        <!-- Glowing gradient effect in back -->
        <div class="absolute inset-0 bg-gradient-to-r from-[#003F87] to-[#0050a3] rounded-2xl blur-md opacity-20 group-hover:opacity-35 transition-opacity"></div>
        <div class="relative bg-gradient-to-r from-[#003F87] to-[#0050a3] rounded-2xl p-5 shadow-[0_8px_30px_rgba(0,63,135,0.15)] flex items-center justify-between text-white border border-[#003F87]/10">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-white/10 flex items-center justify-center shadow-inner animate-pulse">
                    <i class="ti ti-steering-wheel text-[28px] text-white"></i>
                </div>
                <div class="flex flex-col gap-0.5">
                    <span class="text-[16px] font-black tracking-tight leading-tight">Start Driving Session</span>
                    <span class="text-[11px] font-medium text-blue-100">Click to start live tracking & passenger log</span>
                </div>
            </div>
            <div class="w-8 h-8 rounded-full bg-white/10 flex items-center justify-center">
                <i class="ti ti-chevron-right text-lg"></i>
            </div>
        </div>
    </a>

    <!-- ASSIGNED VEHICLE & ROUTE CARD -->
    <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] flex flex-col gap-4">
        <!-- Header -->
        <div class="flex justify-between items-center pb-3 border-b border-slate-100">
            <div class="flex items-center gap-2">
                <i class="ti ti-bus text-[#003F87] text-[18px]"></i>
                <span class="text-xs font-black text-slate-700 uppercase tracking-widest">Active Assignment</span>
            </div>
            @if($bus)
                <span class="px-2.5 py-0.5 text-[9.5px] font-black rounded-md {{ $bus->status === 'active' ? 'bg-rose-50 text-rose-600 border border-rose-100' : 'bg-slate-55 text-slate-500 border border-slate-200' }} tracking-widest uppercase">
                    {{ $bus->status }}
                </span>
            @endif
        </div>

        @if($driver && $driver->assigned_bus)
            <div class="flex flex-col gap-3.5">
                <!-- Vehicle Plate & Cap -->
                @php
                    $vehicleType = 'BUS';
                    if ($bus) {
                        if ($bus->capacity <= 15) {
                            $vehicleType = 'VAN';
                        } elseif ($bus->capacity <= 30) {
                            $vehicleType = 'COACH';
                        } else {
                            $vehicleType = 'BUS';
                        }
                    }
                @endphp
                <div class="flex justify-between items-center bg-slate-50/70 rounded-xl p-3.5 border border-slate-100">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-lg bg-[#003F87]/10 border border-[#003F87]/15 flex items-center justify-center font-bold text-[#003F87] font-mono text-xs shadow-sm">
                            {{ $vehicleType }}
                        </div>
                        <div class="flex flex-col">
                            <span class="text-[14px] font-black text-slate-800 font-mono tracking-wide">{{ $driver->assigned_bus }}</span>
                            <span class="text-[10px] font-semibold text-slate-400">Assigned Shuttle</span>
                        </div>
                    </div>
                    @if($bus)
                        <div class="flex flex-col items-end">
                            <span class="text-sm font-extrabold text-slate-700 font-mono">{{ $bus->capacity }} seats</span>
                            <span class="text-[9.5px] font-semibold text-slate-400">Capacity</span>
                        </div>
                    @endif
                </div>

                <!-- Route Details -->
                @if($route)
                    <div class="flex flex-col gap-2">
                        <div class="flex items-center gap-2">
                            <span class="h-2 w-2 rounded-full" style="background-color: {{ $route->color ?: '#003F87' }}; box-shadow: 0 0 6px {{ $route->color ?: '#003F87' }};"></span>
                            <span class="text-xs font-bold text-slate-450 uppercase tracking-wider">Assigned Route</span>
                        </div>
                        <div class="flex flex-col gap-1 pl-4">
                            <span class="text-sm font-extrabold text-slate-800 tracking-tight leading-tight">{{ $route->name }}</span>
                            @php
                                $startStop = \App\Models\Stop::where('route_id', $route->id)->orderBy('sequence')->first();
                                $endStop = \App\Models\Stop::where('route_id', $route->id)->orderBy('sequence', 'desc')->first();
                            @endphp
                            @if($startStop && $endStop)
                                <span class="text-[10.5px] font-bold text-slate-400 font-mono">
                                    @if($startStop->name === $endStop->name)
                                        {{ $startStop->name }} (Loop Route)
                                    @else
                                        {{ $startStop->name }} ➔ {{ $endStop->name }}
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @else
            <!-- Empty State -->
            <div class="py-6 flex flex-col items-center justify-center text-center">
                <i class="ti ti-barrier-block text-slate-400 text-3xl mb-2"></i>
                <span class="text-xs text-slate-500 font-bold">
                    @if($driver && $driver->status === 'suspended')
                        Suspended — Hindi ka maaaring bumiyahe sa kasalukuyan.
                    @elseif($driver && $driver->status === 'inactive')
                        Off Duty — Naka-off duty ka sa kasalukuyan.
                    @elseif(\App\Models\Bus::where('status', 'active')->count() === 0)
                        Walang available na shuttle sa fleet sa ngayon.
                    @else
                        Walang aktibong shuttle na naka-assign sa iyo ngayong araw.
                    @endif
                </span>
                <span class="text-[10px] text-slate-400 font-semibold mt-1">
                    @if($driver && $driver->status === 'suspended')
                        Mangyaring makipag-ugnayan sa Operations head.
                    @else
                        Mangyaring makipag-ugnayan kay Dispatcher {{ $dispatcherName }}.
                    @endif
                </span>
            </div>
        @endif
    </div>

    <!-- QUICK DISPATCH LINKS -->
    <div class="flex flex-col gap-3">
        <h3 class="text-xs font-black text-slate-450 uppercase tracking-widest px-0.5">Quick Actions</h3>
        
        <div class="grid grid-cols-2 gap-3">
            
            <a href="{{ route('driver.schedule') }}" class="bg-white border border-slate-100 hover:bg-slate-50 active:scale-95 transition-all p-3.5 rounded-2xl flex flex-col gap-2 shadow-sm">
                <div class="w-8 h-8 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-650">
                    <i class="ti ti-calendar-event text-lg"></i>
                </div>
                <div class="flex flex-col leading-tight">
                    <span class="text-[12.5px] font-black text-slate-800">Shift List</span>
                    <span class="text-[9.5px] font-semibold text-slate-450 mt-0.5">Assigned schedules</span>
                </div>
            </a>

            <a href="{{ route('driver.announcements') }}" class="bg-white border border-slate-100 hover:bg-slate-50 active:scale-95 transition-all p-3.5 rounded-2xl flex flex-col gap-2 shadow-sm">
                <div class="w-8 h-8 rounded-xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-650">
                    <i class="ti ti-bell text-lg"></i>
                </div>
                <div class="flex flex-col leading-tight">
                    <span class="text-[12.5px] font-black text-slate-800">Service Alerts</span>
                    <span class="text-[9.5px] font-semibold text-slate-450 mt-0.5">Broadcast bulletin</span>
                </div>
            </a>

        </div>
    </div>

</div>
@endsection
