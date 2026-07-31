@props([
    'prefix' => 'admin',
    'title' => 'Recent Account Activity',
])

<div class="bg-white border border-slate-200 rounded-2xl shadow-sm overflow-hidden mt-6">
    <div class="border-b border-slate-100 bg-slate-50/50 px-6 py-4 flex items-center justify-between">
        <div class="flex items-center gap-2">
            <i class="ti ti-history text-lg text-[#003F87]"></i>
            <h2 class="text-sm font-extrabold uppercase tracking-wider text-slate-800">{{ $title }}</h2>
        </div>
        <span class="text-[11px] font-semibold text-slate-400">Latest 10 activities</span>
    </div>

    <div class="p-6">
        <div id="{{ $prefix }}-recent-activity-loading" class="text-xs font-semibold text-slate-400 flex items-center gap-2 py-4">
            <i class="ti ti-loader animate-spin text-sm text-[#003F87]"></i> Loading activity history...
        </div>
        <div id="{{ $prefix }}-recent-activity-empty" class="hidden text-xs font-semibold text-slate-400 text-center py-6">
            No recent activity records found.
        </div>
        <div id="{{ $prefix }}-recent-activity-list" class="space-y-3"></div>
    </div>
</div>
