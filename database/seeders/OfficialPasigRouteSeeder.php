<?php

namespace Database\Seeders;

use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class OfficialPasigRouteSeeder extends Seeder
{
    private const RADIUS_METERS = 100;

    public function run(): void
    {
        if (! Schema::hasTable('route_variants') || ! Schema::hasTable('route_variant_stops')) {
            return;
        }

        foreach ($this->officialRoutes() as $definition) {
            $route = Route::updateOrCreate(
                ['name' => $definition['name']],
                [
                    'color' => $definition['color'],
                    'description' => $definition['description'],
                    'polyline_coordinates' => [],
                    'travel_time_minutes' => $definition['travel_time_minutes'],
                    'delay_threshold_minutes' => $definition['delay_threshold_minutes'],
                    'min_speed' => $definition['min_speed'],
                    'max_speed' => $definition['max_speed'],
                    'target_on_time_rate' => $definition['target_on_time_rate'],
                    'target_headway_minutes' => $definition['target_headway_minutes'],
                    'status' => 'Active',
                ]
            );

            foreach ($definition['variants'] as $variantDefinition) {
                $variant = RouteVariant::updateOrCreate(
                    [
                        'route_id' => $route->id,
                        'direction' => $variantDefinition['direction'],
                    ],
                    [
                        'origin_name' => $variantDefinition['origin_name'],
                        'destination_name' => $variantDefinition['destination_name'],
                        'polyline_coordinates' => $variantDefinition['polyline_coordinates'],
                        'geometry_version' => 0,
                        'geometry_status' => $variantDefinition['geometry_status'],
                        'is_default' => $variantDefinition['is_default'],
                    ]
                );

                foreach ($variantDefinition['stops'] as $index => $stopDefinition) {
                    $variantStop = RouteVariantStop::firstOrNew([
                        'route_variant_id' => $variant->id,
                        'sequence' => $index + 1,
                    ]);
                    $variantStop->fill([
                        'name' => $stopDefinition['name'],
                        'lat' => $stopDefinition['lat'] ?? $variantStop->lat,
                        'lng' => $stopDefinition['lng'] ?? $variantStop->lng,
                        'radius_meters' => self::RADIUS_METERS,
                        'stop_type' => $stopDefinition['stop_type'],
                    ]);
                    if (! $variantStop->exists) {
                        $variantStop->fill([
                            'canonical_stop_id' => null,
                            'lat' => $stopDefinition['lat'] ?? null,
                            'lng' => $stopDefinition['lng'] ?? null,
                            'coordinate_status' => isset($stopDefinition['lat'], $stopDefinition['lng']) ? 'verified' : 'pending',
                            'coordinate_source' => isset($stopDefinition['lat'], $stopDefinition['lng']) ? 'official beneficiary data' : null,
                            'coordinates_verified_at' => null,
                            'coordinates_verified_by_user_id' => null,
                            'coordinate_notes' => null,
                        ]);
                    }
                    $variantStop->save();
                }

                // Keep the default projection for legacy stop consumers.
                // Variant-aware commuter journeys retain both directions
                // without merging their independent stop sequences.
                if ((bool) $variant->is_default) {
                    $this->syncLegacyCommuterStops($route, $variant);
                }

                $this->seedServiceSchedules($route, $variant);
            }
        }
    }

    private function syncLegacyCommuterStops(Route $route, RouteVariant $variant): void
    {
        if (! Schema::hasTable('stops')) {
            return;
        }

        $variant->loadMissing(['stops' => fn ($query) => $query->orderBy('sequence')]);

        foreach ($variant->stops as $variantStop) {
            if ($variantStop->lat === null || $variantStop->lng === null) {
                continue;
            }

            $stop = Stop::firstOrCreate(
                [
                    'route_id' => $route->id,
                    'sequence' => $variantStop->sequence,
                ],
                [
                    'name' => $variantStop->name,
                    'lat' => $variantStop->lat,
                    'lng' => $variantStop->lng,
                    'radius_meters' => $variantStop->radius_meters ?: self::RADIUS_METERS,
                ]
            );

            if ((int) $variantStop->canonical_stop_id !== (int) $stop->id) {
                $variantStop->forceFill(['canonical_stop_id' => $stop->id])->save();
            }
        }
    }

    private function seedServiceSchedules(Route $route, RouteVariant $variant): void
    {
        if (! Schema::hasTable('route_service_schedules')) {
            return;
        }

        $windows = $variant->direction === 'inbound'
            ? [
                ['first_trip_time' => '06:00:00', 'last_trip_time' => '09:00:00'],
                ['first_trip_time' => '15:00:00', 'last_trip_time' => '18:00:00'],
            ]
            : [
                ['first_trip_time' => '05:30:00', 'last_trip_time' => '09:00:00'],
                ['first_trip_time' => '15:00:00', 'last_trip_time' => '17:00:00'],
            ];

        foreach ($windows as $window) {
            RouteServiceSchedule::updateOrCreate(
                [
                    'route_id' => $route->id,
                    'route_variant_id' => $variant->id,
                    'first_trip_time' => $window['first_trip_time'],
                    'last_trip_time' => $window['last_trip_time'],
                ],
                [
                    'service_configuration' => 'with_designated_stops',
                    'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
                    'is_active' => true,
                    'source' => RouteServiceSchedule::SOURCE_BENEFICIARY_OFFICIAL,
                    'effective_from' => null,
                    'effective_until' => null,
                ]
            );
        }
    }

    private function officialRoutes(): array
    {
        return [
            [
                'name' => 'Route 2',
                'color' => '#BA7517',
                'description' => 'SPED (Caruncho Ave.) to Ligaya',
                'travel_time_minutes' => 35,
                'delay_threshold_minutes' => 10,
                'min_speed' => 15,
                'max_speed' => 40,
                'target_on_time_rate' => 85,
                'target_headway_minutes' => 15,
                'variants' => [
                    [
                        'direction' => 'outbound',
                        'origin_name' => 'SPED',
                        'destination_name' => 'Ligaya',
                        'polyline_coordinates' => [],
                        'geometry_status' => 'pending',
                        'is_default' => true,
                        'stops' => $this->typedStops(['SPED (Caruncho Ave.)'], 'pickup_point', [
                            'Caruncho (Dunkin)',
                            'Kapasigan (Landbank)',
                            'Kapasigan (after Meralco)',
                            'Rotonda',
                            'Caniogan (Eastern Police)',
                            'Caniogan Rizal High',
                            'Caniogan (Pag-Asa St)',
                            'Stella Maris',
                            'Sandoval Bridge',
                            'Bernal',
                            'Tramo',
                            "Jenny's",
                            'Amang Road',
                            'Manggahan (Brgy. Hall - Petron Mangahan)',
                            'Mabini (Pasig Doctors)',
                            'Magsaysay (Simbahan)',
                            'Santolan (Green Park Vill)',
                            'Santolan (tapat ng Jollibee)',
                            'Ligaya (Puregold)',
                        ], 'designated_stop'),
                    ],
                    [
                        'direction' => 'inbound',
                        'origin_name' => 'Ligaya',
                        'destination_name' => 'SPED',
                        'polyline_coordinates' => [],
                        'geometry_status' => 'pending',
                        'is_default' => false,
                        'stops' => $this->typedStops([
                            'Ligaya (Puregold)',
                            'Santolan (Metrobank)',
                            'Magsaysay',
                            'Mabini (Pasig Doctors)',
                            'Rosario (Manila Water)',
                            'Rosario (Tulay)',
                            'Rosario (Alfonso Hospital)',
                            'Sandoval Bridge',
                            'Stella Mariz',
                            'Pag - asa',
                            'Rotonda',
                            'Kapasigan (Security Bank)',
                            'Kapasigan (SSS)',
                            'Caruncho (Cemetery)',
                            'Caruncho (Chowking)',
                            'Pasig Market (Revolving)',
                            'Pasig Market (Mang Inasal)',
                        ], 'pickup_point', ['SPED (Caruncho Ave.)'], 'designated_stop'),
                    ],
                ],
            ],
            [
                'name' => 'Route 3',
                'color' => '#639922',
                'description' => 'SPED (Caruncho Ave.) to One San Miguel Ave.',
                'travel_time_minutes' => 40,
                'delay_threshold_minutes' => 15,
                'min_speed' => 25,
                'max_speed' => 50,
                'target_on_time_rate' => 80,
                'target_headway_minutes' => 20,
                'variants' => [
                    [
                        'direction' => 'outbound',
                        'origin_name' => 'SPED',
                        'destination_name' => 'One San Miguel Ave.',
                        'polyline_coordinates' => [],
                        'geometry_status' => 'pending',
                        'is_default' => true,
                        'stops' => $this->typedStops(['SPED (Caruncho Ave.)'], 'pickup_point', [
                            'Pasig Market (in front of Mercury Drug)',
                            'Kapasigan (Landbank)',
                            'Kapasigan (after Meralco)',
                            'Rotonda (BPI)',
                            'Bagong Ilog (Tulay)',
                            'Bagong Ilog (URC)',
                            'Pineda (RMC)',
                            'Pineda (Lumiere)',
                            'Oranbo (BDO Pasig)',
                            'Capitol (Unimart)',
                            'Pioneer',
                            'One San Miguel',
                        ], 'designated_stop'),
                    ],
                    [
                        'direction' => 'inbound',
                        'origin_name' => 'One San Miguel Ave.',
                        'destination_name' => 'SPED',
                        'polyline_coordinates' => [],
                        'geometry_status' => 'pending',
                        'is_default' => false,
                        'stops' => $this->typedStops([
                            'One San Miguel',
                            'Pioneer',
                            'Capitol',
                            'Oranbo',
                            'Pineda (Lumiere)',
                            'Pineda (RMC)',
                            'Bagong Ilog (Talipapa)',
                            'Rotonda (Baliuag)',
                            'Kapasigan (Security bank)',
                            'Kapasigan (SSS)',
                            'Caruncho (Cemetery)',
                            'Caruncho (Chowking)',
                            'Pasig Market (Revolving)',
                        ], 'pickup_point', ['SPED (Caruncho Ave.)'], 'designated_stop'),
                    ],
                ],
            ],
            [
                'name' => 'Route 4',
                'color' => '#E24B4A',
                'description' => 'SPED (Caruncho Ave.) to Nagpayong',
                'travel_time_minutes' => 30,
                'delay_threshold_minutes' => 8,
                'min_speed' => 18,
                'max_speed' => 45,
                'target_on_time_rate' => 85,
                'target_headway_minutes' => 12,
                'variants' => [
                    [
                        'direction' => 'outbound',
                        'origin_name' => 'SPED',
                        'destination_name' => 'Nagpayong',
                        'polyline_coordinates' => [],
                        'geometry_status' => 'pending',
                        'is_default' => true,
                        'stops' => $this->typedStops(['SPED (Caruncho Ave.)'], 'pickup_point', [
                            'Pasig Market (Gulayan)',
                            'Pinagbuhatan High School',
                            'Isla Home - Pag Ibig Home',
                            'Ilugin',
                            'Arezzo',
                            'Centennial II',
                            'Kenneth Road',
                        ], 'designated_stop'),
                    ],
                    [
                        'direction' => 'inbound',
                        'origin_name' => 'Nagpayong',
                        'destination_name' => 'SPED',
                        'polyline_coordinates' => [],
                        'geometry_status' => 'pending',
                        'is_default' => false,
                        'stops' => $this->typedStops([
                            'Kenneth Road',
                            'Centennial II',
                            'Arezzo',
                            'Ilugin',
                            'Isla Home',
                            'Pinagbuhatan High School',
                            'Pasig Market (Novo)',
                            'Pasig Market (Beside Mang Inasal)',
                        ], 'pickup_point', ['SPED (Caruncho Ave.)'], 'designated_stop'),
                    ],
                ],
            ],
        ];
    }

    private function typedStops(array $firstGroup, string $firstType, array $secondGroup, string $secondType): array
    {
        return array_merge(
            array_map(fn (string $name) => ['name' => $name, 'stop_type' => $firstType], $firstGroup),
            array_map(fn (string $name) => ['name' => $name, 'stop_type' => $secondType], $secondGroup),
        );
    }
}
