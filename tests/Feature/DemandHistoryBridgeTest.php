<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\DemandHistory;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Models\TimeSlotConfiguration;
use App\Services\Commuter\CommuterJourneyCoordinator;
use App\Services\DemandHistoryBridgeService;
use App\Services\SimulationDispatchService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DemandHistoryBridgeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        TimeSlotConfiguration::create([
            'name' => 'Hour 08-09',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'time_slot_display' => '08:00-09:00',
            'order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_real_commuter_check_in_creates_one_localized_history_bucket(): void
    {
        [$route, $origin, $destination] = $this->officialRouteWithStops();
        $this->variantFor($route, $origin, $destination);
        $token = 'dhb-real-check-in';
        CommuterSession::create([
            'session_token' => $token,
            'expires_at' => now()->addDay(),
        ]);

        $createdAt = CarbonImmutable::create(2026, 8, 10, 0, 15, 0, 'UTC');
        Carbon::setTestNow($createdAt);

        try {
            $trip = app(CommuterJourneyCoordinator::class)
                ->initializeWaitingJourney($token, $origin->id, $destination->id);
        } finally {
            Carbon::setTestNow();
        }

        $this->assertFalse($trip->fresh()->is_simulated);
        $history = DemandHistory::query()
            ->where('route_id', $route->id)
            ->whereDate('date', '2026-08-10')
            ->where('time_slot', '08:00-09:00')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame('Monday', $history->day_of_week);
        $this->assertSame(1, $history->total_commuters);
        $this->assertSame(0, $history->buses_dispatched);

        app(DemandHistoryBridgeService::class)->recordCommuterCheckIn($trip->fresh());

        $this->assertDatabaseCount('demand_history', 1);
        $this->assertSame(
            1,
            DemandHistory::query()
                ->where('route_id', $route->id)
                ->whereDate('date', '2026-08-10')
                ->where('time_slot', '08:00-09:00')
                ->value('total_commuters')
        );
    }

    public function test_simulated_and_non_official_commuter_trips_do_not_enter_history(): void
    {
        [$route, $origin, $destination] = $this->officialRouteWithStops();
        $legacyRoute = Route::create([
            'name' => 'Route A',
            'description' => 'Legacy route',
            'status' => 'Active',
        ]);
        $legacyOrigin = Stop::create([
            'route_id' => $legacyRoute->id,
            'name' => 'Legacy Origin',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 1,
        ]);
        $legacyDestination = Stop::create([
            'route_id' => $legacyRoute->id,
            'name' => 'Legacy Destination',
            'lat' => 14.6,
            'lng' => 121.1,
            'sequence' => 2,
        ]);

        $createdAt = CarbonImmutable::create(2026, 8, 10, 0, 20, 0, 'UTC');
        $realToken = 'dhb-sim-real';
        $simulatedToken = 'dhb-sim-simulated';
        $legacyToken = 'dhb-sim-legacy';

        foreach ([$realToken, $simulatedToken, $legacyToken] as $token) {
            CommuterSession::create([
                'session_token' => $token,
                'expires_at' => now()->addDay(),
            ]);
        }

        $realTrip = CommuterTrip::create([
            'session_token' => $realToken,
            'route_id' => $route->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'status' => 'WAITING',
            'is_simulated' => false,
            'created_at' => $createdAt,
        ]);
        $realTrip->forceFill(['created_at' => $createdAt])->save();
        $simulatedTrip = CommuterTrip::create([
            'session_token' => $simulatedToken,
            'route_id' => $route->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'status' => 'WAITING',
            'is_simulated' => true,
            'created_at' => $createdAt,
        ]);
        $simulatedTrip->forceFill(['created_at' => $createdAt])->save();
        $legacyTrip = CommuterTrip::create([
            'session_token' => $legacyToken,
            'route_id' => $legacyRoute->id,
            'origin_stop_id' => $legacyOrigin->id,
            'destination_stop_id' => $legacyDestination->id,
            'status' => 'WAITING',
            'is_simulated' => false,
            'created_at' => $createdAt,
        ]);
        $legacyTrip->forceFill(['created_at' => $createdAt])->save();

        $bridge = app(DemandHistoryBridgeService::class);
        $bridge->recordCommuterCheckIn($realTrip->fresh());
        $this->assertNull($bridge->recordCommuterCheckIn($simulatedTrip));
        $this->assertNull($bridge->recordCommuterCheckIn($legacyTrip));

        $this->assertDatabaseCount('demand_history', 1);
        $this->assertDatabaseHas('demand_history', [
            'route_id' => $route->id,
            'total_commuters' => 1,
        ]);
    }

    public function test_dispatch_flow_refreshes_actual_buses_dispatched_in_the_same_bucket(): void
    {
        [$route, $origin, $destination] = $this->officialRouteWithStops();
        $variant = $this->variantFor($route, $origin, $destination);
        $dispatchAt = CarbonImmutable::create(2026, 8, 10, 0, 30, 0, 'UTC');
        Carbon::setTestNow($dispatchAt);

        try {
            SimulationDispatchService::dispatch(
                Bus::factory()->create(['status' => 'inactive']),
                Driver::factory()->create([
                    'status' => 'active',
                    'operational_status' => 'available',
                ]),
                $route,
                null,
                'Demand history bridge test.',
                $variant
            );
            SimulationDispatchService::dispatch(
                Bus::factory()->create(['status' => 'inactive']),
                Driver::factory()->create([
                    'status' => 'active',
                    'operational_status' => 'available',
                ]),
                $route,
                null,
                'Demand history bridge test.',
                $variant
            );
        } finally {
            Carbon::setTestNow();
        }

        $history = DemandHistory::query()
            ->where('route_id', $route->id)
            ->whereDate('date', '2026-08-10')
            ->where('time_slot', '08:00-09:00')
            ->first();

        $this->assertNotNull($history);
        $this->assertSame(0, $history->total_commuters);
        $this->assertSame(2, $history->buses_dispatched);
        $this->assertDatabaseCount('demand_history', 1);
    }

    private function officialRouteWithStops(): array
    {
        $route = Route::create([
            'name' => 'Route 2',
            'description' => 'SPED to Ligaya',
            'status' => 'Active',
            'color' => '#BA7517',
        ]);
        $origin = Stop::create([
            'route_id' => $route->id,
            'name' => 'SPED',
            'lat' => 14.5602934,
            'lng' => 121.0797616,
            'sequence' => 1,
        ]);
        $destination = Stop::create([
            'route_id' => $route->id,
            'name' => 'Ligaya',
            'lat' => 14.6185612,
            'lng' => 121.0925442,
            'sequence' => 2,
        ]);

        return [$route, $origin, $destination];
    }

    private function variantFor(Route $route, Stop $origin, Stop $destination): RouteVariant
    {
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => $origin->name,
            'destination_name' => $destination->name,
            'polyline_coordinates' => [
                [(float) $origin->lat, (float) $origin->lng],
                [(float) $destination->lat, (float) $destination->lng],
            ],
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);

        RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'canonical_stop_id' => $origin->id,
            'name' => $origin->name,
            'lat' => $origin->lat,
            'lng' => $origin->lng,
            'sequence' => 1,
        ]);
        RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'canonical_stop_id' => $destination->id,
            'name' => $destination->name,
            'lat' => $destination->lat,
            'lng' => $destination->lng,
            'sequence' => 2,
        ]);

        return $variant;
    }
}
