<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\User;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBusRegistrationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    public function test_new_bus_is_always_registered_as_inactive(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-8888',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1', // 17 chars, no I, O, Q
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-8888',
            'status'       => 'inactive',
            'fleet_number' => 'BUS-001',
            'vin'          => '1234567890ABCDEF1',
        ]);
    }

    public function test_registration_ignores_forged_status_parameter(): void
    {
        $this->actingAsAdmin();

        $testStatuses = ['active', 'maintenance', 'breakdown', 'arbitrary_status'];

        foreach ($testStatuses as $index => $status) {
            $response = $this->postJson('/admin/api/buses', [
                'plate_number'          => "PAS-FORGE-{$index}",
                'fleet_number'          => "BUS-10{$index}",
                'vin'                   => str_pad("VNFRGE{$index}", 17, 'X'),
                'manufacturer'          => 'Yutong',
                'model'                 => 'E12',
                'year_model'            => 2023,
                'battery_capacity_kwh'  => 280,
                'charging_port_type'    => 'GB/T',
                'max_charging_power_kw' => 120,
                'capacity'              => 50,
                'status'                => $status,
            ]);

            $response->assertStatus(201);
            $this->assertDatabaseHas('buses', [
                'plate_number' => "PAS-FORGE-{$index}",
                'status'       => 'inactive', // Forged status was successfully ignored
            ]);
        }
    }

    public function test_vin_and_fleet_number_must_be_unique(): void
    {
        $this->actingAsAdmin();

        // Create first bus
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(201);

        // Attempt duplicate fleet_number
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-2222',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF2',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['fleet_number']);

        // Attempt duplicate vin
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-2222',
            'fleet_number'          => 'BUS-002',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['vin']);
    }

    public function test_invalid_vin_characters_and_size_are_rejected(): void
    {
        $this->actingAsAdmin();

        // Size !== 17
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF', // 16 chars
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['vin']);

        // Invalid characters (I, O, Q)
        foreach (['I', 'O', 'Q'] as $invalidChar) {
            $this->postJson('/admin/api/buses', [
                'plate_number'          => 'PAS-1111',
                'fleet_number'          => 'BUS-001',
                'vin'                   => '1234567890ABCDE' . $invalidChar . '1', // contains I, O, Q
                'manufacturer'          => 'BYD',
                'model'                 => 'K9',
                'year_model'            => 2024,
                'battery_capacity_kwh'  => 350.5,
                'charging_port_type'    => 'CCS2',
                'max_charging_power_kw' => 150,
                'capacity'              => 50,
            ])->assertStatus(422)->assertJsonValidationErrors(['vin']);
        }
    }

    public function test_fleet_number_format_must_be_bus_digits(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-ABC', // invalid
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['fleet_number']);

        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'bus-123', // case sensitive (needs uppercase BUS-)
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['fleet_number']);
    }

    public function test_battery_and_power_ranges_are_validated(): void
    {
        $this->actingAsAdmin();

        // battery capacity too low
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 9.9, // < 10
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['battery_capacity_kwh']);

        // battery capacity too high
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 1001, // > 1000
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['battery_capacity_kwh']);

        // max charging power too low
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 9.9, // < 10
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['max_charging_power_kw']);

        // max charging power too high
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 501, // > 500
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['max_charging_power_kw']);
    }

    public function test_custom_manufacturer_must_be_provided_if_others_selected(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'Others',
            'manufacturer_custom'   => null,
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['manufacturer_custom']);

        $response = $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'Others',
            'manufacturer_custom'   => 'Volvo',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ]);
        $response->assertStatus(201);
        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-1111',
            'manufacturer' => 'Volvo',
        ]);
    }

    public function test_duplicate_plate_number_is_rejected(): void
    {
        $this->actingAsAdmin();

        // Create first bus
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(201);

        // Attempt duplicate plate_number
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-002',
            'vin'                   => '1234567890ABCDEF2',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['plate_number']);
    }

    public function test_invalid_charging_port_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'NACS', // Invalid port type
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['charging_port_type']);
    }

    public function test_invalid_year_models_are_rejected(): void
    {
        $this->actingAsAdmin();

        // Too old (below 1980)
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 1979,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['year_model']);

        // Too new (above current + 2)
        $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-1111',
            'fleet_number'          => 'BUS-001',
            'vin'                   => '1234567890ABCDEF1',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => date('Y') + 3,
            'battery_capacity_kwh'  => 350.5,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150,
            'capacity'              => 50,
        ])->assertStatus(422)->assertJsonValidationErrors(['year_model']);
    }

    public function test_registration_ignores_forged_operational_fields(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/admin/api/buses', [
            'plate_number'          => 'PAS-9999',
            'fleet_number'          => 'BUS-999',
            'vin'                   => '1234567890ABCDEF9',
            'manufacturer'          => 'BYD',
            'model'                 => 'K9',
            'year_model'            => 2024,
            'battery_capacity_kwh'  => 350.00,
            'charging_port_type'    => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'capacity'              => 50,
            // Forged operational fields
            'status'                => 'active',
            'route_id'              => 99,
            'driver_name'           => 'Forged Driver',
            'speed'                 => 100,
            'passengers'            => 50,
            'next_stop'             => 'Forged Stop',
            'eta'                   => 999,
            'lat'                   => 90.0,
            'lng'                   => 180.0,
            'is_simulated'          => true,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('buses', [
            'plate_number' => 'PAS-9999',
            'status'       => 'inactive',
            'route_id'     => null,
            'driver_name'  => Bus::getDefaultDriverName(),
            'speed'        => Bus::getInitialSpeed(),
            'passengers'   => Bus::getInitialPassengers(),
            'next_stop'    => Bus::getDefaultNextStop(),
            'eta'          => Bus::getInitialEta(),
            'is_simulated' => false,
        ]);
        
        $bus = Bus::where('plate_number', 'PAS-9999')->first();
        $this->assertEquals((float) SystemSetting::get('map_default_latitude', 14.5593), $bus->lat);
        $this->assertEquals((float) SystemSetting::get('map_default_longitude', 121.0805), $bus->lng);
    }

    public function test_vin_and_plate_number_cannot_be_updated(): void
    {
        $this->actingAsAdmin();

        $bus = Bus::factory()->create([
            'plate_number' => 'PAS-1111',
            'vin'          => '1234567890ABCDEF1',
        ]);

        $response = $this->putJson("/admin/api/buses/{$bus->id}", [
            'fleet_number'          => $bus->fleet_number,
            'manufacturer'          => $bus->manufacturer,
            'model'                 => $bus->model,
            'year_model'            => $bus->year_model,
            'battery_capacity_kwh'  => $bus->battery_capacity_kwh,
            'charging_port_type'    => $bus->charging_port_type,
            'max_charging_power_kw' => $bus->max_charging_power_kw,
            'capacity'              => $bus->capacity,
            'status'                => $bus->status,
            // Forged/Attempted updates to immutable properties
            'plate_number'          => 'PAS-2222',
            'vin'                   => '1234567890ABCDEF2',
        ]);

        $response->assertStatus(200);
        $bus->refresh();
        $this->assertEquals('PAS-1111', $bus->plate_number);
        $this->assertEquals('1234567890ABCDEF1', $bus->vin);
    }
}
