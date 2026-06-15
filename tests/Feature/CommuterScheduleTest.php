<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Schedule;
use App\Models\Route;
use App\Models\Stop;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommuterScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_commuter_schedule_maps_customized_status_labels(): void
    {
        // Set custom status labels in System Settings
        SystemSetting::create(['key' => 'db_status_ontime_label', 'value' => 'On Time (Punctual)']);
        SystemSetting::create(['key' => 'db_status_delayed_label', 'value' => 'Delayed (Late)']);
        SystemSetting::create(['key' => 'db_status_cancelled_label', 'value' => 'Cancelled (No Service)']);

        // Create a test route
        $route = Route::create([
            'name' => 'Route A',
            'description' => 'Test Route A Description',
            'polyline_coordinates' => [[14.5690, 121.0680]],
            'status' => 'Active',
        ]);

        // Create a bus
        $bus = Bus::create([
            'plate_number' => 'PAS-123',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5690,
            'lng' => 121.0680,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Create a driver
        $driver = Driver::create([
            'emp_id' => 'EMP-0021',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2027-12-12',
            'status' => 'inactive',
        ]);

        // Create test schedules with matching custom status values
        $schedule1 = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'departure_time' => '08:00:00',
            'arrival_time' => '09:00:00',
            'status' => 'On Time (Punctual)',
            'delay_minutes' => 0,
        ]);

        $schedule2 = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'departure_time' => '14:00:00',
            'arrival_time' => '15:00:00',
            'status' => 'Delayed (Late)',
            'delay_minutes' => 15,
        ]);

        $schedule3 = Schedule::create([
            'route_id' => $route->id,
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'departure_time' => '18:00:00',
            'arrival_time' => '19:00:00',
            'status' => 'Cancelled (No Service)',
            'delay_minutes' => 0,
        ]);

        // Test the Livewire component maps these correctly
        Livewire::test('commuter.commuter-schedule')
            ->assertSee('On time') // Renders mapped status representation in list
            ->assertSee('Delayed')
            ->assertSee('Cancelled')
            ->call('selectTrip', $schedule1->id)
            ->assertSet('selectedTrip.status', 'on_time')
            ->call('selectTrip', $schedule2->id)
            ->assertSet('selectedTrip.status', 'delayed')
            ->assertSet('selectedTrip.delay_minutes', 15)
            ->call('selectTrip', $schedule3->id)
            ->assertSet('selectedTrip.status', 'cancelled');
    }

    public function test_offsets_respect_database_segment_weights(): void
    {
        // 1. Create a route
        $route = Route::create([
            'name' => 'Route B',
            'description' => 'Test Route B',
            'polyline_coordinates' => [[14.5, 121.0]],
            'status' => 'Active',
        ]);

        // 2. Create 3 stops with distinct segment weights:
        // Stop 1: sequence 1, weight null (origin)
        // Stop 2: sequence 2, weight 1.0
        // Stop 3: sequence 3, weight 3.0
        $stop1 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 1',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 1,
            'segment_weight' => null,
        ]);

        $stop2 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 2',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 2,
            'segment_weight' => 1.0,
        ]);

        $stop3 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 3',
            'lat' => 14.5,
            'lng' => 121.0,
            'sequence' => 3,
            'segment_weight' => 3.0,
        ]);

        $stops = collect([$stop1, $stop2, $stop3]);
        
        // Duration: 40 minutes.
        // segment 1->2 has weight 1.0. Segment 2->3 has weight 3.0.
        // Total weight = 4.0.
        // Offset at Stop 2: 1/4 * 40 = 10 minutes.
        // Offset at Stop 3: (1+3)/4 * 40 = 40 minutes.
        $offsets = Stop::getDistanceWeightedOffsets($stops, 40.0);

        $this->assertEquals(0.0, $offsets[0]);
        $this->assertEquals(10.0, $offsets[1]);
        $this->assertEquals(40.0, $offsets[2]);
    }

    public function test_offsets_fallback_to_distance_when_segment_weights_missing(): void
    {
        // 1. Create a route
        $route = Route::create([
            'name' => 'Route C',
            'description' => 'Test Route C',
            'polyline_coordinates' => [[14.5, 121.0]],
            'status' => 'Active',
        ]);

        // Stops at different coords
        // Distance 1->2 is ~111km (1 deg lat). Distance 2->3 is ~111km (1 deg lat).
        // If we set segment_weight to null, it should fallback to distance-weighted offsets
        $stop1 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 1',
            'lat' => 14.0,
            'lng' => 121.0,
            'sequence' => 1,
            'segment_weight' => null,
        ]);

        $stop2 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 2',
            'lat' => 15.0,
            'lng' => 121.0,
            'sequence' => 2,
            'segment_weight' => null,
        ]);

        $stop3 = Stop::create([
            'route_id' => $route->id,
            'name' => 'Stop 3',
            'lat' => 16.0,
            'lng' => 121.0,
            'sequence' => 3,
            'segment_weight' => null,
        ]);

        $stops = collect([$stop1, $stop2, $stop3]);
        
        $offsets = Stop::getDistanceWeightedOffsets($stops, 30.0);

        // Since distances are equal (~111km each), it should distribute evenly: [0, 15, 30]
        $this->assertEquals(0.0, $offsets[0]);
        $this->assertEquals(15.0, $offsets[1]);
        $this->assertEquals(30.0, $offsets[2]);
    }
}
