@extends('layouts.admin')

@section('title', 'GoPasig Admin Dashboard')

@section('content')
<div class="flex h-screen overflow-hidden bg-slate-50">
    <!-- LEFT SIDEBAR -->
    <x-admin.sidebar active="maintenance" />

    <!-- MAIN APP WRAPPER -->
    <div class="flex flex-1 flex-col overflow-hidden">
        <!-- TOP HEADER BAR (56px) -->
        <x-admin.header />

        <!-- MAIN SCROLLABLE CANVAS -->
        <main class="flex-1 overflow-y-auto bg-slate-50/50 p-6">
            <div class="mx-auto w-full max-w-[1366px]">
                
                <!-- BREADCRUMB & HEADER -->
                <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('admin.dashboard') }}#maintenance" 
                           class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 no-underline" 
                           title="Back to Maintenance Logs">
                            <i class="ti ti-arrow-left text-lg"></i>
                        </a>
                        <div>
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">View Maintenance Ticket</h1>
                            <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                                <span>Dashboard</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Operations</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Maintenance Logs</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span class="text-[#003F87] font-bold">{{ $record->ticket_number }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step progress timeline -->
                <div class="mb-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_2px_8px_rgba(0,0,0,0.04)] max-w-5xl">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4">Maintenance Ticket Progress</h3>
                    
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4 md:gap-0 relative">
                        <!-- Connecting Line (hidden on mobile, absolute behind circles) -->
                        <div class="hidden md:block absolute top-4 left-[10%] right-[10%] h-0.5 bg-slate-200 z-0"></div>
                        <!-- Active progress filling -->
                        <div class="hidden md:block absolute top-4 left-[10%] h-0.5 bg-[#003F87] z-0 transition-all duration-300" 
                             style="width: {{ $record->status === 'completed' ? '80%' : ($record->status === 'in_progress' ? '40%' : '0%') }}"></div>

                        <!-- Step 1: Scheduled -->
                        <div class="flex flex-col items-center text-center z-10 w-full md:w-1/3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold transition-all duration-300
                                {{ in_array($record->status, ['scheduled', 'in_progress', 'completed']) ? 'bg-[#003F87] text-white ring-4 ring-blue-50' : 'bg-slate-100 text-slate-400' }}">
                                1
                            </div>
                            <span class="text-xs font-bold text-slate-800 mt-2">Scheduled</span>
                            <span class="text-[10px] text-slate-400 mt-0.5 font-medium">{{ $record->created_at ? $record->created_at->timezone('Asia/Manila')->format('M d, Y') : '' }}</span>
                        </div>

                        <!-- Step 2: In Progress -->
                        <div class="flex flex-col items-center text-center z-10 w-full md:w-1/3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold transition-all duration-300
                                {{ in_array($record->status, ['in_progress', 'completed']) ? 'bg-[#003F87] text-white ring-4 ring-blue-50' : 'bg-slate-100 text-slate-400' }}">
                                2
                            </div>
                            <span class="text-xs font-bold text-slate-800 mt-2">In Progress</span>
                            <span class="text-[10px] text-slate-400 mt-0.5 font-medium">Pending Execution</span>
                        </div>

                        <!-- Step 3: Completed -->
                        <div class="flex flex-col items-center text-center z-10 w-full md:w-1/3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold transition-all duration-300
                                {{ $record->status === 'completed' ? 'bg-[#639922] text-white ring-4 ring-emerald-50' : 'bg-slate-100 text-slate-400' }}">
                                3
                            </div>
                            <span class="text-xs font-bold text-slate-800 mt-2">Completed</span>
                            <span class="text-[10px] text-slate-400 mt-0.5 font-medium">
                                {{ $record->completed_at ? $record->completed_at->format('M d, Y') : 'Not Completed Yet' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Detail Grid Layout -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-5xl">
                    
                    <!-- Left Column: Primary Details -->
                    <div class="lg:col-span-2 space-y-6">
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <div class="mb-5 pb-3 border-b border-slate-100 flex items-center justify-between">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Maintenance Details</h2>
                                @if($record->status === 'scheduled')
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-100">Scheduled</span>
                                @elseif($record->status === 'in_progress')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-100">In Progress</span>
                                @elseif($record->status === 'completed')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-100">Completed</span>
                                @elseif($record->status === 'cancelled')
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-200">Cancelled</span>
                                @endif
                            </div>

                            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold text-slate-800">
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Maintenance Type</dt>
                                    <dd class="text-sm font-extrabold text-slate-900 mt-1">{{ $record->type }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Technician / Service Provider</dt>
                                    <dd class="text-sm font-bold text-slate-800 mt-1">{{ $record->technician_name ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Scheduled Date & Time</dt>
                                    <dd class="text-slate-800 mt-1">
                                        {{ $record->scheduled_at ? $record->scheduled_at->timezone('Asia/Manila')->format('F d, Y') : '—' }}<br>
                                        <span class="text-[10px] text-slate-400 font-medium">{{ $record->scheduled_at ? $record->scheduled_at->timezone('Asia/Manila')->format('g:i A') : '' }}</span>
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Estimated Duration</dt>
                                    <dd class="text-slate-800 mt-1">{{ $record->expected_duration_minutes ? $record->expected_duration_minutes . ' Minutes' : '120 Minutes (Default)' }}</dd>
                                </div>
                                <div class="md:col-span-2 pt-3 border-t border-slate-100">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Maintenance Work Order</dt>
                                    <dd class="text-slate-700 bg-slate-50 border border-slate-100 rounded-lg p-3 mt-1.5 leading-relaxed font-semibold whitespace-pre-line">{{ $record->description ?: 'No work description provided.' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Completion Details Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <div class="mb-4 pb-3 border-b border-slate-100">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Completion Information</h2>
                            </div>
                            @if($record->status === 'completed')
                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-semibold text-slate-800">
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Completed On</dt>
                                        <dd class="text-sm font-bold text-slate-900 mt-1">{{ $record->completed_at ? $record->completed_at->timezone('Asia/Manila')->format('F d, Y g:i A') : '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Actual Duration</dt>
                                        <dd class="text-sm font-bold text-slate-900 mt-1">{{ $record->actual_duration_minutes ? $record->actual_duration_minutes . ' Minutes' : 'N/A' }}</dd>
                                    </div>
                                    <div class="md:col-span-2">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Technician Remarks</dt>
                                        <dd class="text-slate-700 mt-1 bg-slate-50 border border-slate-100 rounded-lg p-3 whitespace-pre-line font-bold">{{ $record->technician_notes ?: 'No notes provided.' }}</dd>
                                    </div>
                                </dl>
                            @else
                                <div class="flex items-center gap-3 p-4 rounded-xl bg-slate-50 border border-slate-100 text-slate-500 font-semibold text-xs">
                                    <i class="ti ti-info-circle text-slate-400 text-lg"></i>
                                    <span>Not yet completed. This section will be populated later by the Fleet Operator execution workflow.</span>
                                </div>
                            @endif
                        </div>

                        <!-- Inspection Summary Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <div class="mb-4 pb-3 border-b border-slate-100 flex items-center justify-between">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Inspection & Safety Summary</h2>
                                @if($record->status === 'completed')
                                    @if($record->maintenance_result === 'Passed Inspection')
                                        <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-100">Passed Inspection</span>
                                    @elseif($record->maintenance_result === 'Passed with Observation')
                                        <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-100">Passed with Observation</span>
                                    @elseif($record->maintenance_result === 'Failed Inspection')
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2.5 py-0.5 text-[10px] font-bold text-rose-700 border border-rose-100">Failed Inspection</span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-slate-50 px-2.5 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-200">{{ $record->maintenance_result }}</span>
                                    @endif
                                @endif
                            </div>
                            @if($record->status === 'completed')
                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs font-semibold text-slate-800">
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Inspector</dt>
                                        <dd class="text-sm font-extrabold text-slate-900 mt-1">{{ $record->inspector_name ?: '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bus Condition</dt>
                                        <dd class="text-sm font-bold text-slate-800 mt-1">{{ $record->bus_condition ?: '—' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Roadworthy / Ready for Service</dt>
                                        <dd class="text-sm font-bold mt-1 {{ $record->roadworthy ? 'text-emerald-600' : 'text-rose-600' }}">
                                            {{ $record->roadworthy ? 'YES' : 'NO' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Safety Checklist Status</dt>
                                        <dd class="mt-1 flex flex-wrap gap-1.5">
                                            @php
                                                $checklist = $record->inspection_checklist ?? [];
                                                $checklistItems = [
                                                    'brakes' => 'Brakes',
                                                    'battery' => 'Battery',
                                                    'tires' => 'Tires',
                                                    'lights' => 'Lights',
                                                    'test_drive' => 'Test Drive',
                                                ];
                                            @endphp
                                            @foreach($checklistItems as $key => $lbl)
                                                @if(!empty($checklist[$key]))
                                                    <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold text-emerald-700 border border-emerald-100/50">{{ $lbl }}</span>
                                                @else
                                                    <span class="inline-flex items-center rounded bg-slate-50 px-1.5 py-0.5 text-[9px] font-bold text-slate-450 border border-slate-200">{{ $lbl }} (Failed)</span>
                                                @endif
                                            @endforeach
                                        </dd>
                                    </div>                 </div>
                                    @if($record->recommendation)
                                        <div class="md:col-span-2 pt-3 border-t border-slate-100">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Inspector Recommendations</dt>
                                            <dd class="text-slate-700 bg-slate-50 border border-slate-100 rounded-lg p-3 mt-1.5 leading-relaxed font-semibold whitespace-pre-line font-bold">{{ $record->recommendation }}</dd>
                                        </div>
                                    @endif
                                    @if($record->parts_replaced)
                                        <div class="md:col-span-2 pt-3 border-t border-slate-100">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Parts Replaced</dt>
                                            <dd class="text-slate-700 bg-slate-50 border border-slate-100 rounded-lg p-3 mt-1.5 leading-relaxed font-semibold font-bold">{{ $record->parts_replaced }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            @else
                                <div class="flex items-center gap-3 p-4 rounded-xl bg-slate-50 border border-slate-100 text-slate-500 font-semibold text-xs">
                                    <i class="ti ti-info-circle text-slate-400 text-lg"></i>
                                    <span>Inspection summary is not available yet. Complete the ticket details to view results.</span>
                                </div>
                            @endif
                        </div>

                        <!-- Cost & Release Audit Trail Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <div class="mb-4 pb-3 border-b border-slate-100">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Cost & Release Audit</h2>
                            </div>
                            @if($record->status === 'completed')
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs font-semibold">
                                    <!-- Cost Breakdown (Report Style) -->
                                    <div class="border border-slate-150 bg-slate-50/50 rounded-xl p-4 flex flex-col justify-between">
                                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#003F87] block mb-3 border-b border-slate-100 pb-1.5">Cost Breakdown</span>
                                        <dl class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <dt class="text-slate-500 font-bold">Labor Cost</dt>
                                                <dd class="font-mono text-slate-750 font-bold">₱{{ number_format($record->labor_cost ?? 0, 2) }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <dt class="text-slate-500 font-bold">Parts Cost</dt>
                                                <dd class="font-mono text-slate-750 font-bold">₱{{ number_format($record->parts_cost ?? 0, 2) }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between pb-2 border-b border-slate-200 border-dashed">
                                                <dt class="text-slate-500 font-bold">Other Cost</dt>
                                                <dd class="font-mono text-slate-750 font-bold">₱{{ number_format($record->other_cost ?? 0, 2) }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between pt-1">
                                                <dt class="text-slate-900 font-extrabold text-xs">Total Cost</dt>
                                                <dd class="font-mono text-slate-950 font-extrabold text-sm">₱{{ number_format($record->cost_php ?? 0, 2) }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                    
                                    <!-- Release Status -->
                                    <div class="border border-slate-150 bg-slate-50/50 rounded-xl p-4 flex flex-col justify-between">
                                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#003F87] block mb-3 border-b border-slate-100 pb-1.5">Bus Release Status</span>
                                        <dl class="space-y-2.5">
                                            <div class="flex items-center justify-between">
                                                <dt class="text-slate-500 font-bold">Bus Released</dt>
                                                <dd class="font-bold text-xs">
                                                    @if($record->maintenance_result !== 'Failed Inspection')
                                                        <span class="text-emerald-700 font-extrabold">YES</span>
                                                    @else
                                                        <span class="text-rose-700 font-extrabold">NO</span>
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <dt class="text-slate-500 font-bold">
                                                    @if($record->maintenance_result !== 'Failed Inspection')
                                                        Released At
                                                    @else
                                                        Reason
                                                    @endif
                                                </dt>
                                                <dd class="font-bold text-slate-750">
                                                    @if($record->maintenance_result !== 'Failed Inspection')
                                                        {{ $record->completed_at ? $record->completed_at->format('F d, Y h:i A') : '—' }}
                                                    @else
                                                        Failed Inspection
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <dt class="text-slate-500 font-bold">Released Status</dt>
                                                <dd class="font-mono font-bold text-slate-750">
                                                    @if($record->maintenance_result !== 'Failed Inspection')
                                                        Standby (Inactive)
                                                    @else
                                                        Maintenance (Locked)
                                                    @endif
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-3 p-4 rounded-xl bg-slate-50 border border-slate-100 text-slate-500 font-semibold text-xs">
                                    <i class="ti ti-info-circle text-slate-400 text-lg"></i>
                                    <span>Cost breakdown and release audit details will be generated upon session completion.</span>
                                </div>
                            @endif
                        </div>

                        <!-- Inspection History Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <div class="mb-4 pb-3 border-b border-slate-100">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Inspection History</h2>
                            </div>
                            
                            @if($record->inspectionAttempts->isEmpty())
                                <div class="flex items-center gap-3 p-4 rounded-xl bg-slate-50 border border-slate-100 text-slate-500 font-semibold text-xs">
                                    <i class="ti ti-info-circle text-slate-400 text-lg"></i>
                                    <span>No inspection attempts recorded yet.</span>
                                </div>
                            @else
                                <!-- Inspection Summary Block -->
                                <div class="mb-6 p-4 rounded-xl bg-slate-50 border border-slate-150">
                                    <h3 class="text-[10px] font-extrabold uppercase tracking-wider text-[#003F87] mb-3 border-b border-slate-200 pb-1.5">Inspection Summary</h3>
                                    @php
                                        $totalAttempts = $record->inspectionAttempts->count();
                                        $latestAttempt = $record->inspectionAttempts->last();
                                        $latestResult = $latestAttempt ? match($latestAttempt->maintenance_result) {
                                            'Passed Inspection'       => 'Passed',
                                            'Passed with Observation' => 'Passed with Observation',
                                            'Failed Inspection'       => 'Failed',
                                            default                   => $latestAttempt->maintenance_result,
                                        } : 'N/A';
                                        $latestInspector = $latestAttempt ? $latestAttempt->inspector_name : 'N/A';
                                        $latestDate = $latestAttempt && $latestAttempt->inspected_at 
                                            ? $latestAttempt->inspected_at->timezone('Asia/Manila')->format('M d, Y h:i A') 
                                            : 'N/A';
                                    @endphp
                                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-xs font-semibold text-slate-800">
                                        <div class="flex justify-between md:justify-start gap-4">
                                            <dt class="text-slate-500 w-44">Total Inspection Attempts</dt>
                                            <dd class="text-slate-900 font-extrabold">{{ $totalAttempts }}</dd>
                                        </div>
                                        <div class="flex justify-between md:justify-start gap-4">
                                            <dt class="text-slate-500 w-44">Latest Result</dt>
                                            <dd class="font-extrabold">
                                                @if($latestAttempt)
                                                    @if($latestAttempt->maintenance_result === 'Passed Inspection')
                                                        <span class="text-emerald-700">Passed</span>
                                                    @elseif($latestAttempt->maintenance_result === 'Passed with Observation')
                                                        <span class="text-amber-700">Passed with Observation</span>
                                                    @else
                                                        <span class="text-rose-700">Failed</span>
                                                    @endif
                                                @else
                                                    N/A
                                                @endif
                                            </dd>
                                        </div>
                                        <div class="flex justify-between md:justify-start gap-4">
                                            <dt class="text-slate-500 w-44">Latest Inspector</dt>
                                            <dd class="text-slate-900 font-bold">{{ $latestInspector }}</dd>
                                        </div>
                                        <div class="flex justify-between md:justify-start gap-4">
                                            <dt class="text-slate-500 w-44">Last Inspection Date</dt>
                                            <dd class="text-slate-800 font-mono font-bold">{{ $latestDate }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <div class="space-y-6">
                                    @foreach($record->inspectionAttempts as $attempt)
                                        <div class="border-b border-slate-100 pb-6 last:border-0 last:pb-0">
                                            <div class="flex items-center justify-between mb-3">
                                                <span class="text-sm font-extrabold text-slate-900">Attempt #{{ $attempt->attempt_no }}</span>
                                                @php
                                                    $badgeLabel = match($attempt->maintenance_result) {
                                                        'Passed Inspection'       => 'Passed',
                                                        'Passed with Observation' => 'Observation',
                                                        'Failed Inspection'       => 'Failed',
                                                        default                   => $attempt->maintenance_result,
                                                    };
                                                @endphp
                                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[10px] font-bold border {{ $attempt->getResultBadgeClass() }}">
                                                    {{ $badgeLabel }}
                                                </span>
                                            </div>
                                            
                                            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-3 text-xs font-semibold text-slate-800">
                                                <div>
                                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Inspection Date & Time</dt>
                                                    <dd class="text-slate-800 mt-1">
                                                        {{ $attempt->inspected_at ? $attempt->inspected_at->timezone('Asia/Manila')->format('F d, Y \a\t h:i A') : 'N/A' }}
                                                    </dd>
                                                </div>
                                                <div>
                                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Inspector</dt>
                                                    <dd class="text-slate-800 mt-1">{{ $attempt->inspector_name }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bus Condition</dt>
                                                    <dd class="text-slate-800 mt-1">{{ $attempt->bus_condition }}</dd>
                                                </div>
                                                <div>
                                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Roadworthy Status</dt>
                                                    <dd class="text-slate-800 mt-1 {{ $attempt->roadworthy ? 'text-emerald-600' : 'text-rose-600' }}">
                                                        {{ $attempt->roadworthy ? 'Roadworthy' : 'Not Roadworthy' }}
                                                    </dd>
                                                </div>
                                                
                                                @if($attempt->recommendation)
                                                    <div class="md:col-span-2 pt-2">
                                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Recommendation</dt>
                                                        <dd class="text-slate-750 bg-slate-50 border border-slate-100 rounded-lg p-3 mt-1 leading-relaxed font-semibold whitespace-pre-line font-bold">
                                                            {{ $attempt->recommendation }}
                                                        </dd>
                                                    </div>
                                                @endif

                                                @if($attempt->technician_notes)
                                                    <div class="md:col-span-2 pt-2">
                                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Technician Notes</dt>
                                                        <dd class="text-slate-750 bg-slate-50 border border-slate-100 rounded-lg p-3 mt-1 leading-relaxed font-semibold whitespace-pre-line font-bold">
                                                            {{ $attempt->technician_notes }}
                                                        </dd>
                                                    </div>
                                                @endif
                                            </dl>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Right Column: Bus Asset Card -->
                    <div class="space-y-6">
                        <!-- Bus Details Card -->
                        @php
                            $bus = \App\Models\Bus::find($record->getRawOriginal('bus_id'));
                        @endphp
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#003F87] mb-3 pb-1.5 border-b border-slate-100">Bus Information</h3>
                            <dl class="space-y-3.5 text-xs font-semibold text-slate-800">
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Fleet Number</dt>
                                    <dd class="text-sm font-extrabold text-slate-900 mt-0.5">{{ $bus ? $bus->fleet_number : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Plate Number</dt>
                                    <dd class="text-slate-800 mt-0.5">{{ $bus ? $bus->plate_number : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bus Status Badge</dt>
                                    <dd class="mt-1">
                                        @if($bus)
                                            @if($bus->status === 'active')
                                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-100">Active</span>
                                            @elseif($bus->status === 'inactive')
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-200">Standby (Inactive)</span>
                                            @elseif($bus->status === 'maintenance')
                                                <span class="inline-flex items-center rounded-full bg-orange-50 px-2 py-0.5 text-[10px] font-bold text-orange-700 border border-orange-100">Maintenance</span>
                                            @elseif($bus->status === 'breakdown')
                                                <span class="inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-bold text-red-700 border border-red-100">Breakdown</span>
                                            @else
                                                <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-200">{{ strtoupper($bus->status) }}</span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Manufacturer & Model</dt>
                                    <dd class="text-slate-800 mt-0.5">{{ $bus ? $bus->manufacturer . ' ' . $bus->model : '—' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Ticket Metadata Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#003F87] mb-3 pb-1.5 border-b border-slate-100">Ticket Information</h3>
                            <dl class="space-y-3 text-xs font-semibold text-slate-800">
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Ticket Number</dt>
                                    <dd class="text-slate-900 font-extrabold mt-0.5">{{ $record->ticket_number }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Created By</dt>
                                    <dd class="text-slate-800 mt-0.5">System</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Created On</dt>
                                    <dd class="text-slate-800 mt-0.5">{{ $record->created_at ? $record->created_at->timezone('Asia/Manila')->format('F d, Y g:i A') : '—' }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Maintenance History Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-[0_2px_8px_rgba(0,0,0,0.04)]">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#003F87] mb-3 pb-1.5 border-b border-slate-100">Maintenance History</h3>
                            @if($previousTickets->isEmpty())
                                <p class="text-xs text-slate-400 font-semibold py-2">No previous tickets for this bus.</p>
                            @else
                                <div class="space-y-3.5">
                                    @foreach($previousTickets as $prevTicket)
                                        <div class="text-xs font-semibold">
                                            <div class="flex items-center justify-between gap-2">
                                                <a href="{{ route('admin.maintenance.show', $prevTicket->id) }}" class="text-[#003F87] font-extrabold hover:underline truncate">
                                                    {{ $prevTicket->ticket_number ?: ('MT-2026-' . str_pad($prevTicket->id, 6, '0', STR_PAD_LEFT)) }}
                                                </a>
                                                @if($prevTicket->status === 'completed')
                                                    <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700 border border-emerald-100 shrink-0">Completed</span>
                                                @elseif($prevTicket->status === 'cancelled')
                                                    <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-600 border border-slate-200 shrink-0">Cancelled</span>
                                                @elseif($prevTicket->status === 'scheduled')
                                                    <span class="inline-flex rounded-full bg-blue-50 px-2 py-0.5 text-[9px] font-bold text-blue-700 border border-blue-100 shrink-0">Scheduled</span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-bold text-amber-700 border border-amber-100 shrink-0">{{ ucfirst($prevTicket->status) }}</span>
                                                @endif
                                            </div>
                                            <div class="flex items-center justify-between text-[10px] text-slate-400 font-medium mt-1">
                                                <span class="truncate pr-2">{{ $prevTicket->type }}</span>
                                                <span class="shrink-0">{{ $prevTicket->created_at ? $prevTicket->created_at->timezone('Asia/Manila')->format('M d, Y') : '' }}</span>
                                            </div>
                                        </div>
                                        @if(!$loop->last)
                                            <hr class="border-t border-slate-100 my-2">
                                        @endif
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                </div>

            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Override switchScreen function to redirect back to dashboard with hash
        window.switchScreen = function(screenName) {
            window.location.href = "{{ route('admin.dashboard') }}#" + screenName;
        };
    });
</script>
@endsection
