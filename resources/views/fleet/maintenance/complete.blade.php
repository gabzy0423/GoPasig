@extends('layouts.fleet')

@section('title', 'GoPasig Fleet Ops - Complete Maintenance')

@section('content')
<div class="flex h-screen w-screen overflow-hidden bg-white">
    <!-- LEFT SIDEBAR -->
    <x-fleet.sidebar />

    <!-- MAIN AREA -->
    <div class="flex flex-1 flex-col min-w-0 bg-white">
        <!-- TOP HEADER BAR -->
        <x-fleet.topbar />

        <!-- MAIN SCROLLABLE CANVAS -->
        <main class="flex-grow overflow-y-auto bg-slate-50/50 p-6 relative">
            <div class="mx-auto w-full max-w-[768px] space-y-6">

                <!-- BREADCRUMB & HEADER -->
                <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('fleet.maintenance.show', $record->id) }}" 
                           class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 no-underline" 
                           title="Back to Ticket">
                            <i class="ti ti-arrow-left text-lg"></i>
                        </a>
                        <div>
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Complete Maintenance Checklist</h1>
                            <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                                <span>Dashboard</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Operations</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Maintenance Logs</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span class="text-[#003F87] font-bold">Complete ({{ $record->ticket_number }})</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Alert Box -->
                @if(session('error'))
                    <div class="p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-bold flex items-center justify-between animate-fade-in-up">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-circle-x text-base"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-red-750 hover:opacity-85"><i class="ti ti-x"></i></button>
                    </div>
                @endif
                @if ($errors->any())
                    <div class="p-3.5 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-bold space-y-1 animate-fade-in-up">
                        <p class="font-extrabold">Please correct the following validation errors:</p>
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- Form Card -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                    <form id="complete-service-form" method="POST" action="{{ route('fleet.maintenance.completeService', $record->id) }}" class="space-y-7" onsubmit="return handleFormSubmit(event)">
                        @csrf

                        <!-- SECTION 1: INSPECTION INFO -->
                        <div class="space-y-4">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#003F87] border-b border-slate-100 pb-2">Inspection Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-1">
                                    <label for="inspector_name" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Inspector Name</label>
                                    <input type="text" id="inspector_name" name="inspector_name" required value="{{ old('inspector_name') }}"
                                           class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-2 px-3 text-xs font-semibold text-slate-800 outline-none transition focus:border-[#003F87] focus:bg-white" placeholder="Lead Inspector Name">
                                </div>
                                <div class="space-y-1">
                                    <label for="bus_condition" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Bus Condition</label>
                                    <select id="bus_condition" name="bus_condition" required
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-2 px-3 text-xs font-semibold text-slate-800 outline-none transition focus:border-[#003F87] focus:bg-white cursor-pointer">
                                        <option value="" disabled selected>Select Bus Condition</option>
                                        <option value="Excellent" {{ old('bus_condition') === 'Excellent' ? 'selected' : '' }}>Excellent</option>
                                        <option value="Good" {{ old('bus_condition') === 'Good' ? 'selected' : '' }}>Good</option>
                                        <option value="Fair" {{ old('bus_condition') === 'Fair' ? 'selected' : '' }}>Fair</option>
                                        <option value="Needs Follow-up" {{ old('bus_condition') === 'Needs Follow-up' ? 'selected' : '' }}>Needs Follow-up</option>
                                    </select>
                                </div>

                                <!-- Maintenance Result — single source of truth -->
                                <div class="md:col-span-2 space-y-1">
                                    <label for="maintenance_result" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Maintenance Result</label>
                                    <select id="maintenance_result" name="maintenance_result" required
                                            onchange="onResultChange()"
                                            class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-2 px-3 text-xs font-semibold text-slate-800 outline-none transition focus:border-[#003F87] focus:bg-white cursor-pointer">
                                        <option value="Passed Inspection" {{ old('maintenance_result', 'Passed Inspection') === 'Passed Inspection' ? 'selected' : '' }}>
                                            Passed Inspection — Vehicle fully repaired and safe for operation.
                                        </option>
                                        <option value="Passed with Observation" {{ old('maintenance_result') === 'Passed with Observation' ? 'selected' : '' }}>
                                            Passed with Observation — Vehicle is operational but requires monitoring.
                                        </option>
                                        <option value="Failed Inspection" {{ old('maintenance_result') === 'Failed Inspection' ? 'selected' : '' }}>
                                            Failed Inspection — Vehicle remains unsafe for operation. New ticket required.
                                        </option>
                                    </select>
                                </div>

                                <!-- Roadworthy — read-only, derived from Maintenance Result -->
                                <div class="md:col-span-2">
                                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-500 mb-1.5">Roadworthy Status</p>
                                    <div id="roadworthy-display" class="flex items-center gap-3 rounded-lg border px-4 py-3 text-xs font-bold transition-all duration-200">
                                        <!-- Populated by JS -->
                                    </div>
                                    <!-- Hidden input — backend ignores this and re-derives from maintenance_result -->
                                    <input type="hidden" id="roadworthy" name="roadworthy" value="1">
                                    <p class="text-[10px] text-slate-400 font-semibold mt-1">
                                        <i class="ti ti-lock text-[9px]"></i> Automatically determined by Maintenance Result. Cannot be changed manually.
                                    </p>
                                </div>
                            </div>

                            <!-- Result Impact Banner -->
                            <div id="result-impact-banner" class="rounded-xl border p-4 text-xs font-semibold transition-all duration-200 space-y-1">
                                <!-- Populated by JS -->
                            </div>
                        </div>

                        <!-- SECTION 2: SAFETY CHECKLIST -->
                        <div class="space-y-3">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#003F87] border-b border-slate-100 pb-2">Safety Checklist</h3>
                            <p class="text-[10px] text-slate-400 font-extrabold uppercase tracking-wider mb-2">Check all items that successfully passed the safety checks (All are required)</p>
                            
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                                @php
                                    $checklistItems = [
                                        'brakes'     => 'Brakes Inspection',
                                        'battery'    => 'Battery Test',
                                        'tires'      => 'Tires & Alignment',
                                        'lights'     => 'Lights & Wiring',
                                        'test_drive' => 'Road Test Drive',
                                    ];
                                @endphp
                                @foreach($checklistItems as $key => $lbl)
                                    <div class="flex items-center gap-2.5 p-2.5 rounded-lg border border-slate-200 bg-slate-50/30 hover:bg-slate-50 hover:border-slate-300 transition">
                                        <input type="hidden" name="inspection_checklist[{{ $key }}]" value="0">
                                        <input type="checkbox" id="chk_{{ $key }}" name="inspection_checklist[{{ $key }}]" value="1"
                                               {{ old("inspection_checklist.$key") == '1' ? 'checked' : '' }}
                                               class="h-4 w-4 rounded border-slate-350 text-[#003F87] focus:ring-[#003F87] cursor-pointer">
                                        <label for="chk_{{ $key }}" class="text-xs font-semibold text-slate-700 cursor-pointer select-none">{{ $lbl }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <!-- SECTION 3: COST BREAKDOWN -->
                        <div class="space-y-4">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#003F87] border-b border-slate-100 pb-2">Cost Breakdown</h3>
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                <div class="space-y-1">
                                    <label for="labor_cost" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Labor Cost (PHP)</label>
                                    <input type="number" id="labor_cost" name="labor_cost" step="0.01" min="0" value="{{ old('labor_cost', '0.00') }}" oninput="calculateTotalCost()"
                                           class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-2 px-3 text-xs font-semibold text-slate-800 outline-none transition focus:border-[#003F87] focus:bg-white font-mono">
                                </div>
                                <div class="space-y-1">
                                    <label for="parts_cost" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Parts Cost (PHP)</label>
                                    <input type="number" id="parts_cost" name="parts_cost" step="0.01" min="0" value="{{ old('parts_cost', '0.00') }}" oninput="calculateTotalCost()"
                                           class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-2 px-3 text-xs font-semibold text-slate-800 outline-none transition focus:border-[#003F87] focus:bg-white font-mono">
                                </div>
                                <div class="space-y-1">
                                    <label for="other_cost" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Other Cost (PHP)</label>
                                    <input type="number" id="other_cost" name="other_cost" step="0.01" min="0" value="{{ old('other_cost', '0.00') }}" oninput="calculateTotalCost()"
                                           class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-2 px-3 text-xs font-semibold text-slate-800 outline-none transition focus:border-[#003F87] focus:bg-white font-mono">
                                </div>
                                <div class="space-y-1">
                                    <label for="total_cost_display" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Total Cost (PHP)</label>
                                    <input type="text" id="total_cost_display" readonly value="₱0.00"
                                           class="w-full rounded-lg border border-slate-200 bg-slate-100 py-2 px-3 text-xs font-black text-[#003F87] font-mono outline-none cursor-not-allowed">
                                </div>
                            </div>
                        </div>

                        <!-- SECTION 4: COMPLETION DETAILS -->
                        <div class="space-y-4">
                            <h3 class="text-xs font-extrabold uppercase tracking-wider text-[#003F87] border-b border-slate-100 pb-2">Completion Details</h3>
                            <div class="space-y-4">
                                <div class="space-y-1">
                                    <label for="parts_replaced" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Parts Replaced</label>
                                    <textarea id="parts_replaced" name="parts_replaced" rows="2"
                                              class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-2 px-3 text-xs font-semibold text-slate-850 outline-none transition focus:border-[#003F87] focus:bg-white resize-y" placeholder="List of new parts, serial numbers, tires replaced...">{{ old('parts_replaced') }}</textarea>
                                </div>
                                <div class="space-y-1">
                                    <label for="technician_notes" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">Technician / Completion Notes</label>
                                    <textarea id="technician_notes" name="technician_notes" rows="3" required
                                              class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-2 px-3 text-xs font-semibold text-slate-850 outline-none transition focus:border-[#003F87] focus:bg-white resize-y" placeholder="Enter service description summary, work details, final checks...">{{ old('technician_notes') }}</textarea>
                                </div>
                                <div class="space-y-1" id="recommendation_container">
                                    <label for="recommendation" class="text-[11px] font-bold uppercase tracking-wider text-slate-500">
                                        Inspector Recommendation
                                        <span id="recommendation_req_badge" class="hidden text-[9.5px] text-red-600 font-extrabold uppercase tracking-wide ml-1">(Required)</span>
                                    </label>
                                    <textarea id="recommendation" name="recommendation" rows="3"
                                              class="w-full rounded-lg border border-slate-200 bg-slate-50/50 py-2 px-3 text-xs font-semibold text-slate-850 outline-none transition focus:border-[#003F87] focus:bg-white resize-y"
                                              placeholder="Describe next steps, observations, or required follow-up dates...">{{ old('recommendation') }}</textarea>
                                </div>
                            </div>
                        </div>

                        <!-- ACTIONS -->
                        <div class="flex items-center justify-end gap-3 pt-5 border-t border-slate-100">
                            <a href="{{ route('fleet.maintenance.show', $record->id) }}" 
                               class="rounded-lg border border-slate-250 bg-white hover:bg-slate-50 text-slate-700 px-5 py-2.5 text-xs font-bold transition select-none no-underline">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="rounded-lg bg-teal-600 hover:bg-teal-700 text-white px-6 py-2.5 text-xs font-extrabold transition shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none select-none">
                                <i class="ti ti-check"></i> Complete Service
                            </button>
                        </div>
                    </form>
                </div>

            </div>
        </main>
    </div>
</div>

<script>
    // ── Result → derived fields map ───────────────────────────────────────────
    const RESULT_CONFIG = {
        'Passed Inspection': {
            roadworthy: true,
            roadworthyLabel: 'YES — Ready for Dispatch',
            roadworthyClass: 'border-emerald-200 bg-emerald-50 text-emerald-800',
            roadworthyIcon: 'ti-circle-check text-emerald-600',
            bannerClass: 'border-emerald-200 bg-emerald-50 text-emerald-800',
            bannerIcon: 'ti-circle-check text-emerald-600',
            bannerTitle: '✓ Safe for Operation',
            bannerLines: [
                'Bus will return to <strong>Standby (Inactive)</strong> after completion.',
                'Bus will be available for dispatch.'
            ],
            recommendationRequired: false,
            recommendationPlaceholder: 'Optional. Note any minor observations or follow-up schedule if needed.',
        },
        'Passed with Observation': {
            roadworthy: true,
            roadworthyLabel: 'YES — Safe with Observations',
            roadworthyClass: 'border-amber-200 bg-amber-50 text-amber-800',
            roadworthyIcon: 'ti-alert-triangle text-amber-600',
            bannerClass: 'border-amber-200 bg-amber-50 text-amber-800',
            bannerIcon: 'ti-alert-triangle text-amber-600',
            bannerTitle: '⚠ Safe with Observations',
            bannerLines: [
                'Bus will return to <strong>Standby (Inactive)</strong> after completion.',
                'A <strong>dispatch observation warning</strong> will be displayed.',
                'Recommendation is <strong>required</strong> — describe what needs monitoring.'
            ],
            recommendationRequired: true,
            recommendationPlaceholder: 'Example: Monitor front suspension. Replace left tire within 2 weeks. Schedule follow-up inspection on [date].',
        },
        'Failed Inspection': {
            roadworthy: false,
            roadworthyLabel: 'NO — Unfit for Service',
            roadworthyClass: 'border-red-200 bg-red-50 text-red-800',
            roadworthyIcon: 'ti-circle-x text-red-600',
            bannerClass: 'border-red-200 bg-red-50 text-red-800',
            bannerIcon: 'ti-circle-x text-red-600',
            bannerTitle: '✕ Unsafe for Operation',
            bannerLines: [
                'Bus will <strong>remain in Maintenance</strong> and cannot be dispatched.',
                'A new maintenance ticket must be created to continue repairs.',
                'Recommendation is <strong>required</strong> — describe why and what failed.'
            ],
            recommendationRequired: true,
            recommendationPlaceholder: 'Example: Brake master cylinder failed pressure test. Replace immediately before returning to service. Do not dispatch until resolved.',
        },
    };

    function onResultChange() {
        const result = document.getElementById('maintenance_result').value;
        const config = RESULT_CONFIG[result];
        if (!config) return;

        // Update hidden roadworthy input (backend will re-derive anyway)
        document.getElementById('roadworthy').value = config.roadworthy ? '1' : '0';

        // Update roadworthy display badge
        const rwDisplay = document.getElementById('roadworthy-display');
        rwDisplay.className = `flex items-center gap-3 rounded-lg border px-4 py-3 text-xs font-bold transition-all duration-200 ${config.roadworthyClass}`;
        rwDisplay.innerHTML = `<i class="ti ${config.roadworthyIcon} text-base shrink-0"></i><span>${config.roadworthyLabel}</span>`;

        // Update impact banner
        const banner = document.getElementById('result-impact-banner');
        banner.className = `rounded-xl border p-4 text-xs font-semibold transition-all duration-200 space-y-1 ${config.bannerClass}`;
        const linesHtml = config.bannerLines.map(l => `<li class="ml-2">${l}</li>`).join('');
        banner.innerHTML = `
            <div class="flex items-center gap-2 mb-2">
                <i class="ti ${config.bannerIcon} text-base shrink-0"></i>
                <span class="font-extrabold text-[13px]">${config.bannerTitle}</span>
            </div>
            <ul class="space-y-0.5 list-disc list-outside ml-3">${linesHtml}</ul>
        `;

        // Update recommendation required state
        const badge  = document.getElementById('recommendation_req_badge');
        const recTa  = document.getElementById('recommendation');
        if (config.recommendationRequired) {
            badge.classList.remove('hidden');
            recTa.required = true;
        } else {
            badge.classList.add('hidden');
            recTa.required = false;
        }
        recTa.placeholder = config.recommendationPlaceholder;
    }

    function calculateTotalCost() {
        const labor = parseFloat(document.getElementById('labor_cost').value) || 0;
        const parts = parseFloat(document.getElementById('parts_cost').value) || 0;
        const other = parseFloat(document.getElementById('other_cost').value) || 0;
        const total = labor + parts + other;
        document.getElementById('total_cost_display').value = '₱' + total.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function handleFormSubmit(event) {
        const result = document.getElementById('maintenance_result').value;
        if (result === 'Failed Inspection') {
            const confirmed = confirm(
                'This maintenance inspection has FAILED.\n\n' +
                'The bus will remain under Maintenance and cannot be dispatched until a new maintenance ticket is created and passed.\n\n' +
                'Continue?'
            );
            if (!confirmed) {
                event.preventDefault();
                return false;
            }
        }
        return true;
    }

    document.addEventListener('DOMContentLoaded', () => {
        calculateTotalCost();
        onResultChange(); // initialize derived fields based on current selection
    });
</script>
@endsection
