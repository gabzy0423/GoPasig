@extends('layouts.driver')

@section('title', 'GoPasig - Driver Shifts')

@section('content')
<div class="flex flex-col gap-5 px-4 pt-4 pb-8 select-none">
    
    <!-- HEADER TITLE & SUB -->
    <div class="flex flex-col gap-0.5">
        <span class="text-[10px] font-extrabold text-[#003F87] uppercase tracking-widest">Timetable Control</span>
        <h1 class="text-xl font-black text-slate-800 tracking-tight leading-none">Shift Schedules</h1>
    </div>

    <!-- MAIN ASSIGNMENT CARD -->
    @if($driver && $driver->assigned_route)
        @php
            $route = \App\Models\Route::find($driver->assigned_route);
            $color = ($route && $route->color) ? $route->color : '#003F87';
        @endphp
        
        <div class="bg-white border border-slate-100 rounded-2xl p-5 shadow-[0_4px_24px_rgba(15,23,42,0.02)] flex flex-col gap-3.5">
            <!-- Route Header -->
            <div class="flex justify-between items-center pb-2.5 border-b border-slate-100">
                <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background-color: {{ $color }}; box-shadow: 0 0 6px {{ $color }};"></span>
                    <span class="text-[14px] font-black text-slate-850 tracking-tight">{{ $route ? $route->name : 'Route ' . $driver->assigned_route }}</span>
                </div>
                <span class="text-[10.5px] font-extrabold text-slate-400 font-mono uppercase tracking-widest">Active Line</span>
            </div>

            <!-- Route Details -->
            <div class="grid grid-cols-2 gap-4 text-slate-600">
                <div class="flex flex-col gap-0.5">
                    <span class="text-[9.5px] font-extrabold text-slate-400 uppercase tracking-wider">Assigned Bus</span>
                    <span class="text-sm font-extrabold text-slate-800 font-mono tracking-wide">{{ $driver->assigned_bus ?: 'N/A' }}</span>
                </div>
                <div class="flex flex-col gap-0.5 items-end">
                    <span class="text-[9.5px] font-extrabold text-slate-400 uppercase tracking-wider">Target Trips</span>
                    <span class="text-sm font-extrabold text-[#003F87] font-mono">{{ $schedules->count() }} {{ $schedules->count() === 1 ? 'Run' : 'Runs' }}</span>
                </div>
            </div>
        </div>

        <!-- TRIP TIMETABLE BOARD -->
        <div class="flex flex-col gap-3">
            <h3 class="text-xs font-black text-slate-450 uppercase tracking-widest px-0.5">Assigned Timetable today</h3>
            
            <div class="flex flex-col gap-3">
                @forelse($schedules as $index => $sched)
                    @php
                        // Check if trip is simulated as done
                        $isDone = ($index < $driver->trips_today);
                        $isCurrent = ($index === $driver->trips_today);
                        
                        $depTime = \Carbon\Carbon::parse($sched->departure_time)->format('g:i A');
                    @endphp

                    <div class="relative bg-white border rounded-2xl p-4 flex items-center justify-between shadow-sm premium-transition
                        {{ $isCurrent ? 'border-[#003F87]/50 bg-white shadow-[0_4px_16px_rgba(0,63,135,0.06)]' : 'border-slate-100' }}
                        {{ $isDone ? 'opacity-65 bg-slate-50/40' : '' }}">
                        
                        <!-- Left Details -->
                        <div class="flex items-center gap-3.5">
                            <!-- Index Badge -->
                            <div class="w-8 h-8 rounded-xl flex items-center justify-center font-black font-mono text-[13px] border
                                {{ $isCurrent ? 'bg-[#003F87]/10 text-[#003F87] border-[#003F87]/20' : '' }}
                                {{ $isDone ? 'bg-emerald-50 text-emerald-600 border-emerald-100' : 'bg-slate-50 text-slate-400 border-slate-200' }}">
                                {{ $index + 1 }}
                            </div>

                            <div class="flex flex-col leading-tight">
                                <span class="text-[13px] font-black text-slate-800 font-mono tracking-wide">{{ $depTime }}</span>
                                <span class="text-[9.5px] font-semibold text-slate-400 mt-0.5">
                                    {{ $sched->next_stop ?: 'All Station Dispatch' }}
                                </span>
                            </div>
                        </div>

                        <!-- Right Status Indicator -->
                        <div>
                            @if($isDone)
                                <span class="px-2.5 py-1 text-[9.5px] font-extrabold bg-emerald-50 text-emerald-600 border border-emerald-100 rounded-md tracking-wider uppercase flex items-center gap-1 shadow-sm">
                                    <i class="ti ti-circle-check text-xs"></i> DONE
                                </span>
                            @elseif($isCurrent)
                                <span class="px-2.5 py-1 text-[9.5px] font-extrabold bg-[#003F87] text-white rounded-md tracking-wider uppercase flex items-center gap-1 shadow-md animate-pulse">
                                    <i class="ti ti-steering-wheel text-xs"></i> ACTIVE
                                </span>
                            @else
                                <span class="px-2.5 py-1 text-[9.5px] font-extrabold bg-slate-50 text-slate-400 border border-slate-200 rounded-md tracking-wider uppercase flex items-center gap-1 shadow-inner">
                                    <i class="ti ti-clock text-xs"></i> PENDING
                                </span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="bg-white border border-slate-100 rounded-2xl p-6 text-center py-10 shadow-sm flex flex-col items-center justify-center">
                        <i class="ti ti-calendar-off text-slate-300 text-5xl mb-3"></i>
                        <h3 class="text-sm font-black text-slate-800 leading-tight">No Shift Scheduled</h3>
                        <p class="text-xs text-slate-450 font-semibold mt-1 px-4">
                            There are no active shift timetables assigned for this route today.
                        </p>
                    </div>
                @endforelse
            </div>
        </div>
    @else
        <!-- Unassigned route warning -->
        <div class="bg-white border border-slate-100 rounded-2xl p-6 shadow-md text-center py-10 flex flex-col items-center justify-center">
            <i class="ti ti-barrier-block text-slate-350 text-5xl mb-3"></i>
            <h2 class="text-base font-black text-slate-850 leading-tight">Shift Blocked</h2>
            <p class="text-xs text-slate-450 font-semibold mt-1 px-4 text-center">
                You do not have a route assigned for active shifts today. Contact dispatcher {{ $dispatcherName }} at fleet operations to assign a shift schedule.
            </p>
        </div>
    @endif

</div>
@endsection
