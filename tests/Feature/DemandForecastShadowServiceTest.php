<?php

namespace Tests\Feature;

use App\Models\DemandForecastSnapshot;
use App\Models\DemandHistory;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\SystemSetting;
use App\Models\TimeSlotConfiguration;
use App\Services\DemandForecastShadowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class DemandForecastShadowServiceTest extends TestCase
{
    use RefreshDatabase;

    private Route $route;

    private RouteVariant $outbound;

    private RouteVariant $inbound;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-02 08:00:00', 'Asia/Manila'));

        foreach ([
            'demand_forecast_lookback_weeks' => '8',
            'demand_forecast_minimum_samples' => '3',
            'default_bus_capacity' => '45',
        ] as $key => $value) {
            SystemSetting::create(compact('key', 'value'));
        }

        TimeSlotConfiguration::create([
            'name' => 'Morning shadow slot',
            'start_time' => '08:00:00',
            'end_time' => '09:00:00',
            'time_slot_display' => '08:00-09:00',
            'order' => 1,
            'is_active' => true,
        ]);

        $this->route = Route::factory()->create([
            'name' => 'Route 2',
            'status' => 'Active',
        ]);
        $this->outbound = $this->createVariant('outbound', 'SPED', 'Ligaya', true);
        $this->inbound = $this->createVariant('inbound', 'Ligaya', 'SPED', false);

        foreach ([$this->outbound, $this->inbound] as $variant) {
            RouteServiceSchedule::create([
                'route_id' => $this->route->id,
                'route_variant_id' => $variant->id,
                'first_trip_time' => '06:00:00',
                'last_trip_time' => '09:00:00',
                'service_configuration' => 'with_designated_stops',
                'service_days' => ['mon'],
                'is_active' => true,
                'source' => RouteServiceSchedule::SOURCE_BENEFICIARY_OFFICIAL,
            ]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_capture_is_direction_aware_immutable_and_has_no_dispatch_side_effect(): void
    {
        foreach ([10, 20, 30] as $index => $commuters) {
            $date = ['2026-07-13', '2026-07-20', '2026-07-27'][$index];
            $this->createHistory($date, $this->outbound, $commuters, true);
            $this->createHistory($date, $this->inbound, $commuters + 15, true);
        }

        $shadow = app(DemandForecastShadowService::class);
        $first = $shadow->capture('2026-08-03');

        $this->assertSame(2, $first['captured']);
        $this->assertSame(2, $first['ready']);
        $this->assertTrue($first['advisory_only']);
        $this->assertDatabaseHas('demand_forecast_snapshots', [
            'target_date' => '2026-08-03',
            'route_variant_id' => $this->outbound->id,
            'expected_commuters' => 20.0,
            'forecast_status' => DemandForecastSnapshot::STATUS_READY,
            'advisory_only' => true,
        ]);
        $this->assertDatabaseHas('demand_forecast_snapshots', [
            'target_date' => '2026-08-03',
            'route_variant_id' => $this->inbound->id,
            'expected_commuters' => 35.0,
        ]);

        DemandHistory::query()
            ->where('route_variant_id', $this->outbound->id)
            ->whereDate('date', '2026-07-27')
            ->update(['total_commuters' => 300]);

        $second = $shadow->capture('2026-08-03');

        $this->assertSame(0, $second['captured']);
        $this->assertSame(2, $second['existing_skipped']);
        $this->assertSame(
            20.0,
            DemandForecastSnapshot::where('route_variant_id', $this->outbound->id)
                ->value('expected_commuters')
        );
        $this->assertDatabaseCount('demand_forecast_snapshots', 2);
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('dispatch_logs', 0);
    }

    public function test_capture_rejects_same_day_or_historical_hindsight(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('must be captured before its target date');

        app(DemandForecastShadowService::class)->capture('2026-08-02');
    }

    public function test_evaluation_uses_exact_finalized_direction_bucket_and_calculates_error(): void
    {
        foreach ([10, 20, 30] as $index => $commuters) {
            $date = ['2026-07-13', '2026-07-20', '2026-07-27'][$index];
            $this->createHistory($date, $this->outbound, $commuters, true);
            $this->createHistory($date, $this->inbound, 50, true);
        }

        $shadow = app(DemandForecastShadowService::class);
        $shadow->capture('2026-08-03');

        Carbon::setTestNow(Carbon::parse('2026-08-03 10:05:00', 'Asia/Manila'));
        $this->createHistory('2026-08-03', $this->outbound, 26, false);
        $summary = $shadow->evaluate('2026-08-03');
        $outbound = DemandForecastSnapshot::where('route_variant_id', $this->outbound->id)->firstOrFail();
        $inbound = DemandForecastSnapshot::where('route_variant_id', $this->inbound->id)->firstOrFail();

        $this->assertSame(1, $summary['evaluated']);
        $this->assertSame(1, $summary['pending_actual']);
        $this->assertSame(26, $outbound->actual_commuters);
        $this->assertSame(6.0, $outbound->error_delta);
        $this->assertSame(6.0, $outbound->absolute_error);
        $this->assertSame(23.08, $outbound->percentage_error);
        $this->assertNotNull($outbound->evaluated_at);
        $this->assertNull($inbound->actual_commuters);

        $dashboard = $shadow->dashboard();
        $this->assertSame(1, $dashboard['summary']['evaluated']);
        $this->assertSame(6.0, $dashboard['summary']['mean_absolute_error']);
        $this->assertSame(23.1, $dashboard['summary']['mean_absolute_percentage_error']);
        $this->assertSame('evaluated', collect($dashboard['rows'])->firstWhere('route_variant_id', $this->outbound->id)['status']);
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_insufficient_forecast_is_not_zero_and_can_record_actual_without_accuracy_claim(): void
    {
        $this->createHistory('2026-07-20', $this->outbound, 0, true);
        $this->createHistory('2026-07-27', $this->outbound, 0, true);

        $shadow = app(DemandForecastShadowService::class);
        $shadow->capture('2026-08-03');
        $snapshot = DemandForecastSnapshot::where('route_variant_id', $this->outbound->id)->firstOrFail();

        $this->assertSame(DemandForecastSnapshot::STATUS_INSUFFICIENT_HISTORY, $snapshot->forecast_status);
        $this->assertNull($snapshot->expected_commuters);

        Carbon::setTestNow(Carbon::parse('2026-08-03 10:05:00', 'Asia/Manila'));
        $this->createHistory('2026-08-03', $this->outbound, 0, false);
        $summary = $shadow->evaluate('2026-08-03');
        $snapshot->refresh();

        $this->assertSame(1, $summary['actual_without_forecast']);
        $this->assertSame(0, $snapshot->actual_commuters);
        $this->assertNull($snapshot->absolute_error);
        $this->assertNull($snapshot->evaluated_at);
        $this->assertSame(
            'actual_without_forecast',
            collect($shadow->dashboard()['rows'])->firstWhere('route_variant_id', $this->outbound->id)['status']
        );
    }

    public function test_artisan_commands_report_advisory_only_and_never_create_trips(): void
    {
        $this->artisan('demand-forecast:capture', ['date' => '2026-08-03'])
            ->expectsOutputToContain('Advisory only')
            ->assertSuccessful();

        Carbon::setTestNow(Carbon::parse('2026-08-04 00:30:00', 'Asia/Manila'));
        $this->artisan('demand-forecast:evaluate', ['date' => '2026-08-03'])
            ->expectsOutputToContain('Advisory only')
            ->assertSuccessful();

        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('dispatch_logs', 0);
    }

    private function createVariant(
        string $direction,
        string $origin,
        string $destination,
        bool $default
    ): RouteVariant {
        return RouteVariant::create([
            'route_id' => $this->route->id,
            'direction' => $direction,
            'origin_name' => $origin,
            'destination_name' => $destination,
            'geometry_status' => 'valid',
            'is_default' => $default,
        ]);
    }

    private function createHistory(
        string $date,
        RouteVariant $variant,
        int $commuters,
        bool $trainingEligible
    ): DemandHistory {
        return DemandHistory::create([
            'route_id' => $this->route->id,
            'route_variant_id' => $variant->id,
            'date' => $date,
            'time_slot' => '08:00-09:00',
            'day_of_week' => 'Monday',
            'total_commuters' => $commuters,
            'buses_dispatched' => 0,
            'source' => DemandHistory::SOURCE_ACTUAL_REBUILD,
            'is_training_eligible' => $trainingEligible,
            'finalized_at' => Carbon::parse($date.' 09:05:00', 'Asia/Manila')->utc(),
        ]);
    }
}
