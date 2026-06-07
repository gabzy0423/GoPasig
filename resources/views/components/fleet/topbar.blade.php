@props([
    'breadcrumb' => 'Overview',
])

<header class="flex h-14 items-center justify-between border-b border-slate-200 bg-white px-6 shrink-0 z-40">
    <div class="flex items-center gap-4">
        <button class="text-slate-500 hover:text-slate-800 md:hidden cursor-pointer" aria-label="Toggle navigation drawer" type="button">
            <i class="ti ti-menu-2 text-xl"></i>
        </button>
        <h1 id="page-title" class="text-base font-extrabold tracking-tight text-slate-900 uppercase">
            Fleet Ops / {{ $breadcrumb }}
        </h1>
    </div>

   

    <div class="flex items-center gap-4">
        <button id="layout-export-btn" class="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors">
            <i class="ti ti-download text-base text-slate-500"></i>
            <span>Export report</span>
        </button>

        <div class="relative">
            <button class="relative rounded-lg p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-800 cursor-pointer" aria-label="View notifications" type="button">
                <i class="ti ti-bell text-lg"></i>
                <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-[#E24B4A]"></span>
            </button>
        </div>

        <div class="flex items-center gap-2.5 border-l border-slate-200 pl-4">
            <div class="h-8 w-8 rounded-full bg-[#003F87]/10 flex items-center justify-center font-extrabold text-[#003F87] text-xs">
                FO
            </div>
            <div class="hidden flex-col items-start leading-none sm:flex">
                <span class="text-xs font-bold text-slate-900">Fleet Operator</span>
                <span class="text-[9px] font-extrabold uppercase tracking-widest text-[#003F87] mt-0.5">GoPasig</span>
            </div>
        </div>
    </div>
</header>
