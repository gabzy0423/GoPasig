<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\ServiceAlert;
use App\Models\Driver;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class Module7ServiceAlertTest extends TestCase
{
    use RefreshDatabase;

    protected $admin;
    protected $route;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\SystemSettingSeeder::class);
        
        // Seed the service alert options
        SystemSetting::updateOrCreate(
            ['key' => 'service_alert_severity_options'],
            ['value' => 'Low,Medium,High,Emergency', 'description' => 'Severity options']
        );
        SystemSetting::updateOrCreate(
            ['key' => 'service_alert_type_options'],
            ['value' => 'Delay,Route change,Suspension,Breakdown,Weather,Emergency', 'description' => 'Type options']
        );

        // Create admin user for auth
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);

        // Create a route
        $this->route = Route::create([
            'name' => 'Route Gold',
            'description' => 'Gold route',
            'color' => '#FFD700',
            'status' => 'Active',
            'target_on_time_rate' => 85,
            'target_headway_minutes' => 15,
        ]);
    }

    /**
     * Test validation rules for severity and type options from database.
     */
    public function test_service_alert_validation_uses_database_settings()
    {
        // Change settings to custom options
        SystemSetting::where('key', 'service_alert_severity_options')->update(['value' => 'Low,Extreme']);
        SystemSetting::where('key', 'service_alert_type_options')->update(['value' => 'Delay,Detour']);

        // Attempt to store with invalid options
        $response = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Test Alert',
            'message' => 'Test message details',
            'severity' => 'High', // Invalid now
            'type' => 'Suspension', // Invalid now
            'affects' => ['Route Gold'],
            'timing' => 'now',
        ]);
        $response->assertStatus(422);

        // Store with valid options
        $response = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Test Alert',
            'message' => 'Test message details',
            'severity' => 'Extreme', // Valid
            'type' => 'Detour', // Valid
            'affects' => ['Route Gold'],
            'timing' => 'now',
        ]);
        $response->assertStatus(201);
    }

    /**
     * Test mapping of severity strings ('Low' => 'info', 'Medium' => 'warning', 'High' => 'high', 'Emergency' => 'critical')
     */
    public function test_severity_mapping_is_correct()
    {
        $severities = [
            'Low' => 'info',
            'Medium' => 'warning',
            'High' => 'high',
            'Emergency' => 'critical'
        ];

        foreach ($severities as $input => $expectedDbValue) {
            $response = $this->postJson(route('admin.api.alerts.store'), [
                'title' => "Alert $input",
                'message' => "Message details for $input",
                'severity' => $input,
                'type' => 'Delay',
                'affects' => ['Route Gold'],
                'timing' => 'now',
            ]);
            $response->assertStatus(201);

            $alert = ServiceAlert::where('title', "Alert $input")->first();
            $this->assertNotNull($alert);
            $this->assertEquals($expectedDbValue, $alert->severity);
        }
    }

    /**
     * Test scheduled alerts: invisible before schedule time, visible after.
     */
    public function test_scheduled_alerts_visibility_filtering()
    {
        $futureTime = Carbon::now()->addHours(2);
        
        // Create a scheduled alert in the future
        $response = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Future Alert',
            'message' => 'This alert is scheduled for later',
            'severity' => 'High',
            'type' => 'Suspension',
            'affects' => ['Route Gold'],
            'timing' => 'later',
            'schedule_time' => $futureTime->toDateTimeString(),
        ]);
        $response->assertStatus(201);

        // Active alerts query should NOT return this alert yet
        $this->assertEquals(0, ServiceAlert::activeAlerts()->count());

        // Travel forward in time to after the scheduled time
        Carbon::setTestNow($futureTime->addMinute());

        // Active alerts query should NOW return this alert
        $this->assertEquals(1, ServiceAlert::activeAlerts()->count());

        // Reset time mocking
        Carbon::setTestNow();
    }

    /**
     * Test route suspension and unsuspension cycle.
     */
    public function test_route_suspension_and_unsuspension_cycle()
    {
        // Create an alert that suspends the route
        $response = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Route Closure',
            'message' => 'Gold route is closed',
            'severity' => 'Emergency',
            'type' => 'Suspension',
            'affects' => ['Route Gold'],
            'timing' => 'now',
            'suspend_route' => true,
        ]);
        $response->assertStatus(201);

        // Verify route status changed to Suspended
        $this->route->refresh();
        $this->assertEquals('Suspended', $this->route->status);

        $alert = ServiceAlert::where('title', 'Route Closure')->first();

        // Resolve the alert
        $resolveResponse = $this->postJson(route('admin.api.alerts.resolve', $alert->id));
        $resolveResponse->assertStatus(200);

        // Verify route status restored to Active
        $this->route->refresh();
        $this->assertEquals('Active', $this->route->status);
    }

    /**
     * Test that route unsuspension respects other active suspension alerts.
     */
    public function test_route_unsuspension_respects_other_suspending_alerts()
    {
        // Create alert 1 that suspends Route Gold
        $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Alert 1',
            'message' => 'Closure 1',
            'severity' => 'Emergency',
            'type' => 'Suspension',
            'affects' => ['Route Gold'],
            'timing' => 'now',
            'suspend_route' => true,
        ]);

        // Create alert 2 that also suspends Route Gold
        $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Alert 2',
            'message' => 'Closure 2',
            'severity' => 'Emergency',
            'type' => 'Suspension',
            'affects' => ['Route Gold'],
            'timing' => 'now',
            'suspend_route' => true,
        ]);

        $this->route->refresh();
        $this->assertEquals('Suspended', $this->route->status);

        $alert1 = ServiceAlert::where('title', 'Alert 1')->first();
        $alert2 = ServiceAlert::where('title', 'Alert 2')->first();

        // Resolve alert 1
        $this->postJson(route('admin.api.alerts.resolve', $alert1->id));

        // Route should STILL be Suspended because Alert 2 is still active
        $this->route->refresh();
        $this->assertEquals('Suspended', $this->route->status);

        // Resolve alert 2
        $this->postJson(route('admin.api.alerts.resolve', $alert2->id));

        // Route should now be Active
        $this->route->refresh();
        $this->assertEquals('Active', $this->route->status);
    }

    /**
     * Test driver counting query handles ID, string, and name formats.
     */
    public function test_driver_counting_handles_all_formats()
    {
        // Driver 1: assigned by route ID (integer)
        Driver::factory()->create([
            'first_name' => 'Driver',
            'last_name' => 'One',
            'assigned_route' => $this->route->id,
        ]);

        // Driver 2: assigned by route ID (string)
        Driver::factory()->create([
            'first_name' => 'Driver',
            'last_name' => 'Two',
            'assigned_route' => (string) $this->route->id,
        ]);

        // Driver 3: assigned by route name
        Driver::factory()->create([
            'first_name' => 'Driver',
            'last_name' => 'Three',
            'assigned_route' => $this->route->name,
        ]);

        // Make an index request to trigger stats calculation
        $response = $this->getJson(route('admin.api.alerts.index'));
        $response->assertStatus(200);

        $stats = $response->json('stats.route_stats.Route Gold');
        $this->assertEquals(3, $stats['drivers']);
    }
}
