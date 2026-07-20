<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\User;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Driver;
use App\Models\SystemSetting;
use App\Models\MaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Module6MaintenanceTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    public function test_validation_rule_duration_reads_from_settings(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-6101',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Change settings to min 30, max 120
        SystemSetting::where('key', 'maintenance_duration_min_minutes')->update(['value' => '30']);
        SystemSetting::where('key', 'maintenance_duration_max_minutes')->update(['value' => '120']);

        // Expect failure at 20 minutes
        $response = $this->postJson('/admin/api/maintenance', [
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'scheduled_at' => '2026-06-12T14:00',
            'expected_duration_minutes' => 20,
        ]);
        $response->assertStatus(422);

        // Expect failure at 150 minutes
        $response = $this->postJson('/admin/api/maintenance', [
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'scheduled_at' => '2026-06-12T14:00',
            'expected_duration_minutes' => 150,
        ]);
        $response->assertStatus(422);

        // Expect success at 60 minutes
        $response = $this->postJson('/admin/api/maintenance', [
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'scheduled_at' => '2026-06-12T14:00',
            'expected_duration_minutes' => 60,
        ]);
        $response->assertStatus(201);
    }

    public function test_prior_status_restored_when_maintenance_deleted(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-6102',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Create maintenance record - locks bus to maintenance
        $response = $this->postJson('/admin/api/maintenance', [
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'scheduled_at' => '2026-06-12T14:00',
        ]);
        $response->assertStatus(201);
        $recordId = $response->json('record.id');

        $bus->refresh();
        $this->assertEquals('maintenance', $bus->status);
        $this->assertEquals('inactive', $bus->previous_status);

        // Delete maintenance record - should restore status to inactive
        $deleteResponse = $this->deleteJson("/admin/api/maintenance/{$recordId}");
        $deleteResponse->assertStatus(200);

        $bus->refresh();
        $this->assertEquals('inactive', $bus->status);
        $this->assertNull($bus->previous_status);
    }

    public function test_in_progress_maintenance_can_be_updated(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-6103',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $record = MaintenanceRecord::create([
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'scheduled_at' => now(),
            'status' => 'in_progress',
            'expected_duration_minutes' => 120,
        ]);

        // Try updating it
        $response = $this->putJson("/admin/api/maintenance/{$record->id}", [
            'technician_name' => 'Tech Master',
            'technician_notes' => 'Updated notes for in progress record',
            'cost_php' => 1500,
        ]);

        $response->assertStatus(422);
    }

    public function test_escalation_mechanism_on_repeated_failed_inspections(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-6104',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $record = MaintenanceRecord::create([
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'scheduled_at' => now(),
            'status' => 'in_progress',
            'expected_duration_minutes' => 120,
        ]);

        // Max failures setting is 3
        SystemSetting::where('key', 'maintenance_max_failed_inspections')->update(['value' => '3']);

        // First failed inspection
        $response = $this->postJson("/admin/api/maintenance/{$record->id}/perform-inspection", [
            'inspection_passed' => false,
            'inspected_by' => 'Inspector A',
            'inspection_notes' => 'Tires look worn out',
        ]);
        $response->assertStatus(200);
        $record->refresh();
        $this->assertEquals(1, $record->failed_inspections_count);
        $this->assertNotEquals('escalated', $record->workflow_status);

        // Second failed inspection
        $response = $this->postJson("/admin/api/maintenance/{$record->id}/perform-inspection", [
            'inspection_passed' => false,
            'inspected_by' => 'Inspector A',
            'inspection_notes' => 'Brakes still failing',
        ]);
        $response->assertStatus(200);
        $record->refresh();
        $this->assertEquals(2, $record->failed_inspections_count);
        $this->assertNotEquals('escalated', $record->workflow_status);

        // Third failed inspection (should escalate)
        $response = $this->postJson("/admin/api/maintenance/{$record->id}/perform-inspection", [
            'inspection_passed' => false,
            'inspected_by' => 'Inspector B',
            'inspection_notes' => 'Suspension broken',
        ]);
        $response->assertStatus(200);
        $record->refresh();
        $this->assertEquals(3, $record->failed_inspections_count);
        $this->assertEquals('escalated', $record->workflow_status);
    }

    public function test_maintenance_creation_blocked_if_bus_has_ongoing_trip(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-6105',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $route = Route::factory()->create([
            'status' => 'active',
        ]);

        $driver = Driver::factory()->create([
            'status' => 'active',
        ]);

        // Create an ongoing trip for this bus
        Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'started_at' => now(),
        ]);

        // Attempting to schedule maintenance should fail
        $response = $this->postJson('/admin/api/maintenance', [
            'bus_id' => $bus->id,
            'type' => 'Preventive Maintenance',
            'scheduled_at' => '2026-06-12T14:00',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment([
            'success' => false,
            'message' => 'Cannot schedule maintenance: The bus currently has an ongoing trip.'
        ]);
    }
}
