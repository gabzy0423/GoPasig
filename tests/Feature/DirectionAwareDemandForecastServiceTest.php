<?php

namespace Tests\Feature;

use App\Models\DemandHistory;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\SystemSetting;
use App\Models\TimeSlotConfiguration;
use App\Services\DirectionAwareDemandForecastService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectionAwareDemandForecastServiceTest extends TestCase
{
    use RefreshDatabase;

    private Route $route;

    private RouteVariant $outbound;

    private RouteVariant $inbound;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::create([
            'key' => 'demand_forecast_lookback_weeks',
            'value' => '8',
        ]);
        SystemSetting::create([
            'key' => 'demand_forecast_minimum_samples',
            'value' => '3',
        ]);
        SystemSetting::create([
            'key' => 'default_bus_capacity',
            'value' => '45',
        ]);

        foreach ([
            ['05:00:00', '06:00:00', '05:00-06:00'],
            ['08:00:00', '09:00:00', '08:00-09:00'],
            ['09:00:00', '10:00:00', '09:00-10:00'],
            ['12:00:00', '13:00:00', '12:00-13:00'],
            ['15:00:00', '16:00:00', '15:00-16:00'],
            ['17:00:00', '18:00:00', '17:00-18:00'],
        ] as $index => [$start, $end, $label]) {
            TimeSlotConfiguration::create([
                'name' => 'Slot '.$label,
                'start_time' => $start,
                'end_time' => $end,
                'time_slot_display' => $label,
                'order' => $index + 1,
                'is_active' => true,
            ]);
        }

        $this->route = Route::factory()->create([
            'name' => 'Route 2',
            'status' => 'Active',
        ]);
        $this->outbound = RouteVariant::create([
            'route_id' => $this->route->id,
            'direction' => 'outbound',
            'origin_name' => 'SPED',
            'destination_name' => 'Ligaya',
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);
        $this->inbound = RouteVariant::create([
            'route_id' => $this->route->id,
            'direction' => 'inbound',
            'origin_name' => 'Ligaya',
            'destination_name' => 'SPED',
            'geometry_status' => 'valid',
            'is_default' => false,
        ]);

        foreach ([
            [$this->outbound, '05:30:00', '09:00:00'],
            [$this->outbound, '15:00:00', '17:00:00'],
            [$this->inbound, '06:00:00', '09:00:00'],
            [$this->inbound, '15:00:00', '18:00:00'],
        ] as [$variant, $firstTrip, $lastTrip]) {
            RouteServiceSchedule::create([
                'route_id' => $this->route->id,
                'route_variant_id' => $variant->id,
                'first_trip_time' => $firstTrip,
                'last_trip_time' => $lastTrip,
                'service_configuration' => 'with_designated_stops',
                'service_days' => ['mon'],
                'is_active' => true,
                'source' => RouteServiceSchedule::SOURCE_BENEFICIARY_OFFICIAL,
            ]);
        }
    }

    public function test_forecast_uses_only_matching_direction_finalized_history(): void
    {
        foreach ([
            ['2026-07-20', $this->outbound, 9],
            ['2026-07-27', $this->outbound, 18],
            ['2026-08-03', $this->outbound, 27],
            ['2026-07-20', $this->inbound, 30],
            ['2026-07-27', $this->inbound, 30],
            ['2026-08-03', $this->inbound, 30],
        ] as [$date, $variant, $commuters]) {
            $this->createTrustedHistory($date, $variant, '08:00-09:00', $commuters);
        }

        DemandHistory::create([
            'route_id' => $this->route->id,
            'date' => '2026-08-03',
            'time_slot' => '08:00-09:00',
            'day_of_week' => 'Monday',
            'total_commuters' => 999,
            'buses_dispatched' => 8,
        ]);
        DemandHistory::create([
            'route_id' => $this->route->id,
            'route_variant_id' => $this->outbound->id,
            'date' => '2026-07-13',
            'time_slot' => '08:00-09:00',
            'day_of_week' => 'Monday',
            'total_commuters' => 999,
            'buses_dispatched' => 8,
            'source' => DemandHistory::SOURCE_ACTUAL_RUNTIME,
            'is_training_eligible' => false,
        ]);

        $forecast = app(DirectionAwareDemandForecastService::class)
            ->forecastForDate('2026-08-10');
        $rows = collect($forecast['rows']);
        $outbound = $rows->first(fn (array $row) => $row['route_variant_id'] === $this->outbound->id
            && $row['time_slot'] === '08:00-09:00');
        $inbound = $rows->first(fn (array $row) => $row['route_variant_id'] === $this->inbound->id
            && $row['time_slot'] === '08:00-09:00');

        $this->assertSame(18.0, $outbound['expected_commuters']);
        $this->assertSame(3, $outbound['sample_count']);
        $this->assertSame('low', $outbound['confidence']);
        $this->assertSame(1, $outbound['minimum_buses']);
        $this->assertSame(30.0, $inbound['expected_commuters']);
        $this->assertSame(3, $inbound['sample_count']);
        $this->assertTrue($forecast['advisory_only']);
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_forecast_only_exposes_slots_that_overlap_official_direction_windows(): void
    {
        $forecast = app(DirectionAwareDemandForecastService::class)
            ->forecastForDate('2026-08-10');
        $rows = collect($forecast['rows']);
        $outboundSlots = $rows->where('route_variant_id', $this->outbound->id)->pluck('time_slot')->all();
        $inboundSlots = $rows->where('route_variant_id', $this->inbound->id)->pluck('time_slot')->all();
        $firstOutbound = $rows->first(fn (array $row) => $row['route_variant_id'] === $this->outbound->id
            && $row['time_slot'] === '05:00-06:00');

        $this->assertSame(['05:00-06:00', '08:00-09:00', '15:00-16:00'], $outboundSlots);
        $this->assertSame(['08:00-09:00', '15:00-16:00', '17:00-18:00'], $inboundSlots);
        $this->assertSame(['05:30-06:00'], $firstOutbound['service_periods']);
        $this->assertNotContains('09:00-10:00', $outboundSlots);
        $this->assertNotContains('12:00-13:00', $inboundSlots);
    }

    public function test_insufficient_samples_are_not_published_as_zero_demand(): void
    {
        $this->createTrustedHistory('2026-07-27', $this->outbound, '08:00-09:00', 0);
        $this->createTrustedHistory('2026-08-03', $this->outbound, '08:00-09:00', 0);

        $forecast = app(DirectionAwareDemandForecastService::class)
            ->forecastForDate('2026-08-10');
        $row = collect($forecast['rows'])->first(fn (array $candidate) => $candidate['route_variant_id'] === $this->outbound->id
            && $candidate['time_slot'] === '08:00-09:00');

        $this->assertSame('insufficient_history', $row['status']);
        $this->assertNull($row['expected_commuters']);
        $this->assertNull($row['minimum_buses']);
        $this->assertSame(2, $row['sample_count']);
    }

    public function test_day_without_official_service_returns_no_actionable_rows(): void
    {
        $forecast = app(DirectionAwareDemandForecastService::class)
            ->forecastForDate('2026-08-09');

        $this->assertSame([], $forecast['rows']);
        $this->assertSame('no_official_service', $forecast['overall_summary']['status']);
        $this->assertSame('no_official_service', $forecast['route_summaries'][0]['status']);
    }

    private function createTrustedHistory(
        string $date,
        RouteVariant $variant,
        string $timeSlot,
        int $commuters
    ): DemandHistory {
        return DemandHistory::create([
            'route_id' => $this->route->id,
            'route_variant_id' => $variant->id,
            'date' => $date,
            'time_slot' => $timeSlot,
            'day_of_week' => 'Monday',
            'total_commuters' => $commuters,
            'buses_dispatched' => 0,
            'source' => DemandHistory::SOURCE_ACTUAL_REBUILD,
            'is_training_eligible' => true,
            'finalized_at' => $date.' 10:00:00',
        ]);
    }
}
