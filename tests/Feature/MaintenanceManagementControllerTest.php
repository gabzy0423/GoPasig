<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Bus;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceManagementControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_maintenance_summary_metrics(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $route = Route::create([
            'id' => 1,
            'name' => 'Route 1',
            'description' => 'SPED to Pasig City Hall',
            'polyline_coordinates' => [[14.5593, 121.0805]],
            'status' => 'Active',
        ]);

        // Bus 1: active
        Bus::create([
            'plate_number' => 'PAS-111',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5,
            'lng' => 121.0,
            'speed' => 0,
            'passengers' => 0,
            'route_id' => $route->id,
        ]);

        // Bus 2: inactive (standby)
        Bus::create([
            'plate_number' => 'PAS-222',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5,
            'lng' => 121.0,
            'speed' => 0,
            'passengers' => 0,
            'route_id' => $route->id,
        ]);

        // Bus 3: maintenance
        Bus::create([
            'plate_number' => 'PAS-333',
            'status' => 'maintenance',
            'capacity' => 45,
            'lat' => 14.5,
            'lng' => 121.0,
            'speed' => 0,
            'passengers' => 0,
            'route_id' => $route->id,
        ]);

        // Bus 4: breakdown
        Bus::create([
            'plate_number' => 'PAS-444',
            'status' => 'breakdown',
            'capacity' => 45,
            'lat' => 14.5,
            'lng' => 121.0,
            'speed' => 0,
            'passengers' => 0,
            'route_id' => $route->id,
        ]);

        $response = $this->actingAs($dispatcher)->getJson('/fleet/api/maintenance-data');
        $response->assertStatus(200);

        $data = $response->json();
        $this->assertTrue($data['success']);

        $summary = $data['summary'];
        $this->assertEquals(4, $summary['total_fleet']);
        $this->assertEquals(1, $summary['active_units']);
        $this->assertEquals(1, $summary['under_maintenance']);
        
        // breakdown_count should be 2 (buses with status 'maintenance' or 'breakdown')
        // It should NOT count the inactive (standby) bus!
        $this->assertEquals(2, $summary['breakdown_count']);
    }
}
