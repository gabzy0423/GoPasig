<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetScheduleComplianceTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_can_access_schedule_compliance(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/schedule');

        $response->assertStatus(200);
        $response->assertSeeLivewire('fleet.schedule-compliance');
    }

    public function test_unauthorized_users_cannot_access_schedule_compliance(): void
    {
        // Admin user is unauthorized for dispatcher routes (since middleware requires role:dispatcher)
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

    public function test_livewire_export_compliance_report_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        Livewire::test('fleet.schedule-compliance')
            ->call('exportComplianceReport')
            ->assertFileDownloaded();
    }

    public function test_livewire_filter_compliance_report_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        Livewire::test('fleet.schedule-compliance')
            ->assertSet('selectedRoute', 'all')
            ->assertSet('selectedDriver', 'all')
            ->assertSet('selectedStatus', 'all')
            // Set temporary inputs
            ->set('tempRoute', '1')
            ->set('tempDriver', 'Juan Dela Cruz')
            ->set('tempStatus', 'Late')
            // Click apply filters
            ->call('applyFilters')
            // Assert values have applied
            ->assertSet('selectedRoute', '1')
            ->assertSet('selectedDriver', 'Juan Dela Cruz')
            ->assertSet('selectedStatus', 'Late');
    }
}
