<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\User;
use App\Models\MaintenanceRecord;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMaintenanceSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    public function test_only_inactive_buses_returned_in_dropdown_view(): void
    {
        $this->actingAsAdmin();

        // 1. Create one active bus
        $activeBus = Bus::create([
            'plate_number' => 'PAS-1001',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // 2. Create one inactive bus
        $inactiveBus = Bus::create([
            'plate_number' => 'PAS-1002',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // 3. Create one breakdown bus
        $breakdownBus = Bus::create([
            'plate_number' => 'PAS-1003',
            'status' => 'breakdown',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $response = $this->get('/admin/maintenance/create');
        $response->assertStatus(200);

        // Verify that the view variables contain only inactive buses
        $buses = $response->viewData('buses');
        $this->assertCount(1, $buses);
        $this->assertEquals($inactiveBus->id, $buses->first()->id);
    }

    public function test_schedule_creation_generates_persisted_ticket_number_and_locks_bus(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-1004',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $futureDate = now()->addDays(2)->format('Y-m-d\TH:i');

        $response = $this->postJson('/admin/api/maintenance', [
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'technician_name' => 'John Dela Cruz',
            'description' => 'Check battery cells and tires',
            'scheduled_at' => $futureDate,
            'expected_duration_minutes' => 180,
        ]);

        $response->assertStatus(201);
        
        $recordId = $response->json('record.id');
        $ticketNumber = $response->json('record.ticket_number');

        // Verify ticket number format: MT-YYYY-00000X
        $year = date('Y');
        $expectedTicket = 'MT-' . $year . '-' . str_pad($recordId, 6, '0', STR_PAD_LEFT);
        $this->assertEquals($expectedTicket, $ticketNumber);

        // Verify database persistence
        $this->assertDatabaseHas('maintenance_records', [
            'id' => $recordId,
            'ticket_number' => $expectedTicket,
            'status' => 'scheduled',
            'technician_name' => 'John Dela Cruz',
        ]);

        // Verify bus status locks to maintenance
        $bus->refresh();
        $this->assertEquals('maintenance', $bus->status);
    }

    public function test_cannot_schedule_maintenance_in_the_past(): void
    {
        $this->actingAsAdmin();

        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-06-01 12:00:00'));

        $bus = Bus::create([
            'plate_number' => 'PAS-1005',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $pastDate = '2026-05-15T14:00';

        $response = $this->postJson('/admin/api/maintenance', [
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'technician_name' => 'John Dela Cruz',
            'description' => 'Check battery cells and tires',
            'scheduled_at' => $pastDate,
            'expected_duration_minutes' => 180,
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'success' => false,
            'message' => 'Cannot schedule maintenance in the past.'
        ]);

        \Carbon\Carbon::setTestNow();
    }

    public function test_edit_schedule_allowed_only_for_scheduled_records(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-1006',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $record = MaintenanceRecord::create([
            'bus_id' => $bus->id,
            'type' => 'Battery Inspection',
            'technician_name' => 'Original Tech',
            'scheduled_at' => now()->addDays(1),
            'status' => 'scheduled',
            'expected_duration_minutes' => 120,
        ]);

        // 1. Edit allowed when scheduled
        $futureDate = now()->addDays(3)->format('Y-m-d\TH:i');
        $response = $this->putJson("/admin/api/maintenance/{$record->id}", [
            'technician_name' => 'Updated Tech',
            'scheduled_at' => $futureDate,
            'description' => 'New diagnostic order',
            'expected_duration_minutes' => 150,
        ]);
        $response->assertStatus(200);

        $record->refresh();
        $this->assertEquals('Updated Tech', $record->technician_name);
        $this->assertEquals('New diagnostic order', $record->description);
        $this->assertEquals(150, $record->expected_duration_minutes);

        // 2. Edit blocked when status changes (e.g. to completed)
        $record->status = 'completed';
        $record->save();

        $response = $this->putJson("/admin/api/maintenance/{$record->id}", [
            'technician_name' => 'Blocked Tech',
            'scheduled_at' => $futureDate,
            'description' => 'Should fail',
        ]);
        $response->assertStatus(422);
    }

    public function test_cancel_schedule_releases_bus_to_standby(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-1007',
            'status' => 'maintenance',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $record = MaintenanceRecord::create([
            'bus_id' => $bus->id,
            'type' => 'Battery Inspection',
            'technician_name' => 'Cancel Tech',
            'scheduled_at' => now()->addDays(2),
            'status' => 'scheduled',
            'expected_duration_minutes' => 120,
        ]);

        // Cancel the maintenance ticket
        $response = $this->postJson("/admin/api/maintenance/{$record->id}/cancel");
        $response->assertStatus(200);

        $record->refresh();
        $this->assertEquals('cancelled', $record->status);

        // Verify bus returns to standby (inactive)
        $bus->refresh();
        $this->assertEquals('inactive', $bus->status);
    }
}
