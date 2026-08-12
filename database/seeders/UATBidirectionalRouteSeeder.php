<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Schedule;
use App\Models\Stop;
use App\Models\Trip;

class UATBidirectionalRouteSeeder
{
    public const ROUTE_NAME = 'Route UAT (Bidirectional Test Route)';

    /**
     * Run the UAT Bidirectional Route Seeder.
     */
    public function run(): array
    {
        // 1. Create or retrieve the UAT Route
        $route = Route::updateOrCreate(
            ['name' => self::ROUTE_NAME],
            [
                'color' => '#8B5CF6',
                'description' => 'Temporary UAT Bidirectional Test Route for Suspend Route Phase 4 Extended Manual UAT.',
                'travel_time_minutes' => 25,
                'delay_threshold_minutes' => 10,
                'min_speed' => 15,
                'max_speed' => 50,
                'target_on_time_rate' => 95,
                'target_headway_minutes' => 15,
                'status' => 'Active',
            ]
        );

        // 2. Create Outbound Route Variant
        $outboundVariant = RouteVariant::updateOrCreate(
            [
                'route_id' => $route->id,
                'direction' => 'outbound',
            ],
            [
                'origin_name' => 'Terminal A (Pasig Mega Market)',
                'destination_name' => 'Terminal B (San Joaquin)',
                'is_default' => true,
                'geometry_status' => 'valid',
            ]
        );

        // Outbound Stops
        $outboundStops = [
            ['name' => 'Terminal A (Pasig Mega Market)', 'lat' => 14.5593, 'lng' => 121.0805],
            ['name' => 'Waystation 1 (City Hall)', 'lat' => 14.5580, 'lng' => 121.0820],
            ['name' => 'Terminal B (San Joaquin)', 'lat' => 14.5500, 'lng' => 121.0850],
        ];

        foreach ($outboundStops as $index => $stopDef) {
            RouteVariantStop::updateOrCreate(
                [
                    'route_variant_id' => $outboundVariant->id,
                    'sequence' => $index + 1,
                ],
                [
                    'name' => $stopDef['name'],
                    'latitude' => $stopDef['lat'],
                    'longitude' => $stopDef['lng'],
                ]
            );
        }

        // 3. Create Inbound Route Variant
        $inboundVariant = RouteVariant::updateOrCreate(
            [
                'route_id' => $route->id,
                'direction' => 'inbound',
            ],
            [
                'origin_name' => 'Terminal B (San Joaquin)',
                'destination_name' => 'Terminal A (Pasig Mega Market)',
                'is_default' => false,
                'geometry_status' => 'valid',
            ]
        );

        // Inbound Stops
        $inboundStops = [
            ['name' => 'Terminal B (San Joaquin)', 'lat' => 14.5500, 'lng' => 121.0850],
            ['name' => 'Waystation 1 (City Hall)', 'lat' => 14.5580, 'lng' => 121.0820],
            ['name' => 'Terminal A (Pasig Mega Market)', 'lat' => 14.5593, 'lng' => 121.0805],
        ];

        foreach ($inboundStops as $index => $stopDef) {
            RouteVariantStop::updateOrCreate(
                [
                    'route_variant_id' => $inboundVariant->id,
                    'sequence' => $index + 1,
                ],
                [
                    'name' => $stopDef['name'],
                    'latitude' => $stopDef['lat'],
                    'longitude' => $stopDef['lng'],
                ]
            );
        }

        // 4. Create Standard Route Stops for legacy compatibility
        foreach ($outboundStops as $index => $stopDef) {
            Stop::updateOrCreate(
                [
                    'route_id' => $route->id,
                    'sequence' => $index + 1,
                ],
                [
                    'name' => $stopDef['name'],
                    'lat' => $stopDef['lat'],
                    'lng' => $stopDef['lng'],
                ]
            );
        }

        // 5. Assign Bus and Driver for sample Schedules
        $bus1 = Bus::firstOrCreate(
            ['plate_number' => 'PAS-UAT1'],
            ['status' => Bus::STATUS_INACTIVE, 'driver_name' => 'UAT Driver 1', 'passengers' => 0]
        );

        $bus2 = Bus::firstOrCreate(
            ['plate_number' => 'PAS-UAT2'],
            ['status' => Bus::STATUS_INACTIVE, 'driver_name' => 'UAT Driver 2', 'passengers' => 0]
        );

        $driver1 = Driver::firstOrCreate(
            ['license_number' => 'UAT-LIC-001'],
            [
                'emp_id' => 'EMP-UAT1',
                'first_name' => 'UAT',
                'last_name' => 'Driver 1',
                'license_expiry' => '2028-12-31',
                'status' => 'active',
                'operational_status' => 'available',
                'assigned_bus' => 'PAS-UAT1',
                'assigned_route' => $route->id,
                'address' => 'Pasig City Hall UAT Terminal',
                'contact_number' => '09170000001',
                'emergency_contact' => 'Operations Dispatch — 09170000000',
            ]
        );

        $driver2 = Driver::firstOrCreate(
            ['license_number' => 'UAT-LIC-002'],
            [
                'emp_id' => 'EMP-UAT2',
                'first_name' => 'UAT',
                'last_name' => 'Driver 2',
                'license_expiry' => '2028-12-31',
                'status' => 'active',
                'operational_status' => 'available',
                'assigned_bus' => 'PAS-UAT2',
                'assigned_route' => $route->id,
                'address' => 'Pasig City Hall UAT Terminal',
                'contact_number' => '09170000002',
                'emergency_contact' => 'Operations Dispatch — 09170000000',
            ]
        );

        // 6. Create Outbound Schedule (08:00)
        $outboundSchedule = Schedule::updateOrCreate(
            [
                'route_id' => $route->id,
                'departure_time' => '08:00',
            ],
            [
                'arrival_time' => '08:25',
                'bus_id' => $bus1->id,
                'driver_id' => $driver1->id,
                'passengers' => 20,
                'status' => 'On time',
            ]
        );

        // 7. Create Inbound Schedule (08:30)
        $inboundSchedule = Schedule::updateOrCreate(
            [
                'route_id' => $route->id,
                'departure_time' => '08:30',
            ],
            [
                'arrival_time' => '08:55',
                'bus_id' => $bus2->id,
                'driver_id' => $driver2->id,
                'passengers' => 15,
                'status' => 'On time',
            ]
        );

        return [
            'route' => $route,
            'outboundVariant' => $outboundVariant,
            'inboundVariant' => $inboundVariant,
            'outboundSchedule' => $outboundSchedule,
            'inboundSchedule' => $inboundSchedule,
            'bus1' => $bus1,
            'bus2' => $bus2,
            'driver1' => $driver1,
            'driver2' => $driver2,
        ];
    }

    /**
     * Clean up all UAT Bidirectional Route records.
     */
    public static function cleanup(): void
    {
        $route = Route::withTrashed()->where('name', self::ROUTE_NAME)->first();
        if ($route) {
            $variantIds = RouteVariant::where('route_id', $route->id)->pluck('id');
            RouteVariantStop::whereIn('route_variant_id', $variantIds)->delete();
            RouteVariant::whereIn('id', $variantIds)->delete();
            Schedule::where('route_id', $route->id)->delete();
            Trip::where('route_id', $route->id)->delete();
            Stop::where('route_id', $route->id)->delete();

            $route->forceDelete();
        }

        Bus::whereIn('plate_number', ['PAS-UAT1', 'PAS-UAT2'])->delete();
        Driver::where(function ($query) {
            $query->whereIn('license_number', ['UAT-LIC-001', 'UAT-LIC-002'])
                ->orWhereIn('emp_id', ['EMP-UAT1', 'EMP-UAT2']);
        })->delete();
    }
}
