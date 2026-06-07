<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Route;
use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FleetAnnouncementsTest extends TestCase
{
    use RefreshDatabase;

    private $dispatcher;
    private $route;

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
    }

    public function test_dispatcher_can_access_fleet_announcements(): void
    {
        $response = $this->actingAs($this->dispatcher)->get('/fleet/announcements');
        $response->assertStatus(200);
        $response->assertSeeLivewire('fleet.announcements-management');
    }

    public function test_unauthorized_users_cannot_access_fleet_announcements(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get('/fleet/announcements');
        $response->assertStatus(403);

        $driver = User::factory()->create(['role' => 'driver']);
        $response = $this->actingAs($driver)->get('/fleet/announcements');
        $response->assertStatus(403);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/fleet/announcements');
        $response->assertRedirect('/login');
    }

    public function test_livewire_component_loads_successfully(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.announcements-management')
            ->assertSet('filterPriority', 'all')
            ->assertSet('filterAudience', 'all')
            ->assertSet('filterStatus', 'all')
            ->assertSee('All Announcements')
            ->assertSee('No announcements found');
    }

    public function test_livewire_can_post_announcement_immediately(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.announcements-management')
            ->call('openCreateModal')
            ->set('headline', 'System Maintenance Notice')
            ->set('body', 'The Libreng Sakay GPS services will undergo minor maintenance at midnight.')
            ->set('priority', 'Medium')
            ->set('audience', 'All Users')
            ->set('affected_route', 'All Routes')
            ->set('is_draft', false)
            ->call('saveAnnouncement')
            ->assertHasNoErrors()
            ->assertDispatched('announcement-saved');

        $this->assertDatabaseHas('announcements', [
            'headline' => 'System Maintenance Notice',
            'body' => 'The Libreng Sakay GPS services will undergo minor maintenance at midnight.',
            'priority' => 'Medium',
            'audience' => 'All Users',
            'affected_route' => null,
            'is_draft' => false,
            'posted_by' => $this->dispatcher->name,
        ]);

        $announcement = Announcement::first();
        $this->assertEquals('Active', $announcement->status);
    }

    public function test_livewire_can_save_announcement_as_draft(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.announcements-management')
            ->call('openCreateModal')
            ->set('headline', 'Draft Announcement')
            ->set('body', 'This is a draft announcement details.')
            ->set('priority', 'Low')
            ->set('audience', 'Drivers')
            ->set('affected_route', 'Route 1')
            ->set('is_draft', true)
            ->call('saveAnnouncement')
            ->assertHasNoErrors()
            ->assertDispatched('announcement-saved');

        $this->assertDatabaseHas('announcements', [
            'headline' => 'Draft Announcement',
            'is_draft' => true,
            'affected_route' => 'Route 1',
        ]);

        $announcement = Announcement::first();
        $this->assertEquals('Draft', $announcement->status);
    }

    public function test_livewire_can_schedule_announcement(): void
    {
        $this->actingAs($this->dispatcher);

        $scheduledTime = now()->addDays(2)->format('Y-m-d\TH:i');

        Livewire::test('fleet.announcements-management')
            ->call('openCreateModal')
            ->set('headline', 'Scheduled Road Closure')
            ->set('body', 'Shaw Blvd crossing will be closed in two days.')
            ->set('priority', 'High')
            ->set('audience', 'Commuters')
            ->set('is_scheduled', true)
            ->set('scheduled_at', $scheduledTime)
            ->set('is_draft', false)
            ->call('saveAnnouncement')
            ->assertHasNoErrors();

        $announcement = Announcement::first();
        $this->assertTrue($announcement->is_scheduled);
        $this->assertEquals('Scheduled', $announcement->status);
    }

    public function test_livewire_validates_required_fields(): void
    {
        $this->actingAs($this->dispatcher);

        Livewire::test('fleet.announcements-management')
            ->call('openCreateModal')
            ->set('headline', '')
            ->set('body', 'sh')
            ->call('saveAnnouncement')
            ->assertHasErrors(['headline', 'body']);
    }

    public function test_livewire_can_edit_announcement(): void
    {
        $this->actingAs($this->dispatcher);

        $announcement = Announcement::create([
            'headline' => 'Old Headline',
            'body' => 'Old Body of the announcement.',
            'priority' => 'Low',
            'audience' => 'Drivers',
            'posted_by' => 'Danielle Dispatcher',
        ]);

        Livewire::test('fleet.announcements-management')
            ->call('openEditModal', $announcement->id)
            ->assertSet('headline', 'Old Headline')
            ->set('headline', 'Updated Headline')
            ->set('body', 'Updated Body of the announcement.')
            ->call('saveAnnouncement')
            ->assertHasNoErrors()
            ->assertDispatched('announcement-saved');

        $announcement->refresh();
        $this->assertEquals('Updated Headline', $announcement->headline);
        $this->assertEquals('Updated Body of the announcement.', $announcement->body);
    }

    public function test_livewire_can_delete_announcement(): void
    {
        $this->actingAs($this->dispatcher);

        $announcement = Announcement::create([
            'headline' => 'To Be Deleted',
            'body' => 'Some body of the announcement.',
            'posted_by' => 'Danielle Dispatcher',
        ]);

        Livewire::test('fleet.announcements-management')
            ->call('deleteAnnouncement', $announcement->id)
            ->assertDispatched('announcement-deleted');

        $this->assertDatabaseMissing('announcements', [
            'id' => $announcement->id,
        ]);
    }

    public function test_announcement_expires_correctly(): void
    {
        $announcement = Announcement::create([
            'headline' => 'Expired Notice',
            'body' => 'Notice body that has expired.',
            'expires_at' => now()->subHour(),
            'posted_by' => 'Danielle Dispatcher',
        ]);

        $this->assertEquals('Expired', $announcement->status);
    }
}
