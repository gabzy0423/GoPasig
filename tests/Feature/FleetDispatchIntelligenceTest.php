<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\CommuterTrip;
use App\Models\DemandThreshold;
use App\Models\DemandHistory;
use App\Models\DispatchLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use Carbon\Carbon;

class FleetDispatchIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private $dispatcher;
    private $route;
    private $stop1;
    private $stop2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = User::factory()->create(['role' => 'dispatcher']);
        
        $this->route = Route::create([
            'id' => 1,
            'name' => 'Route 1',
            'description' => 'SPED to Pasig City Hall',
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
    }

    public function test_dispatcher_can_access_dispatch_intelligence(): void
    {
        $response = $this->actingAs($this->dispatcher)->get('/fleet/dispatch-intelligence');
        $response->assertStatus(200);
        $response->assertSeeLivewire('fleet.dispatch-intelligence');
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

    public function test_livewire_component_loads_successfully(): void
    {
        $this->actingAs($this->dispatcher);

        $expectedDay = Carbon::now()->englishDayOfWeek;
        $hour = (int) Carbon::now()->format('G');
        if ($hour >= 6 && $hour < 8) {
            $expectedSlot = '06:00-08:00';
        } elseif ($hour >= 8 && $hour < 12) {
            $expectedSlot = '08:00-10:00';
        } elseif ($hour >= 12 && $hour < 16) {
            $expectedSlot = '12:00-14:00';
        } elseif ($hour >= 16 && $hour < 18) {
            $expectedSlot = '16:00-18:00';
        } else {
            $expectedSlot = '18:00-20:00';
        }

        Livewire::test('fleet.dispatch-intelligence')
            ->assertSet('selectedPhase', 1)
            ->assertSet('simulatedDay', $expectedDay)
            ->assertSet('simulatedTimeSlot', $expectedSlot)
            ->assertSee('Active Commuter Demand Board')
            ->assertSee('Real-time Activity Simulator');
    }

    public function test_livewire_can_update_threshold(): void
    {
        $this->actingAs($this->dispatcher);

        // First set of thresholds is created in seeder, but let's test overriding it
        Livewire::test('fleet.dispatch-intelligence')
            ->set('selectedRouteId', $this->route->id)
            ->set('customThreshold', 25)
            ->call('saveThreshold')
            ->assertHasNoErrors()
            ->assertSee('Threshold successfully updated in database!');

        $this->assertDatabaseHas('demand_thresholds', [
            'route_id' => $this->route->id,
            'threshold_count' => 25,
        ]);
    }

    public function test_livewire_can_simulate_commuter_activity(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.dispatch-intelligence')
            ->call('addCommuter', $this->route->id)
            ->call('addManualTicker', $this->route->id)
            ->assertSet('simulatedManualCounts.' . $this->route->id, 1);

        $this->assertDatabaseHas('commuter_trips', [
            'route_id' => $this->route->id,
            'status' => 'pending',
        ]);
    }

    public function test_livewire_can_dispatch_bus_and_reset_queue(): void
    {
        $this->actingAs($this->dispatcher);

        // Seed an inactive bus and driver
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
            'status' => 'inactive',
        ]);

        // Seed commuter checking in
        CommuterTrip::create([
            'route_id' => $this->route->id,
            'origin_stop_id' => $this->stop1->id,
            'destination_stop_id' => $this->stop2->id,
            'status' => 'pending',
            'timestamp' => now(),
        ]);

        Livewire::test('fleet.dispatch-intelligence')
            ->set('simulatedManualCounts.' . $this->route->id, 5)
            ->call('dispatchNow', $this->route->id)
            ->assertSee('Bus successfully dispatched')
            ->assertSet('simulatedManualCounts.' . $this->route->id, 0);

        // Assert bus and driver are now active
        $bus->refresh();
        $this->assertEquals('active', $bus->status);

        $driver->refresh();
        $this->assertEquals('active', $driver->status);

        // Assert pending commuter check-in is now boarded
        $this->assertDatabaseHas('commuter_trips', [
            'route_id' => $this->route->id,
            'status' => 'boarded',
        ]);

        // Assert Dispatch Log exists
        $this->assertDatabaseCount('dispatch_logs', 1);
    }

    public function test_dispatch_fails_when_no_inactive_bus_or_driver_available(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.dispatch-intelligence')
            ->call('dispatchNow', $this->route->id)
            ->assertSee('No available buses or drivers');
    }
}
