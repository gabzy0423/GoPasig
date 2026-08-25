@php
    $analyticsPeriodPresets = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'last_7_days' => 'Last 7 Days',
        'last_30_days' => 'Last 30 Days',
        'this_month' => 'This Month',
        'last_month' => 'Last Month',
        'this_year' => 'This Year',
        'custom' => 'Custom Range',
    ];
@endphp

<div class="analytics-reporting-period-control mb-6 flex flex-col gap-2 rounded-xl border border-slate-200 bg-white p-3 shadow-sm">
    <div class="flex items-center justify-between gap-3">
        <span class="text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Reporting Period</span>
        <span class="analytics-period-summary text-[10px] font-bold text-[#003F87]">Today</span>
    </div>
    <div class="flex max-w-full flex-wrap gap-1.5">
        @foreach($analyticsPeriodPresets as $presetValue => $presetLabel)
            <button type="button" data-analytics-period-preset="{{ $presetValue }}" class="rounded-lg border border-slate-200 bg-white px-2.5 py-1 text-[10px] font-bold text-slate-500 transition hover:bg-slate-50">
                {{ $presetLabel }}
            </button>
        @endforeach
    </div>
    <div class="analytics-custom-range hidden flex-wrap items-center gap-2">
        <input type="date" data-analytics-start-date class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700 outline-none focus:border-[#003F87]">
        <span class="text-[10px] font-bold text-slate-400">to</span>
        <input type="date" data-analytics-end-date class="rounded-lg border border-slate-200 bg-slate-50 px-2 py-1 text-[11px] font-semibold text-slate-700 outline-none focus:border-[#003F87]">
        <button type="button" data-analytics-custom-apply class="rounded-lg bg-[#003F87] px-3 py-1 text-[10px] font-extrabold text-white">Apply</button>
    </div>
</div>
