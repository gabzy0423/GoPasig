<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetDriverPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Driver::create([
            'id' => 1001,
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'emp_id' => 'EMP-1001',
            'license_number' => 'N01-23-456789',
            'license_expiry' => '2027-12-12',
            'status' => 'active',
            'performance_score' => 95,
        ]);
    }

    public function test_dispatcher_can_access_driver_performance(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/drivers');

        $response->assertStatus(200);
        $response->assertSeeLivewire('fleet.driver-performance');
    }

    public function test_unauthorized_users_cannot_access_driver_performance(): void
    {
        // Admin user is unauthorized for dispatcher routes (since middleware requires role:dispatcher)
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/fleet/drivers');
        $response->assertStatus(403);

        // Driver user is unauthorized
        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/fleet/drivers');
        $response->assertStatus(403);
    }

    public function test_guest_users_are_redirected_to_login(): void
    {
        // Guest user is redirected to login
        $response = $this->get('/fleet/drivers');
        $response->assertRedirect('/login');
    }

    public function test_livewire_export_driver_report_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        Livewire::test('fleet.driver-performance')
            ->call('exportDriverReport')
            ->assertFileDownloaded();
    }

    public function test_livewire_message_driver_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        Livewire::test('fleet.driver-performance')
            ->call('messageDriver', 'DRV-0001')
            ->assertSee('Message thread initialized with Driver ID: DRV-0001');
    }

    public function test_livewire_select_driver_opens_drawer(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        Livewire::test('fleet.driver-performance')
            ->assertSet('showDrawer', false)
            ->assertSet('selectedDriverId', null)
            ->call('selectDriver', 'DRV-0001')
            ->assertSet('showDrawer', true)
            ->assertSet('selectedDriverId', 'DRV-0001')
            ->assertSee('Juan Dela Cruz')
            ->assertSee('Trips completed');
    }
}
