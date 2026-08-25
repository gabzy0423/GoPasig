@extends('layouts.fleet')


@section('title', 'GoPasig Fleet Ops - Dashboard')
@php
    $fleetBreadcrumbs = [
        'overview' => 'Overview',
        'monitor' => 'Live Monitor',
        'utilization' => 'Fleet Utilization',
        'drivers' => 'Driver Performance',
        'routes' => 'Route Performance',
        'incidents' => 'Incident Reports',
        'maintenance' => 'Maintenance',
        'analytics' => 'Analytics',
        'dispatch-intelligence' => 'Dispatch Intelligence',
        'commuter-trips' => 'Commuter Trip Log',
        'commuter-sessions' => 'Active Commuter Sessions',
        'profile' => 'Account Profile',
    ];
@endphp
@section('breadcrumb', $fleetBreadcrumbs[$activeFleetTab ?? 'overview'] ?? 'Overview')

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

                    @foreach($fleetBreadcrumbs as $fleetTab => $fleetLabel)
                        @continue($fleetTab === 'overview')
                        <section id="screen-{{ $fleetTab }}" class="hidden animate-fade-in" style="display: none;" data-fleet-module-placeholder="{{ $fleetTab }}" data-loaded="false" data-load-state="idle" aria-live="polite">
                            <div class="flex min-h-[320px] items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50/70 text-center">
                                <div class="space-y-3">
                                    <div class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-white text-[#003F87] shadow-sm">
                                        <i class="ti ti-loader-2 text-lg"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-extrabold text-slate-800">{{ $fleetLabel }}</p>
                                        <p class="text-xs font-semibold text-slate-500">Ready to load.</p>
                                    </div>
                                </div>
                            </div>
                        </section>
                    @endforeach

                </div>
            </main>
        </div>
    </div>

    <!-- ==================== FRONTEND CONTROLLER JAVASCRIPT ==================== -->


    <script>
        window.GoPasigFleetModuleLoaderConfig = {
            initialTab: @json($activeFleetTab ?? 'overview'),
            fragmentUrl: @json(route('fleet.dashboard')),
            scripts: {
                analytics: @json(asset('js/fleet-dashboard/analytics.js') . '?v=' . filemtime(public_path('js/fleet-dashboard/analytics.js'))),
                drivers: @json(asset('js/fleet-dashboard/performance.js') . '?v=' . filemtime(public_path('js/fleet-dashboard/performance.js'))),
                routes: @json(asset('js/fleet-dashboard/performance.js') . '?v=' . filemtime(public_path('js/fleet-dashboard/performance.js'))),
                incidents: @json(asset('js/fleet-dashboard/incidents.js') . '?v=' . filemtime(public_path('js/fleet-dashboard/incidents.js'))),
                maintenance: @json(asset('js/fleet-dashboard/maintenance-management.js') . '?v=' . filemtime(public_path('js/fleet-dashboard/maintenance-management.js'))),
                'dispatch-intelligence': @json(asset('js/fleet-dashboard/dispatch-intelligence.js') . '?v=' . filemtime(public_path('js/fleet-dashboard/dispatch-intelligence.js'))),
                'commuter-trips': @json(asset('js/fleet-dashboard/commuter-trips.js') . '?v=' . filemtime(public_path('js/fleet-dashboard/commuter-trips.js'))),
                'commuter-sessions': @json(asset('js/fleet-dashboard/commuter-sessions.js') . '?v=' . filemtime(public_path('js/fleet-dashboard/commuter-sessions.js'))),
                profile: @json(asset('js/shared/staff-profile.js') . '?v=' . filemtime(public_path('js/shared/staff-profile.js')))
            },
            assets: {
                echarts: @json(asset('js/echarts.min.js') . '?v=' . filemtime(public_path('js/echarts.min.js')))
            }
        };
    </script>
    <script>
            window.GoPasigOverviewInitialData = {
                routes: @json($routes),
                buses: @json($buses),
                tripOutcomes: @json($tripOutcomes)
            };
        </script>

@endsection
