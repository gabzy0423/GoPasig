<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Driver;
use App\Models\Bus;
use App\Models\Route;
use App\Models\Trip;
use App\Models\DispatchLog;

class FleetManagerRoleRefactorTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function fleet_manager_login_redirects_to_fleet_dashboard(): void
    {
        $user = User::factory()->create(['role' => 'fleet_manager']);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertRedirect('/fleet/dashboard');
    }

    /** @test */
    public function fleet_manager_accesses_authorized_fleet_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'fleet_manager']);

        $this->actingAs($user)
            ->get('/fleet/dashboard')
            ->assertStatus(200);

        $this->actingAs($user)
            ->getJson('/fleet/api/overview-data')
            ->assertStatus(200);
    }

    /** @test */
    public function fleet_manager_is_rejected_from_admin_only_endpoints(): void
    {
        $user = User::factory()->create(['role' => 'fleet_manager']);

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertStatus(403);
    }

    /** @test */
    public function admin_retains_central_dispatch_access(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertStatus(200);
    }

    /** @test */
    public function retired_dispatcher_role_is_rejected_from_fleet_routes(): void
    {
        $retiredUser = User::factory()->create();
        // Force raw role value to retired 'dispatcher' for test verification
        \DB::table('users')->where('id', $retiredUser->id)->update(['role' => 'dispatcher']);
        $retiredUser->refresh();

        $this->actingAs($retiredUser)
            ->get('/fleet/dashboard')
            ->assertStatus(403);
    }

    /** @test */
    public function driver_supervisor_contact_displays_correct_hierarchy(): void
    {
        // 1. Neither exists -> Safe generic fallback
        $this->get('/login'); // ensure fresh state
        $driverUser = User::factory()->create(['role' => 'driver']);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);

        $responseFallback = $this->actingAs($driverUser)->get('/driver/dashboard');
        $responseFallback->assertStatus(200);
        $responseFallback->assertSee('Fleet Operations Office');

        // 2. Admin exists -> Administrator / Dispatcher
        $admin = User::factory()->create(['name' => 'Maria Santos', 'role' => 'admin']);
        $responseAdmin = $this->actingAs($driverUser)->get('/driver/dashboard');
        $responseAdmin->assertStatus(200);
        $responseAdmin->assertSee('Administrator / Dispatcher');

        // 3. Fleet Manager exists -> Fleet Operations Manager
        $fleetManager = User::factory()->create(['name' => 'Juan Dela Cruz', 'role' => 'fleet_manager']);
        $responseFleet = $this->actingAs($driverUser)->get('/driver/dashboard');
        $responseFleet->assertStatus(200);
        $responseFleet->assertSee('Fleet Operations Manager');
    }

    /** @test */
    public function fleet_api_profile_supports_full_management(): void
    {
        $user = User::factory()->create(['role' => 'fleet_manager']);

        // Profile read
        $this->actingAs($user)
            ->getJson('/fleet/api/profile')
            ->assertStatus(200)
            ->assertJsonPath('user.role', 'fleet_manager');

        // Profile update
        $this->actingAs($user)
            ->putJson('/fleet/api/profile', [
                'name' => 'Updated Manager',
                'email' => $user->email,
            ])
            ->assertStatus(200);

        // Password update
        $this->actingAs($user)
            ->putJson('/fleet/api/profile/password', [
                'current_password' => 'password',
                'new_password' => 'NewPassword123!',
                'new_password_confirmation' => 'NewPassword123!',
            ])
            ->assertStatus(200);
    }

    /** @test */
    public function dispatcher_api_profile_is_no_longer_an_active_endpoint(): void
    {
        $user = User::factory()->create(['role' => 'fleet_manager']);

        $this->actingAs($user)
            ->getJson('/dispatcher/api/profile')
            ->assertStatus(404);
    }

    /** @test */
    public function role_display_helper_returns_canonical_labels(): void
    {
        $admin = User::factory()->make(['role' => 'admin']);
        $fleetManager = User::factory()->make(['role' => 'fleet_manager']);
        $driver = User::factory()->make(['role' => 'driver']);

        $this->assertEquals('Administrator / Dispatcher', $admin->displayRole());
        $this->assertEquals('Fleet Operations Manager', $fleetManager->displayRole());
        $this->assertEquals('Driver', $driver->displayRole());
    }

    /** @test */
    public function non_role_dispatch_workflows_and_logs_remain_valid(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driverUser = User::factory()->create(['role' => 'driver']);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
        $bus = Bus::factory()->create(['status' => 'active']);
        $route = Route::factory()->create();

        $trip = Trip::factory()->create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
        ]);

        $log = DispatchLog::create([
            'trip_id' => $trip->id,
            'dispatched_by' => $admin->id,
            'status' => 'dispatched',
        ]);

        $this->assertDatabaseHas('dispatch_logs', [
            'id' => $log->id,
            'dispatched_by' => $admin->id,
        ]);
        $this->assertEquals($admin->id, $log->dispatcher->id);
    }
}
