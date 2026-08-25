<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Bus;
use App\Models\Geofence;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Enums\GeofenceType;
use Illuminate\Support\Facades\Schema;

class RouteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Create the 4 operational routes
        $route1 = Route::create([
            'id' => 1,
            'name' => 'Route A',
            'color' => '#003F87',
            'description' => 'SPED (Caruncho Ave.) to Temporary Pasig City Hall',
            'polyline_coordinates' => [
                [14.5593, 121.0805],
                [14.5620, 121.0820],
                [14.5680, 121.0760],
                [14.5710, 121.0710],
                [14.5780, 121.0650],
                [14.5838, 121.0620]
            ],
            'travel_time_minutes' => 25,
            'delay_threshold_minutes' => 5,
            'min_speed' => 20,
            'max_speed' => 35,
            'target_on_time_rate' => 90,
            'target_headway_minutes' => 10,
        ]);

        $route2 = Route::create([
            'id' => 2,
            'name' => 'Route B',
            'color' => '#BA7517',
            'description' => 'SPED (Caruncho Ave.) to Ligaya (Santolan) via PCGH',
            'polyline_coordinates' => [
                [14.5593, 121.0805],
                [14.5580, 121.0750],
                [14.5540, 121.0620],
                [14.5520, 121.0560],
                [14.5500, 121.0500]
            ],
            'travel_time_minutes' => 35,
            'delay_threshold_minutes' => 10,
            'min_speed' => 15,
            'max_speed' => 40,
            'target_on_time_rate' => 85,
            'target_headway_minutes' => 15,
        ]);

        $route3 = Route::create([
            'id' => 3,
            'name' => 'Route C',
            'color' => '#639922',
            'description' => 'SPED (Caruncho Ave.) to One San Miguel Ave via Shaw',
            'polyline_coordinates' => [
                [14.5593, 121.0805],
                [14.5650, 121.0750],
                [14.5690, 121.0700],
                [14.5680, 121.0580],
                [14.5750, 121.0450],
                [14.5786, 121.0360]
            ],
            'travel_time_minutes' => 40,
            'delay_threshold_minutes' => 15,
            'min_speed' => 25,
            'max_speed' => 50,
            'target_on_time_rate' => 80,
            'target_headway_minutes' => 20,
        ]);

        $route4 = Route::create([
            'id' => 4,
            'name' => 'Route D',
            'color' => '#E24B4A',
            'description' => 'SPED (Caruncho Ave.) to Nagpayong (Pinagbuhatan) via Urbano Velasco',
            'polyline_coordinates' => [
                [14.5593, 121.0805],
                [14.5520, 121.0830],
                [14.5480, 121.0880],
                [14.5450, 121.0920]
            ],
            'travel_time_minutes' => 30,
            'delay_threshold_minutes' => 8,
            'min_speed' => 18,
            'max_speed' => 45,
            'target_on_time_rate' => 85,
            'target_headway_minutes' => 12,
        ]);

        // 2. Create Stops assigned to these routes
        // Route 1 stops (Point to point)
        Stop::create([
            'route_id' => $route1->id,
            'name' => 'SPED Terminal (Caruncho Ave.)',
            'lat' => 14.5593,
            'lng' => 121.0805,
            'sequence' => 1,
            'segment_weight' => null,
            'amenities' => 'Shelter, Security, Ticket Booth, Charging Station'
        ]);
        Stop::create([
            'route_id' => $route1->id,
            'name' => 'Temporary Pasig City Hall',
            'lat' => 14.5838,
            'lng' => 121.0620,
            'sequence' => 2,
            'segment_weight' => 1.0,
            'amenities' => 'Premium Station, Wi-Fi, Security Guard'
        ]);

        // Route 2 stops
        Stop::create([
            'route_id' => $route2->id,
            'name' => 'SPED Terminal (Caruncho Ave.)',
            'lat' => 14.5593,
            'lng' => 121.0805,
            'sequence' => 1,
            'segment_weight' => null,
            'amenities' => 'Shelter, Security, Ticket Booth, Charging Station'
        ]);
        Stop::create([
            'route_id' => $route2->id,
            'name' => 'Pasig City General Hospital (Maybunga)',
            'lat' => 14.5680,
            'lng' => 121.0760,
            'sequence' => 2,
            'segment_weight' => 1.0,
            'amenities' => 'Shelter, CCTV, Well-lit'
        ]);
        Stop::create([
            'route_id' => $route2->id,
            'name' => 'Ligaya (Santolan) Terminal',
            'lat' => 14.5500,
            'lng' => 121.0500,
            'sequence' => 3,
            'segment_weight' => 3.0,
            'amenities' => 'Shelter, Security Post'
        ]);

        // Route 3 stops
        Stop::create([
            'route_id' => $route3->id,
            'name' => 'SPED Terminal (Caruncho Ave.)',
            'lat' => 14.5593,
            'lng' => 121.0805,
            'sequence' => 1,
            'segment_weight' => null,
            'amenities' => 'Shelter, Security, Ticket Booth, Charging Station'
        ]);
        Stop::create([
            'route_id' => $route3->id,
            'name' => 'Shaw Blvd. Crossing',
            'lat' => 14.5680,
            'lng' => 121.0580,
            'sequence' => 2,
            'segment_weight' => 1.0,
            'amenities' => 'Shelter, Near Shaw Transit hub'
        ]);
        Stop::create([
            'route_id' => $route3->id,
            'name' => 'One San Miguel Ave (San Antonio)',
            'lat' => 14.5786,
            'lng' => 121.0360,
            'sequence' => 3,
            'segment_weight' => 1.0,
            'amenities' => 'Shelter, CCTV, Charging Station'
        ]);

        // Route 4 stops
        Stop::create([
            'route_id' => $route4->id,
            'name' => 'SPED Terminal (Caruncho Ave.)',
            'lat' => 14.5593,
            'lng' => 121.0805,
            'sequence' => 1,
            'segment_weight' => null,
            'amenities' => 'Shelter, Security, Ticket Booth, Charging Station'
        ]);
        Stop::create([
            'route_id' => $route4->id,
            'name' => 'Urbano Velasco Ave.',
            'lat' => 14.5520,
            'lng' => 121.0830,
            'sequence' => 2,
            'segment_weight' => 1.0,
            'amenities' => 'Shelter, Near Market'
        ]);
        Stop::create([
            'route_id' => $route4->id,
            'name' => 'Nagpayong (Pinagbuhatan) Terminal',
            'lat' => 14.5450,
            'lng' => 121.0920,
            'sequence' => 3,
            'segment_weight' => 1.5,
            'amenities' => 'Shelter, Security Post'
        ]);

        $this->backfillDefaultVariants();

        // 3. Create Geofences corresponding to Stops
        $uniqueStops = Stop::all()->unique('name');
        foreach ($uniqueStops as $stop) {
            $isTerminal = str_contains(strtolower($stop->name), 'terminal');
            
            Geofence::create([
                'name' => $stop->name,
                'type' => $isTerminal ? GeofenceType::TERMINAL : GeofenceType::STOP,
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$stop->lng, $stop->lat]
                ],
                'radius' => 30.0,
                'lat' => $stop->lat,
                'lng' => $stop->lng,
                'priority' => $isTerminal ? 80 : 100,
                'status' => 'active'
            ]);
        }

        // 4. Create a Depot geofence
        Geofence::create([
            'name' => 'Pasig Central Depot',
            'type' => GeofenceType::DEPOT,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => [121.0810, 14.5615]
            ],
            'radius' => 50.0,
            'lat' => 14.5615,
            'lng' => 121.0810,
            'priority' => 50,
            'status' => 'active'
        ]);

    }

    private function backfillDefaultVariants(): void
    {
        if (!Schema::hasTable('route_variants') || !Schema::hasTable('route_variant_stops')) {
            return;
        }

        Route::with(['stops' => fn ($query) => $query->orderBy('sequence')])
            ->orderBy('id')
            ->get()
            ->each(function (Route $route) {
                if ($route->variants()->where('direction', 'outbound')->exists()) {
                    return;
                }

                $stops = $route->stops;
                $variant = RouteVariant::create([
                    'route_id' => $route->id,
                    'direction' => 'outbound',
                    'origin_name' => $stops->first()?->name,
                    'destination_name' => $stops->last()?->name,
                    'polyline_coordinates' => $route->polyline_coordinates ?: [],
                    'geometry_version' => $route->geometry_version ?? 0,
                    'geometry_status' => empty($route->polyline_coordinates) ? 'pending' : 'valid',
                    'is_default' => true,
                ]);

                foreach ($stops as $stop) {
                    RouteVariantStop::create([
                        'route_variant_id' => $variant->id,
                        'canonical_stop_id' => $stop->id,
                        'name' => $stop->name,
                        'lat' => $stop->lat,
                        'lng' => $stop->lng,
                        'radius_meters' => $stop->radius_meters,
                        'sequence' => $stop->sequence,
                    ]);
                }
            });
    }
}
