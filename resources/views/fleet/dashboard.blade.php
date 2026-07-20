@extends('layouts.fleet')


@section('title', 'GoPasig Fleet Ops - Dashboard')
@section('breadcrumb', 'Overview')

@section('content')
    <div class="flex h-screen w-screen overflow-hidden bg-white">
        <!-- LEFT SIDEBAR -->
        <x-fleet.sidebar />

        <!-- MAIN AREA -->
        <div class="flex flex-1 flex-col min-w-0 bg-white">
            <!-- TOP HEADER BAR -->
            <x-fleet.topbar :breadcrumb="trim($__env->yieldContent('breadcrumb', 'Overview'))" />

            <!-- MAIN SCROLLABLE CANVAS -->
            <main class="flex-grow overflow-y-auto bg-white p-6 relative">
                <div class="mx-auto w-full max-w-[1366px]">

                    @include('fleet.overview-content')

                    @include('fleet.monitor.index')

                    @include('fleet.utilization.index')

                    @include('fleet.performance.drivers.index')

                    @include('fleet.performance.routes.index')

                    @include('fleet.schedule.index')

                    @include('fleet.incidents.index')

                    @include('fleet.announcements.index')

                    @include('fleet.analytics.index')

                    @include('fleet.dispatch-intelligence.index')

                    @include('fleet.commuter-trips.index')

                    @include('fleet.commuter-sessions.index')


                    <!-- ==================== GENERIC PLACEHOLDER SCREEN ==================== -->
                    <section id="screen-placeholder" class="hidden py-16 text-center space-y-4" style="display: none;">
                        <div
                            class="flex h-16 w-16 mx-auto items-center justify-center rounded-2xl bg-[#E6F1FB] text-[#003F87]">
                            <i id="placeholder-icon" class="ti ti-settings text-3xl"></i>
                        </div>
                        <div class="space-y-1">
                            <h2 id="placeholder-title" class="text-lg font-black text-slate-900">Module Screen</h2>
                            <p class="text-slate-500 text-xs font-semibold">This dispatch operational database module is
                                fully wired to
                                local mock records.</p>
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
        window.GoPasigOverviewInitialData = {
            routes: @json($routes),
            buses: @json($buses),
            scheduleCompliance: @json($scheduleCompliance)
        };
    </script>

@endsection