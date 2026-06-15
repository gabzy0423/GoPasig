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
            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-colors sm:px-3">
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

        <div class="flex items-center gap-2 border-l border-slate-200 pl-2 sm:gap-2.5 sm:pl-4">
            <div
                class="h-8 w-8 rounded-full bg-[#003F87]/10 flex items-center justify-center font-extrabold text-[#003F87] text-xs">
                AD
            </div>
            <div class="max-sm:hidden flex flex-col items-start leading-none">
                <span class="text-xs font-bold text-slate-900">Administrator</span>
                <span class="text-[9px] font-extrabold uppercase tracking-widest text-[#003F87] mt-0.5">Admin
                    Panel</span>
            </div>
        </div>
    </div>
</header>