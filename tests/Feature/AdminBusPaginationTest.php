<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBusPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    /**
     * Test GET /admin/api/buses returns paginated buses.
     */
    public function test_api_paginates_buses_to_ten_per_page(): void
    {
        $this->actingAsAdmin();

        // Create 12 buses
        for ($i = 1; $i <= 12; $i++) {
            Bus::create([
                'plate_number' => 'PAS-PA' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'fleet_number' => 'BUS-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'vin' => '17CHARVINNUMB' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'manufacturer' => 'BYD',
                'model' => 'K9',
                'year_model' => 2023,
                'capacity' => 45,
                'status' => 'inactive', // standby
            ]);
        }

        // Fetch page 1
        $response = $this->getJson('/admin/api/buses?page=1');
        $response->assertStatus(200);

        // Verify pagination structure and counts
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
        $this->assertEquals(12, $response->json('total'));
        $this->assertCount(10, $response->json('data'));

        // Fetch page 2
        $response2 = $this->getJson('/admin/api/buses?page=2');
        $response2->assertStatus(200);
        $this->assertEquals(2, $response2->json('current_page'));
        $this->assertCount(2, $response2->json('data'));
    }

    /**
     * Test GET /admin/api/buses supports status filtering (with Standby mapping).
     */
    public function test_api_filters_buses_by_status(): void
    {
        $this->actingAsAdmin();

        // Create active and standby buses
        Bus::create([
            'plate_number' => 'PAS-ACT01',
            'fleet_number' => 'BUS-ACT01',
            'vin' => '17CHARVINNUMBACT01',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2023,
            'capacity' => 45,
            'status' => 'active',
        ]);

        Bus::create([
            'plate_number' => 'PAS-STB01',
            'fleet_number' => 'BUS-STB01',
            'vin' => '17CHARVINNUMBSTB01',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2023,
            'capacity' => 45,
            'status' => 'inactive', // standby
        ]);

        // Filter active
        $respActive = $this->getJson('/admin/api/buses?status=active');
        $respActive->assertStatus(200);
        $this->assertEquals(1, $respActive->json('total'));
        $this->assertEquals('PAS-ACT01', $respActive->json('data.0.plate_number'));

        // Filter standby (maps to inactive)
        $respStandby = $this->getJson('/admin/api/buses?status=standby');
        $respStandby->assertStatus(200);
        $this->assertEquals(1, $respStandby->json('total'));
        $this->assertEquals('PAS-STB01', $respStandby->json('data.0.plate_number'));
    }

    /**
     * Test GET /admin/api/buses supports search.
     */
    public function test_api_filters_buses_by_search_keyword(): void
    {
        $this->actingAsAdmin();

        Bus::create([
            'plate_number' => 'PAS-MATCH1',
            'fleet_number' => 'BUS-001',
            'vin' => '17CHARVINNUMB00001',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2023,
            'capacity' => 45,
            'status' => 'active',
            'driver_name' => 'Michael Jordan',
        ]);

        Bus::create([
            'plate_number' => 'PAS-MISMATCH',
            'fleet_number' => 'BUS-002',
            'vin' => '17CHARVINNUMB00002',
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2023,
            'capacity' => 45,
            'status' => 'active',
            'driver_name' => 'LeBron James',
        ]);

        // Search by plate number
        $respPlate = $this->getJson('/admin/api/buses?search=MATCH1');
        $respPlate->assertStatus(200);
        $this->assertEquals(1, $respPlate->json('total'));
        $this->assertEquals('PAS-MATCH1', $respPlate->json('data.0.plate_number'));

        // Search by driver name
        $respDriver = $this->getJson('/admin/api/buses?search=James');
        $respDriver->assertStatus(200);
        $this->assertEquals(1, $respDriver->json('total'));
        $this->assertEquals('PAS-MISMATCH', $respDriver->json('data.0.plate_number'));
    }
}
