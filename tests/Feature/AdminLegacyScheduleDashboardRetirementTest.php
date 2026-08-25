<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminLegacyScheduleDashboardRetirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_does_not_expose_legacy_schedule_runtime_config(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin/dashboard');

        $response->assertOk();
        $response->assertSee('routeServiceSchedulesUrl', false);
        $response->assertDontSee('schedulesBaseUrl', false);
        $response->assertDontSee('/admin/api/schedules', false);
    }

    public function test_routes_dashboard_script_no_longer_fetches_legacy_schedule_api(): void
    {
        $script = file_get_contents(public_path('js/admin-dashboard/routes.js'));

        $this->assertStringNotContainsString("fetch(baseUrl)", $script);
        $this->assertStringNotContainsString("'/admin/api/schedules'", $script);
        $this->assertStringContainsString('schedulesData = [];', $script);
    }
}
