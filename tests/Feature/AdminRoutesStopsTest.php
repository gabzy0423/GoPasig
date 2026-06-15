<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminRoutesStopsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);

        $this->actingAs($user);

        return $user;
    }

    public function test_admin_can_create_route_with_default_stops(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/admin/api/routes');

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $route = Route::latest('id')->first();
        $this->assertNotNull($route);
        $this->assertSame('Active', $route->status);
        $this->assertCount(2, Stop::where('route_id', $route->id)->get());
    }

    public function test_admin_can_suspend_route_and_update_description(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'name' => 'Route 99',
            'description' => 'Original endpoints',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5620, 121.0820]],
            'status' => 'Active',
        ]);

        $this->putJson("/admin/api/routes/{$route->id}", [
            'status' => 'Suspended',
            'description' => 'Updated endpoints',
        ])->assertOk()->assertJsonPath('success', true);

        $route->refresh();
        $this->assertSame('Suspended', $route->status);
        $this->assertSame('Updated endpoints', $route->description);
    }

    public function test_admin_can_update_route_kpi_targets(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'name' => 'Route 99',
            'description' => 'Original endpoints',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5620, 121.0820]],
            'status' => 'Active',
            'target_on_time_rate' => 85,
            'target_headway_minutes' => 15,
        ]);

        $this->putJson("/admin/api/routes/{$route->id}", [
            'target_on_time_rate' => 92,
            'target_headway_minutes' => 12,
        ])->assertOk()->assertJsonPath('success', true);

        $route->refresh();
        $this->assertEquals(92, $route->target_on_time_rate);
        $this->assertEquals(12, $route->target_headway_minutes);
    }

    public function test_admin_can_add_reorder_and_delete_stops(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'name' => 'Route Test',
            'description' => 'Test line',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5620, 121.0820]],
            'status' => 'Active',
        ]);

        $origin = Stop::create([
            'route_id' => $route->id,
            'name' => 'Origin',
            'lat' => 14.5593,
            'lng' => 121.0805,
            'sequence' => 1,
        ]);

        $terminus = Stop::create([
            'route_id' => $route->id,
            'name' => 'Terminus',
            'lat' => 14.5620,
            'lng' => 121.0820,
            'sequence' => 2,
        ]);

        $addResponse = $this->postJson('/admin/api/stops', [
            'route_id' => $route->id,
            'name' => 'Mid Stop',
        ]);

        $addResponse->assertCreated()->assertJsonPath('success', true);

        $midStop = Stop::where('route_id', $route->id)->where('name', 'Mid Stop')->first();
        $this->assertNotNull($midStop);
        $this->assertSame(2, $midStop->sequence);
        $terminus->refresh();
        $this->assertSame(3, $terminus->sequence);

        $this->putJson("/admin/api/routes/{$route->id}/stops/reorder", [
            'stop_ids' => [$origin->id, $terminus->id, $midStop->id],
        ])->assertOk();

        $this->assertSame(1, $origin->fresh()->sequence);
        $this->assertSame(2, $terminus->fresh()->sequence);
        $this->assertSame(3, $midStop->fresh()->sequence);

        $this->deleteJson("/admin/api/stops/{$midStop->id}")
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseMissing('stops', ['id' => $midStop->id]);
        $this->assertSame(1, $origin->fresh()->sequence);
        $this->assertSame(2, $terminus->fresh()->sequence);
    }

    public function test_guest_cannot_manage_routes(): void
    {
        $this->postJson('/admin/api/routes')->assertUnauthorized();
    }

    public function test_admin_can_assign_bus_to_route(): void
    {
        $this->actingAsAdmin();

        $route = Route::create([
            'name' => 'Route 1',
            'description' => 'Pasig Route',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5620, 121.0820]],
            'status' => 'Active',
        ]);

        $bus = \App\Models\Bus::create([
            'plate_number' => 'PAS-999',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
            'route_id' => null,
        ]);

        $response = $this->putJson("/admin/api/buses/{$bus->id}/assign-route", [
            'route_id' => $route->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);

        $bus->refresh();
        $this->assertEquals($route->id, $bus->route_id);
    }
}
