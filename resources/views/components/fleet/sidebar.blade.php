@php
    $activeFleetTab = request()->query('tab', 'overview');
    $isCommuterMonitorOpen = in_array($activeFleetTab, ['commuter-trips', 'commuter-sessions'], true);
    $navBase = 'flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left';
    $navActive = 'bg-white/12 text-white';
    $navInactive = 'text-white/70 hover:text-white hover:bg-white/[0.04]';
    $subNavBase = 'flex w-full items-center gap-3 px-4 py-2 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left';
@endphp

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
        <a href="{{ route('fleet.dashboard') }}" data-nav="overview" class="{{ $navBase }} {{ $activeFleetTab === 'overview' ? $navActive : $navInactive }}">
            <i class="ti ti-layout-dashboard text-[20px]"></i>
            Overview
        </a>
        <a href="{{ route('fleet.dashboard', ['tab' => 'monitor']) }}" data-nav="monitor" class="{{ $navBase }} {{ $activeFleetTab === 'monitor' ? $navActive : $navInactive }}">
            <i class="ti ti-map-pin text-[20px]"></i>
            Trace
        </a>
        <a href="{{ route('fleet.dashboard', ['tab' => 'utilization']) }}" data-nav="utilization" class="{{ $navBase }} {{ $activeFleetTab === 'utilization' ? $navActive : $navInactive }}">
            <i class="ti ti-chart-dots text-[20px]"></i>
            Utilization
        </a>
        <a href="{{ route('fleet.dashboard', ['tab' => 'drivers']) }}" data-nav="drivers" class="{{ $navBase }} {{ $activeFleetTab === 'drivers' ? $navActive : $navInactive }}">
            <i class="ti ti-id text-[20px]"></i>
            Drivers
        </a>
        <a href="{{ route('fleet.dashboard', ['tab' => 'routes']) }}" data-nav="routes" class="{{ $navBase }} {{ $activeFleetTab === 'routes' ? $navActive : $navInactive }}">
            <i class="ti ti-route text-[20px]"></i>
            Routes
        </a>
        <a href="{{ route('fleet.dashboard', ['tab' => 'schedule']) }}" data-nav="schedule" class="{{ $navBase }} {{ $activeFleetTab === 'schedule' ? $navActive : $navInactive }}">
            <i class="ti ti-clock-check text-[20px]"></i>
            Schedule
        </a>
        <a href="{{ route('fleet.dashboard', ['tab' => 'incidents']) }}" data-nav="incidents" class="{{ $navBase }} {{ $activeFleetTab === 'incidents' ? $navActive : $navInactive }}">
            <i class="ti ti-alert-triangle text-[20px]"></i>
            Incidents
        </a>
        <a href="{{ route('fleet.dashboard', ['tab' => 'maintenance']) }}" data-nav="maintenance" class="{{ $navBase }} {{ $activeFleetTab === 'maintenance' ? $navActive : $navInactive }}">
            <i class="ti ti-tool text-[20px]"></i>
            Maintenance
        </a>
        <a href="{{ route('fleet.dashboard', ['tab' => 'analytics']) }}" data-nav="analytics" class="{{ $navBase }} {{ $activeFleetTab === 'analytics' ? $navActive : $navInactive }}">
            <i class="ti ti-chart-bar text-[20px]"></i>
            Analytics
        </a>
        <a href="{{ route('fleet.dashboard', ['tab' => 'dispatch-intelligence']) }}" data-nav="dispatch-intelligence" class="{{ $navBase }} {{ $activeFleetTab === 'dispatch-intelligence' ? $navActive : $navInactive }}">
            <i class="ti ti-brain text-[20px]"></i>
            Dispatch Intelligence
        </a>

        <!-- Commuter Monitor Collapsible Dropdown -->
        <div class="space-y-1">
            <button onclick="toggleCommuterDropdown()" class="flex w-full items-center justify-between px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ $isCommuterMonitorOpen ? $navActive : $navInactive }}">
                <div class="flex items-center gap-3">
                    <i class="ti ti-users text-[20px]"></i>
                    <span>Commuter Monitor</span>
                </div>
                <i id="commuter-dropdown-arrow" class="ti ti-chevron-down text-[12px] transition-transform duration-200 {{ $isCommuterMonitorOpen ? 'rotate-180' : '' }}"></i>
            </button>
            <div id="commuter-dropdown-menu" class="{{ $isCommuterMonitorOpen ? '' : 'hidden' }} pl-6 space-y-1">
                <a href="{{ route('fleet.dashboard', ['tab' => 'commuter-trips']) }}" data-nav="commuter-trips" class="{{ $subNavBase }} {{ $activeFleetTab === 'commuter-trips' ? $navActive : $navInactive }}">
                    <i class="ti ti-clipboard-list text-[18px]"></i>
                    Commuter Trip Log
                </a>
                <a href="{{ route('fleet.dashboard', ['tab' => 'commuter-sessions']) }}" data-nav="commuter-sessions" class="{{ $subNavBase }} {{ $activeFleetTab === 'commuter-sessions' ? $navActive : $navInactive }}">
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
        <button onclick="event.preventDefault(); if (this.disabled) return; this.disabled = true; window.GoPasigUI?.showLoadingOverlay('Signing you out...', 'Please wait.'); window.GoPasigFleetRequestLifecycle?.beginLogout(); document.getElementById('logout-form').submit();" class="ml-auto text-white/60 hover:text-white transition-colors flex items-center justify-center p-1" title="Sign Out">
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