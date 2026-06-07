@extends('layouts.admin')

@section('title', 'GoPasig Admin Dashboard')

@section('content')
<div class="flex h-screen overflow-hidden bg-slate-50">
    <!-- LEFT SIDEBAR -->
    <x-admin.sidebar />

    <!-- MAIN APP WRAPPER -->
    <div class="flex flex-1 flex-col overflow-hidden">
        <!-- TOP HEADER BAR (56px) -->
        <x-admin.header />

        <!-- MAIN SCROLLABLE CANVAS -->
        <main class="flex-1 overflow-y-auto bg-white p-6">
            <div class="mx-auto w-full max-w-[1366px]">
                
                @include('admin.overview')

                @include('admin.buses.index')

                @include('admin.dispatch.index')

                @include('admin.maintenance.index')

                @include('admin.map.index')

                @include('admin.reports.index')

                @include('admin.drivers.index')

                @include('admin.schedules.index')

                @include('admin.alerts.index')

                <!-- ==================== GENERIC PLACEHOLDER SCREEN ==================== -->
                <section id="screen-placeholder" class="hidden py-16 text-center space-y-4">
                    <div class="flex h-16 w-16 mx-auto items-center justify-center rounded-2xl bg-[#E6F1FB] text-[#003F87]">
                        <i id="placeholder-icon" class="ti ti-settings text-3xl"></i>
                    </div>
                    <div class="space-y-1">
                        <h2 id="placeholder-title" class="text-lg font-black text-slate-900">Module Screen</h2>
                        <p class="text-slate-500 text-xs font-semibold">This dispatch operational database module is fully wired to local mock records.</p>
                    </div>
                    <button onclick="switchScreen('overview')" class="rounded-lg bg-[#003F87] px-4 py-2 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer">
                        Back to Overview
                    </button>
                </section>
                
            </div>
        </main>
    </div>
</div>

<!-- ==================== MODAL 1: ADD BUS MODAL ==================== -->
<div id="add-bus-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden p-4 animate-fade-in-up">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
            <span id="add-bus-modal-title" class="text-sm font-extrabold uppercase tracking-widest text-[#003F87]">Add Bus Registration</span>
            <button onclick="closeAddBusModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i class="ti ti-x text-lg"></i></button>
        </div>
        
        <form onsubmit="handleBusSubmit(event)" id="add-bus-form" class="mt-4 space-y-4">
            <input type="hidden" id="edit-bus-id" value="">
            <div class="space-y-1">
                <label for="new-bus-plate" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Bus Plate No</label>
                <input id="new-bus-plate" type="text" placeholder="e.g. PAS-439" required
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
            </div>
            
            <div class="space-y-1">
                <label for="new-bus-route" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Route Assignment</label>
                <select id="new-bus-route" required
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                    <option value="None">None - Unassigned</option>
                    <option value="1">Route 1 - SPED to Temporary Pasig City Hall</option>
                    <option value="2">Route 2 - SPED to Ligaya (Santolan) via PCGH</option>
                    <option value="3">Route 3 - SPED to One San Miguel Ave via Shaw</option>
                    <option value="4">Route 4 - SPED to Nagpayong via Urbano Velasco</option>
                </select>
            </div>

            <div class="space-y-1">
                <label for="new-bus-driver" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Driver Assignment</label>
                <input id="new-bus-driver" type="text" placeholder="e.g. Cardo Dalisay" required
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
            </div>

            <div class="space-y-1">
                <label for="new-bus-capacity" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Seating Capacity</label>
                <input id="new-bus-capacity" type="number" placeholder="e.g. 45" value="45" min="10" max="100" required
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
            </div>

            <div class="space-y-1">
                <label for="new-bus-status" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Status</label>
                <select id="new-bus-status" required
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                    <option value="Maintenance">Maintenance</option>
                </select>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 shrink-0">
                <button type="button" onclick="closeAddBusModal()" class="rounded-lg bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200 transition cursor-pointer">Cancel</button>
                <button type="submit" id="bus-submit-btn" class="rounded-lg bg-[#003F87] px-5 py-2 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer">Register Bus</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== MODAL 2: SCHEDULE MAINTENANCE MODAL ==================== -->
<div id="schedule-maintenance-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-sm hidden p-4 animate-fade-in-up">
    <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl border border-slate-100">
        <div class="flex items-center justify-between border-b border-slate-100 pb-3 shrink-0">
            <span class="text-sm font-extrabold uppercase tracking-widest text-[#003F87]">Schedule Maintenance</span>
            <button onclick="closeScheduleMaintenanceModal()" class="text-slate-400 hover:text-slate-600 cursor-pointer"><i class="ti ti-x text-lg"></i></button>
        </div>
        
        <form onsubmit="handleMaintenanceSubmit(event)" id="schedule-maintenance-form" class="mt-4 space-y-4">
            <div class="space-y-1">
                <label for="maintenance-bus-id" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Bus Unit</label>
                <select id="maintenance-bus-id" required
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                    <option value="">Select a Bus...</option>
                    <!-- Will be populated dynamically via JavaScript -->
                </select>
            </div>
            
            <div class="space-y-1">
                <label for="maintenance-type" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Maintenance Type</label>
                <select id="maintenance-type" required
                        class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
                    <option value="Preventive Maintenance">Preventive Maintenance</option>
                    <option value="Corrective Maintenance">Corrective Maintenance</option>
                </select>
            </div>

            <div class="space-y-1">
                <label for="maintenance-technician" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Technician / Lead</label>
                <input id="maintenance-technician" type="text" placeholder="e.g. Engr. Jose Rizal" required
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
            </div>

            <div class="space-y-1">
                <label for="maintenance-date" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Scheduled Date & Time</label>
                <input id="maintenance-date" type="datetime-local" required
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white">
            </div>

            <div class="space-y-1">
                <label for="maintenance-desc" class="text-[11px] font-extrabold uppercase tracking-wider text-slate-400">Tasks / Description</label>
                <textarea id="maintenance-desc" placeholder="Describe the maintenance tasks..." rows="3"
                       class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold text-slate-900 outline-none transition focus:border-[#003F87] focus:bg-white resize-none"></textarea>
            </div>

            <div class="pt-4 flex items-center justify-end gap-3 border-t border-slate-100 shrink-0">
                <button type="button" onclick="closeScheduleMaintenanceModal()" class="rounded-lg bg-slate-100 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-200 transition cursor-pointer">Cancel</button>
                <button type="submit" id="maintenance-submit-btn" class="rounded-lg bg-[#003F87] px-5 py-2 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer">Schedule</button>
            </div>
        </form>
    </div>
</div>

<!-- ==================== FRONTEND CONTROLLER JAVASCRIPT ==================== -->
<script>
    window.GoPasigConfig = {
        fleetDataUrl: "{{ route('admin.api.fleet-data') }}",
        analyticsUrl: "{{ route('admin.api.analytics') }}",
        busesBaseUrl: "{{ url('admin/api/buses') }}",
        driversBaseUrl: "{{ url('admin/api/drivers') }}",
        schedulesBaseUrl: "{{ url('admin/api/schedules') }}",
        routesBaseUrl: "{{ url('admin/api/routes') }}",
        stopsBaseUrl: "{{ url('admin/api/stops') }}",
        maintenanceBaseUrl: "{{ url('admin/api/maintenance') }}",
        csrfToken: "{{ csrf_token() }}"
    };
</script>
@endsection
