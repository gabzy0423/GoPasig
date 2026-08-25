@props([
    'breadcrumb' => 'Overview',
    'icon'       => 'ti-bus',
])

@php
    $user = auth()->user();
    $photoUrl = null;
    $initials = 'FO';
    if ($user) {
        $photoPath = $user->staffProfile?->profile_photo_path;
        if ($photoPath) {
            $url = \Illuminate\Support\Facades\Storage::url($photoPath);
            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                $parsed = parse_url($url, PHP_URL_PATH);
                $photoUrl = $parsed ? '/' . ltrim($parsed, '/') : '/storage/' . ltrim($photoPath, '/');
            } else {
                $photoUrl = '/' . ltrim($url, '/');
            }
        }
        $nameParts = array_filter(explode(' ', trim($user->name ?? '')));
        if (count($nameParts) >= 2) {
            $initials = strtoupper(substr($nameParts[0], 0, 1) . substr(end($nameParts), 0, 1));
        } elseif (count($nameParts) === 1) {
            $initials = strtoupper(substr($nameParts[0], 0, 2));
        }
    }
@endphp

<header class="flex h-14 items-center justify-between border-b border-slate-200 bg-white px-6 shrink-0 z-40">
    <div class="flex items-center gap-4">
        <button class="text-slate-500 hover:text-slate-800 md:hidden cursor-pointer" aria-label="Toggle navigation drawer" type="button">
            <i class="ti ti-menu-2 text-xl"></i>
        </button>

        {{-- Branded static title --}}
        <div class="flex items-center gap-2 select-none">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#003F87] text-white shadow-sm">
                <i class="ti ti-truck text-base"></i>
            </div>
            <span class="text-xs font-black uppercase tracking-wider text-slate-900">Fleet Dashboard</span>
        </div>
    </div>

    <div class="flex items-center gap-4">
        <button id="layout-export-btn"
            type="button"
            disabled
            aria-disabled="true"
            data-export-enabled="false"
            title="Use the report section export controls when data is available."
            class="flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-400 transition-colors">
            <i class="ti ti-download text-base text-slate-500"></i>
            <span>Export report</span>
        </button>

        <div class="relative">
            <button class="relative rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 cursor-pointer" aria-label="View notifications" type="button">
                <i class="ti ti-bell text-lg"></i>
                <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-[#E24B4A]"></span>
            </button>
        </div>

        <!-- Dynamic User Identity Button Trigger for Account Profile -->
        <button id="topbar-identity-trigger" type="button" onclick="window.activateFleetModule ? window.activateFleetModule('profile') : window.location.href='{{ route('fleet.dashboard', ['tab' => 'profile']) }}'"
                class="flex items-center gap-2.5 border-l border-slate-200 pl-4 bg-transparent border-none cursor-pointer hover:opacity-80 transition text-left focus:outline-none"
                aria-label="Open Account Profile">
            <div id="topbar-avatar-container" class="h-8 w-8 rounded-full bg-[#003F87]/10 flex items-center justify-center font-extrabold text-[#003F87] text-xs shrink-0 border border-[#003F87]/20 overflow-hidden">
                @if($photoUrl)
                    <img id="topbar-avatar-img" src="{{ $photoUrl }}" alt="{{ $user->name ?? 'User' }}" class="h-full w-full object-cover">
                @else
                    <span id="topbar-avatar-initials">{{ $initials }}</span>
                @endif
            </div>
            <div class="hidden flex-col items-start leading-none sm:flex">
                <span id="topbar-user-name" class="text-xs font-bold text-slate-900">{{ $user->name ?? 'Staff User' }}</span>
                <span id="topbar-user-role" class="text-[9px] font-extrabold uppercase tracking-widest text-[#003F87] mt-0.5">{{ isset($user) && method_exists($user, 'displayRole') ? $user->displayRole() : 'Fleet Operations Manager' }}</span>
            </div>
        </button>
    </div>
</header>
