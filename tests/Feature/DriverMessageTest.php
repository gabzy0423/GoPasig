<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\DriverMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DriverMessageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function dispatcher_can_send_message_to_driver(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);
        $driver = Driver::factory()->create(['first_name' => 'Juan']);

        $response = $this->actingAs($dispatcher)->postJson(
            route('fleet.api.drivers-message', ['id' => 'DRV-' . str_pad($driver->id, 4, '0', STR_PAD_LEFT)]),
            ['message' => 'Please report to depot after your last trip.']
        );

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Message sent to Juan',
            ]);

        $this->assertDatabaseHas('driver_messages', [
            'driver_id' => $driver->id,
            'sender_id' => $dispatcher->id,
            'message' => 'Please report to depot after your last trip.',
            'is_read' => false,
        ]);
    }

    #[Test]
    public function message_driver_returns_404_for_unknown_driver(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);

        $response = $this->actingAs($dispatcher)->postJson(
            route('fleet.api.drivers-message', ['id' => 'DRV-9999']),
            ['message' => 'Hello']
        );

        $response->assertNotFound()
            ->assertJson(['success' => false, 'message' => 'Driver not found.']);

        $this->assertDatabaseCount('driver_messages', 0);
    }

    #[Test]
    public function message_driver_requires_message_body(): void
    {
        $dispatcher = User::factory()->create(['role' => 'dispatcher']);
        $driver = Driver::factory()->create();

        $response = $this->actingAs($dispatcher)->postJson(
            route('fleet.api.drivers-message', ['id' => $driver->id]),
            ['message' => '']
        );

        $response->assertUnprocessable();
        $this->assertDatabaseCount('driver_messages', 0);
    }

    #[Test]
    public function unauthorized_users_cannot_message_drivers(): void
    {
        $driver = Driver::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->postJson(
            route('fleet.api.drivers-message', ['id' => $driver->id]),
            ['message' => 'Unauthorized attempt']
        );

        $response->assertForbidden();
        $this->assertDatabaseCount('driver_messages', 0);
    }

    #[Test]
    public function driver_can_view_messages_on_announcements_page(): void
    {
        $driverUser = User::factory()->create(['role' => 'driver', 'name' => 'Fleet Dispatcher']);
        $driver = Driver::factory()->create(['user_id' => $driverUser->id]);
        $dispatcher = User::factory()->create(['role' => 'dispatcher', 'name' => 'Maria Santos']);

        DriverMessage::create([
            'driver_id' => $driver->id,
            'sender_id' => $dispatcher->id,
            'message' => 'Shift ends at 6 PM today.',
            'is_read' => false,
        ]);

        $response = $this->actingAs($driverUser)->get(route('driver.announcements'));

        $response->assertOk();
        $response->assertSee('Shift ends at 6 PM today.');
        $response->assertSee('Maria Santos');
        $response->assertSee('From Dispatch');
    }
}
