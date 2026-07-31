<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('trips:cleanup-orphaned', function () {
    $expiredTokens = CommuterSession::whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->pluck('session_token');

    // Find WAITING or ON_BUS trips that are expired or orphaned
    $affectedTrips = CommuterTrip::whereIn('status', ['WAITING', 'ON_BUS'])
        ->where(function ($query) use ($expiredTokens) {
            $query->whereIn('session_token', $expiredTokens)
                  ->orWhereNotExists(function ($q) {
                      $q->select(DB::raw(1))
                        ->from('commuter_sessions')
                        ->whereColumn('commuter_sessions.session_token', 'commuter_trips.session_token');
                  });
        })
        ->update([
            'status' => 'CANCELLED',
            'updated_at' => now(),
        ]);

    $this->info("Successfully cancelled {$affectedTrips} orphaned commuter trips.");
})->purpose('Auto-cancel commuter trips with expired sessions');

// Schedule the command to run every 30 minutes
Schedule::command('trips:cleanup-orphaned')->everyThirtyMinutes();

Artisan::command('buses:clean-inconsistent-statuses {--confirm : Actually perform the update}', function () {
    // 1. Active status but no ongoing trip
    $query1 = \App\Models\Bus::whereNotIn('status', ['maintenance', 'breakdown', 'inactive'])
        ->whereDoesntHave('trips', function ($q) {
            $q->where('status', 'ongoing');
        });

    // 2. Inactive/Maintenance status but has an ongoing trip
    $query2 = \App\Models\Bus::whereIn('status', ['inactive', 'maintenance'])
        ->whereHas('trips', function ($q) {
            $q->where('status', 'ongoing');
        });

    $buses1 = $query1->get(['id', 'plate_number', 'status']);
    $buses2 = $query2->get(['id', 'plate_number', 'status']);

    if ($buses1->isEmpty() && $buses2->isEmpty()) {
        $this->info("No buses with inconsistent statuses found.");
        return;
    }

    if ($buses1->isNotEmpty()) {
        $this->info("Found {$buses1->count()} active buses with no ongoing trip:");
        foreach ($buses1 as $bus) {
            $this->line("- Bus #{$bus->id}: {$bus->plate_number} (status: {$bus->status})");
        }
    }

    if ($buses2->isNotEmpty()) {
        $this->info("Found {$buses2->count()} inactive/maintenance buses with ongoing trips:");
        foreach ($buses2 as $bus) {
            $this->line("- Bus #{$bus->id}: {$bus->plate_number} (status: {$bus->status})");
        }
    }

    if ($this->option('confirm')) {
        $count = 0;
        foreach ($buses1 as $bus) {
            \App\Services\BusStateService::transition($bus, \App\Models\Bus::STATUS_INACTIVE, 'Inconsistent state: active status but no ongoing trip');
            $count++;
        }
        foreach ($buses2 as $bus) {
            // Re-transition to inactive to clean up the ongoing trip
            \App\Services\BusStateService::transition($bus, \App\Models\Bus::STATUS_INACTIVE, 'Inconsistent state: inactive/maintenance status with ongoing trip');
            $count++;
        }
        $this->info("Successfully reset {$count} inconsistent buses to 'inactive'.");
    } else {
        $this->warn("Dry-run only. Run with --confirm option to reset the status of these buses.");
    }
})->purpose('Find and optionally reset buses with inconsistent status and trip states');

// Schedule Phase 4 GPS log cleanup job daily
Schedule::job(new \App\Jobs\CleanupGPSLogsJob())->daily();

Artisan::command('fleet:check-offline', function () {
    $threshold = now()->subSeconds(120);

    // Find active buses that have not updated in the last 120 seconds
    $offlineBuses = \App\Models\Bus::where('status', 'active')
        ->where(function ($query) use ($threshold) {
            $query->whereExists(function ($q) use ($threshold) {
                $q->select(DB::raw(1))
                  ->from('vehicle_positions')
                  ->whereColumn('vehicle_positions.bus_id', 'buses.id')
                  ->where('vehicle_positions.last_updated_at', '<', $threshold);
            })
            ->orWhereNotExists(function ($q) {
                $q->select(DB::raw(1))
                  ->from('vehicle_positions')
                  ->whereColumn('vehicle_positions.bus_id', 'buses.id');
            });
        })
        ->get();

    foreach ($offlineBuses as $bus) {
        $position = \App\Models\VehiclePosition::where('bus_id', $bus->id)->first();
        if ($position && $position->status !== 'Offline') {
            $position->update([
                'status' => 'Offline',
                'last_updated_at' => now(),
            ]);
            event(new \App\Events\VehicleOffline($bus->id));
            $this->info("Bus #{$bus->id} ({$bus->plate_number}) telemetry is stale. Marked Offline.");
        }
    }
})->purpose('Check and transition buses with stale telemetry to offline status');

Schedule::command('fleet:check-offline')->everyMinute();

Artisan::command('fleet:simulate-trip', function () {
    $this->info("=== Starting Fleet GPS Simulation ===");

    // 1. Setup Bus and Driver
    $bus = \App\Models\Bus::where('plate_number', 'PAS-001')->first();
    if (!$bus) {
        $this->error("Bus PAS-001 not found. Make sure database is seeded.");
        return;
    }
    
    // Make sure we have a driver
    $driver = \App\Models\Driver::first();
    if (!$driver) {
        $this->error("No driver found. Seed the database first.");
        return;
    }

    // Set bus to active
    $bus->update(['status' => 'active']);

    // Find or create an ongoing trip for PAS-001 on Route 1
    $trip = \App\Models\Trip::where('bus_id', $bus->id)->where('status', 'ongoing')->first();
    if ($trip) {
        $trip->update(['status' => 'completed', 'ended_at' => now()]);
    }
    
    $trip = \App\Models\Trip::create([
        'bus_id' => $bus->id,
        'driver_id' => $driver->id,
        'route_id' => 1,
        'status' => 'ongoing',
        'started_at' => now(),
    ]);

    // Setup initial TripProgress
    \App\Models\TripProgress::updateOrCreate(
        ['trip_id' => $trip->id],
        [
            'route_adherence' => 'On Route',
            'completed_stops_count' => 0,
            'remaining_stops_count' => 0,
        ]
    );

    // Delete any old transitions to ensure clean slate
    \App\Models\GeofenceTransition::where('bus_id', $bus->id)->delete();

    // 2. Define Simulation Steps
    // Each step: [lat, lng, speed, description]
    $steps = [
        // Step 1: Exactly at SPED Terminal (Stop 1)
        [14.5593, 121.0805, 0, "At SPED Terminal (Terminal Geofence Entered)"],
        
        // Step 2: Still at SPED Terminal, showing dwell timer incrementing
        [14.5593, 121.0805, 0, "Dwell inside SPED Terminal"],

        // Step 3: Moving away from SPED Terminal (Exit pending / exited)
        [14.5605, 121.0810, 15, "Leaving SPED Terminal (Exiting Geofence)"],

        // Step 4: Normal moving along Route A corridor
        [14.5620, 121.0820, 25, "On Route: Caruncho Ave"],

        // Step 5: Moving off-route (deviation)
        [14.5625, 121.0865, 30, "Route Deviation (Moving off-route)"],

        // Step 6: Still off-route (continuing deviation)
        [14.5630, 121.0870, 20, "Route Deviation (Still off-route)"],

        // Step 7: Moving back to Route A corridor
        [14.5680, 121.0760, 25, "On Route: Approaching next waypoint (Recovery)"],

        // Step 8: Approaching Temporary Pasig City Hall (Stop 2)
        [14.5820, 121.0630, 15, "Approaching Temporary Pasig City Hall"],

        // Step 9: Arrived at Temporary Pasig City Hall
        [14.5838, 121.0620, 0, "Arrived at Temporary Pasig City Hall (Stop Geofence Entered)"]
    ];

    foreach ($steps as $i => $step) {
        $lat = $step[0];
        $lng = $step[1];
        $speed = $step[2];
        $desc = $step[3];

        $this->info("Step " . ($i + 1) . ": {$desc} (Lat: {$lat}, Lng: {$lng}, Speed: {$speed} km/h)");

        // Update database vehicle position
        $position = \App\Models\VehiclePosition::updateOrCreate(
            ['bus_id' => $bus->id],
            [
                'trip_id' => $trip->id,
                'lat' => $lat,
                'lng' => $lng,
                'speed' => $speed,
                'passengers' => 24,
                'status' => $speed > 0 ? 'Moving' : 'Stopped',
                'last_updated_at' => now(),
            ]
        );

        // Dispatch position updated event (triggers pipeline Validation -> Kalman -> Geofencing -> Corridor)
        event(new \App\Events\PositionUpdated($position));

        $this->line("Event dispatched. Sleeping 3 seconds...");
        sleep(3);
    }

    $this->info("=== Simulation Completed ===");
})->purpose('Simulates GPS coordinates for PAS-001 along Route A to test geofencing and corridor monitoring');

Artisan::command('location-uat:route-c {--no-reset : Keep existing dedicated UAT trip derived data before replay} {--json : Print full JSON output}', function () {
    $harness = app(\App\Services\Testing\ControlledLocationIntelligenceHarness::class);

    try {
        $run = $harness->run([
            'route_id' => 3,
            'reset' => ! $this->option('no-reset'),
        ]);
    } catch (\Throwable $e) {
        $this->error($e->getMessage());
        return 1;
    }

    $this->info('Controlled Location Intelligence UAT Harness');
    $this->line("Route: {$run['route']['id']} {$run['route']['name']} ({$run['route']['polyline_points']} polyline points)");
    $this->line("Trip: {$run['trip']['id']} | Bus: {$run['trip']['bus_id']} | Driver: {$run['trip']['driver_id']}");

    foreach ($run['results'] as $result) {
        $this->newLine();
        $this->info("STEP {$result['step']} - {$result['label']}");
        $this->line('Sent: '.$result['coordinate_sent']['lat'].', '.$result['coordinate_sent']['lng']
            .' | cached='.($result['coordinate_sent']['is_cached_fix'] ? 'true' : 'false')
            .' | speed='.$result['coordinate_sent']['speed_mps'].' m/s'
            .' | heading='.($result['coordinate_sent']['heading'] ?? 'null')
            .' | accuracy='.$result['coordinate_sent']['accuracy'].'m');
        $this->line('HTTP: '.$result['http']['status'].' | '.($result['http']['message'] ?? 'no message').' | log_id='.($result['http']['log_id'] ?? 'none'));
        $this->line('GPSLog: status='.($result['gps_log']['processing_status'] ?? 'none')
            .' | filtered='.($result['gps_log']['filtered_lat'] ?? 'null').', '.($result['gps_log']['filtered_lng'] ?? 'null'));
        $this->line('VehiclePosition: '.($result['vehicle_position']['lat'] ?? 'null').', '.($result['vehicle_position']['lng'] ?? 'null')
            .' | corridor='.($result['vehicle_position']['corridor_distance'] ?? 'null')
            .' | movement='.($result['vehicle_position']['movement_state'] ?? 'null')
            .' | gps_quality='.($result['vehicle_position']['gps_quality_state'] ?? 'null'));
        $this->line('TripProgress: current='.($result['trip_progress']['current_stop_id'] ?? 'null')
            .' | next='.($result['trip_progress']['next_stop_id'] ?? 'null')
            .' | completed='.($result['trip_progress']['completed_stops_count'] ?? 'null')
            .' | pct='.($result['trip_progress']['trip_percentage'] ?? 'null')
            .' | adherence='.($result['trip_progress']['route_adherence'] ?? 'null'));
        $this->line('Geofence: transition='.($result['geofence']['id'] ?? 'none')
            .' | geofence_id='.($result['geofence']['geofence_id'] ?? 'none')
            .' | exited_at='.($result['geofence']['exited_at'] ?? 'null'));
        $this->line('StopArrival: id='.($result['stop_arrival']['id'] ?? 'none')
            .' | stop_id='.($result['stop_arrival']['stop_id'] ?? 'none')
            .' | departed='.($result['stop_arrival']['departure_time'] ?? 'null'));
        $this->line('RouteDeviation: id='.($result['route_deviation']['id'] ?? 'none')
            .' | distance='.($result['route_deviation']['distance_meters'] ?? 'null')
            .' | severity='.($result['route_deviation']['severity'] ?? 'null')
            .' | resolved_at='.($result['route_deviation']['resolved_at'] ?? 'null'));
        $this->line('Fleet API: next='.($result['fleet_api']['next_stop'] ?? 'null')
            .' | upcoming='.($result['fleet_api']['upcoming_stop'] ?? 'null')
            .' | eta='.($result['fleet_api']['eta'] ?? 'null')
            .' | corridor='.($result['fleet_api']['corridor_distance'] ?? 'null')
            .' | adherence='.($result['fleet_api']['route_adherence'] ?? 'null'));
        $this->line('Admin API: next='.($result['admin_api']['next_stop'] ?? 'null')
            .' | eta='.($result['admin_api']['eta'] ?? 'null')
            .' | corridor='.($result['admin_api']['corridor_distance'] ?? 'not_exposed')
            .' | adherence='.($result['admin_api']['route_adherence'] ?? 'not_exposed'));

        if (! empty($result['fleet_admin_mismatches'])) {
            $this->warn('Fleet/Admin mismatch: '.json_encode($result['fleet_admin_mismatches']));
        }
    }

    if ($this->option('json')) {
        $this->newLine();
        $this->line(json_encode($run, JSON_PRETTY_PRINT));
    }

    return 0;
})->purpose('Run the guarded Route C controlled Location Intelligence UAT harness through driver GPS ingestion');

Artisan::command('phase2-uat:cleanup {--confirm : Delete the disposable Phase 2 UAT records}', function () {
    $routeNames = ['PHASE2-UAT Point-to-Point A-B'];
    $busPlates = ['PHASE2-UAT-BUS'];
    $userEmails = ['phase2.uat.driver@gopasig.test'];

    $routes = \App\Models\Route::withTrashed()->whereIn('name', $routeNames)->get();
    $buses = \App\Models\Bus::whereIn('plate_number', $busPlates)->get();
    $users = \App\Models\User::whereIn('email', $userEmails)->get();
    $drivers = \App\Models\Driver::whereIn('user_id', $users->pluck('id'))
        ->orWhere('emp_id', 'PHASE2-UAT-DRIVER')
        ->get();

    $tripQuery = \App\Models\Trip::query()
        ->where(function ($query) use ($routes, $buses, $drivers) {
            $query->whereIn('route_id', $routes->pluck('id'))
                ->orWhereIn('bus_id', $buses->pluck('id'))
                ->orWhereIn('driver_id', $drivers->pluck('id'));
        });

    $summary = [
        'routes' => $routes->count(),
        'route_variants' => \App\Models\RouteVariant::whereIn('route_id', $routes->pluck('id'))->count(),
        'route_stops' => \App\Models\Stop::whereIn('route_id', $routes->pluck('id'))->count(),
        'trips' => (clone $tripQuery)->count(),
        'buses' => $buses->count(),
        'drivers' => $drivers->count(),
        'users' => $users->count(),
    ];

    $this->info('Phase 2 UAT cleanup target summary:');
    foreach ($summary as $label => $count) {
        $this->line("- {$label}: {$count}");
    }

    if (! $this->option('confirm')) {
        $this->warn('Dry-run only. Run php artisan phase2-uat:cleanup --confirm to delete these disposable records.');
        return 0;
    }

    DB::transaction(function () use ($routes, $buses, $drivers, $users, $tripQuery) {
        $tripIds = (clone $tripQuery)->pluck('id');

        if ($tripIds->isNotEmpty()) {
            \App\Models\DispatchLog::whereIn('trip_id', $tripIds)->delete();
            \App\Models\GPSLog::whereIn('trip_id', $tripIds)->delete();
            \App\Models\Trip::whereIn('id', $tripIds)->delete();
        }

        if ($buses->isNotEmpty()) {
            DB::table('vehicle_positions')->whereIn('bus_id', $buses->pluck('id'))->delete();
            DB::table('geofence_transitions')->whereIn('bus_id', $buses->pluck('id'))->delete();
            DB::table('bus_status_audit_log')->whereIn('bus_id', $buses->pluck('id'))->delete();
            \App\Models\Bus::whereIn('id', $buses->pluck('id'))->delete();
        }

        if ($routes->isNotEmpty()) {
            foreach ($routes as $route) {
                $route->forceDelete();
            }
        }

        if ($drivers->isNotEmpty()) {
            \App\Models\Driver::whereIn('id', $drivers->pluck('id'))->delete();
        }

        if ($users->isNotEmpty()) {
            \App\Models\User::whereIn('id', $users->pluck('id'))->delete();
        }
    });

    \Illuminate\Support\Facades\Cache::forget('routes_all');
    $this->info('Disposable Phase 2 UAT records removed.');

    return 0;
})->purpose('Dry-run or remove isolated Phase 2 point-to-point UAT data');

Artisan::command('phase2-uat:setup', function () {
    if (app()->environment('production')) {
        $this->error('Refusing to create Phase 2 UAT data in production.');
        return 1;
    }

    $this->call('phase2-uat:cleanup', ['--confirm' => true]);

    $uat = DB::transaction(function () {
        $now = now();
        $route = \App\Models\Route::create([
            'name' => 'PHASE2-UAT Point-to-Point A-B',
            'description' => 'Disposable local-only Phase 2 point-to-point UAT route. Safe to remove with phase2-uat:cleanup.',
            'color' => '#3B6D11',
            'polyline_coordinates' => [[14.5593000, 121.0805000], [14.5603000, 121.0815000]],
            'geometry_version' => 1,
            'travel_time_minutes' => 5,
            'delay_threshold_minutes' => 5,
            'min_speed' => 0,
            'max_speed' => 40,
            'target_on_time_rate' => 95,
            'target_headway_minutes' => 10,
        ]);

        $stopA = \App\Models\Stop::create([
            'route_id' => $route->id,
            'name' => 'PHASE2-UAT Point A',
            'lat' => 14.5593000,
            'lng' => 121.0805000,
            'radius_meters' => 60,
            'sequence' => 1,
        ]);

        $stopB = \App\Models\Stop::create([
            'route_id' => $route->id,
            'name' => 'PHASE2-UAT Point B',
            'lat' => 14.5603000,
            'lng' => 121.0815000,
            'radius_meters' => 60,
            'sequence' => 2,
        ]);

        $outbound = \App\Models\RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'PHASE2-UAT Point A',
            'destination_name' => 'PHASE2-UAT Point B',
            'polyline_coordinates' => [[14.5593000, 121.0805000], [14.5603000, 121.0815000]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);

        $inbound = \App\Models\RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => 'PHASE2-UAT Point B',
            'destination_name' => 'PHASE2-UAT Point A',
            'polyline_coordinates' => [[14.5603000, 121.0815000], [14.5593000, 121.0805000]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => false,
        ]);

        \App\Models\RouteVariantStop::create([
            'route_variant_id' => $outbound->id,
            'canonical_stop_id' => $stopA->id,
            'name' => $stopA->name,
            'lat' => $stopA->lat,
            'lng' => $stopA->lng,
            'radius_meters' => 60,
            'sequence' => 1,
        ]);
        \App\Models\RouteVariantStop::create([
            'route_variant_id' => $outbound->id,
            'canonical_stop_id' => $stopB->id,
            'name' => $stopB->name,
            'lat' => $stopB->lat,
            'lng' => $stopB->lng,
            'radius_meters' => 60,
            'sequence' => 2,
        ]);
        \App\Models\RouteVariantStop::create([
            'route_variant_id' => $inbound->id,
            'canonical_stop_id' => $stopB->id,
            'name' => $stopB->name,
            'lat' => $stopB->lat,
            'lng' => $stopB->lng,
            'radius_meters' => 60,
            'sequence' => 1,
        ]);
        \App\Models\RouteVariantStop::create([
            'route_variant_id' => $inbound->id,
            'canonical_stop_id' => $stopA->id,
            'name' => $stopA->name,
            'lat' => $stopA->lat,
            'lng' => $stopA->lng,
            'radius_meters' => 60,
            'sequence' => 2,
        ]);

        $user = \App\Models\User::create([
            'name' => 'Phase2 UAT Driver',
            'email' => 'phase2.uat.driver@gopasig.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'driver',
            'email_verified_at' => $now,
        ]);

        $driver = \App\Models\Driver::create([
            'user_id' => $user->id,
            'first_name' => 'Phase2',
            'last_name' => 'UAT Driver',
            'emp_id' => 'PHASE2-UAT-DRIVER',
            'license_number' => 'PHASE2-UAT-LICENSE',
            'license_expiry' => $now->copy()->addYear()->toDateString(),
            'status' => 'active',
            'operational_status' => 'available',
            'trips_today' => 0,
            'pax_today' => 0,
            'performance_score' => 100,
            'incidents_30' => 0,
        ]);

        $bus = \App\Models\Bus::create([
            'plate_number' => 'PHASE2-UAT-BUS',
            'fleet_number' => 'PHASE2-UAT-FLEET',
            'vin' => 'PHASE2UATVIN00001',
            'manufacturer' => 'UAT',
            'model' => 'Phase2 Harness',
            'year_model' => 2026,
            'route_id' => null,
            'driver_name' => null,
            'capacity' => 45,
            'speed' => 0,
            'passengers' => 0,
            'next_stop' => null,
            'eta' => 0,
            'lat' => 14.5593000,
            'lng' => 121.0805000,
            'status' => 'available',
            'is_simulated' => false,
        ]);

        $trip = \App\Services\SimulationDispatchService::dispatch(
            $bus,
            $driver,
            $route,
            null,
            'Phase 2 local UAT initial outbound dispatch.',
            $outbound
        );

        return compact('route', 'outbound', 'inbound', 'stopA', 'stopB', 'user', 'driver', 'bus', 'trip');
    });

    \Illuminate\Support\Facades\Cache::forget('routes_all');

    $this->info('Phase 2 point-to-point UAT setup created.');
    $this->line("Driver login: phase2.uat.driver@gopasig.test / password");
    $this->line("Bus: {$uat['bus']->plate_number} | Driver: {$uat['driver']->name} | Route: {$uat['route']->id} {$uat['route']->name}");
    $this->line("Outbound variant: {$uat['outbound']->id} {$uat['outbound']->origin_name} -> {$uat['outbound']->destination_name}");
    $this->line("Inbound variant: {$uat['inbound']->id} {$uat['inbound']->origin_name} -> {$uat['inbound']->destination_name}");
    $this->line("Initial Trip: {$uat['trip']->id} status={$uat['trip']->status} gps_session={$uat['trip']->gps_session} route_variant_id={$uat['trip']->route_variant_id}");
    $this->newLine();
    $this->info('Manual UAT flow:');
    $this->line('1. Log in as the UAT driver and open /driver/trip.');
    $this->line('2. Start Live Trip Session. The existing dispatched outbound Trip should become ongoing / GPS ACTIVE.');
    $this->line('3. End Trip. The outbound Trip should become completed / GPS CLOSED and the assignment should remain ready/assigned.');
    $this->line('4. Click Start Next Trip. A separate inbound Trip should be created and started with GPS ACTIVE.');
    $this->newLine();
    $this->warn('Cleanup command: php artisan phase2-uat:cleanup --confirm');

    return 0;
})->purpose('Create isolated local-only Phase 2 point-to-point UAT data');
Artisan::command('phase3c-uat:cleanup {--confirm : Delete the disposable Phase 3C UAT records}', function () {
    $routeName = 'PHASE3C-UAT Point-to-Point A-B';
    $busPlate = 'PHASE3C-UAT-BUS';
    $userEmail = 'phase3c.uat.driver@gopasig.test';
    $driverEmpId = 'PHASE3C-UAT-DRIVER';

    $routes = \App\Models\Route::withTrashed()
        ->where('name', $routeName)
        ->orWhere('description', 'like', '%PHASE3C-UAT%')
        ->get();
    $buses = \App\Models\Bus::where('plate_number', $busPlate)
        ->orWhere('fleet_number', 'PHASE3C-UAT-FLEET')
        ->get();
    $users = \App\Models\User::where('email', $userEmail)->get();
    $drivers = \App\Models\Driver::where('emp_id', $driverEmpId)
        ->orWhereIn('user_id', $users->pluck('id'))
        ->get();
    $schedules = \App\Models\Schedule::where(function ($query) use ($routes, $buses, $drivers) {
            $query->whereIn('route_id', $routes->pluck('id'))
                ->orWhereIn('bus_id', $buses->pluck('id'))
                ->orWhereIn('driver_id', $drivers->pluck('id'));
        })
        ->get();

    $tripQuery = \App\Models\Trip::query()
        ->where(function ($query) use ($routes, $buses, $drivers, $schedules) {
            $query->whereIn('route_id', $routes->pluck('id'))
                ->orWhereIn('bus_id', $buses->pluck('id'))
                ->orWhereIn('driver_id', $drivers->pluck('id'))
                ->orWhereIn('schedule_id', $schedules->pluck('id'));
        });

    $summary = [
        'routes' => $routes->count(),
        'route_variants' => \App\Models\RouteVariant::whereIn('route_id', $routes->pluck('id'))->count(),
        'route_variant_stops' => \App\Models\RouteVariantStop::whereIn('route_variant_id', \App\Models\RouteVariant::whereIn('route_id', $routes->pluck('id'))->pluck('id'))->count(),
        'route_stops' => \App\Models\Stop::whereIn('route_id', $routes->pluck('id'))->count(),
        'schedules' => $schedules->count(),
        'trips' => (clone $tripQuery)->count(),
        'buses' => $buses->count(),
        'drivers' => $drivers->count(),
        'users' => $users->count(),
    ];

    $this->info('Phase 3C UAT cleanup target summary:');
    foreach ($summary as $label => $count) {
        $this->line("- {$label}: {$count}");
    }

    if (! $this->option('confirm')) {
        $this->warn('Dry-run only. Run php artisan phase3c-uat:cleanup --confirm to delete these disposable records.');
        return 0;
    }

    DB::transaction(function () use ($routes, $buses, $drivers, $users, $schedules, $tripQuery) {
        $routeIds = $routes->pluck('id');
        $busIds = $buses->pluck('id');
        $driverIds = $drivers->pluck('id');
        $scheduleIds = $schedules->pluck('id');
        $tripIds = (clone $tripQuery)->pluck('id');
        $variantIds = \App\Models\RouteVariant::whereIn('route_id', $routeIds)->pluck('id');

        if ($tripIds->isNotEmpty()) {
            \App\Models\DispatchLog::whereIn('trip_id', $tripIds)->delete();
            \App\Models\GPSLog::whereIn('trip_id', $tripIds)->delete();
            \App\Models\Incident::whereIn('trip_id', $tripIds)->delete();

            foreach (['trip_progress', 'stop_arrivals', 'route_deviations'] as $table) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    DB::table($table)->whereIn('trip_id', $tripIds)->delete();
                }
            }

            \App\Models\Trip::whereIn('id', $tripIds)->delete();
        }

        if ($scheduleIds->isNotEmpty()) {
            \App\Models\Schedule::whereIn('id', $scheduleIds)->delete();
        }

        if ($busIds->isNotEmpty()) {
            foreach (['vehicle_positions', 'geofence_transitions', 'bus_status_audit_log'] as $table) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    DB::table($table)->whereIn('bus_id', $busIds)->delete();
                }
            }

            \App\Models\Bus::whereIn('id', $busIds)->delete();
        }

        if ($variantIds->isNotEmpty()) {
            \App\Models\RouteVariantStop::whereIn('route_variant_id', $variantIds)->delete();
            \App\Models\RouteVariant::whereIn('id', $variantIds)->delete();
        }

        if ($routeIds->isNotEmpty()) {
            \App\Models\Stop::whereIn('route_id', $routeIds)->delete();
            foreach ($routes as $route) {
                $route->forceDelete();
            }
        }

        if ($driverIds->isNotEmpty()) {
            \App\Models\Driver::whereIn('id', $driverIds)->delete();
        }

        if ($users->isNotEmpty()) {
            \App\Models\User::whereIn('id', $users->pluck('id'))->delete();
        }
    });

    \Illuminate\Support\Facades\Cache::forget('routes_all');
    $this->info('Disposable Phase 3C UAT records removed.');

    return 0;
})->purpose('Dry-run or remove isolated Phase 3C scheduled point-to-point UAT data');

Artisan::command('phase3c-uat:setup', function () {
    if (app()->environment('production')) {
        $this->error('Refusing to create Phase 3C UAT data in production.');
        return 1;
    }

    $this->call('phase3c-uat:cleanup', ['--confirm' => true]);

    $uat = DB::transaction(function () {
        $now = now('Asia/Manila');
        $serviceDate = $now->toDateString();

        $route = \App\Models\Route::create([
            'name' => 'PHASE3C-UAT Point-to-Point A-B',
            'description' => 'Disposable local-only Phase 3C scheduled return-leg UAT route. Safe to remove with phase3c-uat:cleanup.',
            'color' => '#0F766E',
            'polyline_coordinates' => [[14.5593000, 121.0805000], [14.5603000, 121.0815000]],
            'geometry_version' => 1,
            'travel_time_minutes' => 25,
            'delay_threshold_minutes' => 5,
            'min_speed' => 0,
            'max_speed' => 40,
            'target_on_time_rate' => 95,
            'target_headway_minutes' => 30,
        ]);

        $stopA = \App\Models\Stop::create([
            'route_id' => $route->id,
            'name' => 'PHASE3C-UAT Point A',
            'lat' => 14.5593000,
            'lng' => 121.0805000,
            'radius_meters' => 60,
            'sequence' => 1,
        ]);

        $stopB = \App\Models\Stop::create([
            'route_id' => $route->id,
            'name' => 'PHASE3C-UAT Point B',
            'lat' => 14.5603000,
            'lng' => 121.0815000,
            'radius_meters' => 60,
            'sequence' => 2,
        ]);

        $outbound = \App\Models\RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'PHASE3C-UAT Point A',
            'destination_name' => 'PHASE3C-UAT Point B',
            'polyline_coordinates' => [[14.5593000, 121.0805000], [14.5603000, 121.0815000]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);

        $inbound = \App\Models\RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'inbound',
            'origin_name' => 'PHASE3C-UAT Point B',
            'destination_name' => 'PHASE3C-UAT Point A',
            'polyline_coordinates' => [[14.5603000, 121.0815000], [14.5593000, 121.0805000]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => false,
        ]);

        \App\Models\RouteVariantStop::create([
            'route_variant_id' => $outbound->id,
            'canonical_stop_id' => $stopA->id,
            'name' => $stopA->name,
            'lat' => $stopA->lat,
            'lng' => $stopA->lng,
            'radius_meters' => 60,
            'sequence' => 1,
        ]);
        \App\Models\RouteVariantStop::create([
            'route_variant_id' => $outbound->id,
            'canonical_stop_id' => $stopB->id,
            'name' => $stopB->name,
            'lat' => $stopB->lat,
            'lng' => $stopB->lng,
            'radius_meters' => 60,
            'sequence' => 2,
        ]);
        \App\Models\RouteVariantStop::create([
            'route_variant_id' => $inbound->id,
            'canonical_stop_id' => $stopB->id,
            'name' => $stopB->name,
            'lat' => $stopB->lat,
            'lng' => $stopB->lng,
            'radius_meters' => 60,
            'sequence' => 1,
        ]);
        \App\Models\RouteVariantStop::create([
            'route_variant_id' => $inbound->id,
            'canonical_stop_id' => $stopA->id,
            'name' => $stopA->name,
            'lat' => $stopA->lat,
            'lng' => $stopA->lng,
            'radius_meters' => 60,
            'sequence' => 2,
        ]);

        $user = \App\Models\User::create([
            'name' => 'Phase3C UAT Driver',
            'email' => 'phase3c.uat.driver@gopasig.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'driver',
            'email_verified_at' => $now,
        ]);

        $driver = \App\Models\Driver::create([
            'user_id' => $user->id,
            'first_name' => 'Phase3C',
            'last_name' => 'UAT Driver',
            'emp_id' => 'PHASE3C-UAT-DRIVER',
            'license_number' => 'PHASE3C-UAT-LICENSE',
            'license_expiry' => $now->copy()->addYear()->toDateString(),
            'status' => 'active',
            'operational_status' => 'available',
            'trips_today' => 0,
            'pax_today' => 0,
            'performance_score' => 100,
            'incidents_30' => 0,
        ]);

        $bus = \App\Models\Bus::create([
            'plate_number' => 'PHASE3C-UAT-BUS',
            'fleet_number' => 'PHASE3C-UAT-FLEET',
            'vin' => 'PHASE3CUATVIN0001',
            'manufacturer' => 'UAT',
            'model' => 'Phase3C Harness',
            'year_model' => 2026,
            'route_id' => null,
            'driver_name' => null,
            'capacity' => 45,
            'speed' => 0,
            'passengers' => 0,
            'next_stop' => null,
            'eta' => 0,
            'lat' => 14.5593000,
            'lng' => 121.0805000,
            'status' => \App\Models\Bus::STATUS_INACTIVE,
            'is_simulated' => false,
        ]);

        $outboundSchedule = \App\Models\Schedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => $serviceDate,
            'departure_time' => $now->copy()->addMinutes(10)->format('H:i'),
            'arrival_time' => $now->copy()->addMinutes(35)->format('H:i'),
            'passengers' => 0,
            'status' => \App\Models\Schedule::STATUS_ON_TIME,
            'delay_minutes' => 0,
        ]);

        $inboundSchedule = \App\Models\Schedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $inbound->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => $serviceDate,
            'departure_time' => $now->copy()->addHours(3)->format('H:i'),
            'arrival_time' => $now->copy()->addHours(3)->addMinutes(25)->format('H:i'),
            'passengers' => 0,
            'status' => \App\Models\Schedule::STATUS_ON_TIME,
            'delay_minutes' => 0,
        ]);

        $trip = \App\Services\SimulationDispatchService::dispatchFromSchedule(
            $outboundSchedule,
            null,
            'Phase 3C local UAT initial outbound scheduled dispatch.'
        );

        return compact('route', 'outbound', 'inbound', 'stopA', 'stopB', 'user', 'driver', 'bus', 'outboundSchedule', 'inboundSchedule', 'trip');
    });

    \Illuminate\Support\Facades\Cache::forget('routes_all');

    $this->info('Phase 3C scheduled point-to-point UAT setup created.');
    $this->line("Driver login: phase3c.uat.driver@gopasig.test / password");
    $this->line("Route: {$uat['route']->id} {$uat['route']->name}");
    $this->line("Outbound variant: {$uat['outbound']->id} {$uat['outbound']->origin_name} -> {$uat['outbound']->destination_name}");
    $this->line("Inbound variant: {$uat['inbound']->id} {$uat['inbound']->origin_name} -> {$uat['inbound']->destination_name}");
    $this->line("Bus: {$uat['bus']->fresh()->id} {$uat['bus']->plate_number} status={$uat['bus']->fresh()->status}");
    $this->line("Driver: {$uat['driver']->fresh()->id} {$uat['driver']->name} operational_status={$uat['driver']->fresh()->operational_status}");
    $this->line("Schedule #1 outbound: {$uat['outboundSchedule']->id} route_variant_id={$uat['outboundSchedule']->route_variant_id} service_date={$uat['outboundSchedule']->service_date->toDateString()} departure={$uat['outboundSchedule']->departure_time}");
    $this->line("Schedule #2 inbound: {$uat['inboundSchedule']->id} route_variant_id={$uat['inboundSchedule']->route_variant_id} service_date={$uat['inboundSchedule']->service_date->toDateString()} departure={$uat['inboundSchedule']->departure_time} linked_trip=" . ($uat['inboundSchedule']->fresh()->trip?->id ?? 'none'));
    $this->line("Initial Trip #1: {$uat['trip']->id} status={$uat['trip']->status} gps_session={$uat['trip']->gps_session} schedule_id={$uat['trip']->schedule_id} route_variant_id={$uat['trip']->route_variant_id}");
    $this->newLine();
    $this->info('Manual UAT flow:');
    $this->line('1. Log in as the UAT driver and open /driver/trip.');
    $this->line('2. Start Trip #1: A -> B.');
    $this->line('3. End Trip #1. Driver UI should offer scheduled next trip B -> A.');
    $this->line('4. Click START NEXT TRIP. Trip #2 should be linked to Schedule #2 and start ongoing / GPS ACTIVE.');
    $this->line('5. End Trip #2. Final result is two completed directional trips = one round trip.');
    $this->newLine();
    $this->warn('Cleanup dry-run: php artisan phase3c-uat:cleanup');
    $this->warn('Cleanup delete: php artisan phase3c-uat:cleanup --confirm');

    return 0;
})->purpose('Create isolated local-only Phase 3C scheduled point-to-point UAT data');

Artisan::command('phase4a-gps-uat:cleanup {--confirm : Delete the disposable Phase 4A GPS freshness UAT records}', function () {
    $busPlate = 'PHASE4A-GPS-UAT-BUS';
    $driverEmpId = 'PHASE4A-GPS-UAT-DRIVER';

    $buses = \App\Models\Bus::where('plate_number', $busPlate)
        ->orWhere('fleet_number', 'PHASE4A-GPS-UAT-FLEET')
        ->get();
    $drivers = \App\Models\Driver::where('emp_id', $driverEmpId)
        ->orWhere('license_number', 'PHASE4A-GPS-UAT-LICENSE')
        ->get();
    $tripQuery = \App\Models\Trip::query()
        ->where(function ($query) use ($buses, $drivers) {
            $query->whereIn('bus_id', $buses->pluck('id'))
                ->orWhereIn('driver_id', $drivers->pluck('id'));
        });

    $summary = [
        'buses' => $buses->count(),
        'drivers' => $drivers->count(),
        'trips' => (clone $tripQuery)->count(),
        'vehicle_positions' => \Illuminate\Support\Facades\DB::table('vehicle_positions')->whereIn('bus_id', $buses->pluck('id'))->count(),
    ];

    $this->info('Phase 4A GPS freshness UAT cleanup target summary:');
    foreach ($summary as $label => $count) {
        $this->line("- {$label}: {$count}");
    }

    if (! $this->option('confirm')) {
        $this->warn('Dry-run only. Run php artisan phase4a-gps-uat:cleanup --confirm to delete these disposable records.');
        return 0;
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($buses, $drivers, $tripQuery) {
        $busIds = $buses->pluck('id');
        $driverIds = $drivers->pluck('id');
        $tripIds = (clone $tripQuery)->pluck('id');

        if ($tripIds->isNotEmpty()) {
            foreach (['trip_progresses', 'trip_progress', 'stop_arrivals', 'route_deviations'] as $table) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    \Illuminate\Support\Facades\DB::table($table)->whereIn('trip_id', $tripIds)->delete();
                }
            }

            \App\Models\DispatchLog::whereIn('trip_id', $tripIds)->delete();
            \App\Models\GPSLog::whereIn('trip_id', $tripIds)->delete();
            \App\Models\Incident::whereIn('trip_id', $tripIds)->delete();
            \App\Models\Trip::whereIn('id', $tripIds)->delete();
        }

        if ($busIds->isNotEmpty()) {
            foreach (['vehicle_positions', 'geofence_transitions', 'bus_status_audit_log'] as $table) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    \Illuminate\Support\Facades\DB::table($table)->whereIn('bus_id', $busIds)->delete();
                }
            }

            \App\Models\Bus::whereIn('id', $busIds)->delete();
        }

        if ($driverIds->isNotEmpty()) {
            \App\Models\Driver::whereIn('id', $driverIds)->delete();
        }
    });

    $this->info('Disposable Phase 4A GPS freshness UAT records removed.');
    return 0;
})->purpose('Dry-run or remove isolated Phase 4A GPS freshness UAT data');

Artisan::command('phase4a-gps-uat:setup {--route=Route 1 : Canonical public route name to use: Route 1, Route 2, or Route 3} {--variant= : Existing route_variant id to use instead of the route default} {--simulated : Mark the disposable bus as simulated so the Estimated badge is visible}', function () {
    if (app()->environment('production')) {
        $this->error('Refusing to create Phase 4A GPS freshness UAT data in production.');
        return 1;
    }

    $this->call('phase4a-gps-uat:cleanup', ['--confirm' => true]);

    $routeName = trim((string) $this->option('route')) ?: 'Route 1';
    $canonicalRoutes = \App\Models\Route::getCanonicalProductionCached();
    $route = $canonicalRoutes->firstWhere('name', $routeName);

    if (! $route) {
        $this->error("Route '{$routeName}' is not one of the current canonical public routes: ".$canonicalRoutes->pluck('name')->join(', '));
        return 1;
    }

    $variantId = $this->option('variant');
    $variantQuery = \App\Models\RouteVariant::where('route_id', $route->id);
    $variant = $variantId
        ? (clone $variantQuery)->whereKey((int) $variantId)->first()
        : (clone $variantQuery)->where('is_default', true)->orderBy('id')->first();
    $variant ??= (clone $variantQuery)->orderBy('id')->first();

    if (! $variant) {
        $this->error("Canonical {$route->name} has no existing RouteVariant. This harness will not create one.");
        return 1;
    }

    $uat = \Illuminate\Support\Facades\DB::transaction(function () use ($route, $variant) {
        $now = now();
        $lat = (float) \App\Models\SystemSetting::get('map_default_latitude', 14.5690);
        $lng = (float) \App\Models\SystemSetting::get('map_default_longitude', 121.0850);
        $driverName = 'Phase4A GPS UAT Driver';

        $driver = \App\Models\Driver::create([
            'first_name' => 'Phase4A GPS',
            'last_name' => 'UAT Driver',
            'emp_id' => 'PHASE4A-GPS-UAT-DRIVER',
            'license_number' => 'PHASE4A-GPS-UAT-LICENSE',
            'license_expiry' => $now->copy()->addYear()->toDateString(),
            'status' => 'active',
            'operational_status' => 'driving',
            'assigned_bus' => 'PHASE4A-GPS-UAT-BUS',
            'assigned_route' => $route->id,
            'trips_today' => 0,
            'pax_today' => 0,
            'performance_score' => 100,
            'incidents_30' => 0,
        ]);

        $bus = \App\Models\Bus::create([
            'plate_number' => 'PHASE4A-GPS-UAT-BUS',
            'fleet_number' => 'PHASE4A-GPS-UAT-FLEET',
            'vin' => 'PHASE4AGPSUAT0001',
            'manufacturer' => 'UAT',
            'model' => 'Phase4A GPS Freshness Harness',
            'year_model' => 2026,
            'route_id' => $route->id,
            'driver_name' => $driverName,
            'capacity' => 45,
            'speed' => 0,
            'passengers' => 0,
            'next_stop' => $variant->origin_name ?: 'Phase 4A UAT Position',
            'eta' => 5,
            'lat' => $lat,
            'lng' => $lng,
            'status' => \App\Models\Bus::STATUS_ACTIVE,
            'is_simulated' => (bool) $this->option('simulated'),
        ]);

        $trip = \App\Models\Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => $now,
            'dispatched_at' => $now,
            'gps_session_started_at' => $now,
            'peak_passengers' => 0,
        ]);

        $position = \App\Models\VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => $lat,
            'lng' => $lng,
            'heading' => 0,
            'speed' => 0,
            'status' => 'UAT',
            'last_updated_at' => $now,
            'last_gps_fix_at' => $now,
            'gps_quality_state' => 'GOOD',
            'gps_quality_reason' => 'phase4a_uat_manual_timestamp',
            'gps_quality_updated_at' => $now,
            'gps_fix_age_seconds' => 0,
        ]);

        return compact('route', 'variant', 'bus', 'driver', 'trip', 'position');
    });

    $this->info('Phase 4A GPS freshness UAT harness created.');
    $this->line("Route: {$uat['route']->id} {$uat['route']->name}");
    $this->line("Existing RouteVariant: {$uat['variant']->id} {$uat['variant']->direction} | {$uat['variant']->origin_name} -> {$uat['variant']->destination_name}");
    $this->line("Bus: {$uat['bus']->id} {$uat['bus']->plate_number} status={$uat['bus']->status} simulated=".($uat['bus']->is_simulated ? 'true' : 'false'));
    $this->line("Driver: {$uat['driver']->id} {$uat['driver']->name}");
    $this->line("Trip: {$uat['trip']->id} status={$uat['trip']->status} gps_session={$uat['trip']->gps_session} route_variant_id={$uat['trip']->route_variant_id}");
    $this->line("VehiclePosition: {$uat['position']->id} last_gps_fix_at={$uat['position']->last_gps_fix_at?->toIso8601String()}");
    $this->newLine();
    $this->info('Freshness commands:');
    $this->line('php artisan phase4a-gps-uat:freshness live');
    $this->line('php artisan phase4a-gps-uat:freshness stale');
    $this->line('php artisan phase4a-gps-uat:freshness offline');
    $this->line('php artisan phase4a-gps-uat:freshness unknown');
    $this->newLine();
    $this->warn('Cleanup dry-run: php artisan phase4a-gps-uat:cleanup');
    $this->warn('Cleanup delete: php artisan phase4a-gps-uat:cleanup --confirm');

    return 0;
})->purpose('Create isolated Phase 4A GPS freshness UAT bus, driver, ongoing canonical trip, and vehicle position');

Artisan::command('phase4a-gps-uat:freshness {state : live|stale|offline|unknown}', function () {
    $state = strtolower((string) $this->argument('state'));
    $bus = \App\Models\Bus::where('plate_number', 'PHASE4A-GPS-UAT-BUS')->first();

    if (! $bus) {
        $this->error('Phase 4A GPS UAT bus not found. Run php artisan phase4a-gps-uat:setup first.');
        return 1;
    }

    $trip = \App\Models\Trip::where('bus_id', $bus->id)->where('status', 'ongoing')->latest('id')->first();
    if (! $trip) {
        $this->error('Phase 4A GPS UAT ongoing trip not found. Run php artisan phase4a-gps-uat:setup again.');
        return 1;
    }

    $lastGpsFixAt = match ($state) {
        'live', 'now' => now(),
        'stale' => now()->subSeconds(60),
        'offline' => now()->subSeconds(130),
        'unknown', 'null' => null,
        default => null,
    };

    if (! in_array($state, ['live', 'now', 'stale', 'offline', 'unknown', 'null'], true)) {
        $this->error('Invalid state. Use one of: live, stale, offline, unknown.');
        return 1;
    }

    $position = \App\Models\VehiclePosition::updateOrCreate(
        ['bus_id' => $bus->id],
        [
            'trip_id' => $trip->id,
            'lat' => $bus->lat,
            'lng' => $bus->lng,
            'heading' => 0,
            'speed' => $bus->speed ?: 0,
            'status' => 'UAT',
            'last_updated_at' => now(),
            'last_gps_fix_at' => $lastGpsFixAt,
            'gps_quality_state' => $lastGpsFixAt ? 'GOOD' : 'UNKNOWN',
            'gps_quality_reason' => $lastGpsFixAt ? 'phase4a_uat_manual_timestamp' : 'phase4a_uat_null_timestamp',
            'gps_quality_updated_at' => now(),
            'gps_fix_age_seconds' => $lastGpsFixAt ? $lastGpsFixAt->diffInSeconds(now()) : null,
        ]
    );

    $expected = match ($state) {
        'live', 'now' => 'LIVE',
        'stale' => 'STALE',
        'offline' => 'OFFLINE',
        default => 'UNKNOWN',
    };

    $this->info("Phase 4A GPS UAT freshness set to {$expected}.");
    $this->line('Bus: '.$bus->plate_number.' | Trip: '.$trip->id.' | VehiclePosition: '.$position->id);
    $this->line('last_gps_fix_at: '.($position->last_gps_fix_at?->toIso8601String() ?? 'NULL'));
    $this->line('Refresh /commuter/tracker and find PHASE4A-GPS-UAT-BUS.');

    return 0;
})->purpose('Set the Phase 4A GPS freshness UAT last_gps_fix_at timestamp');

Artisan::command('phase4b-eta-uat:cleanup {--confirm : Delete the disposable Phase 4B ETA provenance UAT records}', function () {
    $busPlate = 'PHASE4B-ETA-UAT-BUS';
    $driverEmpId = 'PHASE4B-ETA-UAT-DRIVER';
    $driverEmail = 'phase4b.eta.uat.driver@gopasig.test';

    $buses = \App\Models\Bus::where('plate_number', $busPlate)
        ->orWhere('fleet_number', 'PHASE4B-ETA-UAT-FLEET')
        ->get();
    $drivers = \App\Models\Driver::where('emp_id', $driverEmpId)
        ->orWhere('license_number', 'PHASE4B-ETA-UAT-LICENSE')
        ->get();
    $users = \App\Models\User::where('email', $driverEmail)->get();
    $tripQuery = \App\Models\Trip::query()
        ->where(function ($query) use ($buses, $drivers) {
            $query->whereIn('bus_id', $buses->pluck('id'))
                ->orWhereIn('driver_id', $drivers->pluck('id'));
        });

    $summary = [
        'users' => $users->count(),
        'drivers' => $drivers->count(),
        'buses' => $buses->count(),
        'trips' => (clone $tripQuery)->count(),
        'vehicle_positions' => \Illuminate\Support\Facades\DB::table('vehicle_positions')->whereIn('bus_id', $buses->pluck('id'))->count(),
        'trip_progresses' => \Illuminate\Support\Facades\Schema::hasTable('trip_progresses')
            ? \Illuminate\Support\Facades\DB::table('trip_progresses')->whereIn('trip_id', (clone $tripQuery)->pluck('id'))->count()
            : 0,
    ];

    $this->info('Phase 4B ETA provenance UAT cleanup target summary:');
    foreach ($summary as $label => $count) {
        $this->line("- {$label}: {$count}");
    }

    if (! $this->option('confirm')) {
        $this->warn('Dry-run only. Run php artisan phase4b-eta-uat:cleanup --confirm to delete these disposable records.');
        return 0;
    }

    \Illuminate\Support\Facades\DB::transaction(function () use ($buses, $drivers, $users, $tripQuery) {
        $busIds = $buses->pluck('id');
        $driverIds = $drivers->pluck('id');
        $userIds = $users->pluck('id');
        $tripIds = (clone $tripQuery)->pluck('id');

        if ($tripIds->isNotEmpty()) {
            foreach (['trip_progresses', 'trip_progress', 'stop_arrivals', 'route_deviations'] as $table) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    \Illuminate\Support\Facades\DB::table($table)->whereIn('trip_id', $tripIds)->delete();
                }
            }

            \App\Models\DispatchLog::whereIn('trip_id', $tripIds)->delete();
            \App\Models\GPSLog::whereIn('trip_id', $tripIds)->delete();
            \App\Models\Incident::whereIn('trip_id', $tripIds)->delete();
            \App\Models\Trip::whereIn('id', $tripIds)->delete();
        }

        if ($busIds->isNotEmpty()) {
            foreach (['vehicle_positions', 'geofence_transitions', 'bus_status_audit_log'] as $table) {
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    \Illuminate\Support\Facades\DB::table($table)->whereIn('bus_id', $busIds)->delete();
                }
            }

            \App\Models\Bus::whereIn('id', $busIds)->delete();
        }

        if ($driverIds->isNotEmpty()) {
            \App\Models\Driver::whereIn('id', $driverIds)->delete();
        }

        if ($userIds->isNotEmpty()) {
            \App\Models\User::whereIn('id', $userIds)->delete();
        }
    });

    $this->info('Disposable Phase 4B ETA provenance UAT records removed.');
    return 0;
})->purpose('Dry-run or remove isolated Phase 4B ETA provenance UAT data');

Artisan::command('phase4b-eta-uat:setup {--route=Route 1 : Canonical public route name to use: Route 1, Route 2, or Route 3} {--variant= : Existing route_variant id to use instead of the route default}', function () {
    if (app()->environment('production')) {
        $this->error('Refusing to create Phase 4B ETA provenance UAT data in production.');
        return 1;
    }

    $this->call('phase4b-eta-uat:cleanup', ['--confirm' => true]);

    $routeName = trim((string) $this->option('route')) ?: 'Route 1';
    $canonicalRoutes = \App\Models\Route::getCanonicalProductionCached();
    $route = $canonicalRoutes->firstWhere('name', $routeName);

    if (! $route) {
        $this->error("Route '{$routeName}' is not one of the current canonical public routes: ".$canonicalRoutes->pluck('name')->join(', '));
        return 1;
    }

    $variantQuery = \App\Models\RouteVariant::where('route_id', $route->id);
    $variant = $this->option('variant')
        ? (clone $variantQuery)->whereKey((int) $this->option('variant'))->first()
        : (clone $variantQuery)->where('is_default', true)->orderBy('id')->first();
    $variant ??= (clone $variantQuery)->orderBy('id')->first();

    if (! $variant) {
        $this->error("Route '{$route->name}' has no existing RouteVariant. No UAT data was created.");
        return 1;
    }

    $now = now();
    $lat = (float) \App\Models\SystemSetting::get('map_default_latitude', 14.5690);
    $lng = (float) \App\Models\SystemSetting::get('map_default_longitude', 121.0850);

    $uat = \Illuminate\Support\Facades\DB::transaction(function () use ($route, $variant, $now, $lat, $lng) {
        $user = \App\Models\User::create([
            'name' => 'Phase4B ETA UAT Driver',
            'email' => 'phase4b.eta.uat.driver@gopasig.test',
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'driver',
        ]);

        $driver = \App\Models\Driver::create([
            'user_id' => $user->id,
            'first_name' => 'Phase4B ETA',
            'last_name' => 'UAT Driver',
            'emp_id' => 'PHASE4B-ETA-UAT-DRIVER',
            'license_number' => 'PHASE4B-ETA-UAT-LICENSE',
            'license_expiry' => $now->copy()->addYear(),
            'status' => 'active',
            'operational_status' => 'driving',
            'assigned_bus' => 'PHASE4B-ETA-UAT-BUS',
            'assigned_route' => $route->id,
            'trips_today' => 0,
            'pax_today' => 0,
        ]);

        $bus = \App\Models\Bus::create([
            'plate_number' => 'PHASE4B-ETA-UAT-BUS',
            'fleet_number' => 'PHASE4B-ETA-UAT-FLEET',
            'vin' => 'PHASE4BETAUAT0001',
            'manufacturer' => 'UAT',
            'model' => 'Phase4B ETA Provenance Harness',
            'year_model' => 2026,
            'route_id' => $route->id,
            'driver_name' => $driver->name,
            'capacity' => 45,
            'speed' => 0,
            'passengers' => 0,
            'next_stop' => $variant->origin_name ?: 'Phase 4B UAT Position',
            'eta' => 5,
            'lat' => $lat,
            'lng' => $lng,
            'status' => \App\Models\Bus::STATUS_ACTIVE,
            'is_simulated' => false,
        ]);

        $trip = \App\Models\Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'started_at' => $now,
            'dispatched_at' => $now,
            'gps_session_started_at' => $now,
            'peak_passengers' => 0,
        ]);

        $position = \App\Models\VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => $lat,
            'lng' => $lng,
            'heading' => 0,
            'speed' => 0,
            'status' => 'UAT',
            'last_updated_at' => $now,
            'last_gps_fix_at' => $now,
            'gps_quality_state' => 'GOOD',
            'gps_quality_reason' => 'phase4b_eta_uat_manual_freshness',
            'gps_quality_updated_at' => $now,
            'gps_fix_age_seconds' => 0,
        ]);

        return compact('user', 'driver', 'bus', 'trip', 'position');
    });

    $this->info('Phase 4B ETA provenance UAT harness created.');
    $this->line("Route: {$route->id} {$route->name}");
    $this->line("Existing RouteVariant: {$variant->id} {$variant->direction} | {$variant->origin_name} -> {$variant->destination_name}");
    $this->line("Bus: {$uat['bus']->id} {$uat['bus']->plate_number} eta={$uat['bus']->eta} status={$uat['bus']->status}");
    $this->line("Driver: {$uat['driver']->id} {$uat['driver']->name}");
    $this->line("User: {$uat['user']->id} {$uat['user']->email}");
    $this->line("Trip: {$uat['trip']->id} status={$uat['trip']->status} gps_session={$uat['trip']->gps_session} route_variant_id={$uat['trip']->route_variant_id}");
    $this->line("VehiclePosition: {$uat['position']->id} last_gps_fix_at={$uat['position']->last_gps_fix_at?->toIso8601String()}");
    $this->newLine();
    $this->info('ETA toggle commands:');
    $this->line('php artisan phase4b-eta-uat:set fallback');
    $this->line('php artisan phase4b-eta-uat:set none');
    $this->newLine();
    $this->warn('Cleanup dry-run: php artisan phase4b-eta-uat:cleanup');
    $this->warn('Cleanup delete: php artisan phase4b-eta-uat:cleanup --confirm');

    return 0;
})->purpose('Create isolated Phase 4B ETA provenance UAT bus, driver, ongoing canonical trip, and vehicle position');

Artisan::command('phase4b-eta-uat:set {state : fallback|none}', function () {
    $state = strtolower((string) $this->argument('state'));

    if (! in_array($state, ['fallback', 'none'], true)) {
        $this->error('Invalid state. Use one of: fallback, none.');
        return 1;
    }

    $bus = \App\Models\Bus::where('plate_number', 'PHASE4B-ETA-UAT-BUS')->first();
    if (! $bus) {
        $this->error('Phase 4B ETA UAT bus not found. Run php artisan phase4b-eta-uat:setup first.');
        return 1;
    }

    $trip = \App\Models\Trip::where('bus_id', $bus->id)->where('status', 'ongoing')->latest('id')->first();
    if (! $trip) {
        $this->error('Phase 4B ETA UAT ongoing trip not found. Run php artisan phase4b-eta-uat:setup again.');
        return 1;
    }

    if (\Illuminate\Support\Facades\Schema::hasTable('trip_progresses')) {
        \Illuminate\Support\Facades\DB::table('trip_progresses')->where('trip_id', $trip->id)->delete();
    }

    $bus->update([
        'eta' => $state === 'fallback' ? 5 : null,
    ]);

    $position = \App\Models\VehiclePosition::updateOrCreate(
        ['bus_id' => $bus->id],
        [
            'trip_id' => $trip->id,
            'lat' => $bus->lat,
            'lng' => $bus->lng,
            'heading' => 0,
            'speed' => $bus->speed ?: 0,
            'status' => 'UAT',
            'last_updated_at' => now(),
            'last_gps_fix_at' => now(),
            'gps_quality_state' => 'GOOD',
            'gps_quality_reason' => 'phase4b_eta_uat_manual_freshness',
            'gps_quality_updated_at' => now(),
            'gps_fix_age_seconds' => 0,
        ]
    );

    $this->info("Phase 4B ETA UAT state set to {$state}.");
    $this->line('Bus: '.$bus->fresh()->plate_number.' | Trip: '.$trip->id.' | VehiclePosition: '.$position->id);
    $this->line('buses.eta: '.($bus->fresh()->eta ?? 'NULL'));
    $this->line('last_gps_fix_at: '.$position->fresh()->last_gps_fix_at?->toIso8601String());

    if ($state === 'fallback') {
        $this->line('Expected public ETA: Next stop: ~5 min, labeled legacy fallback/non-authoritative.');
    } else {
        $this->line('Expected public ETA: ETA unavailable - official route data pending, when canonical geometry remains pending.');
    }

    return 0;
})->purpose('Toggle the Phase 4B ETA provenance UAT bus between legacy fallback and no ETA');
