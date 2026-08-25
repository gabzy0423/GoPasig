<?php

namespace Tests\Feature;

use App\Models\Route;
use App\Models\ServiceAlert;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminServiceAlertTargetingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Route $route1;
    private Route $route2;
    private Route $route3;
    private Route $legacyRoute;
    private Route $uatRoute;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::updateOrCreate(
            ['key' => 'service_alert_severity_options'],
            ['value' => 'Low,Medium,High,Emergency', 'description' => 'Severity options']
        );
        SystemSetting::updateOrCreate(
            ['key' => 'service_alert_type_options'],
            ['value' => 'Delay,Route change,Suspension,Breakdown,Weather,Emergency', 'description' => 'Type options']
        );

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($this->admin);

        $this->route1 = $this->makeRoute('Route 2');
        $this->route2 = $this->makeRoute('Route 3');
        $this->route3 = $this->makeRoute('Route 4');
        foreach (['Route A', 'Route B', 'Route C', 'Route D'] as $routeName) {
            $this->makeRoute($routeName);
        }
        $this->legacyRoute = Route::where('name', 'Route A')->firstOrFail();
        $this->uatRoute = $this->makeRoute('PHASE3C-UAT Point-to-Point A-B');
    }

    public function test_admin_service_alert_target_selector_exposes_only_canonical_official_routes(): void
    {
        $this->route1->update(['status' => 'Suspended']);

        $response = $this->getJson(route('admin.api.alerts.target-routes'));

        $response->assertOk()
            ->assertJsonPath('all_official_label', 'All official routes');

        $routeNames = collect($response->json('routes'))->pluck('name')->all();

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $routeNames);
        $this->assertContains('Route 2', $routeNames);
        $this->assertNotContains('Route A', $routeNames);
        $this->assertNotContains('Route B', $routeNames);
        $this->assertNotContains('Route C', $routeNames);
        $this->assertNotContains('Route D', $routeNames);
        $this->assertNotContains('PHASE3C-UAT Point-to-Point A-B', $routeNames);
    }

    public function test_single_route_alert_stores_structured_canonical_route_targeting(): void
    {
        $response = $this->postJson(route('admin.api.alerts.store'), $this->payload([
            'affects' => ['Route 2'],
        ]));

        $response->assertCreated();

        $alert = ServiceAlert::firstOrFail();
        $this->assertSame($this->route1->id, $alert->route_id);
        $this->assertSame('Route 2', $alert->affected_routes);
    }

    public function test_route_one_suspension_suspends_only_route_one(): void
    {
        $response = $this->postJson(route('admin.api.alerts.store'), $this->payload([
            'type' => 'Suspension',
            'severity' => 'High',
            'affects' => ['Route 2'],
            'suspend_route' => true,
        ]));

        $response->assertCreated();

        $this->assertSame('Suspended', $this->route1->fresh()->status);
        $this->assertSame('Active', $this->route2->fresh()->status);
        $this->assertSame('Active', $this->route3->fresh()->status);
        $this->assertSame('Active', $this->legacyRoute->fresh()->status);
        $this->assertSame('Active', $this->uatRoute->fresh()->status);
    }

    public function test_multiple_canonical_route_suspension_uses_exact_route_names_only(): void
    {
        $response = $this->postJson(route('admin.api.alerts.store'), $this->payload([
            'type' => 'Suspension',
            'severity' => 'High',
            'affects' => ['Route 2', 'Route 3'],
            'suspend_route' => true,
        ]));

        $response->assertCreated();

        $alert = ServiceAlert::firstOrFail();
        $this->assertNull($alert->route_id);
        $this->assertSame('Route 2,Route 3', $alert->affected_routes);
        $this->assertSame('Suspended', $this->route1->fresh()->status);
        $this->assertSame('Suspended', $this->route2->fresh()->status);
        $this->assertSame('Active', $this->route3->fresh()->status);
        $this->assertSame('Active', $this->legacyRoute->fresh()->status);
        $this->assertSame('Active', $this->uatRoute->fresh()->status);
    }

    public function test_all_official_routes_alert_is_public_and_suspends_only_canonical_routes(): void
    {
        $response = $this->postJson(route('admin.api.alerts.store'), $this->payload([
            'type' => 'Suspension',
            'severity' => 'High',
            'affects' => ['All official routes'],
            'suspend_route' => true,
        ]));

        $response->assertCreated();

        $alert = ServiceAlert::firstOrFail();
        $this->assertNull($alert->route_id);
        $this->assertSame('All official routes', $alert->affected_routes);
        $this->assertTrue(ServiceAlert::activeAlerts()->publicCommuterVisible()->whereKey($alert->id)->exists());
        $this->assertSame('Suspended', $this->route1->fresh()->status);
        $this->assertSame('Suspended', $this->route2->fresh()->status);
        $this->assertSame('Suspended', $this->route3->fresh()->status);
        $this->assertSame('Active', $this->legacyRoute->fresh()->status);
        $this->assertSame('Active', $this->uatRoute->fresh()->status);
    }

    public function test_non_canonical_targets_are_rejected_and_not_suspended(): void
    {
        $response = $this->postJson(route('admin.api.alerts.store'), $this->payload([
            'affects' => ['Route A'],
            'suspend_route' => true,
        ]));

        $response->assertStatus(422);
        $this->assertSame('Active', $this->legacyRoute->fresh()->status);
        $this->assertDatabaseCount('service_alerts', 0);
    }

    public function test_unsupported_notify_controls_are_not_rendered_in_composer(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('id="chk-commuters"', false);
        $response->assertDontSee('id="chk-drivers"', false);
        $response->assertDontSee('id="chk-admin"', false);
        $response->assertDontSee('Commuters (public interface)');
        $response->assertDontSee('Drivers (driver app)');
    }

    public function test_service_alert_ui_has_no_legacy_route_defaults_or_fake_counts(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertDontSee('e.g. Route A delay at Ortigas Ave');
        $response->assertDontSee('Route A</option>', false);
        $response->assertDontSee('Route B</option>', false);
        $response->assertDontSee('Route C</option>', false);
        $response->assertDontSee('847 commuters');
        $response->assertDontSee('12 drivers notified');
        $response->assertDontSee('Last broadcast: 14 min ago');
        $response->assertSee('e.g. Route 2 service advisory');
        $response->assertSee('All official routes only');
        $response->assertSee('Last broadcast: None today');
    }

    public function test_service_alert_stats_are_limited_to_official_routes_and_do_not_use_schedule_passengers(): void
    {
        \App\Models\Schedule::factory()->create([
            'route_id' => $this->route1->id,
            'passengers' => 99,
        ]);

        $response = $this->getJson(route('admin.api.alerts.index'));

        $response->assertOk();

        $routeStats = $response->json('stats.route_stats');
        $this->assertArrayHasKey('Route 2', $routeStats);
        $this->assertArrayHasKey('Route 3', $routeStats);
        $this->assertArrayHasKey('Route 4', $routeStats);
        $this->assertArrayNotHasKey('Route A', $routeStats);
        $this->assertSame(0, $routeStats['Route 2']['commuters']);
        $this->assertTrue($routeStats['Route 2']['no_data']);
        $this->assertSame('unavailable', $routeStats['Route 2']['passenger_metric']);
    }

    public function test_service_alert_composer_exposes_operational_suspension_policy_copy_and_state_rules(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Activate Route Suspension');
        $response->assertSee('Operational action that blocks new dispatches for the selected route(s)');
        $response->assertSee('This alert is informational only. No operational changes will be made.');

        $composerScript = file_get_contents(public_path('js/admin-dashboard/alerts.js'));

        $this->assertStringContainsString("delay: 'Medium'", $composerScript);
        $this->assertStringContainsString("route_change: 'Medium'", $composerScript);
        $this->assertStringContainsString("weather: 'Medium'", $composerScript);
        $this->assertStringContainsString("breakdown: 'High'", $composerScript);
        $this->assertStringContainsString("suspension: 'High'", $composerScript);
        $this->assertStringContainsString("emergency: 'Emergency'", $composerScript);
        $this->assertStringContainsString('composerState.suspendRoute = getDefaultSuspendRouteForType(type) && canSelectedTypeSuspendRoute();', $composerScript);
        $this->assertStringContainsString('New dispatches on the selected route(s) will be blocked immediately. Existing trips will continue until completed.', $composerScript);
        $this->assertStringContainsString('This is an advance suspension advisory. Drivers and commuters will be notified. The selected route(s) will remain active until Route Suspension is activated.', $composerScript);
        $this->assertStringContainsString('Resolve this operational suspension before archiving it.', $composerScript);
        $this->assertStringContainsString('renderAlertDeleteMenuItem(alert)', $composerScript);
        $this->assertStringContainsString('am-resolved-delete', $composerScript);
        $this->assertStringContainsString('deletingAlertIds.has(id)', $composerScript);
        $this->assertStringContainsString('renderResolvedAlerts();', $composerScript);
    }

    public function test_service_alert_display_copy_does_not_render_mojibake_separators(): void
    {
        $composerScript = file_get_contents(public_path('js/admin-dashboard/alerts.js'));

        $this->assertStringContainsString('Yes - ${affectedText} will be suspended', $composerScript);
        $this->assertStringNotContainsString('Yes â€” ${affectedText} will be suspended', $composerScript);
        $this->assertStringNotContainsString("replace(',', ' ï¿½')", $composerScript);
        $this->assertStringNotContainsString("replace(',', ' Â·')", $composerScript);
        $this->assertStringNotContainsString(' Â· ', $composerScript);
    }

    public function test_service_alert_broadcast_success_receipt_precedes_background_feed_refresh(): void
    {
        $composerScript = file_get_contents(public_path('js/admin-dashboard/alerts.js'));

        $this->assertStringContainsString('let broadcastInFlight = false;', $composerScript);
        $this->assertStringContainsString('if (broadcastInFlight) return;', $composerScript);
        $this->assertStringContainsString('setConfirmBroadcastLoading(true);', $composerScript);
        $this->assertStringContainsString('confirmBtn.disabled = isLoading;', $composerScript);
        $this->assertStringContainsString('refreshBroadcastFeedsInBackground()', $composerScript);
        $this->assertStringContainsString('viewBroadcastAlertInFeed()', $composerScript);
        $this->assertStringContainsString('am-alert-card-highlight', $composerScript);

        $confirmStart = strpos($composerScript, 'async function confirmBroadcast()');
        $confirmEnd = strpos($composerScript, 'function showBroadcastReceipt()', $confirmStart);
        $confirmBody = substr($composerScript, $confirmStart, $confirmEnd - $confirmStart);

        $this->assertStringNotContainsString('await loadDatabaseAlertsData();', $confirmBody);
        $this->assertLessThan(
            strpos($confirmBody, 'refreshBroadcastFeedsInBackground();'),
            strpos($confirmBody, 'showBroadcastReceipt();')
        );
    }
    public function test_service_alert_archive_success_receipt_precedes_background_refresh(): void
    {
        $composerScript = file_get_contents(public_path('js/admin-dashboard/alerts.js'));
        $alertsView = file_get_contents(resource_path('views/admin/alerts/index.blade.php'));

        $this->assertStringContainsString('id="receipt-primary-action"', $alertsView);
        $this->assertStringContainsString('id="receipt-secondary-action"', $alertsView);
        $this->assertStringContainsString('data-archive-alert-id="${alert.id}"', $composerScript);
        $this->assertStringContainsString('showArchiveStatusModal', $composerScript);
        $this->assertStringContainsString('setArchiveButtonLoading(id, true);', $composerScript);
        $this->assertStringContainsString('setArchiveButtonLoading(id, false);', $composerScript);
        $this->assertStringContainsString('refreshArchiveViewsInBackground()', $composerScript);
        $this->assertStringContainsString('if (!isAlertHistoryVaultVisible()) return;', $composerScript);
        $this->assertStringContainsString('showArchiveStatusModal(\'Archive Blocked\'', $composerScript);

        $deleteStart = strpos($composerScript, 'async function deleteAlert(id)');
        $deleteEnd = strpos($composerScript, 'function editScheduledAlert', $deleteStart);
        $deleteBody = substr($composerScript, $deleteStart, $deleteEnd - $deleteStart);

        $this->assertStringNotContainsString('alert(', $deleteBody);
        $this->assertStringNotContainsString('await loadDatabaseAlertsData();', $deleteBody);
        $this->assertStringNotContainsString('await loadHistoryAlertsData();', $deleteBody);
        $this->assertLessThan(
            strpos($deleteBody, 'refreshArchiveViewsInBackground();'),
            strpos($deleteBody, 'showArchiveStatusModal(\'Alert Archived Successfully\'')
        );
    }
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Canonical route alert',
            'message' => 'Public service alert for official routes.',
            'severity' => 'Medium',
            'type' => 'Delay',
            'affects' => ['Route 2'],
            'timing' => 'now',
            'suspend_route' => false,
        ], $overrides);
    }

    private function makeRoute(string $name): Route
    {
        return Route::create([
            'name' => $name,
            'description' => $name . ' description',
            'color' => '#003F87',
            'status' => 'Active',
        ]);
    }
}



