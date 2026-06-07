<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatcher_can_access_fleet_analytics(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->get('/fleet/analytics');

        $response->assertStatus(200);
        $response->assertSeeLivewire('fleet.analytics-report');
    }

    public function test_unauthorized_users_cannot_access_fleet_analytics(): void
    {
        // Admin user is unauthorized for dispatcher routes (since middleware requires role:dispatcher)
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/fleet/analytics');
        $response->assertStatus(403);

        // Driver user is unauthorized
        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/fleet/analytics');
        $response->assertStatus(403);
    }

    public function test_guest_users_are_redirected_to_login(): void
    {
        // Guest user is redirected to login
        $response = $this->get('/fleet/analytics');
        $response->assertRedirect('/login');
    }

    public function test_livewire_export_pdf_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        Livewire::test('fleet.analytics-report')
            ->call('exportPdf')
            ->assertSee('PDF export triggered');
    }

    public function test_livewire_export_csv_works(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        Livewire::test('fleet.analytics-report')
            ->call('exportCsv')
            ->assertFileDownloaded();
    }

    public function test_livewire_refresh_recommendations_updates_timestamp(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $this->actingAs($dispatcher);

        // Freeze time so the test-side $nowTime and the Livewire component
        // both see exactly the same clock value, regardless of minute boundaries.
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::now());

        try {
            $nowTime = now()->format('g:i A');

            Livewire::test('fleet.analytics-report')
                ->call('refreshRecommendations')
                ->assertSet('lastUpdatedTime', $nowTime)
                ->assertSee('Recommendations updated based on latest ridership data!');
        } finally {
            \Carbon\Carbon::setTestNow(); // Unfreeze time
        }
    }
}
