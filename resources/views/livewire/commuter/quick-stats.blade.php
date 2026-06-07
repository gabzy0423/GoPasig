<div wire:poll.30s class="bg-white/80 backdrop-blur-md border-b border-slate-100 select-none">
    <div class="flex items-center gap-3 px-4 py-3.5 overflow-x-auto no-scrollbar" style="-webkit-overflow-scrolling: touch;">
        
        <!-- Chip 1: Active buses -->
        <div class="flex items-center gap-1.5 flex-shrink-0 bg-sky-50/70 border border-sky-100 text-[#003F87] rounded-full px-4 py-1.8 text-[12px] font-bold shadow-sm active:scale-95 transition-transform duration-150">
            <i class="ti ti-bus text-[#003F87] text-[15px]"></i>
            <span>{{ $activeBuses }} Active</span>
        </div>

        <!-- Chip 2: Delayed -->
        <div class="flex items-center gap-1.5 flex-shrink-0 bg-amber-50/70 border border-amber-100 text-[#B45309] rounded-full px-4 py-1.8 text-[12px] font-bold shadow-sm active:scale-95 transition-transform duration-150">
            <i class="ti ti-clock text-[#B45309] text-[15px]"></i>
            <span>{{ $delayedBuses }} Delayed</span>
        </div>

        <!-- Chip 3: Riders today -->
        <div class="flex items-center gap-1.5 flex-shrink-0 bg-emerald-50/70 border border-emerald-100 text-[#047857] rounded-full px-4 py-1.8 text-[12px] font-bold shadow-sm active:scale-95 transition-transform duration-150">
            <i class="ti ti-users text-[#047857] text-[15px]"></i>
            <span>{{ number_format($passengersToday) }} Riders</span>
        </div>

        <!-- Chip 4: Alerts -->
        <div class="flex items-center gap-1.5 flex-shrink-0 bg-rose-50/70 border border-rose-100 text-[#B91C1C] rounded-full px-4 py-1.8 text-[12px] font-bold shadow-sm active:scale-95 transition-transform duration-150">
            <i class="ti ti-alert-triangle text-[#B91C1C] text-[15px]"></i>
            <span>{{ $openAlerts }} Alerts</span>
        </div>

    </div>
</div>
