<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Bus;
use App\Models\Driver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverVehicleReportTest extends TestCase
{
    use RefreshDatabase;

    private $driverUser;
    private $driverModel;
    private $bus;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Create a user with role 'driver'
        $this->driverUser = User::factory()->create(['role' => 'driver']);

        // 2. Create a Bus
        $this->bus = Bus::create([
            'plate_number' => 'PAS-888',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // 3. Create a Driver model associated with the user and assigned to the bus
        $this->driverModel = Driver::create([
            'user_id' => $this->driverUser->id,
            'emp_id' => 'EMP-1111',
            'first_name' => 'Cardo',
            'last_name' => 'Dalisay',
            'license_number' => 'A12-34-567890',
            'license_expiry' => '2028-12-12',
            'status' => 'active',
            'assigned_bus' => 'PAS-888',
        ]);
    }

    public function test_driver_can_access_vehicle_page(): void
    {
        $response = $this->actingAs($this->driverUser)->get('/driver/vehicle');
        $response->assertStatus(200);
        $response->assertSee('Vehicle Management');
        $response->assertSee('Inspection Control');
        $response->assertSee('PAS-888');
    }

    public function test_driver_can_submit_defect_report(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->post('/driver/vehicle/report', [
                'type' => 'Aircon malfunctioning',
                'description' => 'Shuttle AC is blowing warm air.',
            ]);

        $response->assertRedirect('/driver/vehicle');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('maintenance_records', [
            'bus_id' => $this->bus->id,
            'type' => 'Aircon malfunctioning',
            'description' => 'Shuttle AC is blowing warm air.',
            'status' => 'scheduled',
        ]);
    }

    public function test_driver_can_edit_scheduled_defect_report(): void
    {
        $recordId = DB::table('maintenance_records')->insertGetId([
            'bus_id' => $this->bus->id,
            'type' => 'Engine Issues',
            'description' => 'Engine makes high pitching noise.',
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/report/{$recordId}/update", [
                'type' => 'Engine Issues',
                'description' => 'Engine makes high pitching noise from the radiator.',
            ]);

        $response->assertRedirect('/driver/vehicle');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('maintenance_records', [
            'id' => $recordId,
            'type' => 'Engine Issues',
            'description' => 'Engine makes high pitching noise from the radiator.',
        ]);
    }

    public function test_driver_cannot_edit_in_progress_or_completed_defect_report(): void
    {
        // 1. In Progress
        $inProgressId = DB::table('maintenance_records')->insertGetId([
            'bus_id' => $this->bus->id,
            'type' => 'Engine Issues',
            'description' => 'Engine makes noise.',
            'scheduled_at' => now(),
            'status' => 'in_progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response1 = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/report/{$inProgressId}/update", [
                'type' => 'Engine Issues',
                'description' => 'New Description.',
            ]);

        $response1->assertRedirect();
        $response1->assertSessionHas('error');

        $this->assertDatabaseHas('maintenance_records', [
            'id' => $inProgressId,
            'description' => 'Engine makes noise.',
        ]);

        // 2. Completed
        $completedId = DB::table('maintenance_records')->insertGetId([
            'bus_id' => $this->bus->id,
            'type' => 'Engine Issues',
            'description' => 'Engine makes noise.',
            'scheduled_at' => now(),
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response2 = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/report/{$completedId}/update", [
                'type' => 'Engine Issues',
                'description' => 'New Description.',
            ]);

        $response2->assertRedirect();
        $response2->assertSessionHas('error');

        $this->assertDatabaseHas('maintenance_records', [
            'id' => $completedId,
            'description' => 'Engine makes noise.',
        ]);
    }

    public function test_driver_can_delete_scheduled_defect_report(): void
    {
        $recordId = DB::table('maintenance_records')->insertGetId([
            'bus_id' => $this->bus->id,
            'type' => 'Engine Issues',
            'description' => 'Engine makes noise.',
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/report/{$recordId}/delete");

        $response->assertRedirect('/driver/vehicle');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('maintenance_records', [
            'id' => $recordId,
        ]);
    }

    public function test_driver_cannot_delete_in_progress_or_completed_defect_report(): void
    {
        $inProgressId = DB::table('maintenance_records')->insertGetId([
            'bus_id' => $this->bus->id,
            'type' => 'Engine Issues',
            'description' => 'Engine makes noise.',
            'scheduled_at' => now(),
            'status' => 'in_progress',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/report/{$inProgressId}/delete");

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('maintenance_records', [
            'id' => $inProgressId,
        ]);
    }

    public function test_driver_cannot_modify_unowned_defect_report(): void
    {
        // Another bus and report
        $otherBus = Bus::create([
            'plate_number' => 'PAS-999',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $recordId = DB::table('maintenance_records')->insertGetId([
            'bus_id' => $otherBus->id,
            'type' => 'Engine Issues',
            'description' => 'Other bus engine noise.',
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try to update
        $response1 = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/report/{$recordId}/update", [
                'type' => 'Engine Issues',
                'description' => 'Attempted hijack.',
            ]);

        $response1->assertRedirect();
        $response1->assertSessionHas('error');

        $this->assertDatabaseHas('maintenance_records', [
            'id' => $recordId,
            'description' => 'Other bus engine noise.',
        ]);

        // Try to delete
        $response2 = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/report/{$recordId}/delete");

        $response2->assertRedirect();
        $response2->assertSessionHas('error');

        $this->assertDatabaseHas('maintenance_records', [
            'id' => $recordId,
        ]);
    }
}
