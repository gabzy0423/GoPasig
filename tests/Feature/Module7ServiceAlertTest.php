<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\ServiceAlert;
use App\Models\ServiceAlertLog;
use App\Models\ServiceAlertRead;
use App\Models\Driver;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
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
            'name' => 'Route 2',
            'description' => 'Official route one',
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
            'affects' => ['Route 2'],
            'timing' => 'now',
        ]);
        $response->assertStatus(422);

        // Store with valid options
        $response = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Test Alert',
            'message' => 'Test message details',
            'severity' => 'Extreme', // Valid
            'type' => 'Detour', // Valid
            'affects' => ['Route 2'],
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
                'affects' => ['Route 2'],
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
            'affects' => ['Route 2'],
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
            'message' => 'Route 2 is closed',
            'severity' => 'Emergency',
            'type' => 'Suspension',
            'affects' => ['Route 2'],
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
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => true,
        ]);

        // Create alert 2 that also suspends Route Gold
        $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Alert 2',
            'message' => 'Closure 2',
            'severity' => 'Emergency',
            'type' => 'Suspension',
            'affects' => ['Route 2'],
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

    public function test_suspension_alert_with_suspend_route_true_suspends_route(): void
    {
        $response = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Suspension Policy Active',
            'message' => 'Suspension route policy test.',
            'severity' => 'High',
            'type' => 'Suspension',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => true,
        ]);

        $response->assertCreated();
        $this->assertSame('Suspended', $this->route->fresh()->status);
    }

    public function test_suspension_alert_with_suspend_route_false_keeps_route_active(): void
    {
        $response = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Suspension Policy Informational',
            'message' => 'Suspension bulletin without route suspension.',
            'severity' => 'High',
            'type' => 'Suspension',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ]);

        $response->assertCreated();
        $this->assertSame('Active', $this->route->fresh()->status);
    }

    public function test_delay_alert_with_suspend_route_false_keeps_route_active(): void
    {
        $response = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Delay Policy Informational',
            'message' => 'Delay bulletin without route suspension.',
            'severity' => 'Medium',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ]);

        $response->assertCreated();
        $this->assertSame('Active', $this->route->fresh()->status);
    }

    #[DataProvider('approvedOperationalSuspensionProvider')]
    public function test_approved_operational_alert_combinations_can_suspend_routes(string $type, string $severity): void
    {
        $response = $this->postJson(route('admin.api.alerts.store'), [
            'title' => $type . ' ' . $severity . ' Operational Suspension',
            'message' => 'Approved operational suspension combination.',
            'severity' => $severity,
            'type' => $type,
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => true,
        ]);

        $response->assertCreated();
        $this->assertSame('Suspended', $this->route->fresh()->status);
    }

    public static function approvedOperationalSuspensionProvider(): array
    {
        return [
            'Weather Emergency' => ['Weather', 'Emergency'],
            'Emergency High' => ['Emergency', 'High'],
            'Emergency Emergency' => ['Emergency', 'Emergency'],
            'Breakdown Emergency' => ['Breakdown', 'Emergency'],
            'Delay Emergency' => ['Delay', 'Emergency'],
        ];
    }

    #[DataProvider('invalidOperationalSuspensionProvider')]
    public function test_invalid_operational_alert_combinations_cannot_suspend_routes(string $type, string $severity): void
    {
        $response = $this->postJson(route('admin.api.alerts.store'), [
            'title' => $type . ' ' . $severity . ' Invalid Suspension Policy',
            'message' => 'Invalid operational suspension combination.',
            'severity' => $severity,
            'type' => $type,
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('suspend_route')
            ->assertJsonPath('errors.suspend_route.0', 'Suspend Route is allowed only for Suspension alerts, Emergency or High emergency alerts, and Emergency-severity weather, breakdown, or delay alerts.');

        $this->assertSame('Active', $this->route->fresh()->status);
        $this->assertDatabaseMissing('service_alerts', [
            'title' => $type . ' ' . $severity . ' Invalid Suspension Policy',
        ]);
    }

    public static function invalidOperationalSuspensionProvider(): array
    {
        return [
            'Delay Low' => ['Delay', 'Low'],
            'Weather Medium' => ['Weather', 'Medium'],
            'Route change Emergency' => ['Route change', 'Emergency'],
            'Announcement Emergency' => ['Announcement', 'Emergency'],
        ];
    }
    public function test_existing_route_suspension_cannot_be_reclassified_as_informational_alert(): void
    {
        $createResponse = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Active Suspension For Reclassification',
            'message' => 'Suspension route policy test.',
            'severity' => 'High',
            'type' => 'Suspension',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => true,
        ]);
        $createResponse->assertCreated();

        $alert = ServiceAlert::where('title', 'Active Suspension For Reclassification')->firstOrFail();
        $this->assertSame('Suspended', $this->route->fresh()->status);

        $updateResponse = $this->putJson(route('admin.api.alerts.update', $alert->id), [
            'title' => 'Active Suspension For Reclassification',
            'message' => 'Attempt to reclassify as delay.',
            'severity' => 'High',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ]);

        $updateResponse->assertStatus(422)
            ->assertJsonValidationErrors('suspend_route');

        $this->assertSame('Suspension', $alert->fresh()->type);
        $this->assertTrue((bool) $alert->fresh()->suspend_route);
        $this->assertSame('Suspended', $this->route->fresh()->status);
    }

    public function test_active_operational_suspension_alert_cannot_be_deleted(): void
    {
        $createResponse = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Delete Blocked Operational Suspension',
            'message' => 'Operational suspension delete protection test.',
            'severity' => 'High',
            'type' => 'Suspension',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => true,
        ]);
        $createResponse->assertCreated();

        $alert = ServiceAlert::where('title', 'Delete Blocked Operational Suspension')->firstOrFail();
        $this->assertSame('Suspended', $this->route->fresh()->status);

        $deleteResponse = $this->deleteJson(route('admin.api.alerts.destroy', $alert->id));

        $deleteResponse->assertStatus(422)
            ->assertJsonPath('message', 'Operational suspension alerts must be resolved before they can be archived.');

        $this->assertDatabaseHas('service_alerts', [
            'id' => $alert->id,
            'status' => 'active',
            'suspend_route' => true,
        ]);
        $this->assertSame('Suspended', $this->route->fresh()->status);
    }

    public function test_resolved_operational_suspension_alert_can_be_deleted(): void
    {
        $createResponse = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Resolved Operational Delete Allowed',
            'message' => 'Resolve before delete lifecycle test.',
            'severity' => 'High',
            'type' => 'Suspension',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => true,
        ]);
        $createResponse->assertCreated();

        $alert = ServiceAlert::where('title', 'Resolved Operational Delete Allowed')->firstOrFail();
        $this->postJson(route('admin.api.alerts.resolve', $alert->id))->assertOk();
        $this->assertSame('Active', $this->route->fresh()->status);

        $this->deleteJson(route('admin.api.alerts.destroy', $alert->id))->assertOk();

        $this->assertSoftDeleted('service_alerts', ['id' => $alert->id]);
        $this->assertDatabaseHas('service_alert_logs', [
            'service_alert_id' => $alert->id,
            'title' => 'Resolved Operational Delete Allowed',
            'status' => 'resolved',
            'suspend_route' => true,
        ]);
        $this->assertSame('Active', $this->route->fresh()->status);
        $this->assertTrue(app(\App\Services\CentralDispatchEligibilityService::class)::route($this->route->fresh())['eligible']);
    }

    public function test_resolved_informational_alert_can_be_deleted(): void
    {
        $createResponse = $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Resolved Delay Delete Allowed',
            'message' => 'Resolved informational delete lifecycle test.',
            'severity' => 'Medium',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ]);
        $createResponse->assertCreated();

        $alert = ServiceAlert::where('title', 'Resolved Delay Delete Allowed')->firstOrFail();
        $this->postJson(route('admin.api.alerts.resolve', $alert->id))->assertOk();

        $this->deleteJson(route('admin.api.alerts.destroy', $alert->id))->assertOk();

        $this->assertSoftDeleted('service_alerts', ['id' => $alert->id]);
        $this->assertDatabaseHas('service_alert_logs', [
            'service_alert_id' => $alert->id,
            'title' => 'Resolved Delay Delete Allowed',
            'status' => 'resolved',
            'suspend_route' => false,
        ]);
        $this->assertSame('Active', $this->route->fresh()->status);
    }

    public function test_deleting_one_of_multiple_resolved_alerts_uses_the_selected_alert_id(): void
    {
        foreach (['Resolved Alert Target', 'Resolved Alert Survivor'] as $title) {
            $this->postJson(route('admin.api.alerts.store'), [
                'title' => $title,
                'message' => 'Correct resolved alert ID targeting test.',
                'severity' => 'Medium',
                'type' => 'Delay',
                'affects' => ['Route 2'],
                'timing' => 'now',
                'suspend_route' => false,
            ])->assertCreated();

            $alert = ServiceAlert::where('title', $title)->firstOrFail();
            $this->postJson(route('admin.api.alerts.resolve', $alert->id))->assertOk();
        }

        $target = ServiceAlert::where('title', 'Resolved Alert Target')->firstOrFail();
        $survivor = ServiceAlert::where('title', 'Resolved Alert Survivor')->firstOrFail();

        $this->deleteJson(route('admin.api.alerts.destroy', $target->id))->assertOk();

        $this->assertSoftDeleted('service_alerts', ['id' => $target->id]);
        $this->assertDatabaseHas('service_alert_logs', [
            'service_alert_id' => $target->id,
            'title' => 'Resolved Alert Target',
        ]);
        $this->assertDatabaseMissing('service_alert_logs', [
            'service_alert_id' => $survivor->id,
        ]);
        $this->assertDatabaseHas('service_alerts', [
            'id' => $survivor->id,
            'title' => 'Resolved Alert Survivor',
            'status' => 'resolved',
        ]);
    }

    public function test_duplicate_delete_returns_controlled_not_found_response(): void
    {
        $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Duplicate Delete Target',
            'message' => 'Duplicate delete handling test.',
            'severity' => 'Medium',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ])->assertCreated();

        $alert = ServiceAlert::where('title', 'Duplicate Delete Target')->firstOrFail();

        $this->deleteJson(route('admin.api.alerts.destroy', $alert->id))->assertOk();
        $this->assertDatabaseCount('service_alert_logs', 1);

        $this->deleteJson(route('admin.api.alerts.destroy', $alert->id))
            ->assertStatus(404)
            ->assertJsonPath('message', 'Service Alert was already archived or no longer exists.');
    }

    public function test_informational_alerts_can_be_deleted_immediately(): void
    {
        foreach ([['Delay', 'Medium'], ['Weather', 'Medium']] as [$type, $severity]) {
            $title = $type . ' Informational Delete Allowed';

            $createResponse = $this->postJson(route('admin.api.alerts.store'), [
                'title' => $title,
                'message' => 'Informational alert delete lifecycle test.',
                'severity' => $severity,
                'type' => $type,
                'affects' => ['Route 2'],
                'timing' => 'now',
                'suspend_route' => false,
            ]);
            $createResponse->assertCreated();

            $alert = ServiceAlert::where('title', $title)->firstOrFail();
            $this->deleteJson(route('admin.api.alerts.destroy', $alert->id))->assertOk();
            $this->assertSoftDeleted('service_alerts', ['id' => $alert->id]);
            $this->assertSame('Active', $this->route->fresh()->status);
        }
    }

    public function test_multiple_operational_suspensions_require_resolve_before_delete_and_preserve_restoration_guard(): void
    {
        foreach (['Lifecycle Suspension One', 'Lifecycle Suspension Two'] as $title) {
            $this->postJson(route('admin.api.alerts.store'), [
                'title' => $title,
                'message' => 'Multiple operational suspension lifecycle test.',
                'severity' => 'High',
                'type' => 'Suspension',
                'affects' => ['Route 2'],
                'timing' => 'now',
                'suspend_route' => true,
            ])->assertCreated();
        }

        $alertOne = ServiceAlert::where('title', 'Lifecycle Suspension One')->firstOrFail();
        $alertTwo = ServiceAlert::where('title', 'Lifecycle Suspension Two')->firstOrFail();
        $this->assertSame('Suspended', $this->route->fresh()->status);

        $this->deleteJson(route('admin.api.alerts.destroy', $alertOne->id))->assertStatus(422);

        $this->postJson(route('admin.api.alerts.resolve', $alertOne->id))->assertOk();
        $this->assertSame('Suspended', $this->route->fresh()->status);
        $this->deleteJson(route('admin.api.alerts.destroy', $alertOne->id))->assertOk();
        $this->assertSoftDeleted('service_alerts', ['id' => $alertOne->id]);
        $this->assertSame('Suspended', $this->route->fresh()->status);

        $this->postJson(route('admin.api.alerts.resolve', $alertTwo->id))->assertOk();
        $this->assertSame('Active', $this->route->fresh()->status);
        $this->deleteJson(route('admin.api.alerts.destroy', $alertTwo->id))->assertOk();
        $this->assertSoftDeleted('service_alerts', ['id' => $alertTwo->id]);
        $this->assertSame('Active', $this->route->fresh()->status);
    }

    public function test_alert_api_returns_created_alert_with_zero_reads_count(): void
    {
        $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Zero Reads Broadcast Visibility',
            'message' => 'Alert should be returned with zero read records.',
            'severity' => 'Medium',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ])->assertCreated();

        $this->getJson(route('admin.api.alerts.index'))
            ->assertOk()
            ->assertJsonPath('alerts.0.title', 'Zero Reads Broadcast Visibility')
            ->assertJsonPath('alerts.0.status', 'active')
            ->assertJsonPath('alerts.0.reads_count', 0);
    }

    public function test_alert_api_counts_matching_service_alert_read_record(): void
    {
        $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'One Read Broadcast Visibility',
            'message' => 'Alert should count one read record.',
            'severity' => 'Medium',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ])->assertCreated();

        $alert = ServiceAlert::where('title', 'One Read Broadcast Visibility')->firstOrFail();
        ServiceAlertRead::create([
            'service_alert_id' => $alert->id,
            'user_id' => $this->admin->id,
            'read_at' => now(),
        ]);

        $this->getJson(route('admin.api.alerts.index'))
            ->assertOk()
            ->assertJsonPath('alerts.0.title', 'One Read Broadcast Visibility')
            ->assertJsonPath('alerts.0.reads_count', 1);
    }

    public function test_service_alert_reads_repair_migration_is_idempotent_when_table_exists(): void
    {
        $this->assertTrue(Schema::hasTable('service_alert_reads'));

        $migration = require database_path('migrations/2026_07_28_000002_restore_missing_service_alert_reads_table.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('service_alert_reads'));
        $columns = Schema::getColumnListing('service_alert_reads');

        $this->assertContains('service_alert_id', $columns);
        $this->assertContains('user_id', $columns);
        $this->assertContains('session_id', $columns);
        $this->assertContains('read_at', $columns);
    }

    public function test_archive_preserves_service_alert_reads_and_creates_history_log(): void
    {
        $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Read Preservation Archive',
            'message' => 'Read rows should survive soft archive.',
            'severity' => 'Medium',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ])->assertCreated();

        $alert = ServiceAlert::where('title', 'Read Preservation Archive')->firstOrFail();
        $read = ServiceAlertRead::create([
            'service_alert_id' => $alert->id,
            'user_id' => $this->admin->id,
            'read_at' => now(),
        ]);

        $this->deleteJson(route('admin.api.alerts.destroy', $alert->id))->assertOk();

        $this->assertSoftDeleted('service_alerts', ['id' => $alert->id]);
        $this->assertDatabaseHas('service_alert_reads', ['id' => $read->id, 'service_alert_id' => $alert->id]);
        $this->assertDatabaseHas('service_alert_logs', ['service_alert_id' => $alert->id, 'title' => 'Read Preservation Archive']);
    }

    public function test_history_endpoint_returns_archived_records_newest_first(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-28 08:00:00'));
        $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Older Archive Log',
            'message' => 'Older archive test.',
            'severity' => 'Medium',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ])->assertCreated();
        $older = ServiceAlert::where('title', 'Older Archive Log')->firstOrFail();
        $this->deleteJson(route('admin.api.alerts.destroy', $older->id))->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-07-28 09:00:00'));
        $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Newer Archive Log',
            'message' => 'Newer archive test.',
            'severity' => 'Medium',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ])->assertCreated();
        $newer = ServiceAlert::where('title', 'Newer Archive Log')->firstOrFail();
        $this->deleteJson(route('admin.api.alerts.destroy', $newer->id))->assertOk();
        Carbon::setTestNow();

        $response = $this->getJson(route('admin.api.alerts.history'));

        $response->assertOk()
            ->assertJsonPath('history.0.title', 'Newer Archive Log')
            ->assertJsonPath('history.1.title', 'Older Archive Log')
            ->assertJsonPath('history.0.affected_routes.0', 'Route 2');
    }

    public function test_normal_service_alert_queries_exclude_archived_records(): void
    {
        $this->postJson(route('admin.api.alerts.store'), [
            'title' => 'Archived Visibility Guard',
            'message' => 'Archived alert should disappear from normal surfaces.',
            'severity' => 'Medium',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ])->assertCreated();
        $alert = ServiceAlert::where('title', 'Archived Visibility Guard')->firstOrFail();

        $this->deleteJson(route('admin.api.alerts.destroy', $alert->id))->assertOk();

        $this->assertFalse(ServiceAlert::whereKey($alert->id)->exists());
        $this->assertTrue(ServiceAlert::withTrashed()->whereKey($alert->id)->exists());
        $this->assertFalse(ServiceAlert::activeAlerts()->whereKey($alert->id)->exists());
        $this->assertFalse(ServiceAlert::where('status', 'resolved')->whereKey($alert->id)->exists());
        $this->assertFalse(ServiceAlert::activeAlerts()->publicCommuterVisible()->whereKey($alert->id)->exists());
        $this->assertFalse(ServiceAlert::where('status', 'resolved')->publicCommuterVisible()->whereKey($alert->id)->exists());
        $this->assertFalse(ServiceAlert::orderByDesc('created_at')->whereKey($alert->id)->exists());

        $this->getJson(route('admin.api.alerts.index'))
            ->assertOk()
            ->assertJsonMissing(['title' => 'Archived Visibility Guard']);
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

        $stats = $response->json('stats.route_stats.Route 2');
        $this->assertEquals(3, $stats['drivers']);
    }
}



