<aside class="flex w-[240px] flex-col bg-[#001F44] text-white shrink-0 z-20">
    <div class="flex h-14 items-center justify-between border-b border-white/10 px-5 shrink-0">
        <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 p-1 backdrop-blur-sm">
                <img src="{{ asset('images/pasig_logo_1.png') }}" alt="Pasig Seal" class="h-full w-full object-contain">
            </div>
            <span class="text-base font-extrabold tracking-wider uppercase">GoPasig</span>
        </div>
        <span class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white/70">Fleet</span>
    </div>

    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 scrollbar-thin scrollbar-thumb-white/10">
        <a href="{{ route('fleet.dashboard') }}" data-nav="overview" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.dashboard') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-layout-dashboard text-[20px]"></i>
            Overview
        </a>
        <a href="{{ route('fleet.monitor') }}" data-nav="monitor" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.monitor') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-map-pin text-[20px]"></i>
            Fleet Monitor
        </a>
        <a href="{{ route('fleet.utilization') }}" data-nav="utilization" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.utilization') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-chart-dots text-[20px]"></i>
            Utilization
        </a>
        <a href="{{ route('fleet.drivers') }}" data-nav="drivers" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.drivers') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-id text-[20px]"></i>
            Drivers
        </a>
        <a href="{{ route('fleet.routes') }}" data-nav="routes" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.routes') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-route text-[20px]"></i>
            Routes
        </a>
        <a href="{{ route('fleet.schedule') }}" data-nav="schedule" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.schedule') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-clock-check text-[20px]"></i>
            Schedule
        </a>
        <a href="{{ route('fleet.incidents') }}" data-nav="incidents" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.incidents') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-alert-triangle text-[20px]"></i>
            Incidents
        </a>
        <a href="{{ route('fleet.maintenance') }}" data-nav="maintenance" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.maintenance') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-tool text-[20px]"></i>
            Maintenance
        </a>
        <a href="{{ route('fleet.announcements') }}" data-nav="announcements" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.announcements') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-speakerphone text-[20px]"></i>
            Announcements
        </a>
        <a href="{{ route('fleet.analytics') }}" data-nav="analytics" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.analytics') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-chart-bar text-[20px]"></i>
            Analytics
        </a>
        <a href="{{ route('fleet.dispatch_intelligence') }}" data-nav="dispatch-intelligence" class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ request()->routeIs('fleet.dispatch_intelligence') ? 'bg-white/12 text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-brain text-[20px]"></i>
            Dispatch Intelligence
        </a>

        <!-- Commuter Monitor Collapsible Dropdown -->
        <div class="space-y-1">
            <button onclick="toggleCommuterDropdown()" class="flex w-full items-center justify-between px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left text-white/70 hover:text-white hover:bg-white/[0.04]">
                <div class="flex items-center gap-3">
                    <i class="ti ti-users text-[20px]"></i>
                    <span>Commuter Monitor</span>
                </div>
                <i id="commuter-dropdown-arrow" class="ti ti-chevron-down text-[12px] transition-transform duration-200"></i>
            </button>
            <div id="commuter-dropdown-menu" class="hidden pl-6 space-y-1">
                <a href="?tab=commuter-trips" data-nav="commuter-trips" class="flex w-full items-center gap-3 px-4 py-2 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left text-white/70 hover:text-white hover:bg-white/[0.04]">
                    <i class="ti ti-clipboard-list text-[18px]"></i>
                    Commuter Trip Log
                </a>
                <a href="?tab=commuter-sessions" data-nav="commuter-sessions" class="flex w-full items-center gap-3 px-4 py-2 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left text-white/70 hover:text-white hover:bg-white/[0.04]">
                    <i class="ti ti-key text-[18px]"></i>
                    Active Sessions
                </a>
            </div>
        </div>
    </nav>

    <div class="border-t border-white/10 p-4 flex items-center gap-3 shrink-0">
        <img src="{{ asset('images/pasig_logo_1.png') }}" alt="Pasig Seal" class="h-9 w-9 object-contain">
        <div class="overflow-hidden">
            <p class="text-[10px] font-extrabold uppercase tracking-widest text-white/50">Lungsod ng Pasig</p>
            <p class="text-xs font-bold truncate text-white">Umaagos Ang Pag-Asa</p>
        </div>
        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
            @csrf
        </form>
        <button onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="ml-auto text-white/60 hover:text-white transition-colors flex items-center justify-center p-1" title="Sign Out">
            <i class="ti ti-logout text-[18px]"></i>
        </button>
    </div>
</aside>

<script>
    function toggleCommuterDropdown() {
        const menu = document.getElementById('commuter-dropdown-menu');
        const arrow = document.getElementById('commuter-dropdown-arrow');
        if (!menu || !arrow) return;

        if (menu.classList.contains('hidden')) {
            menu.classList.remove('hidden');
            arrow.classList.add('rotate-180');
        } else {
            menu.classList.add('hidden');
            arrow.classList.remove('rotate-180');
        }
    }
</script>
