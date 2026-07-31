<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Bus;
use App\Models\Route;
use App\Models\MaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceCompletionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $dispatcher;
    private User $admin;
    private Bus $bus;
    private Route $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcher = User::factory()->create(['role' => 'fleet_manager']);
        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->route = Route::create([
            'id' => 1,
            'name' => 'Route A',
            'description' => 'SPED to Pasig City Hall',
            'polyline_coordinates' => [[14.5593, 121.0805]],
            'status' => 'Active',
        ]);

        $this->bus = Bus::create([
            'plate_number' => 'PAS-123',
            'status' => 'maintenance',
            'capacity' => 40,
            'lat' => 14.5,
            'lng' => 121.0,
            'speed' => 0,
            'passengers' => 0,
            'route_id' => $this->route->id,
        ]);
    }

    public function test_complete_maintenance_requires_checklist_and_validation(): void
    {
        $record = MaintenanceRecord::create([
            'bus_id' => $this->bus->id,
            'type' => 'Preventive',
            'description' => 'Test maintenance',
            'scheduled_at' => now()->subHours(2),
            'status' => 'in_progress',
        ]);

        // Submit without required checklist items
        $response = $this->actingAs($this->dispatcher)->postJson("/fleet/api/maintenance-update-status/{$record->id}", [
            'status' => 'completed',
            'inspector_name' => 'Inspector Jack',
            'bus_condition' => 'Excellent',
            'roadworthy' => 1,
            'maintenance_result' => 'Passed Inspection',
            'inspection_checklist' => [
                'brakes' => true,
                'battery' => false, // missing items
            ],
            'technician_notes' => 'Checked everything',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Please complete all safety checklist items.']);
    }

    public function test_complete_maintenance_with_passed_result_transitions_to_standby_and_resets_has_observation(): void
    {
        // Setup bus with has_observation = true
        $this->bus->update(['has_observation' => true]);

        $record = MaintenanceRecord::create([
            'bus_id' => $this->bus->id,
            'type' => 'Preventive',
            'description' => 'Test maintenance',
            'scheduled_at' => now()->subHours(2),
            'status' => 'in_progress',
        ]);

        $response = $this->actingAs($this->dispatcher)->postJson("/fleet/api/maintenance-update-status/{$record->id}", [
            'status' => 'completed',
            'inspector_name' => 'Inspector Jack',
            'bus_condition' => 'Excellent',
            'roadworthy' => 1,
            'maintenance_result' => 'Passed Inspection',
            'inspection_checklist' => [
                'brakes' => true,
                'battery' => true,
                'tires' => true,
                'lights' => true,
                'test_drive' => true,
            ],
            'labor_cost' => 1000,
            'parts_cost' => 1500,
            'other_cost' => 100,
            'technician_notes' => 'Fixed alignment',
        ]);

        $response->assertStatus(200);
        
        $record->refresh();
        $this->bus->refresh();

        $this->assertEquals('completed', $record->status);
        $this->assertEquals(2600.0, $record->cost_php); // 1000 + 1500 + 100
        $this->assertEquals('inactive', $this->bus->status); // Inactive is Standby
        $this->assertFalse($this->bus->has_observation); // Cleared observation flag
    }

    public function test_complete_maintenance_with_observation_result_requires_recommendation_and_sets_has_observation(): void
    {
        $record = MaintenanceRecord::create([
            'bus_id' => $this->bus->id,
            'type' => 'Preventive',
            'description' => 'Test maintenance',
            'scheduled_at' => now()->subHours(2),
            'status' => 'in_progress',
        ]);

        // Submit without recommendation
        $response = $this->actingAs($this->dispatcher)->postJson("/fleet/api/maintenance-update-status/{$record->id}", [
            'status' => 'completed',
            'inspector_name' => 'Inspector Jack',
            'bus_condition' => 'Good',
            'roadworthy' => 1,
            'maintenance_result' => 'Passed with Observation',
            'inspection_checklist' => [
                'brakes' => true,
                'battery' => true,
                'tires' => true,
                'lights' => true,
                'test_drive' => true,
            ],
            'labor_cost' => 500,
            'parts_cost' => 500,
            'other_cost' => 0,
            'technician_notes' => 'Small observation',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Recommendation is required for buses with observations.']);

        // Submit with recommendation
        $response2 = $this->actingAs($this->dispatcher)->postJson("/fleet/api/maintenance-update-status/{$record->id}", [
            'status' => 'completed',
            'inspector_name' => 'Inspector Jack',
            'bus_condition' => 'Good',
            'roadworthy' => 1,
            'maintenance_result' => 'Passed with Observation',
            'inspection_checklist' => [
                'brakes' => true,
                'battery' => true,
                'tires' => true,
                'lights' => true,
                'test_drive' => true,
            ],
            'labor_cost' => 500,
            'parts_cost' => 500,
            'other_cost' => 0,
            'technician_notes' => 'Small observation',
            'recommendation' => 'Monitor tire tread depth',
        ]);

        $response2->assertStatus(200);

        $this->bus->refresh();
        $this->assertEquals('inactive', $this->bus->status);
        $this->assertTrue($this->bus->has_observation); // Set observation flag
    }

    public function test_complete_maintenance_with_failed_result_requires_recommendation_and_keeps_bus_locked(): void
    {
        $record = MaintenanceRecord::create([
            'bus_id' => $this->bus->id,
            'type' => 'Preventive',
            'description' => 'Test maintenance',
            'scheduled_at' => now()->subHours(2),
            'status' => 'in_progress',
        ]);

        // Submit without recommendation
        $response = $this->actingAs($this->dispatcher)->postJson("/fleet/api/maintenance-update-status/{$record->id}", [
            'status' => 'completed',
            'inspector_name' => 'Inspector Jack',
            'bus_condition' => 'Needs Follow-up',
            'roadworthy' => 0,
            'maintenance_result' => 'Failed Inspection',
            'inspection_checklist' => [
                'brakes' => true,
                'battery' => true,
                'tires' => true,
                'lights' => true,
                'test_drive' => true,
            ],
            'labor_cost' => 0,
            'parts_cost' => 0,
            'other_cost' => 0,
            'technician_notes' => 'Complete failure',
        ]);

        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Recommendation is required before closing the maintenance record.']);

        // Submit with recommendation
        $response2 = $this->actingAs($this->dispatcher)->postJson("/fleet/api/maintenance-update-status/{$record->id}", [
            'status' => 'completed',
            'inspector_name' => 'Inspector Jack',
            'bus_condition' => 'Needs Follow-up',
            'roadworthy' => 0,
            'maintenance_result' => 'Failed Inspection',
            'inspection_checklist' => [
                'brakes' => true,
                'battery' => true,
                'tires' => true,
                'lights' => true,
                'test_drive' => true,
            ],
            'labor_cost' => 0,
            'parts_cost' => 0,
            'other_cost' => 0,
            'technician_notes' => 'Complete failure',
            'recommendation' => 'Replace battery module 4',
        ]);

        $response2->assertStatus(200);

        $this->bus->refresh();
        $this->assertEquals('maintenance', $this->bus->status); // Locked in maintenance!
        $this->assertFalse($this->bus->has_observation);
    }

    public function test_completed_maintenance_is_immutable_and_cannot_be_deleted(): void
    {
        $record = MaintenanceRecord::create([
            'bus_id' => $this->bus->id,
            'type' => 'Preventive',
            'description' => 'Test maintenance',
            'scheduled_at' => now()->subHours(2),
            'status' => 'completed',
            'maintenance_result' => 'Passed Inspection',
        ]);

        // Attempt delete via Fleet Operator endpoint
        $response = $this->actingAs($this->dispatcher)->deleteJson("/fleet/api/maintenance-delete/{$record->id}");
        $response->assertStatus(422);
        $response->assertJsonFragment(['message' => 'Completed maintenance records are immutable and cannot be deleted to preserve the audit trail.']);

        // Attempt delete via Admin endpoint
        $response2 = $this->actingAs($this->admin)->deleteJson("/admin/api/maintenance/{$record->id}");
        $response2->assertStatus(422);
        $response2->assertJsonFragment(['message' => 'Completed maintenance records are immutable and cannot be deleted to preserve the audit trail.']);
    }

    public function test_complete_maintenance_with_invalid_record_id_returns_400_or_404(): void
    {
        // Null/Invalid ID check
        $response = $this->actingAs($this->dispatcher)->postJson("/fleet/api/maintenance-update-status/null", [
            'status' => 'completed',
        ]);
        $response->assertStatus(400);
        $response->assertJsonFragment(['message' => 'Invalid Maintenance Record ID provided.']);

        // Non-existent ID check
        $response2 = $this->actingAs($this->dispatcher)->postJson("/fleet/api/maintenance-update-status/99999", [
            'status' => 'completed',
        ]);
        $response2->assertStatus(404);
        $response2->assertJsonFragment(['message' => 'Maintenance record not found.']);
    }

    public function test_dispatcher_can_access_maintenance_pages(): void
    {
        $record = MaintenanceRecord::create([
            'bus_id' => $this->bus->id,
            'type' => 'Preventive',
            'description' => 'Test maintenance',
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        // Index page
        $response = $this->actingAs($this->dispatcher)->get('/fleet/maintenance');
        $response->assertStatus(200);
        $response->assertViewIs('fleet.maintenance.index');

        // Show page
        $response2 = $this->actingAs($this->dispatcher)->get("/fleet/maintenance/{$record->id}");
        $response2->assertStatus(200);
        $response2->assertViewIs('fleet.maintenance.view');

        // Start page
        $response3 = $this->actingAs($this->dispatcher)->get("/fleet/maintenance/{$record->id}/start");
        $response3->assertStatus(200);
        $response3->assertViewIs('fleet.maintenance.start');

        // Transition to in_progress to access complete page
        $record->update(['status' => 'in_progress']);

        // Complete page
        $response4 = $this->actingAs($this->dispatcher)->get("/fleet/maintenance/{$record->id}/complete");
        $response4->assertStatus(200);
        $response4->assertViewIs('fleet.maintenance.complete');
    }

    public function test_dispatcher_can_execute_web_actions(): void
    {
        $record = MaintenanceRecord::create([
            'bus_id' => $this->bus->id,
            'type' => 'Preventive',
            'description' => 'Test maintenance',
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);

        // Start service
        $response = $this->actingAs($this->dispatcher)->post("/fleet/maintenance/{$record->id}/start");
        $response->assertRedirect("/fleet/maintenance/{$record->id}");
        $record->refresh();
        $this->assertEquals('in_progress', $record->status);

        // Complete service
        $response2 = $this->actingAs($this->dispatcher)->post("/fleet/maintenance/{$record->id}/complete", [
            'inspector_name' => 'Inspector Jack',
            'bus_condition' => 'Excellent',
            'roadworthy' => 1,
            'maintenance_result' => 'Passed Inspection',
            'inspection_checklist' => [
                'brakes' => true,
                'battery' => true,
                'tires' => true,
                'lights' => true,
                'test_drive' => true,
            ],
            'labor_cost' => 500,
            'parts_cost' => 500,
            'other_cost' => 0,
            'technician_notes' => 'Tuned brakes',
        ]);
        $response2->assertRedirect("/fleet/maintenance/{$record->id}");
        $record->refresh();
        $this->assertEquals('completed', $record->status);

        // Deleting completed fails
        $response3 = $this->actingAs($this->dispatcher)->delete("/fleet/maintenance/{$record->id}");
        $response3->assertRedirect('/fleet/maintenance');
        $this->assertNotNull(MaintenanceRecord::find($record->id));

        // Test cancel on scheduled
        $record2 = MaintenanceRecord::create([
            'bus_id' => $this->bus->id,
            'type' => 'Preventive',
            'description' => 'Test cancel',
            'scheduled_at' => now(),
            'status' => 'scheduled',
        ]);
        $response4 = $this->actingAs($this->dispatcher)->post("/fleet/maintenance/{$record2->id}/cancel");
        $response4->assertRedirect("/fleet/maintenance/{$record2->id}");
        $record2->refresh();
        $this->assertEquals('cancelled', $record2->status);

        // Deleting cancelled works
        $response5 = $this->actingAs($this->dispatcher)->delete("/fleet/maintenance/{$record2->id}");
        $response5->assertRedirect('/fleet/maintenance');
        $this->assertNull(MaintenanceRecord::find($record2->id));
    }
}
