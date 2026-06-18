<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Route;
use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FleetScheduleComplianceTest extends TestCase
{
    use RefreshDatabase;

    private $route;
    private $driver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->route = Route::create([
            'id' => 1,
            'name' => 'Route A',
            'description' => 'SPED to Pasig City Hall',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5838, 121.0620]],
            'status' => 'Active',
        ]);

        $this->driver = Driver::create([
            'emp_id' => 'EMP-1234',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'license_number' => 'N01-12-123456',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
        ]);
    }

    public function test_dispatcher_can_access_schedule_compliance(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/schedule');
        $response->assertRedirect('/fleet/dashboard?tab=schedule');

        $dashboardResponse = $this->actingAs($dispatcher)->get('/fleet/dashboard?tab=schedule');
        $dashboardResponse->assertStatus(200);
    }

    public function test_unauthorized_users_cannot_access_schedule_compliance(): void
    {
        // Admin user is unauthorized for dispatcher routes
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/fleet/schedule');
        $response->assertStatus(403);

        // Driver user is unauthorized
        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/fleet/schedule');
        $response->assertStatus(403);
    }

    public function test_guest_users_are_redirected_to_login(): void
    {
        // Guest user is redirected to login
        $response = $this->get('/fleet/schedule');
        $response->assertRedirect('/login');
    }

    public function test_export_compliance_report_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/api/schedule-compliance-export?route_id=all');

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition');
        $this->assertStringContainsString('GoPasig Schedule Compliance Report', $response->streamedContent());
    }

    public function test_api_schedule_compliance_filtering_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/api/schedule-compliance-data', [
            'route_id' => '1',
            'driver' => 'Juan Dela Cruz',
            'status' => 'Late'
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'complianceSummary',
            'routeCompliance',
            'delayTrend',
            'tripLogs',
            'rawTripLogsCount',
            'delayedRoutes',
            'lateDrivers',
        ]);
    }
}

