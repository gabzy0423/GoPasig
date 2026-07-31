<?php

namespace Tests\Feature;

use App\Exceptions\BusUnavailableException;
use App\Exceptions\RouteSuspendedException;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\Trip;
use App\Services\SimulationDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetRuntimeOccupancyResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_bus_dispatch_keeps_zero_runtime_occupancy(): void
    {
        [$bus, $driver, $route] = $this->dispatchableResources(['passengers' => 0]);

        $trip = SimulationDispatchService::dispatch($bus, $driver, $route);

        $this->assertSame(0, $bus->fresh()->passengers);
        $this->assertSame(0, $trip->fresh()->peak_passengers);
    }

    public function test_stale_runtime_occupancy_resets_before_first_scheduled_dispatch_of_service_day(): void
    {
        [$bus, $driver, $route] = $this->dispatchableResources(['passengers' => 18]);

        $yesterdaySchedule = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->subDay()->toDateString(),
            'departure_time' => '07:00:00',
            'arrival_time' => '07:30:00',
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'schedule_id' => $yesterdaySchedule->id,
            'status' => 'completed',
            'peak_passengers' => 18,
            'dispatched_at' => now('Asia/Manila')->subDay(),
            'ended_at' => now('Asia/Manila')->subDay()->addMinutes(30),
        ]);

        $todaySchedule = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'departure_time' => '08:00:00',
            'arrival_time' => '08:30:00',
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        $trip = SimulationDispatchService::dispatchFromSchedule($todaySchedule);

        $this->assertSame(0, $bus->fresh()->passengers);
        $this->assertSame(0, $trip->fresh()->peak_passengers);
    }

    public function test_active_trip_dispatch_does_not_reset_runtime_occupancy(): void
    {
        [$bus, $driver, $route] = $this->dispatchableResources([
            'passengers' => 12,
            'status' => 'operating',
        ]);

        Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'peak_passengers' => 12,
            'started_at' => now(),
        ]);

        $this->expectException(BusUnavailableException::class);

        try {
            SimulationDispatchService::dispatch($bus, $driver, $route);
        } finally {
            $this->assertSame(12, $bus->fresh()->passengers);
        }
    }

    public function test_failed_dispatch_validation_does_not_reset_runtime_occupancy(): void
    {
        [$bus, $driver, $route] = $this->dispatchableResources(['passengers' => 9]);
        $route->update(['status' => 'Suspended']);

        $this->expectException(RouteSuspendedException::class);

        try {
            SimulationDispatchService::dispatch($bus, $driver, $route);
        } finally {
            $this->assertSame(9, $bus->fresh()->passengers);
        }
    }

    public function test_second_dispatch_same_service_day_preserves_runtime_occupancy(): void
    {
        [$bus, $driver, $route] = $this->dispatchableResources(['passengers' => 7]);

        $sameDaySchedule = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'departure_time' => '06:00:00',
            'arrival_time' => '06:30:00',
            'status' => Schedule::STATUS_ON_TIME,
        ]);

        Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'schedule_id' => $sameDaySchedule->id,
            'status' => 'completed',
            'peak_passengers' => 20,
            'dispatched_at' => now('Asia/Manila')->subHour(),
            'ended_at' => now('Asia/Manila')->subMinutes(30),
        ]);

        $trip = SimulationDispatchService::dispatch($bus, $driver, $route);

        $this->assertSame(7, $trip->fresh()->peak_passengers);
        $this->assertSame(0, $bus->fresh()->passengers);
    }

    private function dispatchableResources(array $busOverrides = []): array
    {
        $route = Route::factory()->create(['status' => 'Active']);

        $bus = Bus::factory()->create(array_merge([
            'status' => Bus::STATUS_INACTIVE,
            'route_id' => null,
            'driver_name' => Bus::DEFAULT_DRIVER_NAME,
            'passengers' => 0,
        ], $busOverrides));

        $driver = Driver::factory()->create([
            'status' => 'active',
            'operational_status' => 'available',
            'assigned_bus' => null,
            'assigned_route' => null,
        ]);

        return [$bus, $driver, $route];
    }
}


