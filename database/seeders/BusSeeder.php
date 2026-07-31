<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Bus;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\DB;

class BusSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaultLat = (float) SystemSetting::get('map_default_latitude', 14.5593);
        $defaultLng = (float) SystemSetting::get('map_default_longitude', 121.0805);

        $busesData = [
            [
                'plate_number'          => 'PAS-001',
                'fleet_number'          => 'BUS-001',
                'vin'                   => '1234567890ABCDEF1',
                'manufacturer'          => 'BYD',
                'model'                 => 'K9',
                'year_model'            => 2024,
                'battery_capacity_kwh'  => 324.00,
                'max_charging_power_kw' => 120.00,
                'charging_port_type'    => 'CCS2',
                'purchase_date'         => '2024-01-15',
                'supplier'              => 'BYD Philippines',
                'warranty_expiry'       => '2029-01-15',
                'serial_number'         => 'BYD-K9-2024-001',
                'acquisition_cost'      => 18000000.00,
                'lat'                   => $defaultLat,
                'lng'                   => $defaultLng,
                'driver_name'           => Bus::getDefaultDriverName(),
                'next_stop'             => Bus::getDefaultNextStop(),
                'capacity'              => 26,
                'status'                => 'inactive',
                'route_id'              => null,
                'speed'                 => Bus::getInitialSpeed(),
                'passengers'            => Bus::getInitialPassengers(),
                'eta'                   => Bus::getInitialEta(),
                'is_simulated'          => false,
            ],
            [
                'plate_number'          => 'PAS-002',
                'fleet_number'          => 'BUS-002',
                'vin'                   => '1234567890ABCDEF2',
                'manufacturer'          => 'BYD',
                'model'                 => 'K9UD',
                'year_model'            => 2025,
                'battery_capacity_kwh'  => 350.00,
                'max_charging_power_kw' => 150.00,
                'charging_port_type'    => 'CCS2',
                'purchase_date'         => '2024-02-10',
                'supplier'              => 'BYD Philippines',
                'warranty_expiry'       => '2029-02-10',
                'serial_number'         => 'BYD-K9UD-2024-002',
                'acquisition_cost'      => 18500000.00,
                'lat'                   => $defaultLat,
                'lng'                   => $defaultLng,
                'driver_name'           => Bus::getDefaultDriverName(),
                'next_stop'             => Bus::getDefaultNextStop(),
                'capacity'              => 26,
                'status'                => 'inactive',
                'route_id'              => null,
                'speed'                 => Bus::getInitialSpeed(),
                'passengers'            => Bus::getInitialPassengers(),
                'eta'                   => Bus::getInitialEta(),
                'is_simulated'          => false,
            ],
            [
                'plate_number'          => 'PAS-003',
                'fleet_number'          => 'BUS-003',
                'vin'                   => '1234567890ABCDEF3',
                'manufacturer'          => 'Yutong',
                'model'                 => 'E12',
                'year_model'            => 2024,
                'battery_capacity_kwh'  => 385.00,
                'max_charging_power_kw' => 180.00,
                'charging_port_type'    => 'GB/T',
                'purchase_date'         => '2024-03-05',
                'supplier'              => 'Yutong Philippines',
                'warranty_expiry'       => '2029-03-05',
                'serial_number'         => 'YT-E12-2024-003',
                'acquisition_cost'      => 19200000.00,
                'lat'                   => $defaultLat,
                'lng'                   => $defaultLng,
                'driver_name'           => Bus::getDefaultDriverName(),
                'next_stop'             => Bus::getDefaultNextStop(),
                'capacity'              => 26,
                'status'                => 'inactive',
                'route_id'              => null,
                'speed'                 => Bus::getInitialSpeed(),
                'passengers'            => Bus::getInitialPassengers(),
                'eta'                   => Bus::getInitialEta(),
                'is_simulated'          => false,
            ],
            [
                'plate_number'          => 'PAS-004',
                'fleet_number'          => 'BUS-004',
                'vin'                   => '1234567890ABCDEF4',
                'manufacturer'          => 'Higer',
                'model'                 => 'KLQ6125GEV',
                'year_model'            => 2023,
                'battery_capacity_kwh'  => 350.00,
                'max_charging_power_kw' => 150.00,
                'charging_port_type'    => 'GB/T',
                'purchase_date'         => '2023-11-18',
                'supplier'              => 'Higer Philippines',
                'warranty_expiry'       => '2028-11-18',
                'serial_number'         => 'HG-KLQ6125-004',
                'acquisition_cost'      => 18900000.00,
                'lat'                   => $defaultLat,
                'lng'                   => $defaultLng,
                'driver_name'           => Bus::getDefaultDriverName(),
                'next_stop'             => Bus::getDefaultNextStop(),
                'capacity'              => 26,
                'status'                => 'inactive',
                'route_id'              => null,
                'speed'                 => Bus::getInitialSpeed(),
                'passengers'            => Bus::getInitialPassengers(),
                'eta'                   => Bus::getInitialEta(),
                'is_simulated'          => false,
            ],
            [
                'plate_number'          => 'PAS-005',
                'fleet_number'          => 'BUS-005',
                'vin'                   => '1234567890ABCDEF5',
                'manufacturer'          => 'Golden Dragon',
                'model'                 => 'XML6125JEV',
                'year_model'            => 2025,
                'battery_capacity_kwh'  => 360.00,
                'max_charging_power_kw' => 150.00,
                'charging_port_type'    => 'CCS2',
                'purchase_date'         => '2025-01-22',
                'supplier'              => 'Golden Dragon Philippines',
                'warranty_expiry'       => '2030-01-22',
                'serial_number'         => 'GD-XML6125-005',
                'acquisition_cost'      => 20500000.00,
                'lat'                   => $defaultLat,
                'lng'                   => $defaultLng,
                'driver_name'           => Bus::getDefaultDriverName(),
                'next_stop'             => Bus::getDefaultNextStop(),
                'capacity'              => 26,
                'status'                => 'inactive',
                'route_id'              => null,
                'speed'                 => Bus::getInitialSpeed(),
                'passengers'            => Bus::getInitialPassengers(),
                'eta'                   => Bus::getInitialEta(),
                'is_simulated'          => false,
            ],
            [
                'plate_number'          => 'PAS-006',
                'fleet_number'          => 'BUS-006',
                'vin'                   => '1234567890ABCDEF6',
                'manufacturer'          => 'King Long',
                'model'                 => 'PEV12',
                'year_model'            => 2024,
                'battery_capacity_kwh'  => 340.00,
                'max_charging_power_kw' => 120.00,
                'charging_port_type'    => 'CHAdeMO',
                'purchase_date'         => '2024-04-12',
                'supplier'              => 'King Long Philippines',
                'warranty_expiry'       => '2029-04-12',
                'serial_number'         => 'KL-PEV12-006',
                'acquisition_cost'      => 19800000.00,
                'lat'                   => $defaultLat,
                'lng'                   => $defaultLng,
                'driver_name'           => Bus::getDefaultDriverName(),
                'next_stop'             => Bus::getDefaultNextStop(),
                'capacity'              => 42,
                'status'                => 'inactive',
                'route_id'              => null,
                'speed'                 => Bus::getInitialSpeed(),
                'passengers'            => Bus::getInitialPassengers(),
                'eta'                   => Bus::getInitialEta(),
                'is_simulated'          => false,
            ],
        ];

        if (app()->environment('local', 'testing')) {
            $driver = DB::connection()->getDriverName();
            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = OFF;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            }

            Bus::truncate();

            if ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON;');
            } else {
                DB::statement('SET FOREIGN_KEY_CHECKS=1;');
            }

            foreach ($busesData as $busData) {
                Bus::factory()->create($busData);
            }
        } else {
            foreach ($busesData as $busData) {
                $attributes = Bus::factory()->raw($busData);
                Bus::updateOrCreate(
                    ['fleet_number' => $busData['fleet_number']],
                    $attributes
                );
            }
        }
    }
}
