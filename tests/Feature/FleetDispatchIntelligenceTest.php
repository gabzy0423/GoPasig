<?php

namespace Tests\Feature;

use App\Exceptions\BusUnavailableException;
use App\Exceptions\DispatchException;
use App\Exceptions\DriverUnavailableException;
use App\Exceptions\ScheduleConflictException;
use App\Http\Controllers\Fleet\DispatchIntelligenceController;
use App\Models\Bus;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\DemandThreshold;
use App\Models\DispatchSimulatorCount;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\User;
use App\Services\Commuter\CommuterJourneyCoordinator;
use App\Services\ReactiveDispatchDecisionService;
use App\Services\SimulationDispatchService;
use Carbon\Carbon;
use Database\Seeders\DemandIntelligenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class FleetDispatchIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private $dispatcher;

    private $route;

    private $stop1;

    private $stop2;

    private RouteVariant $variant;

    private RouteVariantStop $variantOrigin;

    private RouteVariantStop $variantDestination;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Carbon::setTestNow(Carbon::parse('2026-08-03 08:00:00', 'Asia/Manila'));

        $this->dispatcher = User::factory()->create(['role' => 'fleet_manager']);

        $this->route = Route::create([
            'name' => 'Route 2',
            'description' => 'SPED to Ligaya',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5838, 121.0620]],
            'status' => 'Active',
        ]);

        $this->stop1 = Stop::create([
            'route_id' => $this->route->id,
            'name' => 'SPED Terminal',
            'lat' => 14.5593,
            'lng' => 121.0805,
            'sequence' => 1,
        ]);

        $this->stop2 = Stop::create([
            'route_id' => $this->route->id,
            'name' => 'Pasig City Hall',
            'lat' => 14.5838,
            'lng' => 121.0620,
            'sequence' => 2,
        ]);

        $this->variant = $this->makeUsableVariant($this->route, $this->stop1, $this->stop2);
        $this->variantOrigin = $this->variant->stops->first();
        $this->variantDestination = $this->variant->stops->last();

        RouteServiceSchedule::create([
            'route_id' => $this->route->id,
            'route_variant_id' => $this->variant->id,
            'first_trip_time' => '05:30:00',
            'last_trip_time' => '17:00:00',
            'service_configuration' => 'with_designated_stops',
            'service_days' => ['mon'],
            'is_active' => true,
            'source' => RouteServiceSchedule::SOURCE_BENEFICIARY_OFFICIAL,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_dispatcher_can_access_dispatch_intelligence(): void
    {
        $response = $this->actingAs($this->dispatcher)->get('/fleet/dispatch-intelligence');
        $response->assertRedirect('/fleet/dashboard?tab=dispatch-intelligence');

        $dashboardResponse = $this->actingAs($this->dispatcher)->get('/fleet/dashboard?tab=dispatch-intelligence');
        $dashboardResponse->assertStatus(200);
    }

    public function test_direction_selection_survives_demand_board_polling_refreshes(): void
    {
        $script = file_get_contents(public_path('js/fleet-dashboard/dispatch-intelligence.js'));
        $view = file_get_contents(resource_path('views/fleet/dispatch-intelligence/index.blade.php'));

        $this->assertStringContainsString('const dispatchVariantSelections = new Map();', $script);
        $this->assertStringContainsString('captureDispatchVariantSelections(container);', $script);
        $this->assertStringContainsString('data-dispatch-variant-route="${route.id}"', $script);
        $this->assertStringContainsString("showDispatchNotification('Select a direction for this route before dispatching.', true);", $script);
        $this->assertStringContainsString('${r.name || `Route ${r.id}`}', $script);
        $this->assertStringContainsString('{{ $r->name }}', $view);
        $this->assertStringContainsString('data-dispatch-variant-route="{{ $r->id }}"', $view);
    }

    public function test_unauthorized_users_cannot_access_dispatch_intelligence(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/fleet/dispatch-intelligence');
        $response->assertStatus(403);

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/fleet/dispatch-intelligence');
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/fleet/dispatch-intelligence');
        $response->assertRedirect('/login');
    }

    public function test_api_data_loads_successfully(): void
    {
        $response = $this->actingAs($this->dispatcher)->get('/fleet/api/dispatch-data');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'routesData' => [
                '*' => [
                    'auto_count',
                    'unresolved_waiting_count',
                    'max_direction_waiting_count',
                    'variants' => [
                        '*' => ['id', 'direction', 'waiting_count'],
                    ],
                ],
            ],
            'activeAlerts',
            'customThreshold',
            'recentDispatches',
            'historicalPatterns',
        ]);
    }

    public function test_can_update_threshold(): void
    {
        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-save-threshold', [
            'route_id' => $this->route->id,
            'day' => Carbon::now()->englishDayOfWeek,
            'time_slot' => '08:00-10:00',
            'threshold' => 25,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('demand_thresholds', [
            'route_id' => $this->route->id,
            'threshold_count' => 25,
        ]);
    }

    public function test_can_simulate_commuter_activity(): void
    {
        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-add-commuter', [
            'route_id' => $this->route->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        $this->assertDatabaseHas('commuter_trips', [
            'route_id' => $this->route->id,
            'status' => 'WAITING',
            'is_simulated' => true,
        ]);
    }

    public function test_real_commuter_request_remains_non_simulated(): void
    {
        $token = 'real-commuter-session';
        CommuterSession::create([
            'session_token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        $trip = app(CommuterJourneyCoordinator::class)
            ->initializeWaitingJourney($token, $this->stop1->id, $this->stop2->id);

        $this->assertFalse($trip->fresh()->is_simulated);
        $this->assertDatabaseHas('commuter_trips', [
            'session_token' => $token,
            'route_id' => $this->route->id,
            'status' => 'WAITING',
            'is_simulated' => false,
        ]);
    }

    public function test_simulator_demand_is_excluded_from_live_auto_count(): void
    {
        $realToken = 'real-demand-session';
        CommuterSession::create([
            'session_token' => $realToken,
            'expires_at' => now()->addHours(24),
        ]);
        CommuterTrip::create([
            'session_token' => $realToken,
            'route_id' => $this->route->id,
            ...$this->outboundVariantIdentity(),
            'origin_stop_id' => $this->stop1->id,
            'destination_stop_id' => $this->stop2->id,
            'status' => 'WAITING',
            'is_simulated' => false,
        ]);

        $simToken = 'simulated-demand-session';
        CommuterSession::create([
            'session_token' => $simToken,
            'expires_at' => now()->addHours(24),
        ]);
        CommuterTrip::create([
            'session_token' => $simToken,
            'route_id' => $this->route->id,
            ...$this->outboundVariantIdentity(),
            'origin_stop_id' => $this->stop1->id,
            'destination_stop_id' => $this->stop2->id,
            'status' => 'WAITING',
            'is_simulated' => true,
        ]);

        DispatchSimulatorCount::create([
            'route_id' => $this->route->id,
            'day_of_week' => 'Monday',
            'time_slot' => '08:00-10:00',
            'manual_count' => 3,
        ]);

        $routeData = app(DispatchIntelligenceController::class)
            ->fetchRoutesData('Monday', '08:00-10:00', 1)
            ->firstWhere('id', $this->route->id);

        $this->assertSame(1, $routeData->auto_count);
        $this->assertSame(1, $routeData->total);
        $this->assertSame(4, $routeData->simulator_total);
    }

    public function test_real_waiting_demand_is_grouped_by_direction_and_unresolved_rows_are_excluded(): void
    {
        $inbound = $this->makeUsableVariant($this->route, $this->stop2, $this->stop1, 'inbound');
        $inboundOrigin = $inbound->stops->first();
        $inboundDestination = $inbound->stops->last();

        foreach (['outbound-one', 'outbound-two'] as $token) {
            CommuterSession::create([
                'session_token' => $token,
                'expires_at' => now()->addHour(),
            ]);
            CommuterTrip::create([
                'session_token' => $token,
                'route_id' => $this->route->id,
                ...$this->outboundVariantIdentity(),
                'origin_stop_id' => $this->stop1->id,
                'destination_stop_id' => $this->stop2->id,
                'status' => 'WAITING',
                'is_simulated' => false,
            ]);
        }

        CommuterSession::create([
            'session_token' => 'inbound-one',
            'expires_at' => now()->addHour(),
        ]);
        CommuterTrip::create([
            'session_token' => 'inbound-one',
            'route_id' => $this->route->id,
            'route_variant_id' => $inbound->id,
            'origin_stop_id' => $this->stop2->id,
            'origin_route_variant_stop_id' => $inboundOrigin->id,
            'destination_stop_id' => $this->stop1->id,
            'destination_route_variant_stop_id' => $inboundDestination->id,
            'status' => 'WAITING',
            'is_simulated' => false,
        ]);

        CommuterSession::create([
            'session_token' => 'unresolved-one',
            'expires_at' => now()->addHour(),
        ]);
        CommuterTrip::create([
            'session_token' => 'unresolved-one',
            'route_id' => $this->route->id,
            'origin_stop_id' => $this->stop1->id,
            'destination_stop_id' => $this->stop2->id,
            'status' => 'WAITING',
            'is_simulated' => false,
        ]);
        DemandThreshold::create([
            'route_id' => $this->route->id,
            'day_of_week' => 'Monday',
            'time_slot' => '08:00-10:00',
            'threshold_count' => 2,
        ]);

        $routeData = app(DispatchIntelligenceController::class)
            ->fetchRoutesData('Monday', '08:00-10:00', 1)
            ->firstWhere('id', $this->route->id);
        $outboundData = $routeData->variants->firstWhere('id', $this->variant->id);
        $inboundData = $routeData->variants->firstWhere('id', $inbound->id);

        $this->assertSame(2, $outboundData['waiting_count']);
        $this->assertSame(1, $inboundData['waiting_count']);
        $this->assertSame(3, $routeData->auto_count);
        $this->assertSame(3, $routeData->total);
        $this->assertSame(2, $routeData->max_direction_waiting_count);
        $this->assertSame(1, $routeData->unresolved_waiting_count);
        $this->assertSame('red', $routeData->status);
        $this->assertSame($this->variant->id, $routeData->critical_variant['id']);

        $this->actingAs($this->dispatcher)
            ->getJson('/fleet/api/dispatch-data?phase=1&day=Monday&time_slot=08%3A00-10%3A00&route_id='.$this->route->id)
            ->assertOk()
            ->assertJsonFragment([
                'route_variant_id' => $this->variant->id,
                'direction' => 'outbound',
                'type' => 'reactive',
            ]);

        $outboundDecision = app(ReactiveDispatchDecisionService::class)
            ->evaluate($this->route, $this->variant, now());
        $inboundDecision = app(ReactiveDispatchDecisionService::class)
            ->evaluate($this->route, $inbound, now());

        $this->assertSame(2, $outboundDecision['waiting_count']);
        $this->assertSame(1, $inboundDecision['waiting_count']);
    }

    public function test_clear_simulator_data_deletes_only_simulator_waiting_rows(): void
    {
        $realToken = 'real-clear-session';
        CommuterSession::create([
            'session_token' => $realToken,
            'expires_at' => now()->addHours(24),
        ]);
        CommuterTrip::create([
            'session_token' => $realToken,
            'route_id' => $this->route->id,
            ...$this->outboundVariantIdentity(),
            'origin_stop_id' => $this->stop1->id,
            'destination_stop_id' => $this->stop2->id,
            'status' => 'WAITING',
            'is_simulated' => false,
        ]);

        $simToken = 'simulated-clear-session';
        CommuterSession::create([
            'session_token' => $simToken,
            'expires_at' => now()->addHours(24),
        ]);
        CommuterTrip::create([
            'session_token' => $simToken,
            'route_id' => $this->route->id,
            ...$this->outboundVariantIdentity(),
            'origin_stop_id' => $this->stop1->id,
            'destination_stop_id' => $this->stop2->id,
            'status' => 'WAITING',
            'is_simulated' => true,
        ]);
        DispatchSimulatorCount::create([
            'route_id' => $this->route->id,
            'day_of_week' => 'Monday',
            'time_slot' => '08:00-10:00',
            'manual_count' => 3,
        ]);

        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-clear-simulator');

        $response->assertOk()->assertJson(['success' => true]);
        $this->assertDatabaseHas('commuter_trips', [
            'session_token' => $realToken,
            'status' => 'WAITING',
            'is_simulated' => false,
        ]);
        $this->assertDatabaseMissing('commuter_trips', [
            'session_token' => $simToken,
        ]);
        $this->assertDatabaseCount('dispatch_simulator_counts', 0);
    }

    public function test_non_official_routes_cannot_enter_live_dispatch_intelligence_demand(): void
    {
        foreach (['Route A', 'Route B', 'Route C', 'Route D', 'Route 9', 'PHASE3C-UAT Point-to-Point A-B', 'Bridgetowne'] as $name) {
            $legacy = Route::create([
                'name' => $name,
                'description' => $name,
                'status' => 'Active',
            ]);
            $origin = Stop::create([
                'route_id' => $legacy->id,
                'name' => $name.' Origin',
                'lat' => 14.5,
                'lng' => 121.0,
                'sequence' => 1,
            ]);
            $destination = Stop::create([
                'route_id' => $legacy->id,
                'name' => $name.' Destination',
                'lat' => 14.6,
                'lng' => 121.1,
                'sequence' => 2,
            ]);
            $token = 'legacy-demand-'.$legacy->id;
            CommuterSession::create([
                'session_token' => $token,
                'expires_at' => now()->addHours(24),
            ]);
            CommuterTrip::create([
                'session_token' => $token,
                'route_id' => $legacy->id,
                'origin_stop_id' => $origin->id,
                'destination_stop_id' => $destination->id,
                'status' => 'WAITING',
                'is_simulated' => false,
            ]);
        }

        Cache::flush();

        $routesData = app(DispatchIntelligenceController::class)
            ->fetchRoutesData('Monday', '08:00-10:00', 1);

        $this->assertSame(['Route 2'], $routesData->pluck('name')->values()->all());
        $this->assertSame(0, $routesData->first()->auto_count);
    }

    public function test_suspended_official_route_is_excluded_from_dispatch_intelligence(): void
    {
        $this->route->update(['status' => 'Suspended']);
        Cache::flush();

        $routesData = app(DispatchIntelligenceController::class)
            ->fetchRoutesData('Monday', '08:00-10:00', 1);

        $this->assertSame([], $routesData->pluck('name')->values()->all());
    }

    public function test_demand_intelligence_seeder_resolves_only_active_official_routes_and_does_not_seed_fake_demand(): void
    {
        foreach (['Route 3', 'Route 4', 'Route A', 'Route B', 'Route C', 'Route D', 'Route 9', 'PHASE3C-UAT Point-to-Point A-B', 'Bridgetowne'] as $name) {
            Route::create([
                'name' => $name,
                'description' => $name,
                'status' => 'Active',
            ]);
        }
        Route::where('name', 'Bridgetowne')->firstOrFail()->delete();
        Cache::flush();

        $this->seed(DemandIntelligenceSeeder::class);

        $thresholdRouteNames = DemandThreshold::with('route')
            ->get()
            ->pluck('route.name')
            ->unique()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(['Route 2', 'Route 3', 'Route 4'], $thresholdRouteNames);
        $this->assertDatabaseCount('demand_history', 0);
        $this->assertDatabaseCount('commuter_trips', 0);
    }

    public function test_simulator_actions_do_not_automatically_dispatch(): void
    {
        $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-add-commuter', [
            'route_id' => $this->route->id,
        ])->assertOk();

        $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-add-manual', [
            'route_id' => $this->route->id,
            'day' => 'Monday',
            'time_slot' => '08:00-10:00',
        ])->assertOk();

        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('dispatch_logs', 0);
    }

    public function test_can_dispatch_bus_and_reset_queue(): void
    {
        // Seed an available bus and active/available driver
        $bus = Bus::create([
            'plate_number' => 'PAS-555',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-5555',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        // Seed commuter checking in
        $token = 'test-token-123';
        CommuterSession::create([
            'session_token' => $token,
            'expires_at' => now()->addHours(24),
        ]);

        CommuterTrip::create([
            'session_token' => $token,
            'route_id' => $this->route->id,
            ...$this->outboundVariantIdentity(),
            'origin_stop_id' => $this->stop1->id,
            'destination_stop_id' => $this->stop2->id,
            'status' => 'WAITING',
            'is_simulated' => false,
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-now', [
            'route_id' => $this->route->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);

        // Assert bus and driver are now dispatched/assigned
        $bus->refresh();
        $this->assertEquals('ready', $bus->status);

        $driver->refresh();
        $this->assertEquals('active', $driver->status);
        $this->assertEquals('assigned', $driver->operational_status);

        // Reactive dispatch must not bypass commuter geofence boarding.
        $this->assertDatabaseHas('commuter_trips', [
            'route_id' => $this->route->id,
            'status' => 'WAITING',
            'bus_id' => null,
            'boarded_at' => null,
        ]);

        // Assert Dispatch Log exists
        $this->assertDatabaseCount('dispatch_logs', 1);
    }

    public function test_dispatch_rejects_direction_outside_official_operating_window(): void
    {
        Bus::create([
            'plate_number' => 'PAS-CLOSED',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        Driver::create([
            'emp_id' => 'EMP-CLOSED',
            'first_name' => 'Closed',
            'last_name' => 'Window',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-08-03 18:01:00', 'Asia/Manila'));

        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-now', [
            'route_id' => $this->route->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
        $response->assertJsonFragment([
            'message' => 'Dispatch failed: The outbound direction is outside its official operating window.',
        ]);
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('dispatch_logs', 0);
    }

    public function test_dispatch_fails_when_no_inactive_bus_or_driver_available(): void
    {
        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-now', [
            'route_id' => $this->route->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['success' => false]);
    }

    public function test_dispatch_creates_single_trip_and_single_dispatch_log(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-X1',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-X1',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456781',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        $response = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-now', [
            'route_id' => $this->route->id,
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseCount('trips', 1);
        $this->assertDatabaseCount('dispatch_logs', 1);
    }

    public function test_duplicate_dispatch_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-X2',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-X2',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456782',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        // First dispatch
        $response1 = $this->actingAs($this->dispatcher)->post('/fleet/api/dispatch-now', [
            'route_id' => $this->route->id,
        ]);
        $response1->assertStatus(200);

        // Replay dispatch immediately with same resource
        try {
            SimulationDispatchService::dispatch($bus, $driver, $this->route);
            $this->fail('Duplicate dispatch should throw DispatchException.');
        } catch (DispatchException $e) {
            $this->assertTrue(
                str_contains($e->getMessage(), 'Active dispatched/ongoing trip exists') ||
                str_contains($e->getMessage(), 'not available for Central Dispatch')
            );
        }
    }

    public function test_maintenance_bus_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-MNT',
            'status' => 'maintenance',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-MNT',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456783',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        $this->expectException(BusUnavailableException::class);
        $this->expectExceptionMessage('Maintenance.');

        SimulationDispatchService::dispatch($bus, $driver, $this->route);
    }

    public function test_breakdown_bus_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-BRK',
            'status' => 'breakdown',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-BRK',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456784',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        $this->expectException(BusUnavailableException::class);
        $this->expectExceptionMessage('Breakdown.');

        SimulationDispatchService::dispatch($bus, $driver, $this->route);
    }

    public function test_unavailable_driver_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-OK',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-SUSP',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456785',
            'license_expiry' => '2027-12-12',
            'status' => 'suspended',
        ]);

        $this->expectException(DriverUnavailableException::class);
        $this->expectExceptionMessage('Suspended.');

        SimulationDispatchService::dispatch($bus, $driver, $this->route);
    }

    public function test_expired_license_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-OK2',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-EXP',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456786',
            'license_expiry' => '2020-12-12', // expired
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        $this->expectException(DriverUnavailableException::class);
        $this->expectExceptionMessage('License expired.');

        SimulationDispatchService::dispatch($bus, $driver, $this->route);
    }

    public function test_schedule_conflict_rejected(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-CON',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-CON',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456787',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        // Seed conflicting schedule for this bus
        Schedule::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $this->route->id,
            'departure_time' => now()->format('H:i:s'),
            'arrival_time' => now()->addMinutes(60)->format('H:i:s'),
            'service_date' => now()->toDateString(),
            'status' => 'scheduled',
        ]);

        $this->expectException(ScheduleConflictException::class);
        $this->expectExceptionMessage('already scheduled');

        SimulationDispatchService::dispatch($bus, $driver, $this->route);
    }

    public function test_transaction_rollback_restores_all_state_and_no_orphan_records(): void
    {
        $bus = Bus::create([
            'plate_number' => 'PAS-ROLL',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $driver = Driver::create([
            'emp_id' => 'EMP-ROLL',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'N01-23-456788',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'operational_status' => 'available',
        ]);

        // Mock Log facade to throw exception inside transition to force rollback midway
        Log::shouldReceive('info')
            ->andThrow(new \RuntimeException('Forced rollback'));

        try {
            SimulationDispatchService::dispatch($bus, $driver, $this->route);
            $this->fail('Rollback should have thrown exception.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Forced rollback', $e->getMessage());
        }

        // Verify: no partial updates remain (bus is still standby, no trips created, no dispatch logs)
        $bus->refresh();
        $this->assertSame('inactive', $bus->status);
        $this->assertDatabaseCount('trips', 0);
        $this->assertDatabaseCount('dispatch_logs', 0);
    }

    private function makeUsableVariant(
        Route $route,
        Stop $origin,
        Stop $destination,
        string $direction = 'outbound'
    ): RouteVariant {
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => $origin->name,
            'destination_name' => $destination->name,
            'polyline_coordinates' => [
                [(float) $origin->lat, (float) $origin->lng],
                [(float) $destination->lat, (float) $destination->lng],
            ],
            'geometry_status' => 'valid',
            'is_default' => $direction === 'outbound',
        ]);

        RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'canonical_stop_id' => $origin->id,
            'name' => $origin->name,
            'lat' => $origin->lat,
            'lng' => $origin->lng,
            'sequence' => 1,
        ]);

        RouteVariantStop::create([
            'route_variant_id' => $variant->id,
            'canonical_stop_id' => $destination->id,
            'name' => $destination->name,
            'lat' => $destination->lat,
            'lng' => $destination->lng,
            'sequence' => 2,
        ]);

        return $variant;
    }

    private function outboundVariantIdentity(): array
    {
        return [
            'route_variant_id' => $this->variant->id,
            'origin_route_variant_stop_id' => $this->variantOrigin->id,
            'destination_route_variant_stop_id' => $this->variantDestination->id,
        ];
    }
}
