<header
    class="sticky top-0 flex h-14 items-center border-b border-slate-200/90 bg-white/95 px-4 backdrop-blur supports-[backdrop-filter]:bg-white/85 sm:px-6 shrink-0 z-40">
    <div class="flex min-w-0 items-center gap-3 sm:gap-4 shrink-0">
        <button onclick="toggleSidebar()"
            class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 hover:text-slate-800 md:hidden cursor-pointer transition-colors"
            aria-label="Toggle navigation drawer" type="button">
            <i class="ti ti-menu-2 text-xl"></i>
        </button>

        {{-- Branded static title --}}
        <div class="flex items-center gap-2 select-none">
            <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-[#003F87] text-white shadow-sm">
                <i class="ti ti-bus text-base"></i>
            </div>
            <span class="text-xs font-black uppercase tracking-wider text-slate-900">Fleet Management</span>
        </div>
    </div>



    <div class="ml-auto flex items-center gap-2 sm:gap-3 shrink-0">
        <button id="layout-export-btn"
            type="button"
            disabled
            aria-disabled="true"
            data-export-enabled="false"
            title="No exportable report data available yet."
            class="inline-flex cursor-not-allowed items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-2.5 py-1.5 text-xs font-semibold text-slate-400 shadow-sm transition-colors sm:px-3">
            <i class="ti ti-download text-base text-slate-500"></i>
            <span>Export report</span>
        </button>

        <div class="relative">
            <button
                class="relative rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 cursor-pointer"
                aria-label="View notifications" type="button">
                <i class="ti ti-bell text-lg"></i>
                <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-[#E24B4A]"></span>
            </button>
        </div>

        @php
            $adminUser = auth()->user();
            $adminName = $adminUser?->name ?? 'Administrator';
            $nameParts = array_values(array_filter(explode(' ', trim($adminName))));
            if (count($nameParts) === 1) {
                $adminInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 1));
            } elseif (count($nameParts) >= 2) {
                $adminInitials = mb_strtoupper(mb_substr($nameParts[0], 0, 1) . mb_substr(end($nameParts), 0, 1));
            } else {
                $adminInitials = 'A';
            }
            $staffProfile = $adminUser?->staffProfile;
            $rawPhotoUrl = ($staffProfile && $staffProfile->profile_photo_path) ? \Illuminate\Support\Facades\Storage::url($staffProfile->profile_photo_path) : null;
            if ($rawPhotoUrl && (str_starts_with($rawPhotoUrl, 'http://') || str_starts_with($rawPhotoUrl, 'https://'))) {
                $parsedPath = parse_url($rawPhotoUrl, PHP_URL_PATH);
                $profilePhotoUrl = $parsedPath ? '/' . ltrim($parsedPath, '/') : '/storage/' . ltrim($staffProfile->profile_photo_path, '/');
            } elseif ($rawPhotoUrl) {
                $profilePhotoUrl = '/' . ltrim($rawPhotoUrl, '/');
            } else {
                $profilePhotoUrl = null;
            }
        @endphp
        <button id="topbar-admin-profile-trigger"
            onclick="switchScreen('profile')"
            type="button"
            aria-label="View Account Profile"
            class="flex items-center gap-2 border-l border-slate-200 pl-2 sm:gap-2.5 sm:pl-4 hover:bg-slate-50 p-1.5 rounded-lg transition-colors cursor-pointer focus:outline-none focus-visible:ring-2 focus-visible:ring-[#003F87]">
            <div id="topbar-admin-avatar"
                class="h-8 w-8 rounded-full bg-[#003F87]/10 flex items-center justify-center font-extrabold text-[#003F87] text-xs shrink-0 overflow-hidden">
                @if($profilePhotoUrl)
                    <img src="{{ $profilePhotoUrl }}" alt="{{ $adminName }}" class="h-full w-full object-cover">
                @else
                    {{ $adminInitials }}
                @endif
            </div>
            <div class="max-sm:hidden flex flex-col items-start leading-none">
                <span id="topbar-admin-name" class="text-xs font-bold text-slate-900">{{ $adminName }}</span>
                <span class="text-[9px] font-extrabold uppercase tracking-widest text-[#003F87] mt-0.5">Admin
                    Panel</span>
            </div>
        </button>
    </div>
</header>
