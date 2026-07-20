@extends('layouts.fleet')

@section('title', 'GoPasig Fleet Ops - Maintenance')

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
            <div class="mx-auto w-full max-w-[1366px] space-y-6">

                <!-- Page Header & Breadcrumbs -->
                <div class="flex flex-col gap-1 border-b border-slate-200 pb-3 shrink-0">
                    <h1 class="text-xl font-bold text-slate-900">Fleet Maintenance Logs</h1>
                    <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-1 select-none">
                        <span>Dashboard</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span>Operations</span>
                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                        <span class="text-slate-600 font-bold">Maintenance Logs</span>
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
                @if(session('error'))
                    <div class="p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-xs font-semibold flex items-center justify-between animate-fade-in-up">
                        <div class="flex items-center gap-1.5">
                            <i class="ti ti-circle-x text-[16px]"></i>
                            <span>{{ session('error') }}</span>
                        </div>
                        <button onclick="this.parentElement.remove()" class="text-red-750 hover:opacity-80"><i class="ti ti-x"></i></button>
                    </div>
                @endif

                <!-- Metrics Summary Cards -->
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-5">
                    <div class="rounded-xl bg-white p-4 border border-slate-200 shadow-3xs">
                        <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <span>Total Fleet</span>
                            <i class="ti ti-bus text-base text-[#185FA5]"></i>
                        </div>
                        <p class="mt-2 text-[24px] font-black text-slate-900 leading-none font-mono">{{ $maintenanceSummary->total_fleet }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 border border-slate-200 shadow-3xs">
                        <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <span>Active Units</span>
                            <i class="ti ti-circle-check text-base text-emerald-600"></i>
                        </div>
                        <p class="mt-2 text-[24px] font-black text-[#0F6E56] leading-none font-mono">{{ $maintenanceSummary->active_units }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 border border-slate-200 shadow-3xs">
                        <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <span>In Service</span>
                            <i class="ti ti-tool text-base text-amber-600"></i>
                        </div>
                        <p class="mt-2 text-[24px] font-black text-amber-700 leading-none font-mono">{{ $maintenanceSummary->under_maintenance }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 border border-slate-200 shadow-3xs">
                        <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <span>Offline / Breakdown</span>
                            <i class="ti ti-alert-triangle text-base text-red-500"></i>
                        </div>
                        <p class="mt-2 text-[24px] font-black text-red-700 leading-none font-mono">{{ $maintenanceSummary->breakdown_count }}</p>
                    </div>
                    <div class="rounded-xl bg-white p-4 border border-slate-200 shadow-3xs">
                        <div class="flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-slate-400">
                            <span>Due For Service</span>
                            <i class="ti ti-clock-exclamation text-base text-purple-600"></i>
                        </div>
                        <p class="mt-2 text-[24px] font-black text-slate-900 leading-none font-mono">{{ $maintenanceSummary->due_for_service }}</p>
                        <p class="mt-1 text-[9px] text-slate-400 font-extrabold uppercase tracking-wide">Within 7 days</p>
                    </div>
                </div>

                <!-- Matrix & Upcoming Schedule Grid -->
                <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <!-- Fleet Health Matrix -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-3xs flex flex-col">
                        <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-2">
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Fleet Health Matrix</h2>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-extrabold text-slate-600">{{ count($busHealth) }} Units</span>
                        </div>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 xl:grid-cols-4 max-h-[300px] overflow-y-auto pr-1">
                            @forelse($busHealth as $bus)
                                <div class="p-3 rounded-xl border flex flex-col justify-between
                                    {{ $bus->status === 'active' ? 'border-emerald-100 bg-emerald-50/20 text-[#0F6E56]' : '' }}
                                    {{ $bus->status === 'maintenance' ? 'border-amber-100 bg-amber-50/20 text-amber-800' : '' }}
                                    {{ $bus->status === 'inactive' ? 'border-slate-200 bg-slate-50 text-slate-600' : '' }}
                                    {{ $bus->status === 'breakdown' ? 'border-red-100 bg-red-50/20 text-red-800' : '' }}">
                                    <div class="flex items-center justify-between">
                                        <span class="font-mono text-xs font-black">{{ $bus->bus_id }}</span>
                                        <span class="h-2 w-2 rounded-full" style="background-color: {{ $bus->route_color }}"></span>
                                    </div>
                                    <p class="mt-2 text-[10px] opacity-80 font-bold truncate">{{ $bus->assigned_route ?: 'No Route Assigned' }}</p>
                                    <div class="mt-2 flex items-center gap-1.5 flex-wrap">
                                        <span class="text-[9px] font-black uppercase tracking-wider">
                                            @if($bus->status === 'inactive')
                                                Standby
                                            @else
                                                {{ ucfirst($bus->status) }}
                                            @endif
                                        </span>
                                        @if($bus->status === 'inactive' && !empty($bus->has_observation))
                                            <span class="bg-amber-100 text-amber-800 border border-amber-200 px-1 py-0.2 rounded text-[8px] font-black uppercase tracking-wider">Obs</span>
                                        @endif
                                    </div>
                                    @if($bus->status === 'maintenance' && !empty($bus->completion_time))
                                        <p class="mt-1.5 text-[9px] font-black text-amber-700 font-mono">Est. Done: {{ $bus->completion_time }}</p>
                                    @endif
                                </div>
                            @empty
                                <p class="col-span-full text-center text-slate-400 py-8 text-xs font-semibold">No bus health data.</p>
                            @endforelse
                        </div>
                    </div>

                    <!-- Upcoming Maintenance Schedule -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-3xs flex flex-col">
                        <div class="mb-4 flex items-center justify-between border-b border-slate-100 pb-2">
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Upcoming Schedule</h2>
                            <span class="rounded-full bg-blue-50 px-2.5 py-0.5 text-[11px] font-extrabold text-[#185FA5]">Next 30 Days</span>
                        </div>
                        <div class="max-h-[300px] overflow-y-auto pr-1 space-y-2">
                            @forelse($upcomingSchedule as $entry)
                                <div class="rounded-xl border border-slate-150 bg-white p-3 border-l-4 border-l-[#185FA5] hover:bg-slate-50 transition-colors">
                                    <p class="text-xs font-black text-slate-800 font-mono">{{ \Illuminate\Support\Carbon::parse($entry->scheduled_date)->timezone('Asia/Manila')->format('F d, Y h:i A') }}</p>
                                    <p class="text-[11px] text-slate-500 font-bold mt-1">Bus <strong class="text-slate-700 font-mono font-black">{{ $entry->bus_id }}</strong> — {{ $entry->description }}</p>
                                </div>
                            @empty
                                <div class="grid min-h-[220px] place-items-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 p-6 text-center">
                                    <div class="space-y-2">
                                        <i class="ti ti-calendar-off text-[40px] text-slate-300"></i>
                                        <p class="text-xs text-slate-500 font-bold">No scheduled maintenance in the next 30 days</p>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- Maintenance Logs Listing Table -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-3xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-800 uppercase tracking-wider">Maintenance Logs</h2>
                            <p class="text-xs text-slate-500">Record of repairs and inspections</p>
                        </div>
                    </div>

                    <!-- Search and Filtering Form -->
                    <form method="GET" action="{{ route('fleet.maintenance') }}" class="flex flex-wrap items-center gap-3 bg-slate-50 p-3 rounded-xl border border-slate-200">
                        <div class="relative shrink-0" style="width: 240px;">
                            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="text" name="search" value="{{ $search }}" class="w-full rounded-lg border border-slate-200 bg-white py-1.5 pl-9 pr-3 text-xs font-semibold text-slate-800 outline-none transition focus:border-[#003F87]" placeholder="Search plate, ticket, tech...">
                        </div>
                        <select name="type" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                            <option value="all" {{ $logTypeFilter === 'all' ? 'selected' : '' }}>All Types</option>
                            <option value="Preventive" {{ $logTypeFilter === 'Preventive' ? 'selected' : '' }}>Preventive</option>
                            <option value="Corrective" {{ $logTypeFilter === 'Corrective' ? 'selected' : '' }}>Corrective</option>
                            <option value="Inspection" {{ $logTypeFilter === 'Inspection' ? 'selected' : '' }}>Inspection</option>
                        </select>
                        <select name="status" onchange="this.form.submit()" class="rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 outline-none focus:border-[#003F87] cursor-pointer">
                            <option value="all" {{ $logStatusFilter === 'all' ? 'selected' : '' }}>All Statuses</option>
                            <option value="scheduled" {{ $logStatusFilter === 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                            <option value="in_progress" {{ $logStatusFilter === 'in_progress' ? 'selected' : '' }}>In progress</option>
                            <option value="completed" {{ $logStatusFilter === 'completed' ? 'selected' : '' }}>Done</option>
                            <option value="cancelled" {{ $logStatusFilter === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                        </select>
                        @if($search || $logTypeFilter !== 'all' || $logStatusFilter !== 'all')
                            <a href="{{ route('fleet.maintenance') }}" class="text-xs text-[#003F87] hover:underline font-bold">Clear Filters</a>
                        @endif
                    </form>

                    <!-- Table Card -->
                    <div class="border border-slate-200 rounded-xl overflow-hidden bg-white font-sans">
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-50 text-slate-405 font-extrabold border-b border-slate-200 uppercase tracking-wider text-[10px] select-none">
                                        <th class="py-3 px-4 w-[16%]">Ticket Number</th>
                                        <th class="py-3 px-4 w-[12%]">Bus Plate</th>
                                        <th class="py-3 px-4 w-[14%]">Type</th>
                                        <th class="py-3 px-4 w-[24%]">Description</th>
                                        <th class="py-3 px-4 w-[14%]">Technician</th>
                                        <th class="py-3 px-4 w-[10%]">Status</th>
                                        <th class="py-3 px-4 w-[10%] text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($maintenanceLogs as $row)
                                        <tr class="hover:bg-slate-50/70 transition-colors border-b border-slate-100 font-semibold text-slate-700 text-xs">
                                            <td class="py-3 px-4 font-mono font-black text-slate-900">{{ $row->ticket_number ?: ('MT-2026-' . str_pad($row->id, 6, '0', STR_PAD_LEFT)) }}</td>
                                            <td class="py-3 px-4 font-mono font-black text-[#003F87]">{{ $row->bus_id }}</td>
                                            <td class="py-3 px-4">{{ $row->type }}</td>
                                            <td class="py-3 px-4 text-slate-500 truncate max-w-[200px]" title="{{ $row->description }}">{{ $row->description }}</td>
                                            <td class="py-3 px-4 font-bold">{{ $row->technician_name ?: '—' }}</td>
                                            <td class="py-3 px-4">
                                                @if($row->status === 'scheduled')
                                                    <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-[10px] font-bold text-blue-700 border border-blue-100">Scheduled</span>
                                                @elseif($row->status === 'in_progress')
                                                    <span class="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 text-[10px] font-bold text-amber-700 border border-amber-100">In Progress</span>
                                                @elseif($row->status === 'completed')
                                                    <span class="inline-flex rounded-full bg-emerald-50 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700 border border-emerald-100">Done</span>
                                                @elseif($row->status === 'cancelled')
                                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-600 border border-slate-200">Cancelled</span>
                                                @else
                                                    <span class="inline-flex rounded-full bg-slate-100 px-2.5 py-0.5 text-[10px] font-bold text-slate-500 border border-slate-200">{{ ucfirst($row->status) }}</span>
                                                @endif
                                            </td>
                                            <td class="py-3 px-4 text-right flex items-center justify-end gap-1.5">
                                                <!-- View: Always Available -->
                                                <a href="{{ route('fleet.maintenance.show', $row->id) }}" class="p-1 rounded bg-slate-100 hover:bg-[#003F87]/10 text-slate-600 hover:text-[#003F87] transition cursor-pointer" title="View details">
                                                    <i class="ti ti-eye text-sm"></i>
                                                </a>

                                                <!-- Action: Start Service -->
                                                @if($row->status === 'scheduled')
                                                    <a href="{{ route('fleet.maintenance.start', $row->id) }}" class="p-1 rounded bg-[#003F87] hover:bg-[#002d62] text-white transition cursor-pointer" title="Start Service">
                                                        <i class="ti ti-player-play text-sm"></i>
                                                    </a>
                                                    <form method="POST" action="{{ route('fleet.maintenance.cancelService', $row->id) }}" onsubmit="return confirm('Are you sure you want to cancel this maintenance schedule?')" class="inline">
                                                        @csrf
                                                        <button type="submit" class="p-1 rounded bg-red-50 hover:bg-red-100 text-red-600 transition cursor-pointer" title="Cancel Service">
                                                            <i class="ti ti-ban text-sm"></i>
                                                        </button>
                                                    </form>
                                                @endif

                                                <!-- Action: Complete Service -->
                                                @if($row->status === 'in_progress')
                                                    <a href="{{ route('fleet.maintenance.complete', $row->id) }}" class="p-1 rounded bg-teal-600 hover:bg-teal-700 text-white transition cursor-pointer" title="Complete Service">
                                                        <i class="ti ti-check text-sm"></i>
                                                    </a>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="py-10 text-center text-slate-400 text-xs font-semibold bg-slate-50/50">
                                                <i class="ti ti-calendar-off text-[32px] text-slate-300 block mb-2"></i>
                                                No maintenance records found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pagination Links -->
                    <div class="pt-2">
                        {{ $maintenanceLogs->links() }}
                    </div>
                </div>

            </div>
        </main>
    </div>
</div>
@endsection
