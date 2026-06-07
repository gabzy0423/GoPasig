<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetRoutePerformanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_can_access_route_performance(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/routes');

        $response->assertStatus(200);
        $response->assertSeeLivewire('fleet.route-performance');
    }

    public function test_unauthorized_users_cannot_access_route_performance(): void
    {
        // Admin user is unauthorized for dispatcher routes (since middleware requires role:dispatcher)
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/fleet/routes');
        $response->assertStatus(403);

        // Driver user is unauthorized
        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/fleet/routes');
        $response->assertStatus(403);
    }

    public function test_guest_users_are_redirected_to_login(): void
    {
        // Guest user is redirected to login
        $response = $this->get('/fleet/routes');
        $response->assertRedirect('/login');
    }

    public function test_livewire_export_route_report_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        Livewire::test('fleet.route-performance')
            ->call('exportRouteReport')
            ->assertFileDownloaded();
    }

    public function test_livewire_select_route_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        // Test route selection filters summary details
        Livewire::test('fleet.route-performance')
            ->assertSet('selectedRoute', 'all')
            ->call('selectRoute', '1')
            ->assertSet('selectedRoute', '1')
            ->assertSee('Route 1')
            ->call('selectRoute', '3')
            ->assertSet('selectedRoute', '3')
            ->assertSee('Route 3');
    }

    public function test_livewire_deviation_filter_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        Livewire::test('fleet.route-performance')
            ->assertSet('selectedDeviationTypes', [])
            ->call('toggleDeviationFilter', 'Off-Route')
            ->assertSet('selectedDeviationTypes', ['Off-Route'])
            ->call('toggleDeviationFilter', 'Off-Route')
            ->assertSet('selectedDeviationTypes', [])
            ->call('toggleDeviationFilter', 'Route Skip')
            ->assertSet('selectedDeviationTypes', ['Route Skip'])
            ->call('clearDeviationFilters')
            ->assertSet('selectedDeviationTypes', []);
    }
}
