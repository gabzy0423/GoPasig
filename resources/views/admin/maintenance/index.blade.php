{{-- ==================== FLEET MAINTENANCE SCREEN ==================== --}}
<section id="screen-maintenance" class="hidden space-y-6"
         style="--color-background-primary:#ffffff;--color-background-secondary:#F8F7F4;--color-text-primary:#1A1917;--color-text-secondary:#5F5E5A;--color-border-tertiary:#E8E6DF;--color-border-secondary:#D6D3C9;">

    {{-- PAGE HEADER ROW --}}
    <div class="bm-page-header border-b border-slate-100 pb-3 flex items-center justify-between shrink-0">
        <div class="bm-page-header-left">
            <h1 class="bm-h1 text-lg font-black text-slate-900">Fleet Maintenance Logs</h1>
            <p class="bm-subtitle text-xs text-slate-500 font-semibold mt-1">Track repairs, scheduled maintenance, and bus operational status</p>
        </div>
        <div class="bm-page-header-right">
            <button class="bm-btn-primary flex items-center gap-2 rounded-lg bg-[#003F87] px-4 py-2 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer" onclick="openScheduleMaintenanceModal()">
                <i class="ti ti-plus"></i> Schedule Maintenance
            </button>
        </div>
    </div>

    <!-- Timeline-style List container -->
    <div class="space-y-4 max-w-3xl" id="maintenance-logs-container">
        <!-- Populated dynamically via public/js/admin-dashboard/maintenance.js -->
        <div class="py-8 text-center text-slate-400 font-semibold text-xs">
            Loading maintenance logs...
        </div>
    </div>
</section>