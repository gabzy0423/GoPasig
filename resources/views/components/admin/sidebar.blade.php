@props(['active' => 'overview'])

<aside id="sidebar"
    class="fixed inset-y-0 left-0 z-50 flex w-[240px] flex-col bg-[#003F87] text-white transition-transform duration-300 md:relative md:translate-x-0 -translate-x-full">
    <!-- Sidebar Brand Header -->
    <div class="flex h-14 items-center justify-between border-b border-white/10 px-5 shrink-0">
        <div class="flex items-center gap-2.5">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-white/10 p-1 backdrop-blur-sm">
                <img src="{{ asset('images/pasig_logo_1.png') }}" alt="Pasig Seal" class="h-full w-full object-contain">
            </div>
            <span class="text-base font-extrabold tracking-wider uppercase">GoPasig</span>
        </div>
        <button onclick="toggleSidebar()" class="text-white/60 hover:text-white md:hidden cursor-pointer"
            aria-label="Collapse sidebar">
            <i class="ti ti-x text-lg"></i>
        </button>
        <span
            class="rounded-full bg-white/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white/70">Admin</span>

    </div>

    <!-- Sidebar Navigation List -->
    <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 scrollbar-thin scrollbar-thumb-white/10">
        <button onclick="switchScreen('overview')" data-nav="overview"
            class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ $active === 'overview' ? 'bg-[#0057BD] text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-layout-dashboard text-[20px]"></i>
            Overview / Dashboard
        </button>

        <button onclick="switchScreen('buses')" data-nav="buses"
            class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ $active === 'buses' ? 'bg-[#0057BD] text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-bus text-[20px]"></i>
            Bus Management
        </button>

        <button onclick="switchScreen('dispatch')" data-nav="dispatch"
            class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ $active === 'dispatch' ? 'bg-[#0057BD] text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-send text-[20px]"></i>
            Dispatch Management
        </button>

        <button onclick="switchScreen('maintenance')" data-nav="maintenance"
            class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ $active === 'maintenance' ? 'bg-[#0057BD] text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-tool text-[20px]"></i>
            Maintenance Records
        </button>

        <!-- Static/Placeholder Tabs for complete navigation layout -->
        <button onclick="switchScreen('map-view')" data-nav="map-view"
            class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ $active === 'map-view' ? 'bg-[#0057BD] text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-map-pin text-[20px]"></i>
            Live Fleet Map
        </button>

        <button onclick="switchScreen('drivers')" data-nav="drivers"
            class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ $active === 'drivers' ? 'bg-[#0057BD] text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-id text-[20px]"></i>
            Driver Management
        </button>

        <!-- Route Service Schedules Dropdown -->
        <div class="space-y-1" id="dropdown-routes-container">
            <button onclick="toggleSidebarDropdown('routes')"
                class="flex w-full items-center justify-between px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left text-white/70 hover:text-white hover:bg-white/[0.04]">
                <span class="flex items-center gap-3">
                    <i class="ti ti-route text-[20px]"></i>
                    Schedule &
                    Routes
                </span>
                <i class="ti ti-chevron-down transition-transform duration-200 text-sm opacity-75"
                    id="chevron-routes"></i>
            </button>
            <div id="menu-routes" class="hidden pl-8 pr-2 space-y-1">
                <button onclick="navigateToRoutesTab('stops')" data-nav="routes-stops"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-xs font-medium transition-all duration-200 rounded-md cursor-pointer text-left text-white/70 hover:text-white hover:bg-white/[0.04]">
                    <i class="ti ti-point text-[16px] opacity-60"></i>
                    Routes
                </button>
                <button onclick="navigateToRoutesTab('schedule')" data-nav="routes-schedule"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-xs font-medium transition-all duration-200 rounded-md cursor-pointer text-left text-white/70 hover:text-white hover:bg-white/[0.04]">
                    <i class="ti ti-point text-[16px] opacity-60"></i>
                    Route Service Schedules
                </button>
            </div>
        </div>

        <button onclick="switchScreen('alerts')" data-nav="alerts"
            class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ $active === 'alerts' ? 'bg-[#0057BD] text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-bell-ringing text-[20px]"></i>
            Service Alerts
        </button>

        <!-- Reports & Analytics Dropdown -->
        <div class="space-y-1" id="dropdown-analytics-container">
            <button onclick="toggleSidebarDropdown('analytics')"
                class="flex w-full items-center justify-between px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left text-white/70 hover:text-white hover:bg-white/[0.04]">
                <span class="flex items-center gap-3">
                    <i class="ti ti-chart-bar text-[20px]"></i>
                    Reports & Analytics
                </span>
                <i class="ti ti-chevron-down transition-transform duration-200 text-sm opacity-75"
                    id="chevron-analytics"></i>
            </button>
            <div id="menu-analytics" class="hidden pl-8 pr-2 space-y-1">
                <button onclick="navigateToAnalyticsSection('analytics-fleet-utilization')"
                    data-nav="analytics-fleet-utilization"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-xs font-medium transition-all duration-200 rounded-md cursor-pointer text-left text-white/70 hover:text-white hover:bg-white/[0.04]">
                    <i class="ti ti-point text-[16px] opacity-60"></i>
                    Fleet Utilization
                </button>
                <button onclick="navigateToAnalyticsSection('analytics-route-performance')"
                    data-nav="analytics-route-performance"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-xs font-medium transition-all duration-200 rounded-md cursor-pointer text-left text-white/70 hover:text-white hover:bg-white/[0.04]">
                    <i class="ti ti-point text-[16px] opacity-60"></i>
                    Route Performance
                </button>
                <button onclick="navigateToAnalyticsSection('analytics-driver-performance')"
                    data-nav="analytics-driver-performance"
                    class="flex w-full items-center gap-2.5 px-3 py-2 text-xs font-medium transition-all duration-200 rounded-md cursor-pointer text-left text-white/70 hover:text-white hover:bg-white/[0.04]">
                    <i class="ti ti-point text-[16px] opacity-60"></i>
                    Driver Performance
                </button>
            </div>
        </div>

        <button onclick="switchScreen('settings')" data-nav="settings"
            class="flex w-full items-center gap-3 px-4 py-2.5 text-xs font-semibold uppercase tracking-wider transition-all duration-200 rounded-md cursor-pointer text-left {{ $active === 'settings' ? 'bg-[#0057BD] text-white' : 'text-white/70 hover:text-white hover:bg-white/[0.04]' }}">
            <i class="ti ti-settings text-[20px]"></i>
            Settings
        </button>
    </nav>

    <!-- Sidebar Footer Seal Badge -->
    <div class="border-t border-white/10 p-4 flex items-center gap-3 shrink-0">
        <img src="{{ asset('images/pasig_logo_1.png') }}" alt="Pasig Seal" class="h-9 w-9 object-contain">
        <div class="overflow-hidden">
            <p class="text-[10px] font-extrabold uppercase tracking-widest text-white/50">Lungsod ng Pasig</p>
            <p class="text-xs font-bold truncate text-white">Umaagos Ang Pag-Asa</p>
        </div>
        <form id="admin-logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
            @csrf
        </form>
        <button onclick="event.preventDefault(); if (this.disabled) return; this.disabled = true; window.GoPasigUI?.showLoadingOverlay('Signing you out...', 'Please wait.'); window.GoPasigAdminRequestLifecycle?.beginLogout(); document.getElementById('admin-logout-form').submit();" class="ml-auto text-white/60 hover:text-white transition-colors flex items-center justify-center p-1 cursor-pointer" title="Sign Out">
            <i class="ti ti-logout text-[18px]"></i>
        </button>
    </div>
</aside>


