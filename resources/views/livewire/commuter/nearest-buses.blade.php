<div wire:poll.30s class="flex flex-col select-none">
    
    <!-- Section Header Row -->
    <div class="flex justify-between items-center px-4 pt-5 pb-3">
        <h2 class="text-[16px] font-bold text-slate-800 tracking-tight">Nearest buses</h2>
        <a href="{{ route('commuter.tracker') }}" class="text-[13px] font-bold text-[#003F87] active:opacity-70 flex items-center gap-0.5">
            See all <i class="ti ti-chevron-right text-xs"></i>
        </a>
    </div>

    <!-- Scrollable Horizontal Cards Row -->
    <div class="flex items-center gap-3.5 px-4 pb-2.5 overflow-x-auto no-scrollbar" style="-webkit-overflow-scrolling: touch;">
        @forelse($nearestBuses as $bus)
            @php
                $isFull = $bus->status === 'Full';
            @endphp
            <!-- Bus Card (Highly Premium Modern Card) -->
            <a href="{{ route('commuter.tracker') }}?bus={{ $bus->id }}" class="flex-shrink-0 w-[210px] bg-white rounded-2xl border border-slate-100 shadow-[0_6px_24px_rgba(15,23,42,0.03)] p-4 flex flex-col gap-3 active:scale-[0.96] active:bg-slate-50 transition-all duration-200 select-none">
                
                <!-- Row 1: Bus license plate & status chip -->
                <div class="flex justify-between items-center">
                    <span class="bg-slate-50 border border-slate-200/80 px-2 py-0.5 rounded text-[11px] font-mono font-bold text-slate-700 tracking-tight">{{ $bus->plate }}</span>
                    @if($bus->status === 'On Time')
                        <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-emerald-50 text-emerald-700 border border-emerald-100 rounded-full shadow-sm">On Time</span>
                    @elseif($bus->status === 'Delayed')
                        <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-amber-50 text-amber-700 border border-amber-100 rounded-full shadow-sm">Delayed</span>
                    @else
                        <span class="px-2.5 py-0.5 text-[10px] font-extrabold bg-rose-50 text-rose-700 border border-rose-100 rounded-full shadow-sm">Full</span>
                    @endif
                </div>

                <!-- Row 2: Route Badge (Capsule look) -->
                <div class="flex items-center gap-2 bg-slate-50/50 border border-slate-100/80 rounded-xl px-2.5 py-1.5 min-w-0">
                    <span class="w-[8px] h-[8px] rounded-full flex-shrink-0" style="background-color: {{ $bus->route_color }}; box-shadow: 0 0 6px {{ $bus->route_color }};"></span>
                    <span class="text-[11.5px] font-bold text-slate-600 truncate">{{ $bus->route_name }}</span>
                </div>

                <!-- Row 3: Next Stop -->
                <div class="flex flex-col gap-0.5">
                    <span class="text-[10.5px] font-semibold text-slate-400 uppercase tracking-wider leading-none">Next stop</span>
                    <span class="text-[13px] font-semibold text-slate-800 leading-tight truncate mt-0.5">{{ $bus->next_stop }}</span>
                </div>

                <!-- Row 4: ETA Large Value -->
                <div class="flex items-baseline gap-1" wire:loading.class="opacity-40 animate-pulse">
                    <span class="text-[25px] font-black text-[#003F87] leading-none tracking-tight">{{ $bus->eta_minutes }} min</span>
                    <span class="text-[12px] font-bold text-slate-400 leading-none">away</span>
                </div>

                <!-- Row 5: Passenger Progress Bar -->
                <div class="flex flex-col gap-1.5 pt-1 border-t border-slate-50">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1">
                            <span class="text-[11px] font-semibold text-slate-500 leading-none">{{ $bus->onboard }} / {{ $bus->capacity }}</span>
                            @if($isFull)
                                <i class="ti ti-alert-circle text-[11px] text-[#E24B4A]"></i>
                            @endif
                        </div>
                        <span class="text-[10px] font-bold text-slate-400 leading-none">{{ round(($bus->onboard / $bus->capacity) * 100) }}%</span>
                    </div>
                    <!-- Modern Progress Track -->
                    <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                        <div class="h-full rounded-full transition-all duration-500" 
                             style="width: {{ min(100, ($bus->onboard / $bus->capacity) * 100) }}%; background: {{ $isFull ? '#E24B4A' : 'linear-gradient(to right, #003F87, #1E88E5)' }};">
                        </div>
                    </div>
                </div>

            </a>
        @empty
            <!-- Empty State -->
            <div class="w-full py-8 flex flex-col items-center justify-center bg-white border border-slate-100 shadow-[0_4px_20px_rgba(15,23,42,0.02)] rounded-2xl px-4">
                <i class="ti ti-bus-off text-slate-300 text-4xl mb-2"></i>
                <p class="text-xs font-semibold text-slate-400 text-center">No active buses nearby at this time.</p>
            </div>
        @endforelse
    </div>
</div>
