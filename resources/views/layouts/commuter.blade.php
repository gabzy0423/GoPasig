<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title>@yield('title', 'GoPasig Libreng Sakay')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/pasig_logo.png') }}">
    
    <!-- Google Fonts: Plus Jakarta Sans & DM Mono -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Mono:wght@400;500&family=Plus+Jakarta+Sans:ital,wght@0,300..800;1,300..800&display=swap" rel="stylesheet">
    
    <!-- Tabler Webfont Icons -->
    <link rel="stylesheet" href="{{ asset('css/tabler-icons.css') }}">
    
    <!-- Tailwind CSS & Livewire Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles

    <style>
        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: #0F172A; /* Dark premium backdrop for screen centering */
        }
        .font-mono {
            font-family: 'DM Mono', monospace;
        }
        /* Custom horizontal scrollbar hidden style */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        /* Smooth premium transitions */
        .premium-transition {
            transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
        }
        :root {
            --commuter-header-height: 60px;
            --commuter-bottom-nav-height: 68px;
            --commuter-bottom-nav-offset: 1rem;
            --commuter-content-breathing-room: 1rem;
            --commuter-safe-top: env(safe-area-inset-top, 0px);
            --commuter-safe-bottom: env(safe-area-inset-bottom, 0px);
        }
        .commuter-shell {
            height: 100vh;
            min-height: 100vh;
        }
        @supports (height: 100dvh) {
            .commuter-shell {
                height: 100dvh;
                min-height: 100dvh;
            }
        }
        .commuter-header {
            height: calc(var(--commuter-header-height) + var(--commuter-safe-top));
            padding-top: var(--commuter-safe-top);
        }
        .commuter-main {
            padding-top: calc(var(--commuter-header-height) + var(--commuter-safe-top));
            padding-bottom: calc(var(--commuter-bottom-nav-height) + var(--commuter-bottom-nav-offset) + var(--commuter-safe-bottom) + var(--commuter-content-breathing-room));
        }
        .commuter-bottom-nav {
            bottom: calc(var(--commuter-bottom-nav-offset) + var(--commuter-safe-bottom));
            height: var(--commuter-bottom-nav-height);
        }
    </style>
</head>
<body class="h-full flex justify-center items-center overflow-hidden antialiased">
    
    <!-- Centered premium mobile container -->
    <div class="commuter-shell relative w-full max-w-[430px] bg-[#F8FAFC] flex flex-col shadow-[0_0_50px_rgba(0,0,0,0.3)] overflow-hidden">
        
        <!-- TOP NAVBAR (Clean Premium White with Highlight Shadow) -->
        <header class="commuter-header absolute top-0 left-0 right-0 bg-white border-b border-slate-100 px-4 flex justify-between items-center z-50 shadow-[0_4px_16px_rgba(15,23,42,0.06)]">
            <!-- Left: Pasig Seal Logo + Brand Title -->
            <a href="{{ route('commuter.dashboard') }}" class="flex items-center gap-2.5 active:opacity-80 transition-opacity select-none">
                <img src="{{ asset('images/pasig_logo.png') }}" alt="Pasig Seal" class="h-8.5 w-8.5 object-contain drop-shadow-[0_2px_4px_rgba(0,0,0,0.1)]">
                <div class="flex flex-col leading-none">
                    <span class="text-[#003F87] text-[19px] font-black tracking-tight">GoPasig</span>
                    <span class="text-slate-400 text-[9px] font-bold tracking-widest mt-[2px] uppercase">Libreng Sakay</span>
                </div>
            </a>

            <!-- Right: Notifications & Profile -->
            <div class="flex items-center gap-2">
                @php
                    $activeAlertsCount = \App\Models\ServiceAlert::activeAlerts()->publicCommuterVisible()->count();
                @endphp
                <!-- Alert Bell in sleek slate button -->
                <a href="{{ route('commuter.alerts') }}" class="relative w-10 h-10 flex items-center justify-center text-slate-600 hover:text-slate-800 rounded-full bg-slate-50 border border-slate-100 active:scale-95 transition-transform" aria-label="Service Alerts">
                    <i class="ti ti-bell text-[21px]"></i>
                    @if($activeAlertsCount > 0)
                        <span class="absolute top-[8px] right-[8px] flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-rose-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-rose-500 shadow-[0_0_8px_#F43F5E]"></span>
                        </span>
                    @endif
                </a>

                <!-- Secondary staff access -->
                <a href="{{ route('login') }}" class="h-10 px-3 flex items-center justify-center gap-1.5 text-[11px] font-bold text-slate-600 hover:text-[#003F87] rounded-full bg-slate-50 border border-slate-100 active:scale-95 transition-transform" aria-label="Staff / Driver Login">
                    <i class="ti ti-user-circle text-[18px]"></i>
                    <span>Staff Login</span>
                </a>
            </div>
        </header>

        <!-- MAIN SCROLLABLE VIEW -->
        <main class="commuter-main flex-grow overflow-y-auto no-scrollbar" style="-webkit-overflow-scrolling: touch;">
            @yield('content')
        </main>

        <!-- FLOATING BOTTOM TAB BAR (Refined and Sleek Capsule) -->
        <nav class="commuter-bottom-nav absolute left-4 right-4 bg-white/95 backdrop-blur-lg border border-[#E2E8F0] rounded-2xl shadow-[0_8px_30px_rgba(15,23,42,0.08)] z-50 flex items-center justify-around px-2">
            
            <!-- 1. Home -->
            @php $isHome = request()->routeIs('commuter.dashboard') || request()->routeIs('commuter.index'); @endphp
            <a href="{{ route('commuter.dashboard') }}" class="flex flex-col items-center justify-center w-[54px] h-[52px] premium-transition active:scale-90 {{ $isHome ? 'text-[#003F87]' : 'text-[#64748B] hover:text-[#003F87]' }}">
                <i class="ti ti-home-2 text-[22px]"></i>
                <span class="text-[9.5px] font-bold tracking-wide mt-0.5">Home</span>
                <!-- Active Indicator Dot -->
                <span class="h-1 w-1 rounded-full premium-transition mt-0.5 {{ $isHome ? 'bg-[#003F87] shadow-[0_0_4px_#003F87]' : 'bg-transparent' }}"></span>
            </a>

            <!-- 2. Tracker -->
            @php $isTracker = request()->routeIs('commuter.tracker'); @endphp
            <a href="{{ route('commuter.tracker') }}" class="flex flex-col items-center justify-center w-[54px] h-[52px] premium-transition active:scale-90 {{ $isTracker ? 'text-[#003F87]' : 'text-[#64748B] hover:text-[#003F87]' }}">
                <i class="ti ti-map-pin text-[22px]"></i>
                <span class="text-[9.5px] font-bold tracking-wide mt-0.5">Tracker</span>
                <span class="h-1 w-1 rounded-full premium-transition mt-0.5 {{ $isTracker ? 'bg-[#003F87] shadow-[0_0_4px_#003F87]' : 'bg-transparent' }}"></span>
            </a>

            <!-- 3. Stops -->
            @php $isStops = request()->routeIs('commuter.stops'); @endphp
            <a href="{{ route('commuter.stops') }}" class="flex flex-col items-center justify-center w-[54px] h-[52px] premium-transition active:scale-90 {{ $isStops ? 'text-[#003F87]' : 'text-[#64748B] hover:text-[#003F87]' }}">
                <i class="ti ti-map-pins text-[22px]"></i>
                <span class="text-[9.5px] font-bold tracking-wide mt-0.5">Stops</span>
                <span class="h-1 w-1 rounded-full premium-transition mt-0.5 {{ $isStops ? 'bg-[#003F87] shadow-[0_0_4px_#003F87]' : 'bg-transparent' }}"></span>
            </a>

            <!-- 4. Routes -->
            @php $isRoutes = request()->routeIs('commuter.routes'); @endphp
            <a href="{{ route('commuter.routes') }}" class="flex flex-col items-center justify-center w-[54px] h-[52px] premium-transition active:scale-90 {{ $isRoutes ? 'text-[#003F87]' : 'text-[#64748B] hover:text-[#003F87]' }}">
                <i class="ti ti-route text-[22px]"></i>
                <span class="text-[9.5px] font-bold tracking-wide mt-0.5">Routes</span>
                <span class="h-1 w-1 rounded-full premium-transition mt-0.5 {{ $isRoutes ? 'bg-[#003F87] shadow-[0_0_4px_#003F87]' : 'bg-transparent' }}"></span>
            </a>

            <!-- 5. Schedule -->
            @php $isSchedule = request()->routeIs('commuter.schedule'); @endphp
            <a href="{{ route('commuter.schedule') }}" class="flex flex-col items-center justify-center w-[54px] h-[52px] premium-transition active:scale-90 {{ $isSchedule ? 'text-[#003F87]' : 'text-[#64748B] hover:text-[#003F87]' }}">
                <i class="ti ti-calendar text-[22px]"></i>
                <span class="text-[9.5px] font-bold tracking-wide mt-0.5">Schedule</span>
                <span class="h-1 w-1 rounded-full premium-transition mt-0.5 {{ $isSchedule ? 'bg-[#003F87] shadow-[0_0_4px_#003F87]' : 'bg-transparent' }}"></span>
            </a>

            <!-- 6. Alerts -->
            @php $isAlerts = request()->routeIs('commuter.alerts'); @endphp
            <a href="{{ route('commuter.alerts') }}" class="relative flex flex-col items-center justify-center w-[54px] h-[52px] premium-transition active:scale-90 {{ $isAlerts ? 'text-[#003F87]' : 'text-[#64748B] hover:text-[#003F87]' }}">
                <i class="ti ti-speakerphone text-[22px]"></i>
                <span class="text-[9.5px] font-bold tracking-wide mt-0.5">Alerts</span>
                @if($activeAlertsCount > 0)
                    <span class="absolute top-[8px] right-[13px] flex h-1.5 w-1.5 rounded-full bg-rose-500 shadow-[0_0_4px_#F43F5E]"></span>
                @endif
                <span class="h-1 w-1 rounded-full premium-transition mt-0.5 {{ $isAlerts ? 'bg-[#003F87] shadow-[0_0_4px_#003F87]' : 'bg-transparent' }}"></span>
            </a>
            
        </nav>

    </div>

    @livewireScriptConfig
    <script src="{{ asset('js/shared/ui-feedback.js') }}?v={{ time() }}" defer></script>
    @yield('scripts')
</body>
</html>




