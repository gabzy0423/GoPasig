<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatcherProfileSpaUiTest extends TestCase
{
    use RefreshDatabase;

    protected $dispatcherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcherUser = User::factory()->create([
            'name' => 'Fleet Operator',
            'email' => 'dispatcher.ui@gopasig.gov.ph',
            'role' => 'fleet_manager',
        ]);
    }

    /** @test */
    public function test_fleet_dashboard_renders_profile_screen_hidden_by_default()
    {
        $response = $this->actingAs($this->dispatcherUser)->get('/fleet/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="screen-profile"', false);
        $response->assertSee('display: none', false);
    }

    /** @test */
    public function test_fleet_topbar_identity_trigger_exists_and_displays_authenticated_name()
    {
        $response = $this->actingAs($this->dispatcherUser)->get('/fleet/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="topbar-identity-trigger"', false);
        $response->assertSee("window.activateFleetModule('profile')", false);
        $response->assertSee('Fleet Operator', false);
    }

    /** @test */
    public function test_fleet_navigation_js_registers_profile_screen_and_load_trigger()
    {
        $navJs = file_get_contents(public_path('js/fleet-dashboard/navigation.js'));

        $this->assertStringContainsString("'profile'", $navJs);
        $this->assertStringContainsString("profile: 'Account Profile'", $navJs);
        $this->assertStringContainsString("profile: () => window.initStaffProfileModule?.() || window.loadDispatcherProfileData?.()", $navJs);

        // Verify existing hooks remain intact
        $this->assertStringContainsString("schedule: () => window.initFleetScheduleModule?.()", $navJs);
        $this->assertStringContainsString("drivers: () => window.initFleetPerformanceModule?.('drivers')", $navJs);
        $this->assertStringContainsString("'commuter-trips': () => window.initFleetCommuterTripsModule?.()", $navJs);
        $this->assertStringContainsString("'commuter-sessions': () => window.initFleetCommuterSessionsModule?.()", $navJs);
    }

    /** @test */
    public function test_shared_staff_profile_js_exists_and_supports_dispatcher_api()
    {
        $staffJs = file_get_contents(public_path('js/shared/staff-profile.js'));

        $this->assertStringContainsString('loadStaffProfileData', $staffJs);
        $this->assertStringContainsString('/fleet/api/profile', $staffJs);
        $this->assertStringContainsString('loadDispatcherProfileData', $staffJs);
    }

    /** @test */
    public function test_dispatcher_profile_view_has_shared_cards()
    {
        $profileBlade = file_get_contents(resource_path('views/fleet/profile/index.blade.php'));

        $this->assertStringContainsString('x-profile.personal-info-card prefix="dispatcher"', $profileBlade);
        $this->assertStringContainsString('x-profile.password-card prefix="dispatcher"', $profileBlade);
        $this->assertStringContainsString('x-profile.account-info-card prefix="dispatcher"', $profileBlade);
        $this->assertStringContainsString('x-profile.recent-activity-card prefix="dispatcher"', $profileBlade);
    }
}
