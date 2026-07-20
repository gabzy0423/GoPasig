<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Trip;
use App\Models\Bus;
use App\Models\User;
use App\Models\VehiclePosition;
use Illuminate\Foundation\Testing\RefreshDatabase;

class FleetDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_fleet_dashboard_api_returns_live_positions_and_buses()
    {
        $bus = Bus::factory()->create(['plate_number' => 'XYZ-789']);
        $trip = Trip::factory()->create(['bus_id' => $bus->id, 'status' => 'ongoing']);

        VehiclePosition::create([
            'bus_id' => $bus->id,
            'trip_id' => $trip->id,
            'lat' => 14.58,
            'lng' => 121.09,
            'speed' => 10.0,
            'heading' => 90.0,
            'status' => 'Moving',
            'last_updated_at' => now()
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson(route('admin.api.fleet-data'));

        $response->assertStatus(200)
            ->assertJsonStructure([
                'routes',
                'buses',
                'trips'
            ])
            ->assertJsonFragment([
                'plate_number' => 'XYZ-789',
                'lat' => 14.58,
                'lng' => 121.09,
                'status' => 'Moving'
            ]);
    }
}
