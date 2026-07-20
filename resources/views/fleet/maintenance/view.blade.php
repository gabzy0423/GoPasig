@extends('layouts.fleet')

@section('title', 'GoPasig Fleet Ops - View Ticket')

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
            <div class="mx-auto w-full max-w-[1024px] space-y-6">

                <!-- BREADCRUMB & HEADER -->
                <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('fleet.maintenance') }}" 
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
                                <span class="text-[#003F87] font-bold">{{ $record->ticket_number ?: ('MT-2026-' . str_pad($record->id, 6, '0', STR_PAD_LEFT)) }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Messages -->
                @if(session('success'))
                    <div class="p-3 bg-[#EAF3DE] border border-[#3B6D11] text-[#3B6D11] rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up">
                        <div class="flex items-center gap-1.5">
                            <i class="ti ti-circle-check text-[16px]"></i>
                            <span>{{ session('success') }}</span>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-[#3B6D11] hover:opacity-80"><i class="ti ti-x"></i></button>
                    </div>
                @endif

                <!-- Timeline Progress Tracker -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-3xs max-w-5xl">
                    <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-4">Maintenance Ticket Progress</h3>
                    <div class="flex flex-col md:flex-row items-center justify-between gap-4 md:gap-0 relative">
                        <div class="hidden md:block absolute top-4 left-[16%] right-[16%] h-0.5 bg-slate-200 z-0"></div>
                        <div class="hidden md:block absolute top-4 left-[16%] h-0.5 bg-[#003F87] z-0 transition-all duration-300" 
                             style="width: {{ $record->status === 'completed' ? '68%' : ($record->status === 'in_progress' ? '34%' : '0%') }}"></div>

                        <div class="flex flex-col items-center text-center z-10 w-full md:w-1/3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold transition-all duration-300
                                {{ in_array($record->status, ['scheduled', 'in_progress', 'completed']) ? 'bg-[#003F87] text-white ring-4 ring-blue-50' : 'bg-slate-100 text-slate-400' }}">1</div>
                            <span class="text-xs font-bold text-slate-800 mt-2">Scheduled</span>
                            <span class="text-[10px] text-slate-400 mt-0.5 font-mono font-medium">{{ $record->created_at ? $record->created_at->timezone('Asia/Manila')->format('M d, Y') : '' }}</span>
                        </div>

                        <div class="flex flex-col items-center text-center z-10 w-full md:w-1/3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold transition-all duration-300
                                {{ in_array($record->status, ['in_progress', 'completed']) ? 'bg-[#003F87] text-white ring-4 ring-blue-50' : 'bg-slate-100 text-slate-400' }}">2</div>
                            <span class="text-xs font-bold text-slate-800 mt-2">In Progress</span>
                            <span class="text-[10px] text-slate-400 mt-0.5 font-medium">Pending Execution</span>
                        </div>

                        <div class="flex flex-col items-center text-center z-10 w-full md:w-1/3">
                            <div class="flex h-9 w-9 items-center justify-center rounded-full text-xs font-bold transition-all duration-300
                                {{ $record->status === 'completed' ? 'bg-[#639922] text-white ring-4 ring-emerald-50' : 'bg-slate-100 text-slate-400' }}">3</div>
                            <span class="text-xs font-bold text-slate-800 mt-2">Completed</span>
                            <span class="text-[10px] text-slate-400 mt-0.5 font-mono font-medium">
                                {{ $record->completed_at ? $record->completed_at->timezone('Asia/Manila')->format('M d, Y') : 'Not Completed Yet' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Main Layout Columns -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-5xl">
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Maintenance Details Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-3xs">
                            <div class="mb-5 pb-3 border-b border-slate-100 flex items-center justify-between">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Maintenance Details</h2>
                                @if($record->status === 'scheduled')
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-100">Scheduled</span>
                                @elseif($record->status === 'in_progress')
                                    <span class="inline-flex items-center rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-100">In Progress</span>
                                @elseif($record->status === 'completed')
                                    <span class="inline-flex items-center rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-100">Done</span>
                                @elseif($record->status === 'cancelled')
                                    <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-200">Cancelled</span>
                                @endif
                            </div>

                            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs">
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Work Order Type</dt>
                                    <dd class="text-slate-800 font-bold mt-1.5">{{ $record->type }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Assigned Technician</dt>
                                    <dd class="text-slate-800 font-bold mt-1.5">{{ $record->technician_name ?: '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Scheduled Start</dt>
                                    <dd class="text-slate-800 mt-1.5 font-mono font-bold">{{ $record->scheduled_at ? $record->scheduled_at->timezone('Asia/Manila')->format('M d, Y \a\t h:i A') : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Expected Duration</dt>
                                    <dd class="text-slate-800 mt-1.5 font-mono font-bold">{{ $record->expected_duration_minutes ?: 120 }} Minutes</dd>
                                </div>
                                <div class="md:col-span-2 pt-3 border-t border-slate-100">
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Description of Work</dt>
                                    <dd class="text-slate-650 bg-slate-50 border border-slate-100 rounded-lg p-3 mt-1.5 leading-relaxed font-semibold">{{ $record->description }}</dd>
                                </div>
                            </dl>
                        </div>

                        <!-- Inspection & Safety Summary Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-3xs">
                            <div class="mb-4 pb-3 border-b border-slate-100">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Inspection & Safety Summary</h2>
                            </div>
                            @if($record->status === 'completed')
                                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 text-xs font-semibold">
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Inspector</dt>
                                        <dd class="text-slate-850 mt-1.5 font-bold">{{ $record->inspector_name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Bus Condition</dt>
                                        <dd class="text-slate-850 mt-1.5 font-bold">{{ $record->bus_condition }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Roadworthy (Ready for Service)</dt>
                                        <dd class="mt-1.5">
                                            @if($record->roadworthy)
                                                <span class="inline-flex items-center rounded bg-emerald-50 px-2 py-0.5 text-[10px] font-bold text-[#3B6D11] border border-emerald-250">Ready</span>
                                            @else
                                                <span class="inline-flex items-center rounded bg-red-50 px-2 py-0.5 text-[10px] font-bold text-[#A32D2D] border border-red-200">Unfit</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Maintenance Result</dt>
                                        <dd class="mt-1.5">
                                            @if($record->maintenance_result === 'Passed Inspection')
                                                <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-100">Passed Inspection</span>
                                            @elseif($record->maintenance_result === 'Passed with Observation')
                                                <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-100">Passed with Observation</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-red-50 px-2.5 py-0.5 text-[10px] font-bold text-red-700 border border-red-100">Failed Inspection</span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="md:col-span-2 pt-3 border-t border-slate-100">
                                        <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Safety Checklist Items</dt>
                                        <dd class="flex flex-wrap gap-2 mt-2">
                                            @php
                                                $checklist = $record->inspection_checklist ?: [];
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
                                                    <span class="inline-flex items-center rounded bg-emerald-50 px-1.5 py-0.5 text-[9px] font-bold text-emerald-700 border border-emerald-100">{{ $lbl }}</span>
                                                @else
                                                    <span class="inline-flex items-center rounded bg-slate-50 px-1.5 py-0.5 text-[9px] font-bold text-slate-450 border border-slate-200">{{ $lbl }} (Failed)</span>
                                                @endif
                                            @endforeach
                                        </dd>
                                    </div>
                                    @if($record->recommendation)
                                        <div class="md:col-span-2 pt-3 border-t border-slate-100">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Inspector Recommendations</dt>
                                            <dd class="text-slate-700 bg-slate-50 border border-slate-100 rounded-lg p-3 mt-1.5 leading-relaxed font-bold whitespace-pre-line">{{ $record->recommendation }}</dd>
                                        </div>
                                    @endif
                                    @if($record->parts_replaced)
                                        <div class="md:col-span-2 pt-3 border-t border-slate-100">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Parts Replaced</dt>
                                            <dd class="text-slate-700 bg-slate-50 border border-slate-100 rounded-lg p-3 mt-1.5 leading-relaxed font-bold">{{ $record->parts_replaced }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            @else
                                <div class="flex items-center gap-3 p-4 rounded-xl bg-slate-50 border border-slate-100 text-slate-500 font-semibold text-xs">
                                    <i class="ti ti-info-circle text-slate-400 text-lg"></i>
                                    <span>Inspection checklist and findings are not recorded yet. Start and complete the service to populate details.</span>
                                </div>
                            @endif
                        </div>

                        <!-- Cost & Release Audit Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-3xs">
                            <div class="mb-4 pb-3 border-b border-slate-100">
                                <h2 class="text-xs font-bold uppercase tracking-wider text-slate-400">Cost & Release Audit</h2>
                            </div>
                            @if($record->status === 'completed')
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs font-semibold">
                                    <div class="border border-slate-200 bg-slate-50/50 rounded-xl p-4 flex flex-col justify-between shadow-3xs">
                                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[#003F87] block mb-3 border-b border-slate-100 pb-1.5">Cost Summary</span>
                                        <dl class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <dt class="text-slate-500">Labor Cost</dt>
                                                <dd class="font-mono text-slate-800 font-bold">₱{{ number_format($record->labor_cost, 2) }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <dt class="text-slate-500">Parts Cost</dt>
                                                <dd class="font-mono text-slate-800 font-bold">₱{{ number_format($record->parts_cost, 2) }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between pb-2 border-b border-slate-200 border-dashed">
                                                <dt class="text-slate-500">Other Cost</dt>
                                                <dd class="font-mono text-slate-800 font-bold">₱{{ number_format($record->other_cost, 2) }}</dd>
                                            </div>
                                            <div class="flex items-center justify-between pt-1">
                                                <dt class="text-slate-900 font-extrabold text-xs">Total Cost</dt>
                                                <dd class="font-mono text-[#003F87] font-black text-sm">₱{{ number_format($record->cost_php, 2) }}</dd>
                                            </div>
                                        </dl>
                                    </div>
                                    <div class="border border-slate-200 bg-slate-50/50 rounded-xl p-4 flex flex-col justify-between shadow-3xs">
                                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-emerald-700 block mb-3 border-b border-slate-100 pb-1.5">Release Verification</span>
                                        <dl class="space-y-2">
                                            <div class="flex items-center justify-between">
                                                <dt class="text-slate-500">Bus Released</dt>
                                                <dd class="font-bold text-slate-800 uppercase tracking-wider">
                                                    @if($record->roadworthy)
                                                        YES
                                                    @else
                                                        NO (Needs Repair)
                                                    @endif
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <dt class="text-slate-500">Released At</dt>
                                                <dd class="font-mono text-slate-800 font-bold">
                                                    {{ $record->completed_at ? $record->completed_at->timezone('Asia/Manila')->format('Y-m-d H:i') : '—' }}
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between">
                                                <dt class="text-slate-500">Actual Duration</dt>
                                                <dd class="font-mono text-slate-800 font-bold">
                                                    {{ $record->actual_duration_minutes ?: '—' }} mins
                                                </dd>
                                            </div>
                                            <div class="flex items-center justify-between pt-1">
                                                <dt class="text-slate-500">Release Status</dt>
                                                <dd class="font-bold text-slate-800 truncate max-w-[120px]" title="{{ $record->recommendation }}">
                                                    {{ $record->maintenance_result ?: '—' }}
                                                </dd>
                                            </div>
                                        </dl>
                                    </div>
                                </div>
                            @else
                                <div class="flex items-center gap-3 p-4 rounded-xl bg-slate-50 border border-slate-100 text-slate-500 font-semibold text-xs">
                                    <i class="ti ti-info-circle text-slate-400 text-lg"></i>
                                    <span>Cost breakdown and release metrics will be recorded upon service completion.</span>
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- Right Column: Bus Details & Actions -->
                    <div class="space-y-6">
                        @if($record->status === 'completed')
                            <!-- Completed: Immutable — no actions available -->
                            <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5 shadow-3xs">
                                <div class="flex items-center gap-2 text-emerald-700 mb-1">
                                    <i class="ti ti-lock text-sm"></i>
                                    <h3 class="text-xs font-extrabold uppercase tracking-wider">Completed — Read Only</h3>
                                </div>
                                <p class="text-[11px] text-emerald-700 font-semibold leading-relaxed">
                                    This maintenance record is complete and immutable. If a follow-up is required, 
                                    <a href="{{ route('admin.dashboard') }}#maintenance" class="font-extrabold underline hover:text-emerald-900">create a new maintenance ticket</a>.
                                </p>
                            </div>
                        @elseif(in_array($record->status, ['scheduled', 'in_progress']))
                            <!-- Actions Card -->
                            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-3xs space-y-3">
                                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400 mb-2 border-b border-slate-100 pb-1.5">Operational Control</h3>
                                
                                @if($record->status === 'scheduled')
                                    <a href="{{ route('fleet.maintenance.start', $record->id) }}" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-[#003F87] hover:bg-[#002d62] text-white py-2 text-xs font-bold transition shadow-3xs cursor-pointer no-underline">
                                        <i class="ti ti-player-play text-sm"></i>
                                        <span>Start Service</span>
                                    </a>
                                    <form method="POST" action="{{ route('fleet.maintenance.cancelService', $record->id) }}" onsubmit="return confirm('Are you sure you want to cancel this maintenance schedule?')" class="block">
                                        @csrf
                                        <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg border border-slate-250 hover:bg-slate-50 text-slate-700 py-2 text-xs font-bold transition cursor-pointer select-none">
                                            <i class="ti ti-ban text-sm"></i>
                                            <span>Cancel Service</span>
                                        </button>
                                    </form>
                                @endif

                                @if($record->status === 'in_progress')
                                    <a href="{{ route('fleet.maintenance.complete', $record->id) }}" class="w-full inline-flex items-center justify-center gap-1.5 rounded-lg bg-teal-600 hover:bg-teal-700 text-white py-2 text-xs font-bold transition shadow-3xs cursor-pointer no-underline">
                                        <i class="ti ti-check text-sm"></i>
                                        <span>Complete Service</span>
                                    </a>
                                @endif
                            </div>
                        @endif

                        <!-- Bus Specs Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-3xs">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#003F87] mb-3 pb-1.5 border-b border-slate-100">Assigned Bus Unit</h3>
                            @php $bus = $record->bus; @endphp
                            <dl class="space-y-3 text-xs font-semibold text-slate-800">
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Plate Number</dt>
                                    <dd class="text-slate-900 font-extrabold font-mono text-sm mt-0.5">{{ $bus ? $bus->plate_number : '—' }}</dd>
                                </div>
                                <div>
                                    <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Current Status</dt>
                                    <dd class="mt-1">
                                        @if($bus)
                                            @if($bus->status === 'active')
                                                <span class="inline-flex rounded-full bg-emerald-50 px-2 py-0.5 text-[9px] font-bold text-emerald-700 border border-emerald-100">Active</span>
                                            @elseif($bus->status === 'maintenance')
                                                <span class="inline-flex rounded-full bg-amber-50 px-2 py-0.5 text-[9px] font-bold text-amber-700 border border-amber-100">Maintenance</span>
                                            @elseif($bus->status === 'inactive')
                                                <span class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-[9px] font-bold text-slate-600 border border-slate-200">Standby</span>
                                            @else
                                                <span class="inline-flex rounded-full bg-red-50 px-2 py-0.5 text-[9px] font-bold text-red-700 border border-red-100">Breakdown</span>
                                            @endif
                                            @if($bus->has_observation)
                                                <span class="inline-flex rounded bg-amber-50 px-1 py-0.2 text-[8px] font-bold text-amber-700 border border-amber-200 ml-1">Obs</span>
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

                        <!-- Maintenance History Card -->
                        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-3xs">
                            <h3 class="text-xs font-bold uppercase tracking-wider text-[#003F87] mb-3 pb-1.5 border-b border-slate-100">Recent Service History</h3>
                            @forelse($previousTickets as $prevTicket)
                                <div class="text-xs font-semibold">
                                    <div class="flex items-center justify-between gap-2">
                                        <a href="{{ route('fleet.maintenance.show', $prevTicket->id) }}" class="text-[#003F87] font-extrabold hover:underline truncate">
                                            {{ $prevTicket->ticket_number ?: ('MT-2026-' . str_pad($prevTicket->id, 6, '0', STR_PAD_LEFT)) }}
                                        </a>
                                        @if($prevTicket->status === 'completed')
                                            <span class="inline-flex rounded bg-emerald-50 px-1.5 py-0.2 text-[9px] font-bold text-emerald-700 border border-emerald-100 shrink-0">Done</span>
                                        @elseif($prevTicket->status === 'cancelled')
                                            <span class="inline-flex rounded bg-slate-100 px-1.5 py-0.2 text-[9px] font-bold text-slate-600 border border-slate-200 shrink-0">Cancelled</span>
                                        @elseif($prevTicket->status === 'scheduled')
                                            <span class="inline-flex rounded bg-blue-50 px-1.5 py-0.2 text-[9px] font-bold text-blue-700 border border-blue-100 shrink-0">Scheduled</span>
                                        @else
                                            <span class="inline-flex rounded bg-amber-50 px-1.5 py-0.2 text-[9px] font-bold text-amber-700 border border-amber-100 shrink-0">{{ ucfirst($prevTicket->status) }}</span>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-slate-400 font-medium mt-1 font-mono">
                                        <span class="truncate pr-2">{{ $prevTicket->type }}</span>
                                        <span class="shrink-0">{{ $prevTicket->created_at ? $prevTicket->created_at->timezone('Asia/Manila')->format('M d, Y') : '' }}</span>
                                    </div>
                                </div>
                                @if(!$loop->last)
                                    <hr class="border-t border-slate-100 my-2">
                                @endif
                            @empty
                                <p class="text-xs text-slate-400 font-semibold py-2">No previous tickets for this bus.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>
@endsection
