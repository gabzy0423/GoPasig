<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Driver;

class DriverSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $drivers = [
            [
                'first_name' => 'Juan',
                'last_name' => 'dela Cruz',
                'emp_id' => 'EMP-0021',
                'license_number' => 'N01-23-456789',
                'license_expiry' => '2027-12-12',
                'status' => 'inactive',
                'assigned_bus' => null,
                'assigned_route' => null,
                'trips_today' => 0,
                'pax_today' => 0,
                'address' => '12 Rizal St, Ugong, Pasig City',
                'contact_number' => '09171234567',
                'emergency_contact' => 'Maria dela Cruz — 09181234567',
                'performance_score' => 88,
                'incidents_30' => 0,
            ],
            [
                'first_name' => 'Maria',
                'last_name' => 'Santos',
                'emp_id' => 'EMP-0022',
                'license_number' => 'N02-45-112233',
                'license_expiry' => '2026-10-10',
                'status' => 'inactive',
                'assigned_bus' => null,
                'assigned_route' => null,
                'trips_today' => 0,
                'pax_today' => 0,
                'address' => '45 Shaw Blvd, Manggahan, Pasig City',
                'contact_number' => '09281234567',
                'emergency_contact' => 'Pedro Santos — 09281239876',
                'performance_score' => 91,
                'incidents_30' => 1,
            ],
            [
                'first_name' => 'Ramon',
                'last_name' => 'Reyes',
                'emp_id' => 'EMP-0023',
                'license_number' => 'N03-67-998877',
                'license_expiry' => '2025-09-01',
                'status' => 'inactive',
                'assigned_bus' => null,
                'assigned_route' => null,
                'trips_today' => 0,
                'pax_today' => 0,
                'address' => '8 Amang Rodriguez Ave, Santolan, Pasig City',
                'contact_number' => '09171239988',
                'emergency_contact' => 'Clara Reyes — 09171239900',
                'performance_score' => 67,
                'incidents_30' => 2,
            ],
            [
                'first_name' => 'Ana',
                'last_name' => 'Flores',
                'emp_id' => 'EMP-0024',
                'license_number' => 'N04-22-556677',
                'license_expiry' => '2026-06-18',
                'status' => 'inactive',
                'assigned_bus' => null,
                'assigned_route' => null,
                'trips_today' => 0,
                'pax_today' => 0,
                'address' => '23 Ortigas Ave, Rosario, Pasig City',
                'contact_number' => '09281230044',
                'emergency_contact' => 'Jose Flores — 09281230099',
                'performance_score' => 94,
                'incidents_30' => 0,
            ],
            [
                'first_name' => 'Pedro',
                'last_name' => 'Mendoza',
                'emp_id' => 'EMP-0025',
                'license_number' => 'N05-11-334455',
                'license_expiry' => '2025-05-15',
                'status' => 'suspended',
                'assigned_bus' => null,
                'assigned_route' => null,
                'trips_today' => 0,
                'pax_today' => 0,
                'address' => '77 Caruncho Ave, San Nicolas, Pasig City',
                'contact_number' => '09171235544',
                'emergency_contact' => 'Lina Mendoza — 09171235599',
                'performance_score' => 54,
                'incidents_30' => 3,
            ],
            [
                'first_name' => 'Carlos',
                'last_name' => 'Bautista',
                'emp_id' => 'EMP-0026',
                'license_number' => 'N06-88-223344',
                'license_expiry' => '2028-06-20',
                'status' => 'inactive',
                'assigned_bus' => null,
                'assigned_route' => null,
                'trips_today' => 0,
                'pax_today' => 0,
                'address' => '34 Meralco Ave, Ugong, Pasig City',
                'contact_number' => '09281238866',
                'emergency_contact' => 'Rosa Bautista — 09281238811',
                'performance_score' => 89,
                'incidents_30' => 0,
            ],
            [
                'first_name' => 'Liza',
                'last_name' => 'Garcia',
                'emp_id' => 'EMP-0027',
                'license_number' => 'N07-33-445566',
                'license_expiry' => '2026-07-10',
                'status' => 'inactive',
                'assigned_bus' => null,
                'assigned_route' => null,
                'trips_today' => 0,
                'pax_today' => 0,
                'address' => '5 Brgy. Bambang, Pasig City',
                'contact_number' => '09171234477',
                'emergency_contact' => 'Marco Garcia — 09171234400',
                'performance_score' => 78,
                'incidents_30' => 0,
            ],
            [
                'first_name' => 'Roberto',
                'last_name' => 'Santos',
                'emp_id' => 'EMP-0028',
                'license_number' => 'N08-55-778899',
                'license_expiry' => '2027-08-15',
                'status' => 'inactive',
                'assigned_bus' => null,
                'assigned_route' => null,
                'trips_today' => 0,
                'pax_today' => 0,
                'address' => '101 Julia Vargas Ave, Wack-wack, Pasig City',
                'contact_number' => '09281235588',
                'emergency_contact' => 'Ana Santos — 09281235500',
                'performance_score' => 92,
                'incidents_30' => 0,
            ]
        ];

        $driverUser = \App\Models\User::where('email', 'driver@example.com')->first();

        foreach ($drivers as $index => $driver) {
            if ($index === 0 && $driverUser) {
                $driver['user_id'] = $driverUser->id;
            }
            Driver::updateOrCreate(
                ['emp_id' => $driver['emp_id']],
                $driver
            );
        }
    }
}
