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
use App\Services\DemandHistoryRebuildService;
use App\Services\SimulationDispatchService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule as ConsoleSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;
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
        $this->assertSame($trip->route_variant_id, $history->route_variant_id);
        $this->assertSame('Monday', $history->day_of_week);
        $this->assertSame(1, $history->total_commuters);
        $this->assertSame(0, $history->buses_dispatched);
        $this->assertSame(DemandHistory::SOURCE_ACTUAL_RUNTIME, $history->source);
        $this->assertFalse($history->is_training_eligible);
        $this->assertNull($history->finalized_at);

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
        $variant = $this->variantFor($route, $origin, $destination);
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
            'route_variant_id' => $variant->id,
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
            'route_variant_id' => $variant->id,
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
            'route_variant_id' => $variant->id,
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
        $this->assertSame($variant->id, $history->route_variant_id);
        $this->assertSame(0, $history->total_commuters);
        $this->assertSame(2, $history->buses_dispatched);
        $this->assertSame(DemandHistory::SOURCE_ACTUAL_RUNTIME, $history->source);
        $this->assertFalse($history->is_training_eligible);
        $this->assertDatabaseCount('demand_history', 1);
    }

    public function test_outbound_and_inbound_are_separate_idempotent_buckets(): void
    {
        [$route, $sped, $ligaya] = $this->officialRouteWithStops();
        $outbound = $this->variantFor($route, $sped, $ligaya, 'outbound');
        $inbound = $this->variantFor($route, $ligaya, $sped, 'inbound');
        $createdAt = CarbonImmutable::create(2026, 8, 10, 0, 20, 0, 'UTC');

        $this->createCommuterTrip('direction-outbound-1', $route, $outbound, $sped, $ligaya, $createdAt);
        $outboundSecond = $this->createCommuterTrip('direction-outbound-2', $route, $outbound, $sped, $ligaya, $createdAt);
        $inboundTrip = $this->createCommuterTrip('direction-inbound-1', $route, $inbound, $ligaya, $sped, $createdAt);

        $bridge = app(DemandHistoryBridgeService::class);
        $bridge->recordCommuterCheckIn($outboundSecond);
        $bridge->recordCommuterCheckIn($inboundTrip);
        $bridge->recordCommuterCheckIn($outboundSecond);

        $this->assertDatabaseCount('demand_history', 2);
        $this->assertDatabaseHas('demand_history', [
            'route_id' => $route->id,
            'route_variant_id' => $outbound->id,
            'total_commuters' => 2,
        ]);
        $this->assertDatabaseHas('demand_history', [
            'route_id' => $route->id,
            'route_variant_id' => $inbound->id,
            'total_commuters' => 1,
        ]);
    }

    public function test_repeated_journeys_from_one_session_count_once_per_direction_bucket(): void
    {
        [$route, $origin, $destination] = $this->officialRouteWithStops();
        $variant = $this->variantFor($route, $origin, $destination);
        $createdAt = CarbonImmutable::create(2026, 8, 10, 0, 20, 0, 'UTC');

        $first = $this->createCommuterTrip(
            'same-session-demand',
            $route,
            $variant,
            $origin,
            $destination,
            $createdAt
        );
        $this->createCommuterTrip(
            'same-session-demand',
            $route,
            $variant,
            $origin,
            $destination,
            $createdAt->addMinutes(5),
            false
        );

        $history = app(DemandHistoryBridgeService::class)->recordCommuterCheckIn($first);

        $this->assertNotNull($history);
        $this->assertSame(1, $history->total_commuters);
    }

    public function test_closed_bucket_rebuild_is_complete_idempotent_and_forecast_eligible(): void
    {
        [$route, $sped, $ligaya] = $this->officialRouteWithStops();
        $outbound = $this->variantFor($route, $sped, $ligaya, 'outbound');
        $inbound = $this->variantFor($route, $ligaya, $sped, 'inbound');
        $createdAt = CarbonImmutable::create(2026, 8, 10, 0, 20, 0, 'UTC');

        $this->createCommuterTrip('rebuild-outbound', $route, $outbound, $sped, $ligaya, $createdAt);

        $rebuild = app(DemandHistoryRebuildService::class);
        $firstSummary = $rebuild->rebuild(
            '2026-08-10',
            '2026-08-10',
            CarbonImmutable::create(2026, 8, 10, 9, 5, 0, 'Asia/Manila')
        );
        $this->assertSame(0, DemandHistory::forecastEligible()->count());

        $secondSummary = $rebuild->rebuild(
            '2026-08-10',
            '2026-08-10',
            CarbonImmutable::create(2026, 8, 10, 9, 5, 0, 'Asia/Manila'),
            true,
            true
        );
        $thirdSummary = $rebuild->rebuild(
            '2026-08-10',
            '2026-08-10',
            CarbonImmutable::create(2026, 8, 10, 9, 5, 0, 'Asia/Manila'),
            true,
            true
        );
        $rebuild->rebuild(
            '2026-08-10',
            '2026-08-10',
            CarbonImmutable::create(2026, 8, 10, 9, 5, 0, 'Asia/Manila')
        );

        $this->assertSame(2, $firstSummary['finalized']);
        $this->assertSame(2, $secondSummary['finalized']);
        $this->assertSame(2, $thirdSummary['skipped_existing']);
        $this->assertDatabaseCount('demand_history', 2);
        $this->assertSame(2, DemandHistory::forecastEligible()->count());
        $this->assertDatabaseHas('demand_history', [
            'route_variant_id' => $outbound->id,
            'total_commuters' => 1,
            'source' => DemandHistory::SOURCE_ACTUAL_REBUILD,
            'is_training_eligible' => true,
        ]);
        $this->assertDatabaseHas('demand_history', [
            'route_variant_id' => $inbound->id,
            'total_commuters' => 0,
            'source' => DemandHistory::SOURCE_ACTUAL_REBUILD,
            'is_training_eligible' => true,
        ]);
    }

    public function test_open_bucket_and_legacy_rows_are_not_forecast_eligible(): void
    {
        [$route, $origin, $destination] = $this->officialRouteWithStops();
        $this->variantFor($route, $origin, $destination);

        $legacy = DemandHistory::create([
            'route_id' => $route->id,
            'date' => '2026-08-10',
            'time_slot' => '08:00-09:00',
            'day_of_week' => 'Monday',
            'total_commuters' => 99,
            'buses_dispatched' => 1,
        ]);

        $summary = app(DemandHistoryRebuildService::class)->rebuild(
            '2026-08-10',
            '2026-08-10',
            CarbonImmutable::create(2026, 8, 10, 8, 30, 0, 'Asia/Manila')
        );

        $this->assertSame(DemandHistory::SOURCE_LEGACY_UNKNOWN, $legacy->source);
        $this->assertFalse($legacy->is_training_eligible);
        $this->assertSame(1, $summary['skipped_open']);
        $this->assertSame(0, DemandHistory::forecastEligible()->count());
        $this->assertDatabaseCount('demand_history', 1);
    }

    public function test_demand_history_rejects_a_variant_from_another_route(): void
    {
        [$route, $origin, $destination] = $this->officialRouteWithStops();
        $otherRoute = Route::create([
            'name' => 'Route 3',
            'description' => 'SPED to One San Miguel',
            'status' => 'Active',
        ]);
        $otherVariant = RouteVariant::create([
            'route_id' => $otherRoute->id,
            'direction' => 'outbound',
            'origin_name' => $origin->name,
            'destination_name' => $destination->name,
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Demand history RouteVariant must belong to the selected Route.');

        DemandHistory::create([
            'route_id' => $route->id,
            'route_variant_id' => $otherVariant->id,
            'date' => '2026-08-10',
            'time_slot' => '08:00-09:00',
            'day_of_week' => 'Monday',
            'total_commuters' => 1,
            'buses_dispatched' => 0,
            'source' => DemandHistory::SOURCE_ACTUAL_RUNTIME,
        ]);
    }

    public function test_late_runtime_event_demotes_a_finalized_bucket_until_rebuilt(): void
    {
        [$route, $origin, $destination] = $this->officialRouteWithStops();
        $variant = $this->variantFor($route, $origin, $destination);
        $createdAt = CarbonImmutable::create(2026, 8, 10, 0, 20, 0, 'UTC');
        $first = $this->createCommuterTrip(
            'late-event-first',
            $route,
            $variant,
            $origin,
            $destination,
            $createdAt
        );
        $bridge = app(DemandHistoryBridgeService::class);

        $bridge->finalizeBucket(
            $route->id,
            $variant->id,
            $createdAt,
            CarbonImmutable::create(2026, 8, 10, 9, 0, 0, 'Asia/Manila'),
            true
        );
        $this->assertSame(1, DemandHistory::forecastEligible()->count());

        $late = $this->createCommuterTrip(
            'late-event-second',
            $route,
            $variant,
            $origin,
            $destination,
            $createdAt->addMinutes(10)
        );
        $history = $bridge->recordCommuterCheckIn($late);

        $this->assertNotNull($history);
        $this->assertSame(2, $history->total_commuters);
        $this->assertSame(DemandHistory::SOURCE_ACTUAL_RUNTIME, $history->source);
        $this->assertFalse($history->is_training_eligible);
        $this->assertNull($history->finalized_at);
        $this->assertSame(0, DemandHistory::forecastEligible()->count());
    }

    public function test_direction_history_rebuild_remains_scheduled_hourly_at_minute_five(): void
    {
        $event = collect(app(ConsoleSchedule::class)->events())
            ->first(fn ($scheduledEvent) => str_contains(
                $scheduledEvent->command,
                'demand-history:rebuild --only-unfinalized'
            ));

        $this->assertNotNull($event);
        $this->assertSame('5 * * * *', $event->expression);
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

    private function variantFor(
        Route $route,
        Stop $origin,
        Stop $destination,
        string $direction = 'outbound'
    ): RouteVariant
    {
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
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

    private function createCommuterTrip(
        string $token,
        Route $route,
        RouteVariant $variant,
        Stop $origin,
        Stop $destination,
        CarbonImmutable $createdAt,
        bool $createSession = true
    ): CommuterTrip {
        if ($createSession) {
            CommuterSession::firstOrCreate(
                ['session_token' => $token],
                ['expires_at' => now()->addDay()]
            );
        }

        $trip = CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'status' => 'WAITING',
            'is_simulated' => false,
        ]);
        $trip->forceFill(['created_at' => $createdAt])->save();

        return $trip->fresh();
    }
}
