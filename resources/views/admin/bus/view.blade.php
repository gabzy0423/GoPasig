@extends('layouts.admin')

@section('title', 'View Electric Bus Asset - GoPasig')

@section('content')
    <div class="flex h-screen overflow-hidden bg-slate-50">
        <!-- LEFT SIDEBAR -->
        <x-admin.sidebar active="buses" />

        <!-- MAIN APP WRAPPER -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- TOP HEADER BAR (56px) -->
            <x-admin.header />

            <!-- MAIN SCROLLABLE CANVAS -->
            <main class="flex-1 overflow-y-auto bg-slate-50/50 p-6">
                <div class="mx-auto w-full max-w-[1366px]">

                    <!-- BREADCRUMB & HEADER -->
                    <div class="flex flex-col gap-1 border-b border-slate-200 pb-4 mb-6 shrink-0">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-4">
                                <a href="{{ route('admin.dashboard') }}#buses"
                                    class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 hover:text-slate-900 transition-all duration-200 shadow-sm cursor-pointer hover:scale-105 active:scale-95 no-underline"
                                    title="Back to Bus Management">
                                    <i class="ti ti-arrow-left text-lg"></i>
                                </a>
                                <div>
                                    <h1 class="text-xl font-bold text-slate-900 tracking-tight">View Electric Bus Asset</h1>
                                    <div
                                        class="flex items-center gap-1 text-[11px] text-slate-400 font-semibold mt-0.5 select-none">
                                        <span>Dashboard</span>
                                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                        <span>Fleet</span>
                                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                        <span>Bus Management</span>
                                        <i class="ti ti-chevron-right text-[9px] text-slate-300"></i>
                                        <span class="text-[#003F87] font-bold">View Bus Asset</span>
                                    </div>
                                </div>
                            </div>
                            <div class="text-right">
                                <span
                                    class="text-[10px] font-bold uppercase tracking-wider text-slate-400 block leading-tight">Record
                                    ID</span>
                                <span
                                    class="text-xs font-mono font-bold text-slate-700 bg-slate-150 px-2 py-0.5 rounded bg-slate-200">Bus
                                    #{{ $bus->id }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- VIEW CARD -->
                    <div
                        class="rounded-2xl border border-slate-200 bg-white p-6 md:p-8 shadow-[0_2px_8px_rgba(0,0,0,0.04)] transition-all duration-300 animate-fade-in max-w-4xl">

                        <!-- BUS SUMMARY SECTION -->
                        <div
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-100 pb-6 mb-6 bg-slate-50/50 rounded-xl p-5 border border-slate-100">
                            <div class="flex items-center gap-4">
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-full bg-[#003F87]/10 text-[#003F87]">
                                    <i class="ti ti-bus text-2xl"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-lg font-extrabold text-slate-900">{{ $bus->fleet_number ?: '—' }}</span>
                                        <span
                                            class="text-xs font-mono font-bold px-2 py-0.5 bg-slate-100 text-slate-600 rounded">{{ $bus->plate_number ?: '—' }}</span>
                                    </div>
                                    <p class="text-sm font-semibold text-slate-500 mt-0.5">{{ $bus->manufacturer ?: '—' }}
                                        {{ $bus->model ?: '—' }}</p>
                                </div>
                            </div>
                            <div>
                                @php
                                    $normalizedStatus = strtolower($bus->status ?? '');
                                    if ($normalizedStatus === 'active') {
                                        $statusText = 'Active';
                                        $badgeClass = 'bg-[#E8F4E0] text-[#639922] border border-[#d5ecbf]';
                                    } elseif ($normalizedStatus === 'inactive' || $normalizedStatus === 'standby') {
                                        $statusText = 'Standby';
                                        $badgeClass = 'bg-slate-50 border border-slate-200 text-slate-500';
                                    } elseif ($normalizedStatus === 'maintenance') {
                                        $statusText = 'Maintenance';
                                        $badgeClass = 'bg-[#FEF7ED] text-[#BA7517] border border-amber-200';
                                    } elseif ($normalizedStatus === 'breakdown') {
                                        $statusText = 'Breakdown';
                                        $badgeClass = 'bg-[#FDF2F2] text-[#E24B4A] border border-red-100';
                                    } else {
                                        $statusText = ucfirst($bus->status ?? 'Inactive');
                                        $badgeClass = 'bg-slate-50 border border-slate-200 text-slate-500';
                                    }
                                @endphp
                                <span
                                    class="inline-flex rounded-full px-3.5 py-1 text-xs font-bold uppercase tracking-wider {{ $badgeClass }}">
                                    {{ $statusText }}
                                </span>
                            </div>
                        </div>

                        <!-- DETAILED GRID (2 Columns on Desktop, collapses to 1 Column on Mobile) -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

                            <!-- Column 1: Bus Identification & Vehicle Information -->
                            <div class="space-y-8">
                                <!-- Section 1: Bus Identification -->
                                <div class="bg-slate-50/30 rounded-xl p-5 border border-slate-100/80">
                                    <h3
                                        class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4 pb-1 border-b border-slate-100">
                                        1. Bus Identification</h3>
                                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Fleet
                                                Number</dt>
                                            <dd class="text-xs font-bold text-slate-800 select-all cursor-pointer mt-0.5"
                                                title="Double click to copy">{{ $bus->fleet_number ?: '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Plate
                                                Number</dt>
                                            <dd class="text-xs font-bold text-slate-800 select-all cursor-pointer mt-0.5"
                                                title="Double click to copy">{{ $bus->plate_number ?: '—' }}</dd>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">VIN /
                                                Chassis Number</dt>
                                            <dd class="flex items-center gap-2 mt-0.5">
                                                <span id="view-info-vin"
                                                    class="text-xs font-mono font-bold text-slate-800 select-all cursor-pointer"
                                                    title="Double click to copy">{{ $bus->vin ?: '—' }}</span>
                                                <button type="button" onclick="copyTextToClipboard('view-info-vin')"
                                                    class="text-slate-400 hover:text-[#003F87] transition cursor-pointer border-none bg-transparent"
                                                    title="Copy VIN">
                                                    <i class="ti ti-copy text-sm"></i>
                                                </button>
                                            </dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- Section 2: Vehicle Information -->
                                <div class="bg-slate-50/30 rounded-xl p-5 border border-slate-100/80">
                                    <h3
                                        class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4 pb-1 border-b border-slate-100">
                                        2. Vehicle Information</h3>
                                    <dl class="grid grid-cols-2 gap-4">
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Manufacturer</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->manufacturer ?: '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Model
                                            </dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">{{ $bus->model ?: '—' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Year
                                                Model</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->year_model ?: '—' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Seating Capacity</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->capacity ? $bus->capacity . ' seats' : '—' }}</dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <!-- Column 2: Powertrain & Assignment -->
                            <div class="space-y-8">
                                <!-- Section 3: Electric Powertrain -->
                                <div class="bg-slate-50/30 rounded-xl p-5 border border-slate-100/80">
                                    <h3
                                        class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4 pb-1 border-b border-slate-100">
                                        3. Electric Powertrain</h3>
                                    <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Battery Capacity</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->battery_capacity_kwh ? number_format($bus->battery_capacity_kwh, 2) . ' kWh' : '—' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Max
                                                Charging Power</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->max_charging_power_kw ? number_format($bus->max_charging_power_kw, 2) . ' kW' : '—' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Charging Port Type</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->charging_port_type ?: '—' }}</dd>
                                        </div>
                                    </dl>
                                </div>

                                <!-- Section 4 & 5: Operational Status & Current Assignment -->
                                <div class="bg-slate-50/30 rounded-xl p-5 border border-slate-100/80">
                                    <h3
                                        class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4 pb-1 border-b border-slate-100">
                                        4 & 5. Status & Assignment</h3>
                                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Assigned Driver</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->driver_name === 'Unassigned' ? 'Unassigned' : ($bus->driver_name ?: 'Unassigned') }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Assigned Route</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->route ? 'Route ' . $bus->route->id : 'Not Assigned' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                            <!-- Full Width Sections below -->
                            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-8 pt-4">

                                <!-- Section 6: Registration Information -->
                                <div class="bg-slate-50/30 rounded-xl p-5 border border-slate-100/80">
                                    <h3
                                        class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4 pb-1 border-b border-slate-100">
                                        6. Registration Information</h3>
                                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                        <div class="flex items-start gap-3">
                                            <span
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                                <i class="ti ti-calendar-plus text-base"></i>
                                            </span>
                                            <div>
                                                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                    Registered On</dt>
                                                <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                    {{ $bus->created_at ? $bus->created_at->format('F d, Y') : '—' }}<br>
                                                    <span
                                                        class="text-[11px] text-slate-500 font-medium">{{ $bus->created_at ? $bus->created_at->format('g:i A') : '' }}</span>
                                                </dd>
                                            </div>
                                        </div>
                                        <div class="flex items-start gap-3">
                                            <span
                                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-500">
                                                <i class="ti ti-calendar-time text-base"></i>
                                            </span>
                                            <div>
                                                <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                    Last Updated</dt>
                                                <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                    {{ $bus->updated_at ? $bus->updated_at->format('F d, Y') : '—' }}<br>
                                                    <span
                                                        class="text-[11px] text-slate-500 font-medium">{{ $bus->updated_at ? $bus->updated_at->format('g:i A') : '' }}</span>
                                                </dd>
                                            </div>
                                        </div>
                                    </dl>
                                </div>

                                <!-- Future-proof Purchase Information -->
                                <div class="bg-slate-50/30 rounded-xl p-5 border border-slate-100/80">
                                    <h3
                                        class="text-[11px] font-extrabold uppercase tracking-widest text-[#003F87] mb-4 pb-1 border-b border-slate-100">
                                        Purchase Information</h3>
                                    <dl class="grid grid-cols-2 sm:grid-cols-3 gap-4">
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Purchase Date</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->purchase_date ? \Carbon\Carbon::parse($bus->purchase_date)->format('F d, Y') : 'Not Available' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Supplier</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->supplier ?: 'Not Available' }}</dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Warranty Expiry</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->warranty_expiry ? \Carbon\Carbon::parse($bus->warranty_expiry)->format('F d, Y') : 'Not Available' }}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Serial
                                                Number</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->serial_number ?: 'Not Available' }}</dd>
                                        </div>
                                        <div class="sm:col-span-2">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-slate-400">
                                                Acquisition Cost</dt>
                                            <dd class="text-xs font-bold text-slate-800 mt-0.5">
                                                {{ $bus->acquisition_cost ? 'PHP ' . number_format($bus->acquisition_cost, 2) : 'Not Available' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            </div>

                        @if($bus->has_observation)
                            @php
                                $latestObs = $bus->maintenanceRecords
                                    ->where('status', 'completed')
                                    ->where('maintenance_result', 'Passed with Observation')
                                    ->first();
                            @endphp
                            @if($latestObs)
                                <div class="bg-amber-50/70 border border-amber-200 rounded-2xl p-5 mt-6 md:col-span-2 shadow-xs">
                                    <h3 class="text-[11px] font-extrabold uppercase tracking-widest text-amber-800 mb-4 pb-1.5 border-b border-amber-200 flex items-center gap-1.5">
                                        <i class="ti ti-alert-triangle text-base text-amber-600"></i>
                                        Observation Status — Bus Under Observation
                                    </h3>
                                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs font-semibold text-slate-800">
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Latest Maintenance Ticket</dt>
                                            <dd class="text-sm font-extrabold text-slate-900 mt-1">
                                                <a href="{{ route('admin.maintenance.show', $latestObs->id) }}" class="text-[#003F87] font-extrabold hover:underline">
                                                    {{ $latestObs->ticket_number ?: ('MT-2026-' . str_pad($latestObs->id, 6, '0', STR_PAD_LEFT)) }}
                                                </a>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Inspection Date</dt>
                                            <dd class="text-xs font-bold text-slate-850 mt-1">
                                                {{ $latestObs->completed_at ? $latestObs->completed_at->format('F d, Y \a\t g:i A') : '—' }}
                                            </dd>
                                        </div>
                                        <div class="sm:col-span-2 border-t border-amber-100 pt-3">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-amber-600">Observation Findings (Technician Notes)</dt>
                                            <dd class="text-xs text-slate-700 bg-white border border-amber-200/50 rounded-lg p-3 mt-1.5 leading-relaxed font-bold whitespace-pre-line">
                                                {{ $latestObs->technician_notes ?: 'No details provided.' }}
                                            </dd>
                                        </div>
                                        <div class="sm:col-span-2 border-t border-amber-100 pt-3">
                                            <dt class="text-[10px] font-bold uppercase tracking-wider text-amber-600 font-extrabold">Recommended Action (Required)</dt>
                                            <dd class="text-xs text-amber-900 bg-amber-100/50 border border-amber-200 rounded-lg p-3 mt-1.5 leading-relaxed font-extrabold whitespace-pre-line">
                                                {{ $latestObs->recommendation ?: 'No recommendation specified.' }}
                                            </dd>
                                        </div>
                                    </dl>
                                </div>
                            @endif
                        @endif

                        </div>

                        <!-- FOOTER ACTION ROW -->
                        <div class="pt-6 flex items-center justify-end gap-3 border-t border-slate-100 mt-8">
                            <a href="{{ route('admin.dashboard') }}#buses"
                                class="rounded-lg bg-slate-100 px-6 py-2.5 text-xs font-bold text-slate-600 hover:bg-slate-200 transition duration-200 cursor-pointer border-none no-underline flex items-center justify-center">
                                Close
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- ==================== CLIPBOARD UTILITIES ==================== -->
    <script>
        function copyTextToClipboard(elementId) {
            const el = document.getElementById(elementId);
            if (!el) return;

            const text = el.textContent || el.innerText;
            navigator.clipboard.writeText(text).then(() => {
                GoPasigUI.alert(`Copied VIN: ${text}`);
            }).catch(err => {
                console.error('Failed to copy text: ', err);
            });
        }
    </script>
@endsection
