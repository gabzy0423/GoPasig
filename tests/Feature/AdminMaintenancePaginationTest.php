<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\User;
use App\Models\MaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMaintenancePaginationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    /**
     * Test index API endpoint paginates records to 10 per page.
     */
    public function test_api_paginates_records_to_ten_per_page(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-1234',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Create 15 maintenance records
        for ($i = 1; $i <= 15; $i++) {
            MaintenanceRecord::create([
                'bus_id' => $bus->id,
                'ticket_number' => "MT-TICKET-" . str_pad($i, 3, '0', STR_PAD_LEFT),
                'type' => 'Preventive Maintenance',
                'scheduled_at' => now()->addDays($i),
                'status' => 'scheduled',
                'technician_name' => 'Technician A',
            ]);
        }

        // Fetch page 1
        $response = $this->getJson('/admin/api/maintenance?page=1');
        $response->assertStatus(200);

        // Verify structure and page 1 items count
        $response->assertJsonStructure([
            'current_page',
            'data',
            'first_page_url',
            'from',
            'last_page',
            'last_page_url',
            'next_page_url',
            'path',
            'per_page',
            'prev_page_url',
            'to',
            'total',
        ]);

        $this->assertEquals(1, $response->json('current_page'));
        $this->assertEquals(2, $response->json('last_page'));
        $this->assertEquals(15, $response->json('total'));
        $this->assertCount(10, $response->json('data'));

        // Fetch page 2
        $responsePage2 = $this->getJson('/admin/api/maintenance?page=2');
        $responsePage2->assertStatus(200);
        $this->assertEquals(2, $responsePage2->json('current_page'));
        $this->assertCount(5, $responsePage2->json('data'));
    }

    /**
     * Test index API endpoint supports status filtering.
     */
    public function test_api_filters_records_by_status(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::create([
            'plate_number' => 'PAS-5678',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Create 8 scheduled and 3 in-progress tickets
        for ($i = 1; $i <= 8; $i++) {
            MaintenanceRecord::create([
                'bus_id' => $bus->id,
                'ticket_number' => "MT-SCH-" . $i,
                'type' => 'Preventive Maintenance',
                'scheduled_at' => now()->addDays($i),
                'status' => 'scheduled',
            ]);
        }

        for ($i = 1; $i <= 3; $i++) {
            MaintenanceRecord::create([
                'bus_id' => $bus->id,
                'ticket_number' => "MT-PRG-" . $i,
                'type' => 'Corrective Maintenance',
                'scheduled_at' => now()->addDays($i),
                'status' => 'in_progress',
            ]);
        }

        // Fetch only scheduled
        $response = $this->getJson('/admin/api/maintenance?status=scheduled');
        $response->assertStatus(200);
        $this->assertEquals(8, $response->json('total'));

        // Fetch only in_progress
        $response2 = $this->getJson('/admin/api/maintenance?status=in_progress');
        $response2->assertStatus(200);
        $this->assertEquals(3, $response2->json('total'));
    }

    /**
     * Test index API endpoint supports search querying.
     */
    public function test_api_filters_records_by_search_keyword(): void
    {
        $this->actingAsAdmin();

        $busA = Bus::create([
            'plate_number' => 'ABC-1234',
            'fleet_number' => 'F-001',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        $busB = Bus::create([
            'plate_number' => 'XYZ-5678',
            'fleet_number' => 'F-002',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
        ]);

        // Ticket 1: matches bus A plate
        MaintenanceRecord::create([
            'bus_id' => $busA->id,
            'ticket_number' => 'MT-TKT-A',
            'type' => 'Preventive Maintenance',
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'technician_name' => 'John Doe',
        ]);

        // Ticket 2: matches technician name
        MaintenanceRecord::create([
            'bus_id' => $busB->id,
            'ticket_number' => 'MT-TKT-B',
            'type' => 'Corrective Maintenance',
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'technician_name' => 'Alice Smith',
        ]);

        // Ticket 3: matches ticket number pattern
        MaintenanceRecord::create([
            'bus_id' => $busB->id,
            'ticket_number' => 'SPECIAL-999',
            'type' => 'Aircon Repair',
            'scheduled_at' => now(),
            'status' => 'scheduled',
            'technician_name' => 'John Doe',
        ]);

        // Search: ABC-1234 (plate number)
        $resp = $this->getJson('/admin/api/maintenance?search=ABC-1234');
        $this->assertEquals(1, $resp->json('total'));
        $this->assertEquals('MT-TKT-A', $resp->json('data.0.ticket_number'));

        // Search: Alice (technician)
        $resp2 = $this->getJson('/admin/api/maintenance?search=Alice');
        $this->assertEquals(1, $resp2->json('total'));
        $this->assertEquals('MT-TKT-B', $resp2->json('data.0.ticket_number'));

        // Search: SPECIAL (ticket number)
        $resp3 = $this->getJson('/admin/api/maintenance?search=SPECIAL');
        $this->assertEquals(1, $resp3->json('total'));
        $this->assertEquals('SPECIAL-999', $resp3->json('data.0.ticket_number'));
    }
}
