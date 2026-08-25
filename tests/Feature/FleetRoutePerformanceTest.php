<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\Incident;
use App\Models\RouteDeviation;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Schedule;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetRoutePerformanceTest extends TestCase
{
    use RefreshDatabase;

    private Route $route2;

    private Route $route3;

    private Route $route4;

    private Route $legacyRoute;

    private RouteVariant $route2Outbound;

    private RouteVariant $route2Inbound;

    private RouteVariantStop $outboundSped;

    private RouteVariantStop $outboundRotonda;

    private RouteVariantStop $inboundLigaya;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-14 12:00:00', 'Asia/Manila'));

        $this->route2 = Route::factory()->official('Route 2')->create();
        $this->route3 = Route::factory()->official('Route 3')->create();
        $this->route4 = Route::factory()->official('Route 4')->create();
        $this->legacyRoute = Route::factory()->create([
            'name' => 'Route A',
            'status' => 'Active',
        ]);
        $this->route2Outbound = RouteVariant::create([
            'route_id' => $this->route2->id,
            'direction' => 'outbound',
            'origin_name' => 'SPED',
            'destination_name' => 'Ligaya',
            'is_default' => true,
        ]);
        $this->route2Inbound = RouteVariant::create([
            'route_id' => $this->route2->id,
            'direction' => 'inbound',
            'origin_name' => 'Ligaya',
            'destination_name' => 'SPED',
            'is_default' => false,
        ]);
        $this->outboundSped = RouteVariantStop::create([
            'route_variant_id' => $this->route2Outbound->id,
            'name' => 'SPED',
            'lat' => 14.5602934,
            'lng' => 121.0797616,
            'sequence' => 1,
        ]);
        $this->outboundRotonda = RouteVariantStop::create([
            'route_variant_id' => $this->route2Outbound->id,
            'name' => 'Rotonda',
            'lat' => 14.5700000,
            'lng' => 121.0800000,
            'sequence' => 2,
        ]);
        $this->inboundLigaya = RouteVariantStop::create([
            'route_variant_id' => $this->route2Inbound->id,
            'name' => 'Ligaya',
            'lat' => 14.6182022,
            'lng' => 121.0924001,
            'sequence' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_fleet_manager_can_access_route_performance(): void
    {
        $fleetManager = $this->fleetManager();

        $this->actingAs($fleetManager)
            ->get('/fleet/routes')
            ->assertRedirect('/fleet/dashboard?tab=routes');

        $fragment = $this->actingAs($fleetManager)
            ->getJson('/fleet/dashboard?tab=routes&fragment=1')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('tab', 'routes');

        $this->assertStringContainsString('Trips run', $fragment->json('html'));
        $this->assertStringContainsString('Avg trip duration', $fragment->json('html'));
        $this->assertStringContainsString('Recorded Boarded', $fragment->json('html'));
        $this->assertStringContainsString('Trip completion reliability', $fragment->json('html'));
        $this->assertStringContainsString('Operational incidents', $fragment->json('html'));
        $this->assertStringNotContainsString('GPS route adherence', $fragment->json('html'));
        $this->assertStringNotContainsString('route deviations', strtolower($fragment->json('html')));
    }

    public function test_unauthorized_users_cannot_access_route_performance(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get('/fleet/routes')
            ->assertForbidden();

        $this->actingAs(User::factory()->create(['role' => 'driver']))
            ->get('/fleet/routes')
            ->assertForbidden();
    }

    public function test_guest_users_are_redirected_to_login(): void
    {
        $this->get('/fleet/routes')->assertRedirect('/login');
    }

    public function test_route_payload_exposes_only_official_public_active_routes(): void
    {
        $response = $this->actingAs($this->fleetManager())
            ->getJson('/fleet/api/routes-data?route_id=all&start_date=2026-08-14&end_date=2026-08-14')
            ->assertOk()
            ->assertJsonStructure([
                'availableRoutes',
                'selectedRoute',
                'routePerformanceSummary' => [
                    'trips_run',
                    'completed_trips',
                    'ongoing_trips',
                    'dispatched_trips',
                    'cancelled_trips',
                    'avg_trip_duration_minutes',
                    'avg_trip_duration_label',
                ],
                'headwayData',
                'tripDurationData',
                'stops',
                'incidentLog',
                'routeHealthScore',
            ]);

        $this->assertSame(
            ['Route 2', 'Route 3', 'Route 4'],
            collect($response->json('availableRoutes'))->pluck('name')->all()
        );
        $this->assertNotContains(
            $this->legacyRoute->id,
            collect($response->json('availableRoutes'))->pluck('id')->all()
        );
    }

    public function test_actual_trip_statuses_drive_the_primary_metrics(): void
    {
        $day = Carbon::parse('2026-08-14 00:00:00', 'Asia/Manila');

        $this->trip($this->route2, [
            'status' => 'completed',
            'started_at' => $day->copy()->addHours(8)->utc(),
            'ended_at' => $day->copy()->addHours(9)->utc(),
        ]);
        $this->trip($this->route2, [
            'status' => 'ongoing',
            'started_at' => $day->copy()->addHours(10)->utc(),
        ]);
        $this->trip($this->route2, [
            'status' => 'dispatched',
            'dispatched_at' => $day->copy()->addHours(11)->utc(),
            'started_at' => null,
        ]);
        $this->trip($this->route2, [
            'status' => 'cancelled',
            'started_at' => $day->copy()->addHours(11),
            'ended_at' => $day->copy()->addHours(12)->utc(),
        ]);

        $response = $this->routeData();

        $response->assertJsonPath('routePerformanceSummary.trips_run', 2);
        $response->assertJsonPath('routePerformanceSummary.completed_trips', 1);
        $response->assertJsonPath('routePerformanceSummary.ongoing_trips', 1);
        $response->assertJsonPath('routePerformanceSummary.dispatched_trips', 1);
        $response->assertJsonPath('routePerformanceSummary.cancelled_trips', 1);
        $response->assertJsonPath('routePerformanceSummary.avg_trip_duration_minutes', 60);
        $response->assertJsonPath('routePerformanceSummary.avg_trip_duration_label', '60 min');
    }

    public function test_actual_headway_is_grouped_by_direction_and_manila_service_day(): void
    {
        $previousDay = Carbon::parse('2026-08-13 23:50:00', 'Asia/Manila');
        $this->trip($this->route2, [
            'route_variant_id' => $this->route2Outbound->id,
            'status' => 'completed',
            'started_at' => $previousDay->copy()->utc(),
            'ended_at' => $previousDay->copy()->addMinutes(20)->utc(),
        ]);

        foreach ([
            ['08:00:00', 'completed', '08:30:00'],
            ['08:20:00', 'ongoing', null],
            ['08:50:00', 'completed', '09:20:00'],
        ] as [$startTime, $status, $endTime]) {
            $this->trip($this->route2, [
                'route_variant_id' => $this->route2Outbound->id,
                'status' => $status,
                'started_at' => Carbon::parse('2026-08-14 ' . $startTime, 'Asia/Manila')->utc(),
                'ended_at' => $endTime
                    ? Carbon::parse('2026-08-14 ' . $endTime, 'Asia/Manila')->utc()
                    : null,
            ]);
        }

        foreach ([['08:10:00', 'completed', '08:40:00'], ['08:50:00', 'ongoing', null]] as [$startTime, $status, $endTime]) {
            $this->trip($this->route2, [
                'route_variant_id' => $this->route2Inbound->id,
                'status' => $status,
                'started_at' => Carbon::parse('2026-08-14 ' . $startTime, 'Asia/Manila')->utc(),
                'ended_at' => $endTime
                    ? Carbon::parse('2026-08-14 ' . $endTime, 'Asia/Manila')->utc()
                    : null,
            ]);
        }

        foreach (['dispatched', 'cancelled'] as $status) {
            $this->trip($this->route2, [
                'route_variant_id' => $this->route2Outbound->id,
                'status' => $status,
                'dispatched_at' => Carbon::parse('2026-08-14 08:30:00', 'Asia/Manila')->utc(),
                'started_at' => Carbon::parse('2026-08-14 08:30:00', 'Asia/Manila')->utc(),
                'ended_at' => $status === 'cancelled'
                    ? Carbon::parse('2026-08-14 08:45:00', 'Asia/Manila')->utc()
                    : null,
            ]);
        }

        $response = $this->routeData('2026-08-13', '2026-08-14');
        $headway = collect($response->json('headwayData'))->keyBy('direction');

        $this->assertSame(2, $headway->count());
        $this->assertSame(25, $headway['outbound']['average_headway_minutes']);
        $this->assertSame(20, $headway['outbound']['minimum_headway_minutes']);
        $this->assertSame(30, $headway['outbound']['maximum_headway_minutes']);
        $this->assertSame(2, $headway['outbound']['observed_intervals']);
        $this->assertSame(40, $headway['inbound']['average_headway_minutes']);
        $this->assertSame(1, $headway['inbound']['observed_intervals']);
        $response->assertJsonPath('routePerformanceSummary.avg_actual_headway_minutes', 30);
        $response->assertJsonPath('routePerformanceSummary.avg_actual_headway_label', '30 min');
        $response->assertJsonPath('routePerformanceSummary.headway_observations', 3);
    }

    public function test_headway_requires_two_actual_trip_starts_in_the_same_direction_and_day(): void
    {
        $this->trip($this->route2, [
            'route_variant_id' => $this->route2Outbound->id,
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
        ]);
        $this->trip($this->route2, [
            'route_variant_id' => $this->route2Inbound->id,
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-08-14 08:20:00', 'Asia/Manila')->utc(),
        ]);

        $this->routeData()
            ->assertJsonPath('headwayData', [])
            ->assertJsonPath('routePerformanceSummary.avg_actual_headway_minutes', null)
            ->assertJsonPath('routePerformanceSummary.avg_actual_headway_label', 'No data')
            ->assertJsonPath('routePerformanceSummary.headway_observations', 0);
    }

    public function test_completed_trip_duration_analysis_is_direction_aware_and_ignores_invalid_records(): void
    {
        foreach ([30, 60] as $duration) {
            $start = Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->addMinutes($duration);
            $this->trip($this->route2, [
                'route_variant_id' => $this->route2Outbound->id,
                'status' => 'completed',
                'started_at' => $start->copy()->utc(),
                'ended_at' => $start->copy()->addMinutes($duration)->utc(),
            ]);
        }
        $this->trip($this->route2, [
            'route_variant_id' => $this->route2Outbound->id,
            'status' => 'completed',
            'started_at' => null,
            'ended_at' => Carbon::parse('2026-08-14 11:00:00', 'Asia/Manila')->utc(),
        ]);
        $this->trip($this->route2, [
            'route_variant_id' => $this->route2Inbound->id,
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-08-14 12:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-08-14 12:40:00', 'Asia/Manila')->utc(),
        ]);
        $this->trip($this->route2, [
            'route_variant_id' => $this->route2Inbound->id,
            'status' => 'cancelled',
            'started_at' => Carbon::parse('2026-08-14 13:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-08-14 14:30:00', 'Asia/Manila')->utc(),
        ]);

        $response = $this->routeData();
        $durations = collect($response->json('tripDurationData'))->keyBy('direction');

        $this->assertSame(2, $durations->count());
        $this->assertSame(45, $durations['outbound']['average_duration_minutes']);
        $this->assertSame(30, $durations['outbound']['minimum_duration_minutes']);
        $this->assertSame(60, $durations['outbound']['maximum_duration_minutes']);
        $this->assertSame(3, $durations['outbound']['completed_trips']);
        $this->assertSame(2, $durations['outbound']['valid_duration_trips']);
        $this->assertSame(40, $durations['inbound']['average_duration_minutes']);
        $this->assertSame(1, $durations['inbound']['valid_duration_trips']);
    }

    public function test_direction_analysis_fails_closed_for_null_or_mismatched_variants(): void
    {
        $route3Outbound = RouteVariant::create([
            'route_id' => $this->route3->id,
            'direction' => 'outbound',
            'origin_name' => 'SPED',
            'destination_name' => 'One San Miguel Ave.',
        ]);

        foreach ([null, $route3Outbound->id] as $variantId) {
            $this->trip($this->route2, [
                'route_variant_id' => $variantId,
                'status' => 'completed',
                'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
                'ended_at' => Carbon::parse('2026-08-14 09:00:00', 'Asia/Manila')->utc(),
            ]);
        }

        $this->routeData()
            ->assertJsonPath('headwayData', [])
            ->assertJsonPath('tripDurationData', []);
    }

    public function test_recorded_stop_activity_aggregates_boarded_and_alighted_events_by_direction(): void
    {
        $outboundOngoing = $this->trip($this->route2, [
            'route_variant_id' => $this->route2Outbound->id,
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
        ]);
        $outboundCompleted = $this->trip($this->route2, [
            'route_variant_id' => $this->route2Outbound->id,
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-08-14 09:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-08-14 10:00:00', 'Asia/Manila')->utc(),
        ]);
        $inboundOngoing = $this->trip($this->route2, [
            'route_variant_id' => $this->route2Inbound->id,
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-08-14 10:00:00', 'Asia/Manila')->utc(),
        ]);

        $this->passengerEvent($outboundOngoing, 'boarded', 3, $this->outboundSped, '08:10:00');
        $this->passengerEvent($outboundOngoing, 'alighted', 1, $this->outboundSped, '08:20:00');
        $this->passengerEvent($outboundCompleted, 'boarded', 2, $this->outboundSped, '09:10:00');
        $this->passengerEvent($outboundOngoing, 'alighted', 2, $this->outboundRotonda, '08:30:00');
        $this->passengerEvent($outboundOngoing, 'boarded', 4, null, '08:40:00');
        $this->passengerEvent($outboundOngoing, 'alighted', 3, $this->inboundLigaya, '08:50:00');
        $this->passengerEvent($inboundOngoing, 'boarded', 5, $this->inboundLigaya, '10:10:00');

        $response = $this->routeData();
        $rows = collect($response->json('stops'));
        $outboundSped = $rows->firstWhere('route_variant_stop_id', $this->outboundSped->id);
        $outboundRotonda = $rows->firstWhere('route_variant_stop_id', $this->outboundRotonda->id);
        $inboundLigaya = $rows->firstWhere('route_variant_stop_id', $this->inboundLigaya->id);
        $unattributed = $rows->first(fn (array $row) => $row['direction'] === 'outbound' && ! $row['is_attributed']);

        $this->assertCount(4, $rows);
        $this->assertSame(5, $outboundSped['recorded_boarded']);
        $this->assertSame(1, $outboundSped['recorded_alighted']);
        $this->assertSame(2, $outboundSped['trips_recorded']);
        $this->assertSame(2, $outboundRotonda['recorded_alighted']);
        $this->assertSame(5, $inboundLigaya['recorded_boarded']);
        $this->assertSame(4, $unattributed['recorded_boarded']);
        $this->assertSame(3, $unattributed['recorded_alighted']);
        $this->assertSame('--', $unattributed['sequence_label']);
        $response->assertJsonPath('routePerformanceSummary.recorded_boarded', 14);
        $response->assertJsonPath('routePerformanceSummary.recorded_alighted', 6);
        $response->assertJsonPath('routePerformanceSummary.unattributed_boarded', 4);
        $response->assertJsonPath('routePerformanceSummary.unattributed_alighted', 3);
    }

    public function test_stop_activity_respects_official_route_trip_status_and_manila_period(): void
    {
        $validTrip = $this->trip($this->route2, [
            'route_variant_id' => $this->route2Outbound->id,
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-08-14 00:00:00', 'Asia/Manila')->utc(),
        ]);
        $dispatchedTrip = $this->trip($this->route2, [
            'route_variant_id' => $this->route2Outbound->id,
            'status' => 'dispatched',
            'dispatched_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
            'started_at' => null,
        ]);
        $nullVariantTrip = $this->trip($this->route2, [
            'route_variant_id' => null,
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
        ]);
        $legacyVariant = RouteVariant::create([
            'route_id' => $this->legacyRoute->id,
            'direction' => 'outbound',
            'origin_name' => 'Legacy Origin',
            'destination_name' => 'Legacy Destination',
        ]);
        $legacyStop = RouteVariantStop::create([
            'route_variant_id' => $legacyVariant->id,
            'name' => 'Legacy Stop',
            'lat' => 14.5000000,
            'lng' => 121.0000000,
            'sequence' => 1,
        ]);
        $legacyTrip = $this->trip($this->legacyRoute, [
            'route_variant_id' => $legacyVariant->id,
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
        ]);

        $this->passengerEvent($validTrip, 'boarded', 2, $this->outboundSped, '00:00:00');
        $this->passengerEvent($validTrip, 'alighted', 3, $this->outboundSped, '23:59:59');
        $this->passengerEvent($validTrip, 'boarded', 20, $this->outboundSped, '23:59:59', '2026-08-13');
        $this->passengerEvent($validTrip, 'boarded', 30, $this->outboundSped, '00:00:00', '2026-08-15');
        $this->passengerEvent($dispatchedTrip, 'boarded', 40, $this->outboundSped, '08:10:00');
        $this->passengerEvent($nullVariantTrip, 'boarded', 50, null, '08:20:00');
        $this->passengerEvent($legacyTrip, 'boarded', 60, $legacyStop, '08:30:00');

        $response = $this->routeData();

        $response->assertJsonCount(1, 'stops');
        $response->assertJsonPath('stops.0.recorded_boarded', 2);
        $response->assertJsonPath('stops.0.recorded_alighted', 3);
        $response->assertJsonPath('stops.0.trips_recorded', 1);
    }

    public function test_schedule_rows_alone_do_not_populate_actual_trip_metrics(): void
    {
        Schedule::factory()->create([
            'route_id' => $this->route2->id,
            'service_date' => '2026-08-14',
            'status' => 'On time',
            'passengers' => 99,
        ]);

        $response = $this->routeData();

        foreach (['trips_run', 'completed_trips', 'ongoing_trips', 'dispatched_trips', 'cancelled_trips'] as $metric) {
            $response->assertJsonPath('routePerformanceSummary.' . $metric, 0);
        }
        $this->assertNull($response->json('routePerformanceSummary.avg_trip_duration_minutes'));
        $response->assertJsonPath('routePerformanceSummary.avg_trip_duration_label', 'No data');
        $response->assertJsonPath('headwayData', []);
        $response->assertJsonPath('tripDurationData', []);
        $response->assertJsonPath('routeHealthScore.overall_score', null);
        $response->assertJsonPath('routeHealthScore.data_status', 'empty');
        $response->assertJsonPath('routeHealthScore.completion_score', null);
        $response->assertJsonPath('routeHealthScore.headway_score', null);
        $response->assertJsonPath('routeHealthScore.incident_free_score', null);
        $response->assertJsonMissingPath('routeHealthScore.route_adherence_score');
        $response->assertJsonMissingPath('routeHealthScore.gps_coverage_percent');
    }

    public function test_selected_period_has_no_all_time_trip_fallback(): void
    {
        $this->trip($this->route2, [
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-08-10 08:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-08-10 09:00:00', 'Asia/Manila')->utc(),
        ]);

        $response = $this->actingAs($this->fleetManager())->getJson(
            '/fleet/api/routes-data?route_id=all&start_date=2026-08-14&end_date=2026-08-14'
        );

        $response->assertOk()->assertJsonPath('routePerformanceSummary.trips_run', 0);
        $response->assertJsonPath('routePerformanceSummary.completed_trips', 0);
        $response->assertJsonPath('routePerformanceSummary.avg_trip_duration_label', 'No data');
    }

    public function test_route_filter_is_official_only_and_invalid_route_falls_back_to_official_aggregate(): void
    {
        foreach ([$this->route2, $this->route3, $this->legacyRoute] as $route) {
            $this->trip($route, [
                'status' => 'completed',
                'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
                'ended_at' => Carbon::parse('2026-08-14 09:00:00', 'Asia/Manila')->utc(),
            ]);
        }

        $this->actingAs($this->fleetManager())
            ->getJson('/fleet/api/routes-data?route_id=' . $this->route2->id . '&start_date=2026-08-14&end_date=2026-08-14')
            ->assertOk()
            ->assertJsonPath('selectedRoute', (string) $this->route2->id)
            ->assertJsonPath('routePerformanceSummary.completed_trips', 1);

        $this->actingAs($this->fleetManager())
            ->getJson('/fleet/api/routes-data?route_id=' . $this->legacyRoute->id . '&start_date=2026-08-14&end_date=2026-08-14')
            ->assertOk()
            ->assertJsonPath('selectedRoute', 'all')
            ->assertJsonPath('routePerformanceSummary.completed_trips', 2);
    }

    public function test_average_duration_ignores_missing_and_invalid_completed_timestamps(): void
    {
        $this->trip($this->route2, [
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-08-14 08:30:00', 'Asia/Manila')->utc(),
        ]);
        $this->trip($this->route2, [
            'status' => 'completed',
            'started_at' => null,
            'ended_at' => Carbon::parse('2026-08-14 09:30:00', 'Asia/Manila')->utc(),
        ]);
        $this->trip($this->route2, [
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-08-14 11:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-08-14 10:00:00', 'Asia/Manila')->utc(),
        ]);

        $this->routeData()
            ->assertJsonPath('routePerformanceSummary.completed_trips', 3)
            ->assertJsonPath('routePerformanceSummary.avg_trip_duration_minutes', 30)
            ->assertJsonPath('routePerformanceSummary.avg_trip_duration_label', '30 min');
    }

    public function test_manila_day_boundaries_are_converted_to_utc_for_trip_queries(): void
    {
        $timestamps = [
            ['2026-08-13 23:59:59', false],
            ['2026-08-14 00:15:00', true],
            ['2026-08-15 00:00:00', false],
        ];

        foreach ($timestamps as [$endedAt, $expectedInRange]) {
            $end = Carbon::parse($endedAt, 'Asia/Manila');
            $this->trip($this->route2, [
                'status' => 'completed',
                'started_at' => $end->copy()->subMinutes(30)->utc(),
                'ended_at' => $end->copy()->utc(),
                'peak_passengers' => $expectedInRange ? 1 : 0,
            ]);
        }

        $this->routeData()
            ->assertJsonPath('routePerformanceSummary.completed_trips', 1)
            ->assertJsonPath('routePerformanceSummary.avg_trip_duration_minutes', 30);
    }

    public function test_export_uses_the_same_actual_trip_labels_as_the_ui(): void
    {
        $trip = $this->trip($this->route2, [
            'route_variant_id' => $this->route2Outbound->id,
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-08-14 09:00:00', 'Asia/Manila')->utc(),
        ]);
        $this->passengerEvent($trip, 'boarded', 3, $this->outboundSped, '08:15:00');
        Incident::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'type' => 'Heavy Traffic Delay',
            'description' => 'Traffic slowed the completed trip.',
            'status' => 'resolved',
            'reported_at' => Carbon::parse('2026-08-14 08:30:00', 'Asia/Manila')->utc(),
        ]);
        RouteDeviation::create([
            'trip_id' => $trip->id,
            'lat' => 14.57,
            'lng' => 121.08,
            'distance_meters' => 60,
            'severity' => 'Minor',
            'detected_at' => Carbon::parse('2026-08-14 08:45:00', 'Asia/Manila')->utc(),
            'resolved_at' => Carbon::parse('2026-08-14 08:50:00', 'Asia/Manila')->utc(),
        ]);

        $response = $this->actingAs($this->fleetManager())->get(
            '/fleet/api/routes-export?route_id=' . $this->route2->id . '&start_date=2026-08-14&end_date=2026-08-14'
        );

        $response->assertOk()->assertHeader('Content-Disposition');
        $content = $response->streamedContent();
        $this->assertStringContainsString('ACTUAL TRIP SUMMARY', $content);
        $this->assertStringContainsString('Trips Run', $content);
        $this->assertStringContainsString('Avg Trip Duration', $content);
        $this->assertStringContainsString('DIRECTION-AWARE ACTUAL HEADWAY', $content);
        $this->assertStringContainsString('ACTUAL TRIP DURATION BY DIRECTION', $content);
        $this->assertStringContainsString('RECORDED STOP ACTIVITY', $content);
        $this->assertStringContainsString('Recorded Boarded', $content);
        $this->assertStringContainsString('SPED', $content);
        $this->assertStringContainsString('ACTUAL ROUTE HEALTH', $content);
        $this->assertStringContainsString('Insufficient evidence', $content);
        $this->assertStringContainsString('Trip Completion Reliability', $content);
        $this->assertStringContainsString('OPERATIONAL INCIDENTS', $content);
        $this->assertStringContainsString('Heavy Traffic Delay', $content);
        $this->assertStringNotContainsString('GPS route deviation', $content);
        $this->assertStringNotContainsString('GPS Route Adherence', $content);
        $this->assertStringNotContainsString('off corridor', $content);
        $this->assertStringContainsString('Actual Trip lifecycle records', $content);
        $this->assertStringNotContainsString('Target Headway', $content);
        $this->assertStringNotContainsString('On-time Rate', $content);
        $this->assertStringNotContainsString('Stop Adherence Rate', $content);
    }

    public function test_operational_incidents_are_reported_while_historical_route_deviations_stay_hidden(): void
    {
        $trip = $this->trip($this->route2, [
            'route_variant_id' => $this->route2Outbound->id,
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
        ]);

        Incident::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'type' => 'Breakdown',
            'description' => 'Engine stopped during the trip.',
            'status' => 'under_review',
            'reported_at' => Carbon::parse('2026-08-14 09:00:00', 'Asia/Manila')->utc(),
        ]);
        RouteDeviation::create([
            'trip_id' => $trip->id,
            'lat' => 14.5700000,
            'lng' => 121.0800000,
            'distance_meters' => 125.4,
            'severity' => 'Major',
            'detected_at' => Carbon::parse('2026-08-14 10:00:00', 'Asia/Manila')->utc(),
        ]);

        $response = $this->routeData(routeId: (string) $this->route2->id)
            ->assertJsonPath('routePerformanceSummary.incidents_count', 1)
            ->assertJsonPath('routePerformanceSummary.open_incidents_count', 1)
            ->assertJsonCount(1, 'incidentLog');

        $response
            ->assertJsonPath('incidentLog.0.source', 'incident')
            ->assertJsonPath('incidentLog.0.event_type', 'Breakdown')
            ->assertJsonPath('incidentLog.0.status_label', 'Under review')
            ->assertJsonMissingPath('routePerformanceSummary.route_deviations_count')
            ->assertJsonMissingPath('incidentLog.0.distance_meters')
            ->assertJsonMissingPath('incidentLog.0.severity');

        $this->assertDatabaseHas('route_deviations', [
            'trip_id' => $trip->id,
            'distance_meters' => 125.4,
        ]);
    }

    public function test_operational_incident_descriptions_are_normalized_for_display(): void
    {
        $trip = $this->trip($this->route2, [
            'route_variant_id' => $this->route2Outbound->id,
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
        ]);

        Incident::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'type' => 'Delay',
            'description' => 'Bus PAS-001 signal lost Ã¢â‚¬â€ last known position: Ligaya (Puregold)',
            'status' => 'reported',
            'reported_at' => Carbon::parse('2026-08-14 09:00:00', 'Asia/Manila')->utc(),
        ]);

        $response = $this->routeData(routeId: (string) $this->route2->id)
            ->assertJsonCount(1, 'incidentLog')
            ->assertJsonPath('incidentLog.0.description', 'Bus PAS-001 signal lost - last known position: Ligaya (Puregold)');

        $this->assertStringNotContainsString('Ã', $response->json('incidentLog.0.description'));
        $this->assertStringNotContainsString('â', $response->json('incidentLog.0.description'));
        $this->assertStringNotContainsString('Â', $response->json('incidentLog.0.description'));
        $this->assertStringNotContainsString('ï¿½', $response->json('incidentLog.0.description'));
    }

    public function test_actual_route_health_uses_complete_actual_operation_evidence(): void
    {
        $trips = collect([
            $this->trip($this->route2, [
                'route_variant_id' => $this->route2Outbound->id,
                'status' => 'completed',
                'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
                'ended_at' => Carbon::parse('2026-08-14 09:00:00', 'Asia/Manila')->utc(),
            ]),
            $this->trip($this->route2, [
                'route_variant_id' => $this->route2Outbound->id,
                'status' => 'completed',
                'started_at' => Carbon::parse('2026-08-14 08:10:00', 'Asia/Manila')->utc(),
                'ended_at' => Carbon::parse('2026-08-14 09:10:00', 'Asia/Manila')->utc(),
            ]),
            $this->trip($this->route2, [
                'route_variant_id' => $this->route2Outbound->id,
                'status' => 'completed',
                'started_at' => Carbon::parse('2026-08-14 08:20:00', 'Asia/Manila')->utc(),
                'ended_at' => Carbon::parse('2026-08-14 09:20:00', 'Asia/Manila')->utc(),
            ]),
            $this->trip($this->route2, [
                'route_variant_id' => $this->route2Outbound->id,
                'status' => 'cancelled',
                'started_at' => Carbon::parse('2026-08-14 10:00:00', 'Asia/Manila')->utc(),
                'ended_at' => Carbon::parse('2026-08-14 10:30:00', 'Asia/Manila')->utc(),
            ]),
        ]);

        Incident::create([
            'trip_id' => $trips[0]->id,
            'driver_id' => $trips[0]->driver_id,
            'type' => 'Passenger Concern',
            'description' => 'Passenger requested assistance during the trip.',
            'status' => 'resolved',
            'reported_at' => Carbon::parse('2026-08-14 08:30:00', 'Asia/Manila')->utc(),
        ]);
        RouteDeviation::create([
            'trip_id' => $trips[1]->id,
            'lat' => 14.57,
            'lng' => 121.08,
            'distance_meters' => 90,
            'severity' => 'Major',
            'detected_at' => Carbon::parse('2026-08-14 08:35:00', 'Asia/Manila')->utc(),
            'resolved_at' => Carbon::parse('2026-08-14 08:40:00', 'Asia/Manila')->utc(),
        ]);

        $this->routeData(routeId: (string) $this->route2->id)
            ->assertJsonPath('routeHealthScore.overall_score', 83)
            ->assertJsonPath('routeHealthScore.score_label', 'Monitor')
            ->assertJsonPath('routeHealthScore.data_status', 'ready')
            ->assertJsonPath('routeHealthScore.completion_score', 75)
            ->assertJsonPath('routeHealthScore.headway_score', 100)
            ->assertJsonPath('routeHealthScore.incident_free_score', 75)
            ->assertJsonMissingPath('routeHealthScore.route_adherence_score')
            ->assertJsonMissingPath('routeHealthScore.gps_coverage_percent')
            ->assertJsonPath('routeHealthScore.missing_evidence', []);
    }

    public function test_route_health_no_longer_requires_gps_or_corridor_evidence(): void
    {
        $trips = collect();
        foreach (['08:00:00', '08:10:00', '08:20:00'] as $startedAt) {
            $start = Carbon::parse('2026-08-14 ' . $startedAt, 'Asia/Manila');
            $trips->push($this->trip($this->route2, [
                'route_variant_id' => $this->route2Outbound->id,
                'status' => 'completed',
                'started_at' => $start->copy()->utc(),
                'ended_at' => $start->copy()->addHour()->utc(),
            ]));
        }

        $this->routeData(routeId: (string) $this->route2->id)
            ->assertJsonPath('routeHealthScore.overall_score', 100)
            ->assertJsonPath('routeHealthScore.data_status', 'ready')
            ->assertJsonPath('routeHealthScore.completion_score', 100)
            ->assertJsonPath('routeHealthScore.headway_score', 100)
            ->assertJsonPath('routeHealthScore.incident_free_score', 100)
            ->assertJsonMissingPath('routeHealthScore.route_adherence_score')
            ->assertJsonMissingPath('routeHealthScore.gps_coverage_percent')
            ->assertJsonPath('routeHealthScore.missing_evidence', []);
    }

    public function test_operational_incident_log_excludes_legacy_routes_and_out_of_period_records(): void
    {
        $officialTrip = $this->trip($this->route2, [
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-08-14 09:00:00', 'Asia/Manila')->utc(),
        ]);
        $legacyTrip = $this->trip($this->legacyRoute, [
            'status' => 'completed',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
            'ended_at' => Carbon::parse('2026-08-14 09:00:00', 'Asia/Manila')->utc(),
        ]);

        foreach ([
            [$officialTrip, '2026-08-14 11:00:00'],
            [$officialTrip, '2026-08-13 23:59:59'],
            [$legacyTrip, '2026-08-14 11:00:00'],
        ] as [$trip, $reportedAt]) {
            Incident::create([
                'trip_id' => $trip->id,
                'driver_id' => $trip->driver_id,
                'type' => 'Passenger Concern',
                'description' => 'Passenger requested assistance.',
                'status' => 'reported',
                'reported_at' => Carbon::parse($reportedAt, 'Asia/Manila')->utc(),
            ]);
        }

        $this->routeData()
            ->assertJsonPath('routePerformanceSummary.incidents_count', 1)
            ->assertJsonCount(1, 'incidentLog')
            ->assertJsonPath('incidentLog.0.route', 'Route 2');
    }

    public function test_retired_event_source_filter_cannot_restore_historical_deviations(): void
    {
        $trip = $this->trip($this->route2, [
            'status' => 'ongoing',
            'started_at' => Carbon::parse('2026-08-14 08:00:00', 'Asia/Manila')->utc(),
        ]);
        Incident::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'type' => 'Passenger Concern',
            'description' => 'Passenger requested assistance.',
            'status' => 'reported',
            'reported_at' => Carbon::parse('2026-08-14 09:00:00', 'Asia/Manila')->utc(),
        ]);
        RouteDeviation::create([
            'trip_id' => $trip->id,
            'lat' => 14.57,
            'lng' => 121.08,
            'distance_meters' => 70,
            'severity' => 'Minor',
            'detected_at' => Carbon::parse('2026-08-14 10:00:00', 'Asia/Manila')->utc(),
        ]);

        $this->actingAs($this->fleetManager())
            ->json('GET', '/fleet/api/routes-data', [
                'route_id' => 'all',
                'start_date' => '2026-08-14',
                'end_date' => '2026-08-14',
                'event_sources' => ['route_deviation'],
            ])
            ->assertOk()
            ->assertJsonCount(1, 'incidentLog')
            ->assertJsonPath('incidentLog.0.source', 'incident')
            ->assertJsonMissingPath('deviationLog');
    }

    private function fleetManager(): User
    {
        return User::factory()->create(['role' => 'fleet_manager']);
    }

    private function routeData(
        string $startDate = '2026-08-14',
        string $endDate = '2026-08-14',
        string $routeId = 'all'
    )
    {
        return $this->actingAs($this->fleetManager())->getJson(
            '/fleet/api/routes-data?route_id=' . $routeId
            . '&start_date=' . $startDate
            . '&end_date=' . $endDate
        )->assertOk();
    }

    private function trip(Route $route, array $attributes): Trip
    {
        return Trip::factory()->create(array_merge([
            'route_id' => $route->id,
            'dispatched_at' => null,
            'started_at' => null,
            'ended_at' => null,
        ], $attributes));
    }

    private function passengerEvent(
        Trip $trip,
        string $eventType,
        int $passengerDelta,
        ?RouteVariantStop $stop,
        string $time,
        string $date = '2026-08-14'
    ): TripPassengerEvent {
        return TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'bus_id' => $trip->bus_id,
            'route_id' => $trip->route_id,
            'route_variant_stop_id' => $stop?->id,
            'event_type' => $eventType,
            'passenger_delta' => $passengerDelta,
            'onboard_after' => $passengerDelta,
            'recorded_at' => Carbon::parse($date . ' ' . $time, 'Asia/Manila')->utc(),
        ]);
    }

}
