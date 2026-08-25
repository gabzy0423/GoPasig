<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\TimeSlotConfiguration;
use App\Models\Trip;
use App\Models\TripPassengerEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetAnalyticsActualOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_rows_alone_do_not_populate_fleet_analytics_metrics(): void
    {
        $route = $this->officialRoute('Route 2');
        $bus = Bus::factory()->create(['route_id' => $route->id, 'status' => 'ready']);
        $driver = Driver::factory()->create();

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => '2026-08-14',
            'departure_time' => '15:00:00',
            'arrival_time' => '15:30:00',
            'passengers' => 99,
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $response = $this->actingAsFleetManager()
            ->getJson('/fleet/api/analytics-data?start_date=2026-08-14&end_date=2026-08-14&route_id=all');

        $response->assertOk();
        $response->assertJsonPath('metricSummary.total_passengers', '0');
        $response->assertJsonPath('metricSummary.trips_completed', 0);
        $response->assertJsonPath('metricSummary.avg_per_trip', 0);
        $response->assertJsonPath('metricSummary.peak_hour', 'No data');
        $response->assertJsonPath('busLogs', []);
        $response->assertJsonPath('dispatchRecommendations', []);
        $response->assertJsonPath('dispatchRecommendationStatus.status', 'standby');
    }

    public function test_actual_trips_and_boarding_events_populate_fleet_analytics(): void
    {
        $this->slot('Afternoon', '15:00:00', '16:00:00', '3 PM');
        $route = $this->officialRoute('Route 2');
        $bus = Bus::factory()->create([
            'plate_number' => 'PAS-ACTUAL',
            'route_id' => $route->id,
            'status' => 'operating',
            'capacity' => 45,
        ]);
        $driver = Driver::factory()->create();
        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => TripStatus::COMPLETED->value,
            'started_at' => $this->manilaUtc('2026-08-14 15:00:00'),
            'ended_at' => $this->manilaUtc('2026-08-14 15:35:00'),
            'peak_passengers' => 18,
        ]);

        $this->passengerEvent($trip, TripPassengerEvent::TYPE_BOARDED, 12, '2026-08-14 15:05:00');
        $this->passengerEvent($trip, TripPassengerEvent::TYPE_ALIGHTED, 4, '2026-08-14 15:20:00');

        $response = $this->actingAsFleetManager()
            ->getJson('/fleet/api/analytics-data?start_date=2026-08-14&end_date=2026-08-14&route_id=all');

        $response->assertOk();
        $response->assertJsonPath('metricSummary.total_passengers', '12');
        $response->assertJsonPath('metricSummary.trips_completed', 1);
        $response->assertJsonPath('metricSummary.avg_per_trip', 12);
        $response->assertJsonPath('metricSummary.busiest_route', 'Route 2');
        $response->assertJsonPath('metricSummary.peak_hour', '3 PM');
        $response->assertJsonPath('routeSummary.0.route_name', 'Route 2');
        $response->assertJsonPath('routeSummary.0.total_passengers', 12);
        $response->assertJsonPath('hourlyRidership.0.count', 12);
        $response->assertJsonPath('busLogs.0.bus_id', 'PAS-ACTUAL');
        $response->assertJsonPath('busLogs.0.trips_completed', 1);
        $response->assertJsonPath('busLogs.0.total_passengers', 12);
        $response->assertJsonPath('busLogs.0.peak_load', 18);
        $response->assertJsonPath('busLogs.0.utilization_rate', 40);
    }

    public function test_non_official_route_activity_does_not_leak_into_fleet_analytics(): void
    {
        $officialRoute = $this->officialRoute('Route 2');
        $legacyRoute = Route::factory()->create(['name' => 'Route A', 'status' => 'Active']);
        $officialTrip = $this->completedTripForRoute($officialRoute, 'PAS-OFFICIAL');
        $legacyTrip = $this->completedTripForRoute($legacyRoute, 'PAS-LEGACY');

        $this->passengerEvent($officialTrip, TripPassengerEvent::TYPE_BOARDED, 7, '2026-08-14 15:05:00');
        $this->passengerEvent($legacyTrip, TripPassengerEvent::TYPE_BOARDED, 50, '2026-08-14 15:05:00');

        $response = $this->actingAsFleetManager()
            ->getJson('/fleet/api/analytics-data?start_date=2026-08-14&end_date=2026-08-14&route_id=all');

        $response->assertOk();
        $response->assertJsonPath('metricSummary.total_passengers', '7');
        $this->assertSame(['Route 2'], collect($response->json('routeSummary'))->pluck('route_name')->all());
        $this->assertSame(['PAS-OFFICIAL'], collect($response->json('busLogs'))->pluck('bus_id')->all());
    }

    public function test_fleet_analytics_page_shows_dispatch_recommendations_as_standby(): void
    {
        $this->officialRoute('Route 2');

        $response = $this->actingAsFleetManager()
            ->get('/fleet/dashboard?tab=analytics&fragment=1');

        $response->assertOk();
        $response->assertSee('Dispatch recommendations are on standby.', false);
        $response->assertSee('Recommendations will return after Dispatch Intelligence is fully operationally aligned.', false);
        $response->assertDontSee('Recommended Dispatch', false);
    }

    private function actingAsFleetManager(): self
    {
        return $this->actingAs(User::factory()->create(['role' => 'fleet_manager']));
    }

    private function officialRoute(string $name): Route
    {
        return Route::factory()->create([
            'name' => $name,
            'status' => 'Active',
            'color' => '#003F87',
        ]);
    }

    private function completedTripForRoute(Route $route, string $plate): Trip
    {
        $bus = Bus::factory()->create([
            'plate_number' => $plate,
            'route_id' => $route->id,
            'status' => 'ready',
            'capacity' => 45,
        ]);
        $driver = Driver::factory()->create();

        return Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => TripStatus::COMPLETED->value,
            'started_at' => $this->manilaUtc('2026-08-14 15:00:00'),
            'ended_at' => $this->manilaUtc('2026-08-14 15:30:00'),
            'peak_passengers' => 10,
        ]);
    }

    private function passengerEvent(Trip $trip, string $type, int $delta, string $recordedAt): TripPassengerEvent
    {
        return TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'driver_id' => $trip->driver_id,
            'bus_id' => $trip->bus_id,
            'route_id' => $trip->route_id,
            'event_type' => $type,
            'passenger_delta' => $delta,
            'onboard_after' => $type === TripPassengerEvent::TYPE_BOARDED ? $delta : 0,
            'recorded_at' => $this->manilaUtc($recordedAt),
        ]);
    }

    private function slot(string $name, string $start, string $end, string $display): TimeSlotConfiguration
    {
        return TimeSlotConfiguration::create([
            'name' => $name,
            'start_time' => $start,
            'end_time' => $end,
            'time_slot_display' => $display,
            'order' => 1,
            'is_active' => true,
        ]);
    }

    private function manilaUtc(string $timestamp): Carbon
    {
        return Carbon::parse($timestamp, 'Asia/Manila')->utc();
    }
}
