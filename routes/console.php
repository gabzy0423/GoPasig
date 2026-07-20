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
