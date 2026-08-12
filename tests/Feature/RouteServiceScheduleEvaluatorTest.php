<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Services\RouteServiceScheduleEvaluator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteServiceScheduleEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_split_windows_evaluate_boundary_times(): void
    {
        [$route, $outbound, $inbound] = $this->routeWithDirections();
        $this->seedOfficialWindows($route, $outbound, $inbound);
        $evaluator = app(RouteServiceScheduleEvaluator::class);

        $this->assertFalse($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 05:29:00')));
        $this->assertTrue($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 05:30:00')));
        $this->assertTrue($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 09:00:00')));
        $this->assertFalse($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 09:01:00')));
        $this->assertFalse($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 14:59:00')));
        $this->assertTrue($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 15:00:00')));
        $this->assertTrue($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 17:00:00')));
        $this->assertFalse($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 17:01:00')));
        $this->assertTrue($evaluator->isVariantOperating($inbound, $this->at('2026-08-03 18:00:00')));
        $this->assertFalse($evaluator->isVariantOperating($inbound, $this->at('2026-08-03 18:01:00')));

        $gapStatus = $evaluator->statusForRoute($route, $this->at('2026-08-03 09:01:00'));
        $this->assertFalse($gapStatus['is_operating']);
        $this->assertSame('Starts in 359 min', $gapStatus['status_label']);
        $this->assertSame('15:00:00', substr($gapStatus['next_window']->first_trip_time, 0, 8));

        $inboundOnlyStatus = $evaluator->statusForRoute($route, $this->at('2026-08-03 17:01:00'));
        $this->assertTrue($inboundOnlyStatus['is_operating']);
        $this->assertSame($inbound->id, $inboundOnlyStatus['current_window']->route_variant_id);
    }

    public function test_inactive_schedule_rows_are_ignored(): void
    {
        [$route, $outbound] = $this->routeWithDirections();
        $this->schedule($route, $outbound, '05:30:00', '18:00:00', ['mon'], false);

        $evaluator = app(RouteServiceScheduleEvaluator::class);

        $this->assertFalse($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 06:00:00')));
        $this->assertSame('Missing configuration', $evaluator->statusForRoute($route, $this->at('2026-08-03 06:00:00'))['status_label']);
    }

    public function test_effective_date_bounds_are_inclusive(): void
    {
        [$route, $outbound] = $this->routeWithDirections();
        $this->schedule($route, $outbound, '05:30:00', '18:00:00', ['mon'], true, '2026-08-03', '2026-08-03');

        $evaluator = app(RouteServiceScheduleEvaluator::class);

        $this->assertTrue($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 06:00:00')));
        $this->assertFalse($evaluator->isVariantOperating($outbound, $this->at('2026-08-04 06:00:00')));
    }

    public function test_service_days_are_respected(): void
    {
        [$route, $outbound] = $this->routeWithDirections();
        $this->schedule($route, $outbound, '05:30:00', '18:00:00', ['tue']);

        $evaluator = app(RouteServiceScheduleEvaluator::class);

        $this->assertFalse($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 06:00:00')));
        $this->assertTrue($evaluator->isVariantOperating($outbound, $this->at('2026-08-04 06:00:00')));
    }

    public function test_empty_service_days_mean_no_configured_service(): void
    {
        [$route, $outbound] = $this->routeWithDirections();
        $this->schedule($route, $outbound, '05:30:00', '18:00:00', []);

        $evaluator = app(RouteServiceScheduleEvaluator::class);
        $status = $evaluator->statusForRoute($route, $this->at('2026-08-03 06:00:00'));

        $this->assertFalse($evaluator->isVariantOperating($outbound, $this->at('2026-08-03 06:00:00')));
        $this->assertFalse($status['is_operating']);
        $this->assertSame('No service today', $status['status_label']);
    }

    private function seedOfficialWindows(Route $route, RouteVariant $outbound, RouteVariant $inbound): void
    {
        $this->schedule($route, $outbound, '05:30:00', '09:00:00');
        $this->schedule($route, $outbound, '15:00:00', '17:00:00');
        $this->schedule($route, $inbound, '06:00:00', '09:00:00');
        $this->schedule($route, $inbound, '15:00:00', '18:00:00');
    }

    private function schedule(
        Route $route,
        RouteVariant $variant,
        string $firstTrip,
        string $lastTrip,
        array $serviceDays = ['mon', 'tue', 'wed', 'thu', 'fri'],
        bool $active = true,
        ?string $effectiveFrom = null,
        ?string $effectiveUntil = null
    ): RouteServiceSchedule {
        return RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'first_trip_time' => $firstTrip,
            'last_trip_time' => $lastTrip,
            'service_configuration' => 'with_designated_stops',
            'service_days' => $serviceDays,
            'is_active' => $active,
            'source' => RouteServiceSchedule::SOURCE_BENEFICIARY_OFFICIAL,
            'effective_from' => $effectiveFrom,
            'effective_until' => $effectiveUntil,
        ]);
    }

    private function routeWithDirections(): array
    {
        $route = Route::factory()->create(['name' => 'Route 2', 'status' => 'Active']);
        $outbound = $this->variantFor($route, 'outbound', 'SPED', 'Ligaya');
        $inbound = $this->variantFor($route, 'inbound', 'Ligaya', 'SPED');

        return [$route, $outbound, $inbound];
    }

    private function variantFor(Route $route, string $direction, string $origin, string $destination): RouteVariant
    {
        return RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => $origin,
            'destination_name' => $destination,
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5603, 121.0815]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => $direction === 'outbound',
        ]);
    }

    private function at(string $datetime): Carbon
    {
        return Carbon::parse($datetime, 'Asia/Manila');
    }
}
