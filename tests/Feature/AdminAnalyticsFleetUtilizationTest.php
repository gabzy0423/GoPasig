<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\DemandHistory;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\MaintenanceRecord;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Models\TimeSlotConfiguration;
use App\Models\Trip;
use App\Models\TripLog;
use App\Models\TripPassengerEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AdminAnalyticsFleetUtilizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-07 10:00:00', 'Asia/Manila'));

        $this->seed(\Database\Seeders\SystemSettingSeeder::class);

        TimeSlotConfiguration::create([
            'name' => 'Morning',
            'start_time' => '05:00:00',
            'end_time' => '09:00:00',
            'time_slot_display' => '05:00-09:00',
            'is_active' => true,
            'order' => 1,
        ]);

        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_buses_operated_counts_only_active_ongoing_trips_on_public_routes(): void
    {
        $route = $this->createPublicRoute();
        $driver = Driver::factory()->create();

        $activeWithoutTrip = Bus::factory()->create(['status' => Bus::STATUS_ACTIVE]);
        $activeWithOngoingTrip = Bus::factory()->create(['status' => Bus::STATUS_ACTIVE]);
        $inactiveWithOngoingTrip = Bus::factory()->create(['status' => Bus::STATUS_INACTIVE]);
        $maintenanceWithOngoingTrip = Bus::factory()->create(['status' => Bus::STATUS_MAINTENANCE]);
        $breakdownWithOngoingTrip = Bus::factory()->create(['status' => Bus::STATUS_BREAKDOWN]);

        foreach ([$activeWithOngoingTrip, $inactiveWithOngoingTrip, $maintenanceWithOngoingTrip, $breakdownWithOngoingTrip] as $bus) {
            Trip::factory()->create([
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'route_id' => $route->id,
                'status' => 'ongoing',
                'started_at' => now()->subMinutes(20),
                'ended_at' => null,
            ]);
        }

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpis.buses_in_service', 1);
        $response->assertJsonPath('kpis.active_buses', 1);
        $response->assertJsonPath('kpis.total_buses', 5);
        $response->assertJsonPath('kpis.fleet_util', 20);

        $this->assertNotNull($activeWithoutTrip);
    }

    public function test_buses_operated_counts_an_operating_bus_with_an_ongoing_trip(): void
    {
        $route = $this->createPublicRoute();
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create(['status' => 'operating']);

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now()->subMinutes(20),
            'ended_at' => null,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpis.buses_in_service', 1);
        $response->assertJsonPath('kpis.active_buses', 1);
        $response->assertJsonPath('kpis.total_buses', 1);
        $response->assertJsonPath('kpis.fleet_util', 100);
    }

    public function test_buses_operated_uses_selected_period_actual_trips_not_current_bus_status(): void
    {
        $route = $this->createPublicRoute();
        $driver = Driver::factory()->create();
        $operatedBus = Bus::factory()->create(['status' => Bus::STATUS_INACTIVE]);
        $yesterdayBus = Bus::factory()->create(['status' => Bus::STATUS_ACTIVE]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $operatedBus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(7, 0),
        ]);
        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $yesterdayBus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->subDay()->setTime(6, 0),
            'ended_at' => now()->subDay()->setTime(7, 0),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpis.buses_in_service', 1);
        $response->assertJsonPath('kpis.active_buses', 1);
        $response->assertJsonPath('kpis.total_buses', 2);
        $response->assertJsonPath('kpis.fleet_util', 50);
    }

    public function test_completed_trips_use_ended_at_and_schedule_rows_do_not_drive_top_kpi_context(): void
    {
        $route = $this->createPublicRoute();
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create(['status' => Bus::STATUS_ACTIVE]);

        Schedule::factory()->count(2)->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
        ]);

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->subDay()->toDateString(),
        ]);

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->addDay()->toDateString(),
        ]);

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'created_at' => now()->subDay(),
            'ended_at' => now()->setTime(9, 30),
        ]);

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'completed',
            'created_at' => now(),
            'ended_at' => now()->subDay()->setTime(9, 30),
        ]);

        Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'created_at' => now(),
            'ended_at' => now()->setTime(10, 30),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpis.trips_completed', 1);
        $response->assertJsonPath('kpis.trips_scheduled', null);
    }

    public function test_schedule_passenger_driver_counters_and_status_fields_do_not_drive_top_kpis(): void
    {
        $route = $this->createPublicRoute();
        $driver = Driver::factory()->create(['pax_today' => 777]);
        $bus = Bus::factory()->create(['status' => Bus::STATUS_ACTIVE]);

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
            'passengers' => 999,
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
            'passengers' => 111,
            'status' => Schedule::STATUS_DELAYED,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpis.total_pax_today', 0);
        $response->assertJsonPath('kpis.pax_change_yesterday', 'Recorded boarded events in selected period');
        $response->assertJsonPath('kpis.pax_this_week', 0);
        $response->assertJsonPath('kpis.pax_change_last_week', 'Recorded boarded events in selected period');
        $response->assertJsonPath('kpis.avg_pax_trip', 0);
        $response->assertJsonPath('kpis.avg_pax_trip_change', 'Average peak load per actual trip in selected period');
        $this->assertArrayNotHasKey('on_time_rate', $response->json('kpis'));
        $this->assertArrayNotHasKey('delayed_trips', $response->json('kpis'));
    }

    public function test_passengers_handled_kpi_uses_boarded_passenger_events_in_selected_period(): void
    {
        $route = $this->createPublicRoute();
        $driver = Driver::factory()->create(['pax_today' => 999]);
        $bus = Bus::factory()->create(['status' => Bus::STATUS_ACTIVE]);

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
            'passengers' => 500,
        ]);

        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'started_at' => now()->setTime(8, 0),
            'peak_passengers' => 0,
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 12,
            'onboard_after' => 12,
            'recorded_at' => now()->setTime(8, 10),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 5,
            'onboard_after' => 17,
            'recorded_at' => now()->setTime(8, 20),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_ALIGHTED,
            'passenger_delta' => 4,
            'onboard_after' => 13,
            'recorded_at' => now()->setTime(8, 30),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 20,
            'onboard_after' => 33,
            'recorded_at' => now()->subDay()->setTime(8, 10),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpis.total_pax_today', 17);
        $response->assertJsonPath('kpis.pax_change_yesterday', 'Recorded boarded events in selected period');
        $response->assertJsonPath('kpis.avg_pax_trip', 0);
        $this->assertArrayNotHasKey('on_time_rate', $response->json('kpis'));
    }

    public function test_selected_period_passengers_kpi_uses_boarded_events_in_selected_period(): void
    {
        $route = $this->createPublicRoute();
        $driver = Driver::factory()->create(['pax_today' => 999]);
        $bus = Bus::factory()->create(['status' => Bus::STATUS_ACTIVE]);

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
            'passengers' => 500,
        ]);

        DemandHistory::create([
            'date' => now()->toDateString(),
            'route_id' => $route->id,
            'total_commuters' => 600,
            'day_of_week' => now()->format('l'),
            'time_slot' => '05:00-09:00',
            'buses_dispatched' => 0,
        ]);

        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(8, 0),
            'ended_at' => now()->setTime(9, 0),
            'peak_passengers' => 0,
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 11,
            'onboard_after' => 11,
            'recorded_at' => now()->setTime(8, 10),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 7,
            'onboard_after' => 18,
            'recorded_at' => now()->subDays(2)->setTime(8, 10),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_ALIGHTED,
            'passenger_delta' => 5,
            'onboard_after' => 13,
            'recorded_at' => now()->setTime(8, 30),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 19,
            'onboard_after' => 32,
            'recorded_at' => now()->subWeek()->setTime(8, 10),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpis.total_pax_today', 11);
        $response->assertJsonPath('kpis.pax_this_week', 11);
        $response->assertJsonPath('kpis.pax_change_last_week', 'Recorded boarded events in selected period');
        $response->assertJsonPath('kpis.avg_pax_trip', 0);
        $this->assertArrayNotHasKey('on_time_rate', $response->json('kpis'));
    }

    public function test_selected_period_passengers_excludes_non_official_routes_and_out_of_range_events(): void
    {
        $officialRoute = $this->createPublicRoute();
        $nonOfficialRoute = Route::factory()->create([
            'name' => 'Route A',
            'status' => 'Active',
        ]);
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();

        $officialTrip = Trip::factory()->create([
            'route_id' => $officialRoute->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(8, 0),
            'ended_at' => now()->setTime(9, 0),
        ]);
        $nonOfficialTrip = Trip::factory()->create([
            'route_id' => $nonOfficialRoute->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(10, 0),
            'ended_at' => now()->setTime(11, 0),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $officialTrip->id,
            'route_id' => $officialRoute->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 13,
            'onboard_after' => 13,
            'recorded_at' => now()->setTime(8, 10),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $nonOfficialTrip->id,
            'route_id' => $nonOfficialRoute->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 1000,
            'onboard_after' => 1000,
            'recorded_at' => now()->setTime(8, 20),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $officialTrip->id,
            'route_id' => $officialRoute->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 500,
            'onboard_after' => 500,
            'recorded_at' => now()->subWeek()->setTime(8, 30),
        ]);

        $selectedDate = now()->toDateString();
        $response = $this->getJson(route('admin.api.analytics', [
            'start' => $selectedDate,
            'end' => $selectedDate,
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpis.pax_this_week', 13);
    }

    public function test_selected_period_passengers_follow_a_custom_date_range(): void
    {
        $route = $this->createPublicRoute();
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();
        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->subDays(3)->setTime(8, 0),
            'ended_at' => now()->subDays(3)->setTime(9, 0),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 17,
            'onboard_after' => 17,
            'recorded_at' => now()->subDays(3)->setTime(8, 15),
        ]);

        TripPassengerEvent::create([
            'trip_id' => $trip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 99,
            'onboard_after' => 99,
            'recorded_at' => now()->setTime(8, 15),
        ]);

        $selectedDate = now()->subDays(3)->toDateString();
        $response = $this->getJson(route('admin.api.analytics', [
            'start' => $selectedDate,
            'end' => $selectedDate,
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpis.pax_this_week', 17);
        $response->assertJsonPath('kpis.pax_change_last_week', 'Recorded boarded events in selected period');
    }

    public function test_avg_passenger_load_kpi_uses_trip_peak_passengers_in_selected_period(): void
    {
        $route = $this->createPublicRoute();
        $nonOfficialRoute = Route::factory()->create([
            'name' => 'Route A',
            'status' => 'Active',
        ]);
        $driver = Driver::factory()->create(['pax_today' => 999]);
        $bus = Bus::factory()->create(['status' => Bus::STATUS_ACTIVE]);

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
            'passengers' => 500,
        ]);

        $completedTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(8, 0),
            'ended_at' => now()->setTime(9, 0),
            'peak_passengers' => 20,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'started_at' => now()->setTime(10, 0),
            'ended_at' => null,
            'peak_passengers' => 40,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'dispatched',
            'started_at' => null,
            'dispatched_at' => now()->setTime(11, 0),
            'ended_at' => null,
            'peak_passengers' => 10,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
            'started_at' => now()->setTime(12, 0),
            'ended_at' => now()->setTime(12, 30),
            'peak_passengers' => 30,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->subDay()->setTime(8, 0),
            'ended_at' => now()->subDay()->setTime(9, 0),
            'peak_passengers' => 100,
        ]);

        Trip::factory()->create([
            'route_id' => $nonOfficialRoute->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(13, 0),
            'ended_at' => now()->setTime(14, 0),
            'peak_passengers' => 200,
        ]);

        TripPassengerEvent::create([
            'trip_id' => $completedTrip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 999,
            'onboard_after' => 999,
            'recorded_at' => now()->setTime(8, 15),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $response->assertJsonPath('kpis.avg_pax_trip', 30);
        $response->assertJsonPath('kpis.avg_pax_trip_change', 'Average peak load per actual trip in selected period');
        $response->assertJsonPath('kpis.total_pax_today', 999);
        $response->assertJsonPath('kpis.pax_this_week', 999);
        $this->assertArrayNotHasKey('on_time_rate', $response->json('kpis'));
    }

    public function test_bus_operation_load_summary_uses_actual_trip_and_passenger_event_metrics(): void
    {
        $route = $this->createPublicRoute();
        $driver = Driver::factory()->create(['pax_today' => 999]);
        $bus = Bus::factory()->create([
            'status' => Bus::STATUS_ACTIVE,
            'capacity' => 50,
        ]);
        $idleBus = Bus::factory()->create([
            'status' => Bus::STATUS_ACTIVE,
            'capacity' => 40,
        ]);

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
            'passengers' => 49,
        ]);

        $completedTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(8, 0),
            'ended_at' => now()->setTime(9, 0),
            'peak_passengers' => 31,
        ]);
        $ongoingTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'started_at' => now()->setTime(10, 0),
            'ended_at' => null,
            'peak_passengers' => 19,
        ]);
        $dispatchedTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'dispatched',
            'started_at' => null,
            'dispatched_at' => now()->setTime(11, 0),
            'ended_at' => null,
            'peak_passengers' => 45,
        ]);
        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
            'started_at' => now()->setTime(12, 0),
            'ended_at' => now()->setTime(12, 20),
            'peak_passengers' => 5,
        ]);
        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->subDay()->setTime(8, 0),
            'ended_at' => now()->subDay()->setTime(9, 0),
            'peak_passengers' => 99,
        ]);

        foreach ([[$completedTrip, 12, 12], [$ongoingTrip, 8, 20], [$dispatchedTrip, 4, 24]] as [$trip, $delta, $minute]) {
            TripPassengerEvent::create([
                'trip_id' => $trip->id,
                'route_id' => $route->id,
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'event_type' => TripPassengerEvent::TYPE_BOARDED,
                'passenger_delta' => $delta,
                'onboard_after' => $delta,
                'recorded_at' => now()->setTime(8, $minute),
            ]);
        }

        TripPassengerEvent::create([
            'trip_id' => $ongoingTrip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_ALIGHTED,
            'passenger_delta' => 7,
            'onboard_after' => 13,
            'recorded_at' => now()->setTime(8, 35),
        ]);
        TripPassengerEvent::create([
            'trip_id' => $completedTrip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 50,
            'onboard_after' => 50,
            'recorded_at' => now()->subDay()->setTime(8, 15),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();

        $busCard = collect($response->json('busSummaryCards'))
            ->firstWhere('plate', $bus->plate_number);

        $this->assertNotNull($busCard);
        $this->assertSame(2, $busCard['trips']);
        $this->assertSame(24, $busCard['totalPax']);
        $this->assertSame(25, $busCard['avgPax']);
        $this->assertSame(31, $busCard['peakLoad']);
        $this->assertSame(62, $busCard['avgCapacity']);
        $this->assertNotSame(49, $busCard['peakLoad']);

        $idleBusCard = collect($response->json('busSummaryCards'))
            ->firstWhere('plate', $idleBus->plate_number);

        $this->assertNotNull($idleBusCard);
        $this->assertSame(0, $idleBusCard['trips']);
        $this->assertSame(0, $idleBusCard['totalPax']);
        $this->assertSame(0, $idleBusCard['avgPax']);
        $this->assertSame(0, $idleBusCard['peakLoad']);
    }

    public function test_top_kpi_strip_uses_buses_operated_wording_without_fake_values(): void
    {
        $view = file_get_contents(resource_path('views/admin/reports/index.blade.php'));
        $topKpiStart = strpos($view, 'TOP KPI OVERVIEW STRIP');
        $topKpiEnd = strpos($view, 'BUS RIDERSHIP SUMMARY');
        $topKpiStrip = substr($view, $topKpiStart, $topKpiEnd - $topKpiStart);
        $renderer = file_get_contents(public_path('js/admin-dashboard/analytics-renderers.js'));

        $this->assertStringContainsString('Buses Operated', $topKpiStrip);
        $this->assertStringContainsString('Passengers Handled', $topKpiStrip);
        $this->assertStringContainsString('Passengers in Selected Period', $topKpiStrip);
        $this->assertStringContainsString('Avg Peak Load / Trip', $topKpiStrip);
        $this->assertStringContainsString('Recorded boarded events in selected period', $topKpiStrip);
        $this->assertStringContainsString('Recorded boarded events in selected period', $topKpiStrip);
        $this->assertStringContainsString('Average peak load per actual trip in selected period', $topKpiStrip);
        $this->assertStringContainsString('Actual operations', $topKpiStrip);
        $this->assertStringContainsString('completed trips in selected period', $topKpiStrip);
        $this->assertStringNotContainsString('Fleet Util.', $topKpiStrip);
        $this->assertStringNotContainsString('Total Pax Today', $topKpiStrip);
        $this->assertStringNotContainsString('Pax This Week', $topKpiStrip);
        $this->assertStringNotContainsString('Avg Pax / Trip', $topKpiStrip);
        $this->assertStringNotContainsString('On-Time Performance', $topKpiStrip);
        $this->assertStringNotContainsString('On-Time Rate', $topKpiStrip);
        $this->assertStringNotContainsString('Actual timing source required', $topKpiStrip);
        $this->assertStringNotContainsString('not schedule-status backed', $topKpiStrip);
        $this->assertStringNotContainsString('Delayed trips', $topKpiStrip);
        $this->assertStringNotContainsString('9 of 12 active', $topKpiStrip);
        $this->assertStringNotContainsString('78%', $topKpiStrip);
        $this->assertStringNotContainsString('1,284', $topKpiStrip);
        $this->assertStringNotContainsString('8,471', $topKpiStrip);
        $this->assertStringNotContainsString('43.2', $topKpiStrip);
        $this->assertStringNotContainsString('91% Completion', $topKpiStrip);
        $this->assertStringNotContainsString('of 32 scheduled', $topKpiStrip);
        $this->assertStringNotContainsString('+8% vs yesterday', $topKpiStrip);
        $this->assertStringNotContainsString('+3% vs last week', $topKpiStrip);
        $this->assertStringNotContainsString('-2% vs yesterday', $topKpiStrip);
        $this->assertStringNotContainsString('-5% vs yesterday', $topKpiStrip);
        $this->assertStringNotContainsString('8 buses', $topKpiStrip);

        $this->assertStringContainsString('buses operated in selected period', $renderer);
        $this->assertStringContainsString('completed trips in selected period', $renderer);
        $this->assertStringNotContainsString('kpi-on-time-rate', $renderer);
        $this->assertStringNotContainsString('kpi-on-time-sub', $renderer);
        $this->assertStringNotContainsString('not schedule-status backed', $renderer);
        $this->assertStringNotContainsString('trips_scheduled > 0', $renderer);
        $this->assertStringNotContainsString('delayed trips today', $renderer);
        $this->assertStringNotContainsString('of ${kpisData.trips_scheduled || 0} scheduled', $renderer);
        $this->assertStringNotContainsString('+8% vs yesterday', $renderer);
        $this->assertStringNotContainsString('+3% vs last week', $renderer);
        $this->assertStringNotContainsString('-2% vs yesterday', $renderer);
    }

    public function test_admin_analytics_has_shared_reporting_period_presets(): void
    {
        $view = file_get_contents(resource_path('views/admin/reports/index.blade.php'));
        $partial = file_get_contents(resource_path('views/admin/reports/partials/reporting-period-filter.blade.php'));
        $data = file_get_contents(public_path('js/admin-dashboard/analytics-data.js'));
        $interactions = file_get_contents(public_path('js/admin-dashboard/analytics-interactions.js'));

        foreach ([
            'Today',
            'Yesterday',
            'Last 7 Days',
            'Last 30 Days',
            'This Month',
            'Last Month',
            'This Year',
            'Custom Range',
        ] as $label) {
            $this->assertStringContainsString($label, $partial);
        }

        $this->assertSame(3, substr_count($view, "admin.reports.partials.reporting-period-filter"));
        $this->assertStringContainsString('analytics-period-label', $view);
        $this->assertStringContainsString('getAnalyticsPresetRange', $data);
        $this->assertStringContainsString("url.searchParams.set('start'", $data);
        $this->assertStringContainsString("url.searchParams.set('end'", $data);
        $this->assertStringContainsString("url.searchParams.set('period'", $data);
        $this->assertStringContainsString('applyAnalyticsReportingPeriod', $interactions);
        $this->assertStringContainsString('data-analytics-custom-apply', $interactions);
    }

    public function test_forecast_cards_use_direction_aware_advisory_copy_without_demo_values(): void
    {
        $view = file_get_contents(resource_path('views/admin/reports/index.blade.php'));
        $renderer = file_get_contents(public_path('js/admin-dashboard/analytics-renderers.js'));
        $data = file_get_contents(public_path('js/admin-dashboard/analytics-data.js'));
        $charts = file_get_contents(public_path('js/admin-dashboard/analytics-charts.js'));

        foreach ([
            'Dispatch Forecast',
            'Direction-aware | Advisory only',
            'Uses finalized same-weekday demand for each route direction.',
            'Expected Demand Volume',
            'Peak Minimum Buses',
            'No official service direction is configured for tomorrow.',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $view . $renderer . $data);
        }

        $this->assertStringContainsString("switchPredictionRoute('all')", $charts);

        foreach ([
            'Tomorrow\'s Dispatch Action Plan',
            '8 buses',
            '6 peak hours',
            '7-8 AM (+2)',
            '5-6 PM (+2)',
            '1,284 pax / day',
            '29 recommended',
            'Expected highest boarding',
            'Pasig City Hall',
            '~67 passengers',
            '1,247 pax',
            '891 pax',
            '+4.2%',
            'todayPax = 1284',
            '|| 1284',
            'pax / day',
        ] as $demoText) {
            $this->assertStringNotContainsString($demoText, $view);
            $this->assertStringNotContainsString($demoText, $renderer);
            $this->assertStringNotContainsString($demoText, $data);
            $this->assertStringNotContainsString($demoText, $charts);
        }
    }

    public function test_admin_analytics_publishes_tomorrow_direction_forecast_without_creating_trips(): void
    {
        $route = $this->createPublicRoute();
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'SPED',
            'destination_name' => 'Ligaya',
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);
        RouteServiceSchedule::create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'first_trip_time' => '05:30:00',
            'last_trip_time' => '09:00:00',
            'service_configuration' => 'with_designated_stops',
            'service_days' => ['sat'],
            'is_active' => true,
            'source' => RouteServiceSchedule::SOURCE_BENEFICIARY_OFFICIAL,
        ]);

        foreach ([
            ['2026-07-18', 9],
            ['2026-07-25', 18],
            ['2026-08-01', 27],
        ] as [$date, $commuters]) {
            DemandHistory::create([
                'route_id' => $route->id,
                'route_variant_id' => $variant->id,
                'date' => $date,
                'time_slot' => '05:00-09:00',
                'day_of_week' => 'Saturday',
                'total_commuters' => $commuters,
                'buses_dispatched' => 1,
                'source' => DemandHistory::SOURCE_ACTUAL_REBUILD,
                'is_training_eligible' => true,
                'finalized_at' => $date.' 10:00:00',
            ]);
        }

        $response = $this->getJson(route('admin.api.analytics'));

        $response->assertOk();
        $response->assertJsonPath('demandForecast.target_date', '2026-08-08');
        $response->assertJsonPath('demandForecast.advisory_only', true);
        $response->assertJsonPath('forecastTable.0.route_variant_id', $variant->id);
        $response->assertJsonPath('forecastTable.0.direction', 'outbound');
        $response->assertJsonPath('forecastTable.0.expected_commuters', 18);
        $response->assertJsonPath('forecastTable.0.minimum_buses', 1);
        $response->assertJsonPath('forecastTable.0.status', 'ready');
        $this->assertDatabaseCount('trips', 0);
    }

    public function test_route_performance_uses_only_canonical_routes_even_when_all_routes_cache_is_stale(): void
    {
        $routes = collect([
            Route::factory()->create(['name' => 'Route 2', 'status' => 'Active', 'color' => '#003F87']),
            Route::factory()->create(['name' => 'Route 3', 'status' => 'Active', 'color' => '#639922']),
            Route::factory()->create(['name' => 'Route 4', 'status' => 'Active', 'color' => '#BA7517']),
        ]);

        $nonOfficialRoutes = collect([
            Route::factory()->create(['name' => 'Route A', 'status' => 'Active']),
            Route::factory()->create(['name' => 'Route B', 'status' => 'Active']),
            Route::factory()->create(['name' => 'Route C', 'status' => 'Active']),
            Route::factory()->create(['name' => 'Route D', 'status' => 'Active']),
            Route::factory()->create(['name' => 'Route 9', 'status' => 'Active']),
            Route::factory()->create(['name' => 'UAT Suspend Route - Bidirectional', 'status' => 'Active']),
            Route::factory()->create(['name' => 'Bridgetowne', 'status' => 'Active']),
        ]);

        $nonOfficialRoutes->each->delete();
        Cache::put('routes_all', $routes->merge($nonOfficialRoutes), 86400);

        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();

        foreach ($routes as $index => $route) {
            Schedule::factory()->create([
                'route_id' => $route->id,
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'service_date' => now()->toDateString(),
                'departure_time' => sprintf('0%d:30:00', 5 + $index),
                'passengers' => 10 + $index,
            ]);
        }

        foreach ($nonOfficialRoutes as $route) {
            Schedule::factory()->create([
                'route_id' => $route->id,
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'service_date' => now()->toDateString(),
                'departure_time' => '07:30:00',
                'passengers' => 999,
            ]);
        }

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();

        $routeNames = collect($response->json('routeComparison'))->pluck('route')->all();
        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $routeNames);

        $hourlyRow = collect($response->json('hourlyRidership'))->first();
        $this->assertNotNull($hourlyRow);
        $this->assertArrayHasKey('Route 2', $hourlyRow);
        $this->assertArrayHasKey('Route 3', $hourlyRow);
        $this->assertArrayHasKey('Route 4', $hourlyRow);

        foreach (['Route A', 'Route B', 'Route C', 'Route D', 'Route 9', 'UAT Suspend Route - Bidirectional', 'Bridgetowne'] as $routeName) {
            $this->assertNotContains($routeName, $routeNames);
            $this->assertArrayNotHasKey($routeName, $hourlyRow);
        }
    }

    public function test_route_performance_uses_actual_trip_lifecycle_not_schedule_rows(): void
    {
        $route2 = Route::factory()->create(['name' => 'Route 2', 'status' => 'Active', 'color' => '#003F87']);
        $route3 = Route::factory()->create(['name' => 'Route 3', 'status' => 'Active', 'color' => '#639922']);
        $route4 = Route::factory()->create(['name' => 'Route 4', 'status' => 'Active', 'color' => '#BA7517']);
        $driver = Driver::factory()->create();
        $bus = Bus::factory()->create();

        foreach ([$route2, $route3, $route4] as $route) {
            Schedule::factory()->create([
                'route_id' => $route->id,
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'service_date' => now()->toDateString(),
                'departure_time' => '06:00:00',
                'passengers' => 999,
            ]);
        }

        Trip::factory()->create([
            'route_id' => $route2->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 10),
            'ended_at' => now()->setTime(8, 30),
        ]);

        Trip::factory()->create([
            'route_id' => $route2->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'started_at' => now()->setTime(7, 15),
            'ended_at' => null,
        ]);

        Trip::factory()->create([
            'route_id' => $route2->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'dispatched',
            'started_at' => null,
            'dispatched_at' => now()->setTime(8, 45),
            'ended_at' => null,
        ]);

        Trip::factory()->create([
            'route_id' => $route2->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
            'started_at' => null,
            'ended_at' => now()->setTime(9, 15),
        ]);

        Trip::factory()->create([
            'route_id' => $route2->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
            'started_at' => null,
            'ended_at' => null,
        ]);

        Trip::factory()->create([
            'route_id' => $route3->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 30),
            'ended_at' => now()->setTime(8, 45),
        ]);

        Trip::factory()->create([
            'route_id' => $route3->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->subDay()->setTime(6, 30),
            'ended_at' => now()->subDay()->setTime(8, 45),
        ]);

        Trip::factory()->create([
            'route_id' => $route3->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'started_at' => now()->addDay()->setTime(7, 15),
            'ended_at' => null,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();

        $routeComparison = collect($response->json('routeComparison'))->keyBy('route');

        $this->assertSame(2, $routeComparison['Route 2']['tripsRun']);
        $this->assertSame(1, $routeComparison['Route 2']['completedTrips']);
        $this->assertSame(1, $routeComparison['Route 2']['ongoingTrips']);
        $this->assertSame(1, $routeComparison['Route 2']['dispatchedTrips']);
        $this->assertSame(1, $routeComparison['Route 2']['cancelledTrips']);
        $this->assertSame(50, $routeComparison['Route 2']['completionRate']);
        $this->assertSame(67, $routeComparison['Route 2']['percentage']);
        $this->assertSame(0, $routeComparison['Route 2']['pax']);
        $this->assertSame('No data', $routeComparison['Route 2']['avgPax']);
        $this->assertSame('No data', $routeComparison['Route 2']['peakHour']);

        $this->assertSame(1, $routeComparison['Route 3']['tripsRun']);
        $this->assertSame(1, $routeComparison['Route 3']['completedTrips']);
        $this->assertSame(0, $routeComparison['Route 3']['ongoingTrips']);
        $this->assertSame(100, $routeComparison['Route 3']['completionRate']);
        $this->assertSame(33, $routeComparison['Route 3']['percentage']);

        $this->assertSame(0, $routeComparison['Route 4']['tripsRun']);
        $this->assertSame(0, $routeComparison['Route 4']['completedTrips']);
        $this->assertSame(0, $routeComparison['Route 4']['ongoingTrips']);
        $this->assertSame('No data', $routeComparison['Route 4']['completionRate']);
        $this->assertSame(0, $routeComparison['Route 4']['percentage']);

        $hourlyRow = collect($response->json('hourlyRidership'))->firstWhere('hour', '05:00-09:00');
        $this->assertSame(2, $hourlyRow['Route 2']);
        $this->assertSame(1, $hourlyRow['Route 3']);
        $this->assertSame(0, $hourlyRow['Route 4']);
    }

    public function test_driver_performance_uses_actual_trip_lifecycle_not_schedule_or_driver_counters(): void
    {
        $route = $this->createPublicRoute();
        $bus = Bus::factory()->create([
            'plate_number' => 'PAS-222',
            'capacity' => 45,
        ]);
        $driver = Driver::factory()->create([
            'first_name' => 'Actual',
            'last_name' => 'Operator',
            'assigned_bus' => $bus->plate_number,
            'assigned_route' => $route->id,
            'trips_today' => 99,
            'pax_today' => 999,
            'incidents_30' => 7,
            'performance_score' => 10,
        ]);

        Schedule::factory()->count(3)->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
            'passengers' => 999,
        ]);

        $completedTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(7, 0),
            'peak_passengers' => 31,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'started_at' => now()->setTime(8, 0),
            'ended_at' => null,
            'peak_passengers' => 42,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'dispatched',
            'started_at' => null,
            'dispatched_at' => now()->setTime(9, 0),
            'ended_at' => null,
            'peak_passengers' => 12,
        ]);

        $cancelledTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
            'started_at' => now()->setTime(9, 15),
            'ended_at' => now()->setTime(9, 30),
            'peak_passengers' => 18,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
            'started_at' => now()->setTime(10, 0),
            'ended_at' => null,
            'peak_passengers' => 45,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->subDay()->setTime(6, 0),
            'ended_at' => now()->subDay()->setTime(7, 0),
            'peak_passengers' => 44,
        ]);

        Incident::create([
            'trip_id' => $cancelledTrip->id,
            'driver_id' => $driver->id,
            'type' => 'Breakdown',
            'description' => 'Date scoped incident.',
            'status' => 'reported',
            'reported_at' => now()->setTime(9, 20),
        ]);

        Incident::create([
            'trip_id' => $cancelledTrip->id,
            'driver_id' => $driver->id,
            'type' => 'Accident',
            'description' => 'Old incident.',
            'status' => 'reported',
            'reported_at' => now()->subDay(),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();

        $driverRow = collect($response->json('driverPerformance'))
            ->firstWhere('name', 'Actual Operator');

        $this->assertNotNull($driverRow);
        $this->assertSame(2, $driverRow['tripsRun']);
        $this->assertSame(1, $driverRow['completedTrips']);
        $this->assertSame(1, $driverRow['ongoingTrips']);
        $this->assertSame(1, $driverRow['dispatchedTrips']);
        $this->assertSame(1, $driverRow['cancelledTrips']);
        $this->assertSame(42, $driverRow['peakLoad']);
        $this->assertSame(1, $driverRow['incidents']);
        $this->assertSame(90, $driverRow['operationalScore']);
        $this->assertSame(1, $driverRow['qualifyingIncidents']);
        $this->assertSame(0, $driverRow['pax']);
        $this->assertSame('Deferred', $driverRow['avgPax']);
    }

    public function test_driver_peak_load_ignores_dispatched_and_cancelled_trips(): void
    {
        $route = $this->createPublicRoute();
        $driver = Driver::factory()->create([
            'first_name' => 'Peak',
            'last_name' => 'Operator',
        ]);
        $bus = Bus::factory()->create();

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(7, 0),
            'peak_passengers' => 31,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'started_at' => now()->setTime(8, 0),
            'ended_at' => null,
            'peak_passengers' => 42,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'dispatched',
            'started_at' => null,
            'dispatched_at' => now()->setTime(9, 0),
            'ended_at' => null,
            'peak_passengers' => 80,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
            'started_at' => now()->setTime(9, 15),
            'ended_at' => now()->setTime(9, 30),
            'peak_passengers' => 90,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $driverRow = collect($response->json('driverPerformance'))
            ->firstWhere('name', 'Peak Operator');

        $this->assertNotNull($driverRow);
        $this->assertSame(2, $driverRow['tripsRun']);
        $this->assertSame(1, $driverRow['dispatchedTrips']);
        $this->assertSame(1, $driverRow['cancelledTrips']);
        $this->assertSame(42, $driverRow['peakLoad']);
    }

    public function test_driver_operational_score_ignores_cancelled_trips_and_non_qualifying_incidents(): void
    {
        $route = $this->createPublicRoute();
        $bus = Bus::factory()->create();
        $driver = Driver::factory()->create([
            'performance_score' => 5,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(7, 0),
        ]);

        $cancelledTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
            'started_at' => now()->setTime(8, 0),
            'ended_at' => now()->setTime(8, 15),
        ]);

        Incident::create([
            'trip_id' => $cancelledTrip->id,
            'driver_id' => $driver->id,
            'type' => Incident::getTrafficDelayType(),
            'description' => 'Traffic delay does not affect operational score.',
            'status' => 'reported',
            'reported_at' => now()->setTime(8, 5),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $driverRow = collect($response->json('driverPerformance'))
            ->firstWhere('name', "{$driver->first_name} {$driver->last_name}");

        $this->assertNotNull($driverRow);
        $this->assertSame(1, $driverRow['completedTrips']);
        $this->assertSame(1, $driverRow['cancelledTrips']);
        $this->assertSame(0, $driverRow['qualifyingIncidents']);
        $this->assertSame(100, $driverRow['operationalScore']);

        Incident::create([
            'trip_id' => $cancelledTrip->id,
            'driver_id' => $driver->id,
            'type' => Incident::getAccidentType(),
            'description' => 'Qualifying incident applies the configured penalty.',
            'status' => 'reported',
            'reported_at' => now()->setTime(8, 10),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $driverRow = collect($response->json('driverPerformance'))
            ->firstWhere('name', "{$driver->first_name} {$driver->last_name}");

        $this->assertSame(1, $driverRow['qualifyingIncidents']);
        $this->assertSame(90, $driverRow['operationalScore']);
    }

    public function test_route_performance_section_has_no_demo_placeholder_data(): void
    {
        $view = file_get_contents(resource_path('views/admin/reports/index.blade.php'));
        $sectionStart = strpos($view, 'ROUTE PERFORMANCE ANALYTICS SCREEN');
        $sectionEnd = strpos($view, 'DRIVER PERFORMANCE ANALYTICS SCREEN');
        $routePerformanceSection = substr($view, $sectionStart, $sectionEnd - $sectionStart);

        foreach ([
            '>Route A<',
            '>Route B<',
            '>Route C<',
            '1,284',
            'Peak hours identified',
            'AM Peak',
            'PM Peak',
            '322 pax',
            '298 pax',
            'Pasig City Hall',
            'Ortigas Center',
            'Shaw Blvd',
            'Kapitolyo',
            'Rosario',
        ] as $demoText) {
            $this->assertStringNotContainsString($demoText, $routePerformanceSection);
        }

        $renderer = file_get_contents(public_path('js/admin-dashboard/analytics-renderers.js'));
        $charts = file_get_contents(public_path('js/admin-dashboard/analytics-charts.js'));

        foreach (['+4%', '+2%', '-1%', 'Most consistent: Weekdays', 'AM Peak', 'PM Peak', '322 pax', '298 pax'] as $demoText) {
            $this->assertStringNotContainsString($demoText, $renderer);
            $this->assertStringNotContainsString($demoText, $charts);
        }
    }

    public function test_route_demand_sections_are_marked_separately_from_driver_operations(): void
    {
        $view = file_get_contents(resource_path('views/admin/reports/index.blade.php'));
        $sectionStart = strpos($view, 'ROUTE PERFORMANCE ANALYTICS SCREEN');
        $sectionEnd = strpos($view, 'DRIVER PERFORMANCE ANALYTICS SCREEN');
        $routePerformanceSection = substr($view, $sectionStart, $sectionEnd - $sectionStart);

        $this->assertStringContainsString('Route demand signals', $routePerformanceSection);
        $this->assertStringContainsString('Separate from driver-operation metrics', $routePerformanceSection);
        $this->assertStringContainsString('DemandHistory averages; not Trip lifecycle data', $routePerformanceSection);
        $this->assertStringContainsString('Top 5 stops demand flow', $routePerformanceSection);

        $renderer = file_get_contents(public_path('js/admin-dashboard/analytics-renderers.js'));
        $charts = file_get_contents(public_path('js/admin-dashboard/analytics-charts.js'));

        $this->assertStringContainsString('No stop demand data available', $renderer);
        $this->assertStringContainsString("val + ' requests'", $charts);
    }

    public function test_driver_performance_section_has_actual_operations_labels_and_no_demo_fallback_rows(): void
    {
        $view = file_get_contents(resource_path('views/admin/reports/index.blade.php'));
        $sectionStart = strpos($view, 'DRIVER PERFORMANCE TABLE');
        $sectionEnd = strpos($view, 'REPORT GENERATION');
        $driverPerformanceSection = substr($view, $sectionStart, $sectionEnd - $sectionStart);
        $renderer = file_get_contents(public_path('js/admin-dashboard/analytics-renderers.js'));

        foreach ([
            'Driver operations by selected period',
            'Trips run',
            'Completed',
            'Ongoing',
            'Dispatched',
            'Cancelled',
            'Peak load',
            'Operational safety score',
            'Incidents',
        ] as $expectedLabel) {
            $this->assertStringContainsString($expectedLabel, $driverPerformanceSection);
        }

        $this->assertStringContainsString('Safety score shows 100 when the driver has actual trips and no Accident/Breakdown incidents in the selected period. No trips means No data.', $driverPerformanceSection);

        foreach ([
            'Ridership by driver',
            'Trips Today',
            'Total Pax Served',
            'Avg Pax/Trip',
            'Peak Load Reached',
            'Ana Flores',
            'Juan dela Cruz',
            'Maria Santos',
            'Pedro Garcia',
            'Route A',
            'Route B',
            'Route C',
            '221 pax',
            '187 pax',
            'Full x3',
        ] as $staleText) {
            $this->assertStringNotContainsString($staleText, $driverPerformanceSection);
        }

        $this->assertStringContainsString('driver.tripsRun', $renderer);
        $this->assertStringContainsString('driver.completedTrips', $renderer);
        $this->assertStringContainsString('driver.ongoingTrips', $renderer);
        $this->assertStringContainsString('driver.dispatchedTrips', $renderer);
        $this->assertStringContainsString('driver.cancelledTrips', $renderer);
        $this->assertStringContainsString('driver.operationalScore', $renderer);
        $this->assertStringNotContainsString('${driver.pax} pax', $renderer);
        $this->assertStringNotContainsString('${driver.avgPax}', $renderer);
    }

    public function test_trip_load_table_uses_actual_trip_records_not_schedule_rows(): void
    {
        $route = $this->createPublicRoute();
        $bus = Bus::factory()->create(['plate_number' => 'PAS-LOAD-1']);
        $driver = Driver::factory()->create([
            'first_name' => 'Load',
            'last_name' => 'Driver',
        ]);

        $schedule = Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
            'passengers' => 99,
        ]);

        $completedTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(7, 0),
            'peak_passengers' => 31,
            'schedule_id' => null,
        ]);

        TripLog::factory()->create([
            'trip_id' => $completedTrip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'started_at' => $completedTrip->started_at,
            'completed_at' => $completedTrip->ended_at,
            'passengers' => 14,
            'alighted_passengers' => 9,
            'peak_passengers' => 99,
            'status' => 'completed',
        ]);

        $cancelledTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
            'started_at' => now()->setTime(7, 15),
            'ended_at' => now()->setTime(7, 45),
            'peak_passengers' => 22,
            'schedule_id' => $schedule->id,
        ]);

        TripLog::factory()->create([
            'trip_id' => $cancelledTrip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'started_at' => $cancelledTrip->started_at,
            'completed_at' => $cancelledTrip->ended_at,
            'passengers' => 8,
            'alighted_passengers' => 5,
            'peak_passengers' => 88,
            'status' => 'cancelled',
        ]);

        $ongoingTrip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'started_at' => now()->setTime(8, 0),
            'ended_at' => null,
            'peak_passengers' => 18,
        ]);

        TripPassengerEvent::create([
            'trip_id' => $ongoingTrip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 6,
            'onboard_after' => 6,
            'recorded_at' => now()->setTime(8, 5),
        ]);
        TripPassengerEvent::create([
            'trip_id' => $ongoingTrip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 4,
            'onboard_after' => 10,
            'recorded_at' => now()->setTime(8, 10),
        ]);
        TripPassengerEvent::create([
            'trip_id' => $ongoingTrip->id,
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'event_type' => TripPassengerEvent::TYPE_ALIGHTED,
            'passenger_delta' => 3,
            'onboard_after' => 7,
            'recorded_at' => now()->setTime(8, 20),
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'dispatched',
            'started_at' => null,
            'dispatched_at' => now()->setTime(9, 0),
            'ended_at' => null,
            'peak_passengers' => 7,
        ]);

        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->subDay()->setTime(6, 0),
            'ended_at' => now()->subDay()->setTime(7, 0),
            'peak_passengers' => 55,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();

        $rows = collect($response->json('tripPaxTable'));

        $this->assertCount(4, $rows);
        $this->assertSame(['Completed', 'Cancelled', 'Ongoing', 'Dispatched'], $rows->pluck('status')->all());
        $this->assertSame([31, 22, 18, 7], $rows->pluck('peakLoad')->all());
        $this->assertSame([14, 8, 10, 0], $rows->pluck('recordedBoarded')->all());
        $this->assertSame([9, 5, 3, 0], $rows->pluck('recordedAlighted')->all());
        $this->assertSame('Load Driver', $rows->first()['driver']);
        $this->assertSame('PAS-LOAD-1', $rows->first()['plate']);
        $this->assertSame('Route 2', $rows->first()['route']);
        $this->assertArrayNotHasKey('capacity', $rows->first());
        $this->assertNotContains(99, $rows->pluck('peakLoad')->all());
        $this->assertNotContains(88, $rows->pluck('peakLoad')->all());
        $this->assertNotContains(99, $rows->pluck('recordedBoarded')->all());
        $this->assertFalse($rows->contains(fn ($row) => $row['peakLoad'] === 55));
    }

    public function test_schedule_rows_alone_do_not_populate_trip_load_table(): void
    {
        $route = $this->createPublicRoute();
        $bus = Bus::factory()->create();
        $driver = Driver::factory()->create();

        Schedule::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now()->toDateString(),
            'passengers' => 88,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $this->assertSame([], $response->json('tripPaxTable'));
    }

    public function test_non_official_route_operations_do_not_leak_into_fleet_or_driver_analytics(): void
    {
        $officialRoute = $this->createPublicRoute();
        $staleRoute = Route::factory()->create([
            'name' => 'Route A',
            'status' => 'Active',
        ]);
        $bus = Bus::factory()->create([
            'plate_number' => 'PAS-SCOPE-1',
            'capacity' => 45,
        ]);
        $driver = Driver::factory()->create([
            'first_name' => 'Scoped',
            'last_name' => 'Operator',
            'assigned_bus' => $bus->plate_number,
            'assigned_route' => $officialRoute->id,
        ]);

        $officialTrip = Trip::factory()->create([
            'route_id' => $officialRoute->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(7, 0),
            'peak_passengers' => 7,
        ]);
        $staleTrip = Trip::factory()->create([
            'route_id' => $staleRoute->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(8, 0),
            'ended_at' => now()->setTime(9, 0),
            'peak_passengers' => 99,
        ]);

        TripPassengerEvent::create([
            'trip_id' => $officialTrip->id,
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $officialRoute->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 4,
            'onboard_after' => 4,
            'recorded_at' => now()->setTime(6, 15),
        ]);
        TripPassengerEvent::create([
            'trip_id' => $staleTrip->id,
            'driver_id' => $driver->id,
            'bus_id' => $bus->id,
            'route_id' => $staleRoute->id,
            'event_type' => TripPassengerEvent::TYPE_BOARDED,
            'passenger_delta' => 90,
            'onboard_after' => 90,
            'recorded_at' => now()->setTime(8, 15),
        ]);

        Incident::create([
            'trip_id' => $staleTrip->id,
            'driver_id' => $driver->id,
            'type' => 'Accident',
            'description' => 'Stale route incident should not affect official analytics.',
            'status' => 'reported',
            'reported_at' => now()->setTime(8, 30),
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $data = $response->json();

        $this->assertSame(1, $data['kpis']['trips_completed']);
        $this->assertSame(4, $data['kpis']['total_pax_today']);

        $tripRows = collect($data['tripPaxTable']);
        $this->assertCount(1, $tripRows);
        $this->assertSame('Route 2', $tripRows->first()['route']);
        $this->assertSame(7, $tripRows->first()['peakLoad']);
        $this->assertSame(4, $tripRows->first()['recordedBoarded']);

        $busRow = collect($data['busSummaryCards'])->firstWhere('plate', $bus->plate_number);
        $this->assertSame(1, $busRow['trips']);
        $this->assertSame(4, $busRow['totalPax']);
        $this->assertSame(7, $busRow['peakLoad']);

        $driverRow = collect($data['driverPerformance'])->firstWhere('name', 'Scoped Operator');
        $this->assertSame(1, $driverRow['tripsRun']);
        $this->assertSame(1, $driverRow['completedTrips']);
        $this->assertSame(0, $driverRow['qualifyingIncidents']);
        $this->assertSame(100, $driverRow['operationalScore']);
        $this->assertSame(7, $driverRow['peakLoad']);
    }

    public function test_trip_load_records_section_uses_trip_operation_labels(): void
    {
        $view = file_get_contents(resource_path('views/admin/reports/index.blade.php'));
        $renderer = file_get_contents(public_path('js/admin-dashboard/analytics-renderers.js'));
        $interactions = file_get_contents(public_path('js/admin-dashboard/analytics-interactions.js'));

        foreach ([
            'Trip load records',
            'selected period',
            'Based on actual Trip records. Peak load is captured from driver passenger updates.',
            'Trip',
            'Driver',
            'Bus',
            'Route',
            'Status',
            'Started',
            'Ended',
            'Recorded boarded',
            'Recorded alighted',
            'Peak load',
        ] as $expectedText) {
            $this->assertStringContainsString($expectedText, $view);
        }

        foreach ([
            'Passenger count per trip',
            'Dispatch passenger records',
            'Based on dispatch rows with TripLog or passenger-flow records when available.',
            'Real-time driver accountability',
            'Trip #',
            'Pax Boarded',
            'Pax Alighted',
            'Recorded capacity %',
            'Planned departure',
            'Planned arrival',
        ] as $staleText) {
            $this->assertStringNotContainsString($staleText, $view);
        }

        $this->assertStringContainsString('No trip load records for the selected period.', $renderer);
        $this->assertStringContainsString('trip.startedAt', $renderer);
        $this->assertStringContainsString('trip.endedAt', $renderer);
        $this->assertStringContainsString('trip.recordedBoarded', $renderer);
        $this->assertStringContainsString('trip.recordedAlighted', $renderer);
        $this->assertStringContainsString('trip.peakLoad', $renderer);
        $this->assertStringContainsString('"Recorded boarded"', $interactions);
        $this->assertStringContainsString('"Recorded alighted"', $interactions);
        $this->assertStringContainsString('t.recordedBoarded', $interactions);
        $this->assertStringContainsString('t.recordedAlighted', $interactions);
        $this->assertStringNotContainsString('trip.boarded', $renderer);
        $this->assertStringNotContainsString('trip.alighted', $renderer);
        $this->assertStringNotContainsString('avg recorded boarded / dispatch row', $renderer);
        $this->assertStringNotContainsString('actual Trip-based passenger reporting', $view);
        $this->assertStringNotContainsString('actual Trip-based passenger reporting', $renderer);
    }

    public function test_route_performance_labels_match_current_data_sources(): void
    {
        $view = file_get_contents(resource_path('views/admin/reports/index.blade.php'));
        $sectionStart = strpos($view, 'ROUTE PERFORMANCE ANALYTICS SCREEN');
        $sectionEnd = strpos($view, 'DRIVER PERFORMANCE ANALYTICS SCREEN');
        $routePerformanceSection = substr($view, $sectionStart, $sectionEnd - $sectionStart);

        foreach ([
            'Trips started by time slot',
            'Actual route operations by start time',
            'Trips by route',
            'Trips run',
            'Completed',
            'Ongoing',
            'Dispatched',
            'Cancelled',
            'Completion rate',
            'Historical demand by weekday/time slot',
            'Boarding requests',
            'Alighting requests',
        ] as $expectedLabel) {
            $this->assertStringContainsString($expectedLabel, $routePerformanceSection);
        }

        foreach ([
            'Hourly ridership by route',
            'Passengers by route today',
            'Trips Today',
            'Total Pax',
            'Avg Pax/Trip',
            'Peak Hour',
            'Weekly ridership pattern',
            'Avg Boarding',
            'Avg Alight',
            'Recorded dispatch passengers by time slot',
            'Recorded dispatch passengers by route',
            'Scheduled dispatches',
            'Highest recorded dispatch hour',
        ] as $staleLabel) {
            $this->assertStringNotContainsString($staleLabel, $routePerformanceSection);
        }

        $renderer = file_get_contents(public_path('js/admin-dashboard/analytics-renderers.js'));

        $this->assertStringContainsString('${tripsRun}', $renderer);
        $this->assertStringContainsString('${completedTrips}', $renderer);
        $this->assertStringContainsString('${ongoingTrips}', $renderer);
        $this->assertStringContainsString('${dispatchedTrips}', $renderer);
        $this->assertStringContainsString('${cancelledTrips}', $renderer);
        $this->assertStringContainsString(": 'No data'", $renderer);
        $this->assertStringContainsString('${stop.boarding} requests', $renderer);
        $this->assertStringContainsString('${stop.alighting} requests', $renderer);
        $this->assertStringNotContainsString('${r.trips} trips', $renderer);
        $this->assertStringNotContainsString('${totalTrips} trips', $renderer);
        $this->assertStringNotContainsString('${r.trips} dispatches', $renderer);
        $this->assertStringNotContainsString('${stop.boarding} / day', $renderer);
        $this->assertStringNotContainsString('${stop.alighting} / day', $renderer);
    }

    public function test_analytics_date_refresh_uses_shared_payload_state(): void
    {
        $dataScript = file_get_contents(public_path('js/admin-dashboard/analytics-data.js'));
        $interactionsScript = file_get_contents(public_path('js/admin-dashboard/analytics-interactions.js'));

        $this->assertStringContainsString('function applyAnalyticsPayload(data)', $dataScript);
        $this->assertStringContainsString('busCardsData = Array.isArray(data.busSummaryCards)', $dataScript);
        $this->assertStringContainsString('applyAnalyticsPayload(data)', $interactionsScript);
        $this->assertStringNotContainsString('window.kpisData = data.kpis', $interactionsScript);
        $this->assertStringContainsString('const exportRows =', $interactionsScript);
        $this->assertStringContainsString('formatAnalyticsDate', $interactionsScript);
    }

    public function test_peak_load_timeline_uses_trip_start_time_and_dynamic_capacity(): void
    {
        $chartsScript = file_get_contents(public_path('js/admin-dashboard/analytics-charts.js'));

        $this->assertStringContainsString('getHourLabelFromTime(t.startedAt)', $chartsScript);
        $this->assertStringNotContainsString('getHourLabelFromDepTime(t.depTime)', $chartsScript);
        $this->assertStringContainsString('const timelineTrips = peakLoadTimelineData;', $chartsScript);
        $this->assertStringContainsString('const matchingTrips = timelineTrips.filter', $chartsScript);
        $this->assertStringContainsString('const chartMax =', $chartsScript);
        $this->assertStringContainsString('max: chartMax', $chartsScript);
    }

    public function test_peak_load_timeline_payload_excludes_dispatched_and_cancelled_trips(): void
    {
        $route = $this->createPublicRoute();
        $bus = Bus::factory()->create();
        $driver = Driver::factory()->create();

        $completed = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => now()->setTime(6, 0),
            'ended_at' => now()->setTime(6, 30),
            'peak_passengers' => 31,
        ]);
        $ongoing = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'ongoing',
            'started_at' => now()->setTime(7, 0),
            'ended_at' => null,
            'peak_passengers' => 22,
        ]);
        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'dispatched',
            'started_at' => null,
            'dispatched_at' => now()->setTime(8, 0),
            'peak_passengers' => 80,
        ]);
        Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'cancelled',
            'started_at' => now()->setTime(8, 15),
            'ended_at' => now()->setTime(8, 30),
            'peak_passengers' => 90,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => now()->toDateString(),
            'end' => now()->toDateString(),
        ]));

        $response->assertOk();
        $payload = $response->json();
        $timeline = collect($payload['peakLoadTimeline']);

        $this->assertCount(4, $payload['tripPaxTable']);
        $this->assertCount(2, $timeline);
        $this->assertSame(
            [$completed->id, $ongoing->id],
            $timeline->pluck('tripNo')->map(fn ($tripNo) => (int) str_replace('TRIP-', '', $tripNo))->values()->all()
        );
        $this->assertSame(['Completed', 'Ongoing'], $timeline->pluck('status')->values()->all());
        $this->assertSame([31, 22], $timeline->pluck('peakLoad')->values()->all());
    }

    public function test_trip_times_and_hourly_buckets_use_asia_manila(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 02:00:00', 'UTC'));

        $route = $this->createPublicRoute();
        $bus = Bus::factory()->create();
        $driver = Driver::factory()->create();

        TimeSlotConfiguration::create([
            'name' => 'Afternoon',
            'start_time' => '15:00:00',
            'end_time' => '16:00:00',
            'time_slot_display' => '15:00-16:00',
            'is_active' => true,
            'order' => 2,
        ]);

        $trip = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => '2026-08-07 07:22:32',
            'ended_at' => '2026-08-07 07:30:00',
            'peak_passengers' => 12,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => '2026-08-07',
            'end' => '2026-08-07',
        ]));

        $response->assertOk();
        $tripNumber = 'TRIP-' . str_pad((string) $trip->id, 3, '0', STR_PAD_LEFT);
        $tripRow = collect($response->json('tripPaxTable'))->firstWhere('tripNo', $tripNumber);
        $timelineRow = collect($response->json('peakLoadTimeline'))->firstWhere('tripNo', $tripNumber);
        $morning = collect($response->json('hourlyRidership'))->firstWhere('hour', '05:00-09:00');
        $afternoon = collect($response->json('hourlyRidership'))->firstWhere('hour', '15:00-16:00');

        $this->assertSame('3:22 PM', $tripRow['startedAt']);
        $this->assertSame('3:30 PM', $tripRow['endedAt']);
        $this->assertSame('3:22 PM', $timelineRow['startedAt']);
        $this->assertSame(0, $morning[$route->name]);
        $this->assertSame(1, $afternoon[$route->name]);
    }

    public function test_selected_report_date_uses_manila_midnight_boundaries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 02:00:00', 'UTC'));

        $route = $this->createPublicRoute();
        $bus = Bus::factory()->create();
        $driver = Driver::factory()->create();

        $inside = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => '2026-08-06 16:10:00',
            'ended_at' => '2026-08-06 16:30:00',
            'peak_passengers' => 4,
        ]);
        $outside = Trip::factory()->create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'status' => 'completed',
            'started_at' => '2026-08-07 16:10:00',
            'ended_at' => '2026-08-07 16:30:00',
            'peak_passengers' => 8,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => '2026-08-07',
            'end' => '2026-08-07',
        ]));

        $response->assertOk();
        $tripIds = collect($response->json('tripPaxTable'))
            ->map(fn (array $row) => (int) str_replace('TRIP-', '', $row['tripNo']));
        $routeRow = collect($response->json('routeComparison'))->firstWhere('route', $route->name);
        $driverRow = collect($response->json('driverPerformance'))->firstWhere('name', $driver->name);

        $this->assertTrue($tripIds->contains($inside->id));
        $this->assertFalse($tripIds->contains($outside->id));
        $this->assertSame(1, $response->json('kpis.trips_completed'));
        $this->assertSame(1, $routeRow['completedTrips']);
        $this->assertSame(1, $driverRow['completedTrips']);
    }

    public function test_historical_demand_uses_finalized_actual_direction_series_for_fixed_manila_range(): void
    {
        $route2 = $this->createPublicRoute('Route 2');
        $route3 = $this->createPublicRoute('Route 3');
        $route4 = $this->createPublicRoute('Route 4');
        $outbound2 = $this->createDirectionVariant($route2, 'outbound');
        $inbound2 = $this->createDirectionVariant($route2, 'inbound');
        $outbound3 = $this->createDirectionVariant($route3, 'outbound');
        $this->createDirectionVariant($route3, 'inbound');
        $this->createDirectionVariant($route4, 'outbound');
        $this->createDirectionVariant($route4, 'inbound');

        TimeSlotConfiguration::create([
            'name' => 'Late Morning',
            'start_time' => '09:00:00',
            'end_time' => '10:00:00',
            'time_slot_display' => '09:00-10:00',
            'is_active' => true,
            'order' => 2,
        ]);

        $this->createFinalizedHistory($route2, $outbound2, '2026-08-06', '05:00-09:00', 3);
        $this->createFinalizedHistory($route2, $inbound2, '2026-08-06', '05:00-09:00', 0);
        $this->createFinalizedHistory($route2, $inbound2, '2026-08-06', '09:00-10:00', 0);
        $this->createFinalizedHistory($route3, $outbound3, '2026-08-06', '05:00-09:00', 4);

        DemandHistory::create([
            'route_id' => $route2->id,
            'route_variant_id' => $outbound2->id,
            'date' => '2026-08-05',
            'time_slot' => '05:00-09:00',
            'day_of_week' => 'Wednesday',
            'total_commuters' => 99,
            'buses_dispatched' => 0,
            'source' => DemandHistory::SOURCE_ACTUAL_RUNTIME,
            'is_training_eligible' => false,
        ]);
        DemandHistory::create([
            'route_id' => $route2->id,
            'date' => '2026-08-04',
            'time_slot' => '05:00-09:00',
            'day_of_week' => 'Tuesday',
            'total_commuters' => 88,
            'buses_dispatched' => 0,
        ]);

        $legacyRoute = Route::factory()->create(['name' => 'Route A', 'status' => 'Active']);
        $legacyVariant = $this->createDirectionVariant($legacyRoute, 'outbound');
        $this->createFinalizedHistory($legacyRoute, $legacyVariant, '2026-08-06', '05:00-09:00', 77);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => '2026-01-01',
            'end' => '2026-01-02',
        ]));

        $response->assertOk();
        $response->assertJsonPath('historicalDemand.range.start', '2026-07-09');
        $response->assertJsonPath('historicalDemand.range.end', '2026-08-07');
        $response->assertJsonPath('historicalDemand.range.days', 30);
        $response->assertJsonPath('historicalDemand.range.timezone', 'Asia/Manila');

        $payload = $response->json('historicalDemand');
        $series = collect($payload['series']);

        $this->assertCount(30, $payload['dates']);
        $this->assertCount(6, $series);
        $this->assertSame(
            ['Route 2 OUT', 'Route 2 IN', 'Route 3 OUT', 'Route 3 IN', 'Route 4 OUT', 'Route 4 IN'],
            $series->pluck('label')->values()->all()
        );
        $this->assertSame(['outbound', 'inbound'], $series->where('route_id', $route2->id)->pluck('direction')->values()->all());
        $this->assertFalse($series->contains('route_id', $legacyRoute->id));

        $route2Outbound = $series->firstWhere('route_variant_id', $outbound2->id);
        $route2Inbound = $series->firstWhere('route_variant_id', $inbound2->id);
        $outboundFinalizedDate = collect($route2Outbound['points'])->firstWhere('date', '2026-08-06');
        $inboundFinalizedDate = collect($route2Inbound['points'])->firstWhere('date', '2026-08-06');
        $unfinalizedDate = collect($route2Outbound['points'])->firstWhere('date', '2026-08-05');

        $this->assertSame(3, $outboundFinalizedDate['value']);
        $this->assertSame('partial', $outboundFinalizedDate['coverage']);
        $this->assertSame(1, $outboundFinalizedDate['finalized_slots']);
        $this->assertSame(2, $outboundFinalizedDate['expected_slots']);
        $this->assertSame(0, $inboundFinalizedDate['value']);
        $this->assertSame('finalized', $inboundFinalizedDate['coverage']);
        $this->assertNull($unfinalizedDate['value']);
        $this->assertSame('unavailable', $unfinalizedDate['coverage']);
        $this->assertSame(0, Trip::count());

        $compatibilityRow = collect($response->json('historicalTrend'))->firstWhere('date', '2026-08-06');
        $this->assertSame(3, $compatibilityRow['Route 2']);
        $this->assertSame(4, $compatibilityRow['Route 3']);
        $this->assertSame(7, $compatibilityRow['total']);
    }

    public function test_historical_demand_ui_uses_direction_series_without_passenger_kpi_annotation(): void
    {
        $view = file_get_contents(resource_path('views/admin/reports/index.blade.php'));
        $data = file_get_contents(public_path('js/admin-dashboard/analytics-data.js'));
        $charts = file_get_contents(public_path('js/admin-dashboard/analytics-charts.js'));

        $this->assertStringContainsString('historical-demand-legend', $view);
        $this->assertStringContainsString('historical-demand-coverage', $view);
        $this->assertStringNotContainsString('Wkday Avg', $view);
        $this->assertStringNotContainsString('Wkend Avg', $view);
        $this->assertStringNotContainsString('Growth:', $view);
        $this->assertStringContainsString('historicalDemandData = data.historicalDemand || null', $data);
        $this->assertStringContainsString("series.direction === 'inbound'", $charts);
        $this->assertStringContainsString('point.value !== null', $charts);
        $this->assertStringContainsString('spanGaps: false', $charts);
        $this->assertStringNotContainsString('kpisData.total_pax_today', $charts);
        $this->assertStringNotContainsString('Today: ${todayPax', $charts);
    }

    public function test_maintenance_log_report_payload_uses_selected_period_records(): void
    {
        $bus = Bus::factory()->create(['plate_number' => 'PAS-M01']);

        MaintenanceRecord::factory()->create([
            'bus_id' => $bus->id,
            'ticket_number' => 'MT-2026-000101',
            'type' => 'Preventive Maintenance',
            'status' => 'completed',
            'scheduled_at' => Carbon::parse('2026-08-06 08:00:00', 'Asia/Manila')->utc(),
            'completed_at' => Carbon::parse('2026-08-07 09:30:00', 'Asia/Manila')->utc(),
            'technician_name' => 'Ramon Tech',
            'inspector_name' => 'Ana Inspector',
            'maintenance_result' => 'Passed Inspection',
            'roadworthy' => true,
            'cost_php' => 1250.50,
        ]);

        MaintenanceRecord::factory()->create([
            'bus_id' => $bus->id,
            'ticket_number' => 'MT-2026-000102',
            'status' => 'in_progress',
            'scheduled_at' => Carbon::parse('2026-08-07 10:00:00', 'Asia/Manila')->utc(),
            'completed_at' => null,
        ]);

        MaintenanceRecord::factory()->create([
            'bus_id' => $bus->id,
            'ticket_number' => 'MT-2026-000103',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::parse('2026-08-09 10:00:00', 'Asia/Manila')->utc(),
            'completed_at' => null,
        ]);

        $response = $this->getJson(route('admin.api.analytics', [
            'start' => '2026-08-07',
            'end' => '2026-08-07',
        ]));

        $response->assertOk();

        $records = collect($response->json('maintenanceLogRecords'));

        $this->assertSame(2, $response->json('maintenanceSummary.total'));
        $this->assertSame(1, $response->json('maintenanceSummary.completed'));
        $this->assertSame(1, $response->json('maintenanceSummary.active'));
        $this->assertSame(['MT-2026-000102', 'MT-2026-000101'], $records->pluck('ticket')->all());

        $completed = $records->firstWhere('ticket', 'MT-2026-000101');
        $this->assertSame('PAS-M01', $completed['bus']);
        $this->assertSame('Preventive Maintenance', $completed['type']);
        $this->assertSame('completed', $completed['status']);
        $this->assertSame('Aug 07, 2026 9:30 AM', $completed['completedAt']);
        $this->assertSame('Ramon Tech', $completed['technician']);
        $this->assertSame('Ana Inspector', $completed['inspector']);
        $this->assertSame('Passed Inspection', $completed['result']);
        $this->assertSame('Yes', $completed['roadworthy']);
        $this->assertSame(1250.5, $completed['totalCost']);
    }

    private function createPublicRoute(string $name = 'Route 2'): Route
    {
        return Route::factory()->create([
            'name' => $name,
            'status' => 'Active',
        ]);
    }

    private function createDirectionVariant(Route $route, string $direction): RouteVariant
    {
        return RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => $direction === 'outbound' ? 'Origin' : 'Destination',
            'destination_name' => $direction === 'outbound' ? 'Destination' : 'Origin',
            'geometry_status' => 'valid',
            'is_default' => $direction === 'outbound',
        ]);
    }

    private function createFinalizedHistory(
        Route $route,
        RouteVariant $variant,
        string $date,
        string $timeSlot,
        int $commuters
    ): DemandHistory {
        return DemandHistory::create([
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'date' => $date,
            'time_slot' => $timeSlot,
            'day_of_week' => Carbon::parse($date)->englishDayOfWeek,
            'total_commuters' => $commuters,
            'buses_dispatched' => 0,
            'source' => DemandHistory::SOURCE_ACTUAL_REBUILD,
            'is_training_eligible' => false,
            'finalized_at' => $date.' 10:00:00',
        ]);
    }
}
