<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AdminProfileSpaUiTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'name' => 'Gabriel Vargas',
            'email' => 'gabriel.vargas@gopasig.gov.ph',
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function test_admin_dashboard_renders_screen_profile_hidden_by_default()
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="screen-profile"', false);
        $response->assertSee('class="space-y-6 hidden animate-fade-in"', false);
    }

    /** @test */
    public function test_topbar_identity_trigger_exists_and_displays_authenticated_name_and_initials()
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="topbar-admin-profile-trigger"', false);
        $response->assertSee('id="topbar-admin-name"', false);
        $response->assertSee('id="topbar-admin-avatar"', false);
        $response->assertSee('Gabriel Vargas');
        $response->assertSee('GV'); // Initials for Gabriel Vargas
    }

    /** @test */
    public function test_topbar_initials_derive_single_word_name_correctly()
    {
        $singleWordAdmin = User::factory()->create([
            'name' => 'Administrator',
            'email' => 'single.admin@gopasig.gov.ph',
            'role' => 'admin',
        ]);

        $response = $this->actingAs($singleWordAdmin)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="topbar-admin-avatar"', false);
        $response->assertSee('A'); // Single word "Administrator" -> "A"
    }

    /** @test */
    public function test_navigation_js_registers_profile_screen()
    {
        $navigationJs = file_get_contents(public_path('js/admin-dashboard/navigation.js'));

        $this->assertStringContainsString("hideElement('screen-profile')", $navigationJs);
        $this->assertStringContainsString("'profile':     'Account Profile'", $navigationJs);
        $this->assertStringContainsString("if (parentScreenName === 'profile')", $navigationJs);
        $this->assertStringContainsString("loadAdminProfileData()", $navigationJs);
    }

    /** @test */
    public function test_profile_js_references_profile_api_endpoints()
    {
        $profileJs = file_get_contents(public_path('js/admin-dashboard/profile.js'));

        $this->assertStringContainsString('/admin/api/profile', $profileJs);
        $this->assertStringContainsString('/admin/api/profile/photo', $profileJs);
        $this->assertStringContainsString("method: 'GET'", $profileJs);
        $this->assertStringContainsString("method: 'PUT'", $profileJs);
        $this->assertStringContainsString("method: 'POST'", $profileJs);
        $this->assertStringContainsString("method: 'DELETE'", $profileJs);
        $this->assertStringContainsString('updateAdminProfileIdentity', $profileJs);
        $this->assertStringContainsString('getAdminInitials', $profileJs);
        $this->assertStringContainsString('handleAdminPhotoUpload', $profileJs);
        $this->assertStringContainsString('/admin/api/profile/password', $profileJs);
        $this->assertStringContainsString('togglePasswordVisibility', $profileJs);
        $this->assertStringContainsString('handleAdminPasswordSubmit', $profileJs);
        $this->assertStringContainsString('resetAdminPasswordForm', $profileJs);
        $this->assertStringContainsString('populateAccountInformation', $profileJs);
        $this->assertStringContainsString('renderProfileCompletion', $profileJs);
        $this->assertStringContainsString('renderRecentActivity', $profileJs);
    }

    /** @test */
    public function test_profile_blade_has_required_form_hooks_and_read_only_role()
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="admin-profile-form"', false);
        $response->assertSee('id="admin-profile-name"', false);
        $response->assertSee('id="admin-profile-email"', false);
        $response->assertSee('id="admin-profile-role"', false);
        $response->assertSee('id="admin-profile-save"', false);
        $response->assertSee('id="admin-profile-reset"', false);
        $response->assertSee('disabled readonly', false);
    }

    /** @test */
    public function test_password_photo_and_hardening_fields_exist_in_phase_5_profile()
    {
        $profileBlade = file_get_contents(resource_path('views/admin/profile/index.blade.php'));

        $this->assertStringContainsString('x-profile.personal-info-card prefix="admin"', $profileBlade);
        $this->assertStringContainsString('x-profile.password-card prefix="admin"', $profileBlade);
        $this->assertStringContainsString('x-profile.account-info-card prefix="admin"', $profileBlade);
        $this->assertStringContainsString('x-profile.recent-activity-card prefix="admin"', $profileBlade);
    }

    /** @test */
    public function test_required_existing_admin_screen_ids_remain_intact()
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="screen-overview"', false);
        $response->assertSee('id="screen-buses"', false);
        $response->assertSee('id="screen-dispatch"', false);
        $response->assertSee('id="screen-maintenance"', false);
        $response->assertSee('id="screen-settings"', false);
        $response->assertSee('id="screen-placeholder"', false);
    }
}
