<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ROUTE_1_DESCRIPTION = 'SPED (Caruncho Ave.) to Temporary Pasig City Hall';
    private const ROUTE_1_OUT = [[14.5884924, 121.1050783], [14.5924588, 121.0864920]];
    private const ROUTE_1_IN = [[14.5924588, 121.0864920], [14.5884924, 121.1050783]];

    public function up(): void
    {
        if (! Schema::hasTable('routes') || ! Schema::hasTable('route_variants') || ! Schema::hasTable('route_variant_stops')) {
            return;
        }

        DB::transaction(function (): void {
            $ligaya = $this->findRouteByIdentity('Route 1', 'Ligaya');
            $nagpayong = $this->findRouteByIdentity('Route 2', 'Nagpayong');
            $oneSanMiguel = $this->findRouteByIdentity('Route 3', 'One San Miguel');

            if (! $ligaya && ! $nagpayong && ! $oneSanMiguel && ! $this->officialRouteOneExists()) {
                return;
            }

            if (! $ligaya && ! $this->findRouteByIdentity('Route 2', 'Ligaya')) {
                throw new \RuntimeException('RD7.4 reconciliation expected current Route 1 / Ligaya before migration.');
            }
            if (! $nagpayong && ! $this->findRouteByIdentity('Route 4', 'Nagpayong')) {
                throw new \RuntimeException('RD7.4 reconciliation expected current Route 2 / Nagpayong before migration.');
            }
            if (! $oneSanMiguel) {
                throw new \RuntimeException('RD7.4 reconciliation expected Route 3 / One San Miguel to exist.');
            }

            $conflictingRoute4 = DB::table('routes')
                ->where('name', 'Route 4')
                ->where('description', 'not like', '%Nagpayong%')
                ->whereNull('deleted_at')
                ->first();
            if ($conflictingRoute4) {
                throw new \RuntimeException('RD7.4 reconciliation found a conflicting Route 4 record.');
            }

            if ($ligaya) {
                DB::table('routes')->where('id', $ligaya->id)->update([
                    'name' => 'Route 2',
                    'description' => 'SPED (Caruncho Ave.) to Ligaya',
                    'updated_at' => now(),
                ]);
            }

            if ($nagpayong) {
                DB::table('routes')->where('id', $nagpayong->id)->update([
                    'name' => 'Route 4',
                    'description' => 'SPED (Caruncho Ave.) to Nagpayong',
                    'updated_at' => now(),
                ]);
            }

            $this->ensureOfficialRouteOne();
            Cache::forget('routes_all');
            Cache::forget('stops_all');
            Cache::forget('commuter_dashboard_aggregate');
            Cache::forget('commuter_route_stops_aggregate');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('routes') || ! Schema::hasTable('route_variants') || ! Schema::hasTable('route_variant_stops')) {
            return;
        }

        DB::transaction(function (): void {
            $routeOne = $this->findRouteByIdentity('Route 1', 'Temporary Pasig City Hall');
            if ($routeOne) {
                $variantIds = DB::table('route_variants')->where('route_id', $routeOne->id)->pluck('id');
                if (Schema::hasTable('route_variant_corridors')) {
                    DB::table('route_variant_corridors')->whereIn('route_variant_id', $variantIds)->delete();
                }
                DB::table('route_variant_stops')->whereIn('route_variant_id', $variantIds)->delete();
                DB::table('route_variants')->whereIn('id', $variantIds)->delete();
                DB::table('routes')->where('id', $routeOne->id)->delete();
            }

            $route2 = $this->findRouteByIdentity('Route 2', 'Ligaya');
            if ($route2) {
                DB::table('routes')->where('id', $route2->id)->update(['name' => 'Route 1', 'updated_at' => now()]);
            }

            $route4 = $this->findRouteByIdentity('Route 4', 'Nagpayong');
            if ($route4) {
                DB::table('routes')->where('id', $route4->id)->update(['name' => 'Route 2', 'updated_at' => now()]);
            }

            Cache::forget('routes_all');
            Cache::forget('stops_all');
        });
    }

    private function findRouteByIdentity(string $name, string $descriptionNeedle): ?object
    {
        return DB::table('routes')
            ->where('name', $name)
            ->where('description', 'like', '%' . $descriptionNeedle . '%')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();
    }

    private function officialRouteOneExists(): bool
    {
        $route = $this->findRouteByIdentity('Route 1', 'Temporary Pasig City Hall');
        return $route && DB::table('route_variants')->where('route_id', $route->id)->count() === 2;
    }

    private function ensureOfficialRouteOne(): void
    {
        $route = $this->findRouteByIdentity('Route 1', 'Temporary Pasig City Hall');
        $now = now();

        if (! $route) {
            $routeId = DB::table('routes')->insertGetId([
                'name' => 'Route 1',
                'color' => '#003F87',
                'description' => self::ROUTE_1_DESCRIPTION,
                'polyline_coordinates' => json_encode([]),
                'geometry_version' => 0,
                'status' => 'Active',
                'travel_time_minutes' => 20,
                'delay_threshold_minutes' => 8,
                'min_speed' => 15,
                'max_speed' => 45,
                'target_on_time_rate' => 85,
                'target_headway_minutes' => 15,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $route = DB::table('routes')->where('id', $routeId)->first();
        } else {
            DB::table('routes')->where('id', $route->id)->update([
                'description' => self::ROUTE_1_DESCRIPTION,
                'status' => 'Active',
                'updated_at' => $now,
            ]);
        }

        $this->ensureVariant($route->id, 'outbound', 'SPED', 'Bridgetown', self::ROUTE_1_OUT, [
            ['SPED', 14.5884924, 121.1050783, 'pickup_point'],
            ['Bridgetown', 14.5924588, 121.0864920, 'designated_stop'],
        ]);
        $this->ensureVariant($route->id, 'inbound', 'Bridgetown', 'SPED', self::ROUTE_1_IN, [
            ['Bridgetown', 14.5924588, 121.0864920, 'pickup_point'],
            ['SPED', 14.5884924, 121.1050783, 'designated_stop'],
        ]);
    }

    private function ensureVariant(int $routeId, string $direction, string $origin, string $destination, array $coordinates, array $stops): void
    {
        $now = now();
        $variant = DB::table('route_variants')->where('route_id', $routeId)->where('direction', $direction)->first();
        $payload = [
            'origin_name' => $origin,
            'destination_name' => $destination,
            'polyline_coordinates' => json_encode($coordinates, JSON_PRESERVE_ZERO_FRACTION),
            'geometry_version' => (int) ($variant->geometry_version ?? 0) + ($variant ? 0 : 1),
            'geometry_status' => 'schematic',
            'is_default' => $direction === 'outbound',
            'updated_at' => $now,
        ];

        if ($variant) {
            DB::table('route_variants')->where('id', $variant->id)->update($payload);
            $variantId = $variant->id;
        } else {
            $payload['route_id'] = $routeId;
            $payload['direction'] = $direction;
            $payload['created_at'] = $now;
            $variantId = DB::table('route_variants')->insertGetId($payload);
        }

        DB::table('route_variant_stops')->where('route_variant_id', $variantId)->delete();
        foreach ($stops as $index => [$name, $lat, $lng, $type]) {
            DB::table('route_variant_stops')->insert([
                'route_variant_id' => $variantId,
                'canonical_stop_id' => null,
                'name' => $name,
                'lat' => $lat,
                'lng' => $lng,
                'radius_meters' => 100,
                'sequence' => $index + 1,
                'stop_type' => $type,
                'coordinate_status' => 'verified',
                'coordinate_source' => 'official beneficiary data',
                'coordinates_verified_at' => null,
                'coordinates_verified_by_user_id' => null,
                'coordinate_notes' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (Schema::hasTable('route_variant_corridors')) {
            $geometry = ['type' => 'LineString', 'coordinates' => $coordinates, 'coordinate_order' => 'lat_lng'];
            DB::table('route_variant_corridors')->updateOrInsert(
                ['route_variant_id' => $variantId],
                [
                    'geometry' => json_encode($geometry, JSON_PRESERVE_ZERO_FRACTION),
                    'geometry_hash' => hash('sha256', json_encode($coordinates, JSON_PRESERVE_ZERO_FRACTION)),
                    'coordinate_count' => count($coordinates),
                    'generated_at' => $now,
                    'generation_source' => 'route_variant.polyline_coordinates',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }
};
