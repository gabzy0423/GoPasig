<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\Bus;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

class CommuterTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_commuter_tracker_empty_state_uses_official_schedule_copy_without_stale_hours(): void
    {
        Route::create([
            'name' => 'Route 2',
            'status' => 'Active',
        ]);

        Livewire::test(\App\Livewire\Commuter\Tracker::class)
            ->assertSee('No active buses right now')
            ->assertSee("Check the Schedule page for today's official operating windows.", false)
            ->assertDontSee('5:00 AM')
            ->assertDontSee('9:00 PM');
    }

    public function test_commuter_tracker_shows_correct_driver_name_or_fallback(): void
    {
        // 1. Create a Route
        $route = Route::create([
            'id' => 1,
            'name' => 'Route 2',
            'status' => 'Active'
        ]);

        // 2. Create two active buses
        $busWithDriver = Bus::create([
            'plate_number' => 'PAS-101',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 10,
            'passengers' => 12,
            'route_id' => $route->id,
        ]);

        $busWithoutDriver = Bus::create([
            'plate_number' => 'PAS-102',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 10,
            'passengers' => 15,
            'route_id' => $route->id,
        ]);

        // 3. Create a driver assigned to the first bus
        $driver1 = Driver::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'emp_id' => 'EMP-001',
            'license_number' => 'LIC-001',
            'license_expiry' => now()->addYear(),
            'assigned_bus' => 'PAS-101',
            'status' => 'active'
        ]);

        // Create ongoing trips for these buses to make them appear on the map tracker
        \App\Models\Trip::create([
            'bus_id' => $busWithDriver->id,
            'driver_id' => $driver1->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        $dummyDriver = Driver::factory()->create();
        \App\Models\Trip::create([
            'bus_id' => $busWithoutDriver->id,
            'driver_id' => $dummyDriver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        // 4. Test the Livewire component view data
        Livewire::test(\App\Livewire\Commuter\Tracker::class)
            ->assertViewHas('activeBuses', function ($buses) {
                $busesArray = collect($buses)->keyBy('plate_number');

                // Bus 1 should have the correct driver name (Juan Dela Cruz)
                if ($busesArray->get('PAS-101')->driver_name !== 'Juan Dela Cruz') {
                    return false;
                }

                // Bus 2 should have the fallback "No Driver Assigned"
                if ($busesArray->get('PAS-102')->driver_name !== 'No Driver Assigned') {
                    return false;
                }

                return true;
            });
    }

    public function test_tracker_shows_breakdown_buses_but_hides_maintenance_buses(): void
    {
        Cache::flush();
        $route = $this->makeRoute('Route 2');
        $driver = Driver::factory()->create();
        $normalBus = $this->makeBus($route, 'TRK-ACT-101', 'active');
        $breakdownBus = $this->makeBus($route, 'TRK-BRK-101', 'breakdown');
        $maintenanceBus = $this->makeBus($route, 'TRK-MNT-101', 'maintenance');

        Trip::create([
            'bus_id' => $normalBus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);
        Trip::create([
            'bus_id' => $maintenanceBus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        Livewire::test(\App\Livewire\Commuter\Tracker::class)
            ->assertViewHas('activeBuses', function ($buses) use ($breakdownBus, $maintenanceBus) {
                $byPlate = collect($buses)->keyBy('plate_number');

                return $byPlate->has('TRK-ACT-101')
                    && $byPlate->get($breakdownBus->plate_number)?->status === 'breakdown'
                    && ! $byPlate->has($maintenanceBus->plate_number);
            });
    }

    public function test_tracker_shows_breakdown_safety_banner_for_on_bus_commuter_session(): void
    {
        Cache::flush();
        [$route, $origin, $destination] = $this->routeWithStops();
        $bus = $this->makeBus($route, 'TRK-BRK-ONBUS', 'breakdown');
        $token = 'tracker-breakdown-on-bus';
        $this->commuterSession($token);
        CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $route->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'status' => 'ON_BUS',
            'bus_id' => $bus->id,
        ]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(\App\Livewire\Commuter\Tracker::class)
            ->assertViewHas('breakdownAlert', fn ($alert) => str_contains($alert, 'Breakdown detected'))
            ->assertViewHas('maintenanceAlert', fn ($alert) => $alert === null);
    }

    public function test_tracker_shows_maintenance_safety_banner_for_on_bus_commuter_session(): void
    {
        Cache::flush();
        [$route, $origin, $destination] = $this->routeWithStops();
        $bus = $this->makeBus($route, 'TRK-MNT-ONBUS', 'maintenance');
        $token = 'tracker-maintenance-on-bus';
        $this->commuterSession($token);
        CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $route->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'status' => 'ON_BUS',
            'bus_id' => $bus->id,
        ]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(\App\Livewire\Commuter\Tracker::class)
            ->assertViewHas('breakdownAlert', fn ($alert) => $alert === null)
            ->assertViewHas('maintenanceAlert', fn ($alert) => str_contains($alert, 'maintenance issue'));
    }

    public function test_tracker_shows_waiting_breakdown_message_when_route_bus_is_breakdown(): void
    {
        Cache::flush();
        [$route, $origin, $destination] = $this->routeWithStops();
        $this->makeBus($route, 'TRK-BRK-WAIT', 'breakdown');
        $token = 'tracker-breakdown-waiting';
        $this->commuterSession($token);
        CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $route->id,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'status' => 'WAITING',
        ]);

        Livewire::withCookie('commuter_session_token', $token)
            ->test(\App\Livewire\Commuter\Tracker::class)
            ->assertViewHas('breakdownAlert', fn ($alert) => str_contains($alert, 'please wait for next available bus'))
            ->assertViewHas('maintenanceAlert', fn ($alert) => $alert === null);
    }

    private function makeRoute(string $name): Route
    {
        return Route::create([
            'name' => $name,
            'status' => 'Active',
            'color' => '#003F87',
        ]);
    }

    private function routeWithStops(): array
    {
        $route = $this->makeRoute('Route 2');
        $origin = Stop::create([
            'route_id' => $route->id,
            'name' => 'Route 2 Origin',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 1,
            'radius_meters' => 100,
        ]);
        $destination = Stop::create([
            'route_id' => $route->id,
            'name' => 'Route 2 Destination',
            'lat' => 14.501,
            'lng' => 121.001,
            'sequence' => 2,
            'radius_meters' => 100,
        ]);

        return [$route, $origin, $destination];
    }

    private function makeBus(Route $route, string $plate, string $status): Bus
    {
        return Bus::factory()->create([
            'plate_number' => $plate,
            'route_id' => $route->id,
            'status' => $status,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 10,
            'passengers' => 12,
            'eta' => 5,
        ]);
    }

    private function commuterSession(string $token): CommuterSession
    {
        return CommuterSession::create([
            'session_token' => $token,
            'expires_at' => now()->addHour(),
        ]);
    }
}
