<?php

namespace Tests\Feature;

use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\Stop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CommuterCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_cleanup_orphaned_trips_command_cancels_expired_sessions_trips(): void
    {
        // Setup route and stops
        $route = Route::create([
            'id' => 1,
            'name' => 'Route A',
            'travel_time_minutes' => 30,
            'status' => 'active'
        ]);

        $stop1 = Stop::create([
            'id' => 1,
            'route_id' => $route->id,
            'name' => 'Stop 1',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 1
        ]);

        $stop2 = Stop::create([
            'id' => 2,
            'route_id' => $route->id,
            'name' => 'Stop 2',
            'lat' => 14.6,
            'lng' => 121.1,
            'sequence' => 2
        ]);

        // 1. Active Session & Active Trip
        $activeToken = 'active-session-token-12345';
        CommuterSession::create([
            'session_token' => $activeToken,
            'ip_address' => '127.0.0.1',
            'expires_at' => now()->addHours(12),
        ]);

        $activeTrip = CommuterTrip::create([
            'session_token' => $activeToken,
            'origin_stop_id' => $stop1->id,
            'destination_stop_id' => $stop2->id,
            'route_id' => $route->id,
            'status' => 'WAITING',
        ]);

        // 2. Expired Session & WAITING Trip
        $expiredToken = 'expired-session-token-67890';
        CommuterSession::create([
            'session_token' => $expiredToken,
            'ip_address' => '127.0.0.1',
            'expires_at' => now()->subMinutes(10), // Expired 10 minutes ago
        ]);

        $expiredTrip = CommuterTrip::create([
            'session_token' => $expiredToken,
            'origin_stop_id' => $stop1->id,
            'destination_stop_id' => $stop2->id,
            'route_id' => $route->id,
            'status' => 'WAITING',
        ]);

        // Run the command
        Artisan::call('trips:cleanup-orphaned');

        // Verify active trip remains unchanged
        $activeTrip->refresh();
        $this->assertEquals('WAITING', $activeTrip->status);

        // Verify expired trip is updated to CANCELLED
        $expiredTrip->refresh();
        $this->assertEquals('CANCELLED', $expiredTrip->status);

        // Verify expired session still exists in the database
        $this->assertDatabaseHas('commuter_sessions', [
            'session_token' => $expiredToken
        ]);

        // Verify expired trip still exists in the database as CANCELLED
        $this->assertDatabaseHas('commuter_trips', [
            'id' => $expiredTrip->id,
            'status' => 'CANCELLED'
        ]);
    }
}
