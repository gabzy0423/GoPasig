@extends('layouts.fleet')

@section('title', 'GoPasig Fleet Ops - Start Service')

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
            <div class="mx-auto w-full max-w-[640px] space-y-6">

                <!-- BREADCRUMB & HEADER -->
                <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
                    <div class="flex items-center gap-4">
                        <a href="{{ route('fleet.maintenance.show', $record->id) }}" 
                           class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 no-underline" 
                           title="Back to Ticket">
                            <i class="ti ti-arrow-left text-lg"></i>
                        </a>
                        <div>
                            <h1 class="text-xl font-bold text-slate-900 tracking-tight">Start Maintenance Service</h1>
                            <div class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                                <span>Dashboard</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Operations</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span>Maintenance Logs</span>
                                <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                <span class="text-[#003F87] font-bold">Start ({{ $record->ticket_number }})</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirmation Card -->
                <div class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] space-y-6">
                    <div>
                        <h2 class="text-sm font-bold uppercase tracking-wider text-slate-400 mb-1">Confirm Service Initiation</h2>
                        <p class="text-xs text-slate-500">Initiating service transitions the ticket status to <strong>In Progress</strong> and sets the bus status to <strong>Maintenance</strong>.</p>
                    </div>

                    <!-- Record Summary Detail Block -->
                    <div class="rounded-xl bg-slate-50 p-4 border border-slate-100 space-y-4">
                        <p class="text-[10px] font-bold uppercase text-slate-400 mb-1">Maintenance Summary</p>
                        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-semibold">
                            <div>
                                <dt class="text-[10px] font-bold uppercase text-slate-400">Ticket Number</dt>
                                <dd class="text-slate-950 font-black font-mono mt-0.5">{{ $record->ticket_number }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold uppercase text-slate-400">Bus Plate</dt>
                                <dd class="text-slate-950 font-black font-mono mt-0.5">{{ $record->bus ? $record->bus->plate_number : '—' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold uppercase text-slate-400">Maintenance Type</dt>
                                <dd class="text-slate-800 mt-0.5">{{ $record->type }}</dd>
                            </div>
                            <div>
                                <dt class="text-[10px] font-bold uppercase text-slate-400">Assigned Technician</dt>
                                <dd class="text-slate-800 mt-0.5">{{ $record->technician_name ?: '—' }}</dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-[10px] font-bold uppercase text-slate-400">Scheduled Time</dt>
                                <dd class="text-slate-800 mt-0.5 font-mono">{{ $record->scheduled_at ? $record->scheduled_at->timezone('Asia/Manila')->format('F d, Y \a\t h:i A') : '—' }}</dd>
                            </div>
                            <div class="sm:col-span-2 border-t border-slate-200/60 pt-2.5">
                                <dt class="text-[10px] font-bold uppercase text-slate-400">Description</dt>
                                <dd class="text-slate-650 mt-1 block leading-relaxed">{{ $record->description }}</dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Form submission -->
                    <form method="POST" action="{{ route('fleet.maintenance.startService', $record->id) }}" class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100">
                        @csrf
                        <a href="{{ route('fleet.maintenance.show', $record->id) }}" 
                           class="rounded-lg border border-slate-250 bg-white hover:bg-slate-50 text-slate-700 px-5 py-2.5 text-xs font-bold transition select-none no-underline">
                            Back
                        </a>
                        <button type="submit" 
                                class="rounded-lg bg-[#003F87] hover:bg-[#002d62] text-white px-6 py-2.5 text-xs font-extrabold transition shadow-sm cursor-pointer hover:scale-[1.02] active:scale-[0.98] border-none">
                            <i class="ti ti-player-play"></i> Start Service
                        </button>
                    </form>
                </div>

            </div>
        </main>
    </div>
</div>
@endsection
