@extends('layouts.driver')

@section('title', 'GoPasig - Driver Alerts')

@section('content')
<div class="flex flex-col gap-5 px-4 pt-4 pb-8 select-none">
    
    <!-- HEADER TITLE & SUB -->
    <div class="flex flex-col gap-0.5">
        <span class="text-[10px] font-extrabold text-[#003F87] uppercase tracking-widest">Broadcast Control</span>
        <h1 class="text-xl font-black text-slate-800 tracking-tight leading-none">Service Alerts</h1>
    </div>

    <!-- MAIN ALERT BODY -->
    <div class="flex flex-col gap-3.5">
        @forelse($alerts as $alert)
            @php
                // Styling map matching commuter design
                $border = '#378ADD';
                $bg = 'bg-sky-50/55 border-sky-100';
                $icon = 'ti-info-circle';
                $iconColor = 'text-sky-650';

                if ($alert->type === 'delay') {
                    $border = '#D97706';
                    $bg = 'bg-amber-50/55 border-amber-100';
                    $icon = 'ti-clock';
                    $iconColor = 'text-amber-650';
                } elseif ($alert->type === 'suspension') {
                    $border = '#E11D48';
                    $bg = 'bg-rose-50/55 border-rose-100';
                    $icon = 'ti-ban';
                    $iconColor = 'text-rose-650';
                } elseif ($alert->type === 'maintenance') {
                    $border = '#B45309';
                    $bg = 'bg-orange-50/55 border-orange-100';
                    $icon = 'ti-tool';
                    $iconColor = 'text-orange-650';
                }
                
                $diffTime = $alert->created_at ? $alert->created_at->diffForHumans() : 'Just now';
            @endphp

            <div class="rounded-2xl border-l-[4px] border border-t-slate-100 border-r-slate-100 border-b-slate-100 p-4.5 flex flex-col gap-2.5 shadow-sm bg-white" 
                 style="border-left-color: {{ $border }};">
                
                <!-- Header: Icon + Title + Time -->
                <div class="flex justify-between items-start gap-3">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <div class="w-7 h-7 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center flex-shrink-0 {{ $iconColor }}">
                            <i class="ti {{ $icon }} text-base"></i>
                        </div>
                        <div class="flex flex-col leading-tight min-w-0">
                            <span class="text-xs font-black text-slate-800 truncate">{{ $alert->title }}</span>
                            <span class="text-[9px] font-extrabold text-slate-400 uppercase tracking-widest mt-0.5">{{ $alert->type }} bulletin</span>
                        </div>
                    </div>
                    <span class="text-[9px] font-extrabold text-slate-400 mt-1 uppercase tracking-wider whitespace-nowrap">{{ $diffTime }}</span>
                </div>

                <!-- Message -->
                <p class="text-[12px] text-slate-550 font-medium leading-relaxed pl-0.5">
                    {{ $alert->message }}
                </p>

                <!-- Footer details -->
                <div class="flex items-center justify-between pt-2 border-t border-slate-100 pl-0.5 text-[9.5px] font-extrabold text-slate-400 uppercase tracking-wider">
                    <span>Target Route</span>
                    <span class="text-slate-600 font-bold font-mono">{{ $alert->affected_routes ?: 'All Lines' }}</span>
                </div>
            </div>
        @empty
            <!-- Inline Normal Status if empty -->
            <div class="w-full bg-emerald-50 border border-emerald-100 rounded-2xl py-5 px-4 flex flex-col items-center justify-center gap-2 shadow-sm text-center">
                <div class="w-10 h-10 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-600">
                    <i class="ti ti-circle-check text-xl"></i>
                </div>
                <h3 class="text-xs font-black text-slate-800 leading-tight mt-1">Operations Normal</h3>
                <p class="text-[10px] text-slate-450 font-semibold px-4">
                    All routes are running smoothly. No active service alerts from dispatch today.
                </p>
            </div>
        @endforelse
    </div>

    @if($messages->isNotEmpty())
        <div class="flex flex-col gap-0.5 mt-2">
            <span class="text-[10px] font-extrabold text-[#003F87] uppercase tracking-widest">Direct Messages</span>
            <h2 class="text-lg font-black text-slate-800 tracking-tight leading-none">From Dispatch</h2>
        </div>

        <div class="flex flex-col gap-3.5">
            @foreach($messages as $message)
                @php
                    $senderName = $message->sender?->name ?? 'Dispatcher';
                    $diffTime = $message->created_at ? $message->created_at->diffForHumans() : 'Just now';
                @endphp

                <div class="rounded-2xl border-l-[4px] border border-t-slate-100 border-r-slate-100 border-b-slate-100 p-4.5 flex flex-col gap-2.5 shadow-sm bg-white"
                     style="border-left-color: #003F87;">

                    <div class="flex justify-between items-start gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <div class="w-7 h-7 rounded-xl bg-sky-50 border border-sky-100 flex items-center justify-center flex-shrink-0 text-sky-650">
                                <i class="ti ti-message-2 text-base"></i>
                            </div>
                            <div class="flex flex-col leading-tight min-w-0">
                                <span class="text-xs font-black text-slate-800 truncate">{{ $senderName }}</span>
                                <span class="text-[9px] font-extrabold text-slate-400 uppercase tracking-widest mt-0.5">Personal message</span>
                            </div>
                        </div>
                        <span class="text-[9px] font-extrabold text-slate-400 mt-1 uppercase tracking-wider whitespace-nowrap">{{ $diffTime }}</span>
                    </div>

                    <p class="text-[12px] text-slate-550 font-medium leading-relaxed pl-0.5">
                        {{ $message->message }}
                    </p>
                </div>
            @endforeach
        </div>
    @endif

</div>
@endsection
