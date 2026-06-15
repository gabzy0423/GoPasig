<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');
        $response->assertRedirect('/login');
    }

    public function test_dispatcher_can_access_fleet_dashboard(): void
    {
        $this->seed();
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/dashboard');
        $response->assertStatus(200);
    }

    public function test_driver_can_access_trip_without_bus(): void
    {
        $this->seed();
        $user = User::factory()->create(['role' => 'driver']);
        \App\Models\Driver::create([
            'user_id' => $user->id,
            'first_name' => 'Test',
            'last_name' => 'Driver',
            'emp_id' => 'EMP-999',
            'license_number' => 'N99-99-999999',
            'license_expiry' => '2027-12-12',
            'status' => 'inactive',
            'assigned_bus' => null,
            'assigned_route' => null,
        ]);

        $response = $this->actingAs($user)->get('/driver/trip');
        $response->assertStatus(200);
    }
}
