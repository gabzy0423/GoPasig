<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
    <title>@yield('title', 'GoPasig Driver Portal')</title>
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
            background-color: #0F172A; /* Dark backdrop for desktop centering */
        }
        .font-mono {
            font-family: 'DM Mono', monospace;
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .premium-transition {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .driver-shell {
            --driver-header-height: 60px;
            --driver-nav-height: 68px;
            --driver-nav-offset: 1rem;
            --driver-content-gap: 1rem;
            --driver-safe-top: env(safe-area-inset-top, 0px);
            --driver-safe-bottom: env(safe-area-inset-bottom, 0px);
            height: 100vh;
            min-height: 100vh;
        }
        @supports (height: 100dvh) {
            .driver-shell {
                height: 100dvh;
                min-height: 100dvh;
            }
        }
        .driver-topbar {
            top: var(--driver-safe-top);
            height: var(--driver-header-height);
        }
        .driver-scroll {
            padding-top: calc(var(--driver-header-height) + var(--driver-safe-top));
            padding-bottom: calc(var(--driver-nav-height) + var(--driver-nav-offset) + var(--driver-content-gap) + var(--driver-safe-bottom));
        }
        .driver-bottom-nav {
            bottom: calc(var(--driver-nav-offset) + var(--driver-safe-bottom));
            height: var(--driver-nav-height);
        }
    </style>
</head>
<body class="h-full flex justify-center items-center overflow-hidden antialiased">
    
    <!-- Centered premium mobile container for Driver Panel -->
    <div class="driver-shell relative w-full max-w-[430px] bg-[#F8FAFC] flex flex-col shadow-[0_0_50px_rgba(0,0,0,0.3)] overflow-hidden text-slate-800">
        
        <!-- TOP NAVBAR (Clean Premium White with Highlight Shadow) -->
        <header class="driver-topbar absolute left-0 right-0 bg-white border-b border-slate-100 px-4 flex justify-between items-center z-50 shadow-[0_4px_16px_rgba(15,23,42,0.06)]">
            <!-- Left: Back Button or Pasig Logo -->
            @if(request()->routeIs('driver.dashboard'))
                <div class="flex items-center gap-2.5 select-none font-bold">
                    <img src="{{ asset('images/pasig_logo.png') }}" alt="Pasig Logo" class="h-8.5 w-8.5 object-contain">
                    <div class="flex flex-col leading-none">
                        <span class="text-[#003F87] text-[19px] font-black tracking-tight font-sans">{{ config('app.name', 'GoPasig') }} <span class="text-slate-400 font-bold text-xs uppercase tracking-wider">{{ ucfirst(Auth::user()->role ?? 'driver') }}</span></span>
                        <span class="text-slate-400 text-[8.5px] font-bold tracking-widest mt-[2px] uppercase">{{ config('app.service_type', 'Libreng Sakay') }}</span>
                    </div>
                </div>
            @else
                <a href="{{ route('driver.dashboard') }}" class="flex items-center gap-1.5 text-slate-600 hover:text-slate-800 active:scale-95 transition-transform">
                    <i class="ti ti-arrow-left text-[20px]"></i>
                    <span class="text-[13px] font-bold">Dashboard</span>
                </a>
            @endif

            <!-- Right: GPS Live Pulse & Quick Status -->
            <div class="flex items-center gap-2.5">
                <!-- Status Badge -->
                <div id="driver-status-badge" class="flex items-center gap-1.5 px-2.5 py-1 bg-slate-50 border border-slate-100 rounded-full text-[10px] font-extrabold tracking-wider uppercase shadow-inner">
                    @php
                        $user = Auth::user();
                        $drv = $user ? \App\Models\Driver::where('user_id', $user->id)->first() : null;
                        $statusText = $user && $drv ? 'ONLINE' : 'OFFLINE';
                        $pulseColor = $statusText === 'ONLINE' ? 'bg-emerald-500' : 'bg-slate-400';
                    @endphp
                    <span class="relative flex h-2 w-2">
                        @if($statusText === 'ONLINE')
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $pulseColor }} opacity-75"></span>
                        @endif
                        <span class="relative inline-flex rounded-full h-2 w-2 {{ $pulseColor }} status-indicator-dot"></span>
                    </span>
                    <span class="status-indicator-text text-slate-500">{{ $statusText }}</span>
                </div>

                <!-- Driver Initials Avatar -->
                <div class="w-9 h-9 rounded-full bg-[#003F87]/10 border border-[#003F87]/20 flex items-center justify-center text-[#003F87] font-extrabold text-xs shrink-0">
                    {{ $drv ? $drv->initials : (Auth::user() ? strtoupper(substr(Auth::user()->name, 0, 1)) : 'D') }}
                </div>

                <!-- Driver Logout Button -->
                <a href="/logout" onclick="event.preventDefault(); document.getElementById('driver-logout-form').submit();" class="w-9 h-9 rounded-full bg-slate-50 border border-slate-100 text-slate-500 hover:text-[#E24B4A] hover:bg-rose-50 hover:border-rose-100 active:scale-95 transition-all flex items-center justify-center shadow-inner shrink-0" title="Sign Out">
                    <i class="ti ti-logout text-[18px]"></i>
                </a>
                <form id="driver-logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
            </div>
        </header>

        <!-- MAIN SCROLLABLE VIEW -->
        <main class="driver-scroll flex-grow overflow-y-auto no-scrollbar" style="-webkit-overflow-scrolling: touch;">
            @yield('content')
        </main>

        <!-- FLOATING BOTTOM TAB BAR (Refined and Sleek Dynamic Capsule) -->
        @php
            $navTabs = [
                ['route' => 'driver.dashboard', 'icon' => 'ti-layout-dashboard', 'label' => 'Home', 'badge' => false],
                ['route' => 'driver.trip', 'icon' => 'ti-steering-wheel', 'label' => 'Drive', 'badge' => false],
                ['route' => 'driver.schedule', 'icon' => 'ti-calendar-event', 'label' => 'Shift', 'badge' => false],
                [
                    'route' => 'driver.announcements', 
                    'icon' => 'ti-bell', 
                    'label' => 'Alerts', 
                    'badge' => \App\Models\ServiceAlert::where('status', 'active')->count() > 0
                ],
            ];
        @endphp
        <nav class="driver-bottom-nav absolute left-4 right-4 bg-white/95 backdrop-blur-lg border border-[#E2E8F0] rounded-2xl shadow-[0_8px_30px_rgba(15,23,42,0.08)] z-50 flex items-center justify-around px-2">
            @foreach($navTabs as $tab)
                @php 
                    $isActive = request()->routeIs($tab['route']) || ($tab['route'] === 'driver.dashboard' && request()->routeIs('driver.index')); 
                @endphp
                <a href="{{ route($tab['route']) }}" class="relative flex flex-col items-center justify-center w-[60px] h-[52px] premium-transition active:scale-90 {{ $isActive ? 'text-[#003F87]' : 'text-[#64748B] hover:text-[#003F87]' }}">
                    <i class="ti {{ $tab['icon'] }} text-[22px]"></i>
                    <span class="text-[9.5px] font-bold tracking-wide mt-0.5 font-sans">{{ $tab['label'] }}</span>
                    @if($tab['badge'])
                        <span class="absolute top-[6px] right-[14px] flex h-1.5 w-1.5 rounded-full bg-rose-500 shadow-[0_0_4px_#F43F5E]"></span>
                    @endif
                    <span class="h-1 w-1 rounded-full premium-transition mt-0.5 {{ $isActive ? 'bg-[#003F87] shadow-[0_0_4px_#003F87]' : 'bg-transparent' }}"></span>
                </a>
            @endforeach
        </nav>

    </div>

    @livewireScriptConfig
    <script src="{{ asset('js/shared/ui-feedback.js') }}?v={{ time() }}" defer></script>
    @yield('scripts')
</body>
</html>


