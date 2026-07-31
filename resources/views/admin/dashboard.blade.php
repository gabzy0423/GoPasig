@extends('layouts.admin')

@section('title', 'GoPasig Admin Dashboard')

@section('content')
    <div class="flex h-screen overflow-hidden bg-slate-50">
        <!-- LEFT SIDEBAR -->
        <x-admin.sidebar active="none" />

        <!-- MAIN APP WRAPPER -->
        <div class="flex flex-1 flex-col overflow-hidden">
            <!-- TOP HEADER BAR (56px) -->
            <x-admin.header />

            <!-- MAIN SCROLLABLE CANVAS -->
            <main class="flex-1 overflow-y-auto bg-white p-6">
                <div class="mx-auto w-full max-w-[1366px]">

                    @include('admin.overview')

                    @include('admin.bus.index')

                    @include('admin.dispatch.index')

                    @include('admin.maintenance.index')

                    @include('admin.map.index')

                    @include('admin.reports.index')

                    @include('admin.drivers.index')

                    @include('admin.drivers.create')

                    @include('admin.drivers.edit')

                    @include('admin.drivers.show')

                    @include('admin.schedules.index')




                    @include('admin.alerts.index')

                    @include('admin.alerts.history')

                    @include('admin.settings.index')

                    @include('admin.profile.index')

                    <!-- ==================== GENERIC PLACEHOLDER SCREEN ==================== -->
                    <section id="screen-placeholder" class="hidden py-16 text-center space-y-4">
                        <div
                            class="flex h-16 w-16 mx-auto items-center justify-center rounded-2xl bg-[#E6F1FB] text-[#003F87]">
                            <i id="placeholder-icon" class="ti ti-settings text-3xl"></i>
                        </div>
                        <div class="space-y-1">
                            <h2 id="placeholder-title" class="text-lg font-black text-slate-900">Module Screen</h2>
                            <p class="text-slate-500 text-xs font-semibold">This dispatch operational database module is
                                fully wired to local mock records.</p>
                        </div>
                        <button onclick="switchScreen('overview')"
                            class="rounded-lg bg-[#003F87] px-4 py-2 text-xs font-extrabold text-white hover:bg-[#002D62] transition cursor-pointer">
                            Back to Overview
                        </button>
                    </section>

                </div>
            </main>
        </div>
    </div>


    <!-- ==================== FRONTEND CONTROLLER JAVASCRIPT ==================== -->
    <script>
        window.GoPasigConfig = {
            fleetDataUrl: "{{ route('admin.api.fleet-data') }}",
            viewBusRouteTemplate: "{{ route('admin.buses.show', ['bus' => ':id']) }}",
            analyticsUrl: "{{ route('admin.api.analytics') }}",
            profileUrl: "{{ route('admin.api.profile.show') }}",
            profileUpdateUrl: "{{ route('admin.api.profile.update') }}",
            busesBaseUrl: "{{ url('admin/api/buses') }}",
            driversBaseUrl: "{{ url('admin/api/drivers') }}",
            schedulesBaseUrl: "{{ url('admin/api/schedules') }}",
            routeServiceSchedulesUrl: "{{ route('admin.api.route-service-schedules.index') }}",
            dispatchQueueTodayUrl: "{{ route('admin.api.schedules.dispatch-queue.today') }}",
            scheduleDispatchUrlTemplate: "{{ route('admin.api.schedules.dispatch', ['schedule' => ':id']) }}",
            routesBaseUrl: "{{ url('admin/api/routes') }}",
            stopsBaseUrl: "{{ url('admin/api/stops') }}",
            maintenanceBaseUrl: "{{ url('admin/api/maintenance') }}",
            alertsBaseUrl: "{{ url('admin/api/alerts') }}",
            alertsHistoryUrl: "{{ route('admin.api.alerts.history') }}",
            alertTargetRoutesUrl: "{{ route('admin.api.alerts.target-routes') }}",
            defaultTravelTime: {{ $defaultTravelTime }},
            defaultDepartureTime: @json($defaultDepartureTime),
            defaultStopBoarding: {{ $defaultStopBoarding }},
            defaultStopAlighting: {{ $defaultStopAlighting }},
            defaultStopDwellSeconds: {{ $defaultStopDwellSeconds }},
            csrfToken: "{{ csrf_token() }}"
        };
    </script>
@endsection

