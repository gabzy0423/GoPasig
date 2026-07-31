<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Trip;
use App\Models\User;
use App\Services\RouteVariantSelectionService;
use App\Services\SimulationDispatchService;
use Database\Seeders\UATSuspendRouteFixtureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UATSuspendRouteFixtureTest extends TestCase
{
    use RefreshDatabase;

    private function seedFixture(): array
    {
        return (new UATSuspendRouteFixtureSeeder())->run();
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_seeder_creates_isolated_records_and_leaves_official_routes_unchanged(): void
    {
        foreach (['Route 1', 'Route 2', 'Route 3'] as $name) {
            Route::create(['name' => $name, 'status' => 'Active']);
        }

        $this->seedFixture();

        $route = Route::where('name', UATSuspendRouteFixtureSeeder::ROUTE_NAME)->firstOrFail();

        $this->assertSame(1, Route::where('name', UATSuspendRouteFixtureSeeder::ROUTE_NAME)->count());
        $this->assertSame(2, RouteVariant::where('route_id', $route->id)->count());
        $this->assertSame(2, Bus::whereIn('plate_number', [UATSuspendRouteFixtureSeeder::OUTBOUND_BUS_PLATE, UATSuspendRouteFixtureSeeder::INBOUND_BUS_PLATE])->count());
        $this->assertSame(2, Driver::whereIn('emp_id', [UATSuspendRouteFixtureSeeder::OUTBOUND_DRIVER_EMP_ID, UATSuspendRouteFixtureSeeder::INBOUND_DRIVER_EMP_ID])->count());
        $this->assertSame(2, Schedule::where('route_id', $route->id)->count());
        $this->assertSame(['Route 1', 'Route 2', 'Route 3'], Route::whereIn('name', ['Route 1', 'Route 2', 'Route 3'])->orderBy('name')->pluck('name')->all());
    }

    public function test_geometry_is_usable_for_both_directions(): void
    {
        $this->seedFixture();
        $route = Route::where('name', UATSuspendRouteFixtureSeeder::ROUTE_NAME)->firstOrFail();
        $selection = app(RouteVariantSelectionService::class);

        $outbound = RouteVariant::with('stops')->where('route_id', $route->id)->where('direction', 'outbound')->firstOrFail();
        $inbound = RouteVariant::with('stops')->where('route_id', $route->id)->where('direction', 'inbound')->firstOrFail();

        $this->assertTrue($selection->isUsableForLiveDispatch($outbound));
        $this->assertTrue($selection->isUsableForLiveDispatch($inbound));
        $this->assertCount(3, $outbound->polyline_coordinates);
        $this->assertCount(3, $inbound->polyline_coordinates);
        $this->assertTrue($outbound->stops->every(fn ($stop) => $stop->lat !== null && $stop->lng !== null));
        $this->assertTrue($inbound->stops->every(fn ($stop) => $stop->lat !== null && $stop->lng !== null));
    }

    public function test_direction_options_are_selectable_in_dispatch_builder_state(): void
    {
        $this->seedFixture();
        $route = Route::where('name', UATSuspendRouteFixtureSeeder::ROUTE_NAME)->firstOrFail();

        $component = Livewire::test('admin.dispatch-builder')
            ->set('selectedRoute', (string) $route->id);

        $variants = collect($component->get('routeVariants')[$route->id] ?? [])->sortBy('direction')->values();
        $defaultVariant = RouteVariant::where('route_id', $route->id)->where('is_default', true)->firstOrFail();

        $this->assertSame((string) $defaultVariant->id, $component->get('selectedRouteVariant'));
        $this->assertCount(2, $variants);
        $this->assertTrue($variants->every(fn ($variant) => $variant['usable_for_dispatch'] === true));
        $this->assertFalse($variants->contains(fn ($variant) => ! $variant['usable_for_dispatch']));
    }

    public function test_outbound_dispatch_uses_exact_fixture_records(): void
    {
        $fixture = $this->seedFixture();

        $trip = SimulationDispatchService::dispatch(
            $fixture['outboundBus'],
            $fixture['outboundDriver'],
            $fixture['route'],
            null,
            'UAT outbound dispatch.',
            $fixture['outboundVariant']
        );

        $this->assertSame($fixture['route']->id, $trip->route_id);
        $this->assertSame($fixture['outboundVariant']->id, $trip->route_variant_id);
        $this->assertSame($fixture['outboundBus']->id, $trip->bus_id);
        $this->assertSame($fixture['outboundDriver']->id, $trip->driver_id);
        $this->assertSame('dispatched', $trip->status);
    }

    public function test_inbound_dispatch_uses_exact_fixture_records(): void
    {
        $fixture = $this->seedFixture();

        $trip = SimulationDispatchService::dispatch(
            $fixture['inboundBus'],
            $fixture['inboundDriver'],
            $fixture['route'],
            null,
            'UAT inbound dispatch.',
            $fixture['inboundVariant']
        );

        $this->assertSame($fixture['route']->id, $trip->route_id);
        $this->assertSame($fixture['inboundVariant']->id, $trip->route_variant_id);
        $this->assertSame($fixture['inboundBus']->id, $trip->bus_id);
        $this->assertSame($fixture['inboundDriver']->id, $trip->driver_id);
        $this->assertSame('dispatched', $trip->status);
    }

    public function test_suspending_route_with_ongoing_trip_keeps_trip_ongoing(): void
    {
        $fixture = $this->seedFixture();
        $admin = $this->admin();

        $trip = SimulationDispatchService::dispatch($fixture['outboundBus'], $fixture['outboundDriver'], $fixture['route'], null, 'UAT outbound dispatch.', $fixture['outboundVariant']);
        $trip->update(['status' => 'ongoing', 'started_at' => now()]);

        $response = $this->actingAs($admin)->postJson('/admin/api/alerts', [
            'title' => UATSuspendRouteFixtureSeeder::ALERT_TITLE,
            'message' => 'Temporary UAT route suspension validation.',
            'severity' => 'high',
            'type' => 'suspension',
            'affects' => [UATSuspendRouteFixtureSeeder::ROUTE_NAME],
            'timing' => 'now',
            'suspend_route' => true,
        ]);

        $response->assertStatus(201)->assertJson(['success' => true]);
        $this->assertSame('Suspended', $fixture['route']->fresh()->status);
        $this->assertSame('ongoing', $trip->fresh()->status);
    }

    public function test_both_direction_schedules_are_route_suspended_when_route_has_ongoing_trip(): void
    {
        $fixture = $this->seedFixture();
        $admin = $this->admin();

        $extraBus = Bus::create(['plate_number' => 'UAT-SR-ONGOING-BUS', 'status' => Bus::STATUS_ACTIVE, 'driver_name' => 'UAT Route Ongoing']);
        $extraDriver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'assigned']);
        Trip::create([
            'bus_id' => $extraBus->id,
            'driver_id' => $extraDriver->id,
            'route_id' => $fixture['route']->id,
            'route_variant_id' => $fixture['outboundVariant']->id,
            'status' => 'ongoing',
        ]);

        ServiceAlert::create([
            'route_id' => $fixture['route']->id,
            'title' => UATSuspendRouteFixtureSeeder::ALERT_TITLE,
            'message' => 'Temporary UAT route suspension validation.',
            'severity' => 'critical',
            'type' => 'suspension',
            'affected_routes' => UATSuspendRouteFixtureSeeder::ROUTE_NAME,
            'status' => 'active',
            'suspend_route' => true,
        ]);
        $fixture['route']->update(['status' => 'Suspended']);

        $response = $this->actingAs($admin)->getJson('/admin/api/schedules/dispatch-queue/today');
        $response->assertStatus(200);

        $dispatches = collect($response->json('dispatches'))->where('routeName', UATSuspendRouteFixtureSeeder::ROUTE_NAME)->values();
        $this->assertCount(2, $dispatches);
        $this->assertSame(['route_suspended', 'route_suspended'], $dispatches->pluck('dispatchState')->all());
        $this->assertSame([false, false], $dispatches->pluck('canDispatch')->all());
        $this->assertSame([1, 1], $dispatches->pluck('remainingActiveTrips')->all());
    }

    public function test_resolve_without_confirmation_returns_guard_and_does_not_mutate(): void
    {
        $fixture = $this->seedFixture();
        $admin = $this->admin();
        $fixture['route']->update(['status' => 'Suspended']);

        $alert = ServiceAlert::create([
            'route_id' => $fixture['route']->id,
            'title' => UATSuspendRouteFixtureSeeder::ALERT_TITLE,
            'message' => 'Temporary UAT route suspension validation.',
            'severity' => 'critical',
            'type' => 'suspension',
            'affected_routes' => UATSuspendRouteFixtureSeeder::ROUTE_NAME,
            'status' => 'active',
            'suspend_route' => true,
        ]);

        $trip = Trip::create([
            'bus_id' => $fixture['outboundBus']->id,
            'driver_id' => $fixture['outboundDriver']->id,
            'route_id' => $fixture['route']->id,
            'route_variant_id' => $fixture['outboundVariant']->id,
            'status' => 'ongoing',
        ]);

        $response = $this->actingAs($admin)->postJson("/admin/api/alerts/{$alert->id}/resolve");

        $response->assertStatus(200)->assertJson([
            'success' => false,
            'requiresConfirmation' => true,
            'remainingActiveTrips' => 1,
        ]);
        $this->assertSame('active', $alert->fresh()->status);
        $this->assertSame('Suspended', $fixture['route']->fresh()->status);
        $this->assertSame('ongoing', $trip->fresh()->status);
    }

    public function test_confirmed_resolution_restores_route_and_keeps_trip_ongoing(): void
    {
        $fixture = $this->seedFixture();
        $admin = $this->admin();
        $fixture['route']->update(['status' => 'Suspended']);

        $alert = ServiceAlert::create([
            'route_id' => $fixture['route']->id,
            'title' => UATSuspendRouteFixtureSeeder::ALERT_TITLE,
            'message' => 'Temporary UAT route suspension validation.',
            'severity' => 'critical',
            'type' => 'suspension',
            'affected_routes' => UATSuspendRouteFixtureSeeder::ROUTE_NAME,
            'status' => 'active',
            'suspend_route' => true,
        ]);

        $trip = Trip::create([
            'bus_id' => $fixture['outboundBus']->id,
            'driver_id' => $fixture['outboundDriver']->id,
            'route_id' => $fixture['route']->id,
            'route_variant_id' => $fixture['outboundVariant']->id,
            'status' => 'ongoing',
        ]);

        $response = $this->actingAs($admin)->postJson("/admin/api/alerts/{$alert->id}/resolve", ['confirm' => true]);

        $response->assertStatus(200)->assertJson(['success' => true]);
        $this->assertSame('resolved', $alert->fresh()->status);
        $this->assertSame('Active', $fixture['route']->fresh()->status);
        $this->assertSame('ongoing', $trip->fresh()->status);
    }

    public function test_cleanup_removes_only_fixture_records_and_preserves_official_routes(): void
    {
        foreach (['Route 1', 'Route 2', 'Route 3'] as $name) {
            Route::create(['name' => $name, 'status' => 'Active']);
        }
        $unrelatedBus = Bus::create(['plate_number' => 'PAS-UNRELATED', 'status' => Bus::STATUS_INACTIVE]);
        $unrelatedDriver = Driver::factory()->create(['status' => 'active', 'operational_status' => 'available']);

        $this->seedFixture();
        UATSuspendRouteFixtureSeeder::cleanup(force: true, includeLegacy: false);

        $this->assertSame(0, Route::withTrashed()->where('name', UATSuspendRouteFixtureSeeder::ROUTE_NAME)->count());
        $this->assertSame(0, Bus::whereIn('plate_number', [UATSuspendRouteFixtureSeeder::OUTBOUND_BUS_PLATE, UATSuspendRouteFixtureSeeder::INBOUND_BUS_PLATE])->count());
        $this->assertSame(0, Driver::whereIn('emp_id', [UATSuspendRouteFixtureSeeder::OUTBOUND_DRIVER_EMP_ID, UATSuspendRouteFixtureSeeder::INBOUND_DRIVER_EMP_ID])->count());
        $this->assertSame(['Route 1', 'Route 2', 'Route 3'], Route::whereIn('name', ['Route 1', 'Route 2', 'Route 3'])->orderBy('name')->pluck('name')->all());
        $this->assertDatabaseHas('buses', ['id' => $unrelatedBus->id, 'plate_number' => 'PAS-UNRELATED']);
        $this->assertDatabaseHas('drivers', ['id' => $unrelatedDriver->id]);
    }
}