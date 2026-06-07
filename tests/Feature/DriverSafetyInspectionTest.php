<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\SafetyInspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DriverSafetyInspectionTest extends TestCase
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

    public function test_driver_can_access_vehicle_page_with_inspections(): void
    {
        $response = $this->actingAs($this->driverUser)->get('/driver/vehicle');
        $response->assertStatus(200);
        $response->assertSee('Safety Inspection Checklist');
        $response->assertSee('Recent Safety Inspections');
    }

    public function test_driver_can_submit_passing_safety_inspection(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->post('/driver/vehicle/inspection', [
                'oil_ok' => '1',
                'brakes_ok' => '1',
                'ac_ok' => '1',
                'lights_ok' => '1',
                'tires_ok' => '1',
                'notes' => 'All systems functional.',
            ]);

        $response->assertRedirect('/driver/vehicle');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('safety_inspections', [
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driverModel->id,
            'oil_ok' => 1,
            'brakes_ok' => 1,
            'ac_ok' => 1,
            'lights_ok' => 1,
            'tires_ok' => 1,
            'status' => 'passed',
            'notes' => 'All systems functional.',
        ]);
    }

    public function test_driver_can_submit_failing_safety_inspection(): void
    {
        $response = $this->actingAs($this->driverUser)
            ->post('/driver/vehicle/inspection', [
                'oil_ok' => '1',
                // brakes_ok is unchecked
                'ac_ok' => '1',
                'lights_ok' => '1',
                'tires_ok' => '1',
                'notes' => 'Brake feels soft.',
            ]);

        $response->assertRedirect('/driver/vehicle');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('safety_inspections', [
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driverModel->id,
            'oil_ok' => 1,
            'brakes_ok' => 0,
            'ac_ok' => 1,
            'lights_ok' => 1,
            'tires_ok' => 1,
            'status' => 'failed',
            'notes' => 'Brake feels soft.',
        ]);
    }

    public function test_driver_can_edit_inspection_submitted_today(): void
    {
        $inspectionId = DB::table('safety_inspections')->insertGetId([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driverModel->id,
            'oil_ok' => 1,
            'brakes_ok' => 0,
            'ac_ok' => 1,
            'lights_ok' => 1,
            'tires_ok' => 1,
            'status' => 'failed',
            'notes' => 'Brakes soft.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/inspection/{$inspectionId}/update", [
                'oil_ok' => '1',
                'brakes_ok' => '1', // fixed
                'ac_ok' => '1',
                'lights_ok' => '1',
                'tires_ok' => '1',
                'notes' => 'Brakes checked, now ok.',
            ]);

        $response->assertRedirect('/driver/vehicle');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('safety_inspections', [
            'id' => $inspectionId,
            'brakes_ok' => 1,
            'status' => 'passed',
            'notes' => 'Brakes checked, now ok.',
        ]);
    }

    public function test_driver_cannot_edit_inspection_from_previous_day(): void
    {
        $inspectionId = DB::table('safety_inspections')->insertGetId([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driverModel->id,
            'oil_ok' => 1,
            'brakes_ok' => 1,
            'ac_ok' => 1,
            'lights_ok' => 1,
            'tires_ok' => 1,
            'status' => 'passed',
            'notes' => 'Perfect.',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/inspection/{$inspectionId}/update", [
                'oil_ok' => '1',
                'brakes_ok' => '0',
                'ac_ok' => '1',
                'lights_ok' => '1',
                'tires_ok' => '1',
                'notes' => 'Hacked past logs.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('safety_inspections', [
            'id' => $inspectionId,
            'brakes_ok' => 1,
            'notes' => 'Perfect.',
        ]);
    }

    public function test_driver_can_delete_inspection_submitted_today(): void
    {
        $inspectionId = DB::table('safety_inspections')->insertGetId([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driverModel->id,
            'oil_ok' => 1,
            'brakes_ok' => 1,
            'ac_ok' => 1,
            'lights_ok' => 1,
            'tires_ok' => 1,
            'status' => 'passed',
            'notes' => 'Mistake.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/inspection/{$inspectionId}/delete");

        $response->assertRedirect('/driver/vehicle');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('safety_inspections', [
            'id' => $inspectionId,
        ]);
    }

    public function test_driver_cannot_delete_inspection_from_previous_day(): void
    {
        $inspectionId = DB::table('safety_inspections')->insertGetId([
            'bus_id' => $this->bus->id,
            'driver_id' => $this->driverModel->id,
            'oil_ok' => 1,
            'brakes_ok' => 1,
            'ac_ok' => 1,
            'lights_ok' => 1,
            'tires_ok' => 1,
            'status' => 'passed',
            'notes' => 'Historic.',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/inspection/{$inspectionId}/delete");

        $response->assertRedirect();
        $response->assertSessionHas('error');

        $this->assertDatabaseHas('safety_inspections', [
            'id' => $inspectionId,
        ]);
    }

    public function test_driver_cannot_modify_unowned_safety_inspection(): void
    {
        // 1. Create another driver & bus
        $otherDriverUser = User::factory()->create(['role' => 'driver']);
        $otherBus = Bus::create([
            'plate_number' => 'PAS-999',
            'status' => 'active',
            'capacity' => 40,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);
        $otherDriverModel = Driver::create([
            'user_id' => $otherDriverUser->id,
            'emp_id' => 'EMP-2222',
            'first_name' => 'Narda',
            'last_name' => 'Custodio',
            'license_number' => 'B12-34-567890',
            'license_expiry' => '2028-12-12',
            'status' => 'active',
            'assigned_bus' => 'PAS-999',
        ]);

        $inspectionId = DB::table('safety_inspections')->insertGetId([
            'bus_id' => $otherBus->id,
            'driver_id' => $otherDriverModel->id,
            'oil_ok' => 1,
            'brakes_ok' => 1,
            'ac_ok' => 1,
            'lights_ok' => 1,
            'tires_ok' => 1,
            'status' => 'passed',
            'notes' => 'Other driver report.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Attempt update by driverUser
        $response1 = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/inspection/{$inspectionId}/update", [
                'oil_ok' => '0',
                'notes' => 'Attempted hijack.',
            ]);

        $response1->assertRedirect();
        $response1->assertSessionHas('error');

        $this->assertDatabaseHas('safety_inspections', [
            'id' => $inspectionId,
            'oil_ok' => 1,
            'notes' => 'Other driver report.',
        ]);

        // Attempt delete by driverUser
        $response2 = $this->actingAs($this->driverUser)
            ->post("/driver/vehicle/inspection/{$inspectionId}/delete");

        $response2->assertRedirect();
        $response2->assertSessionHas('error');

        $this->assertDatabaseHas('safety_inspections', [
            'id' => $inspectionId,
        ]);
    }
}
