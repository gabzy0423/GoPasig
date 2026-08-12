<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantCorridor;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class RouteVariantCorridorGenerator
{
    public const GENERATION_SOURCE = 'route_variant.polyline_coordinates';
    private const DIRECTIONS = ['outbound', 'inbound'];

    public function buildPlans(): array
    {
        $plans = [];

        foreach (Route::canonicalProductionNames() as $routeName) {
            foreach (self::DIRECTIONS as $direction) {
                $variant = RouteVariant::query()
                    ->where('direction', $direction)
                    ->whereHas('route', fn ($query) => $query->where('name', $routeName))
                    ->with(['route', 'corridor'])
                    ->first();

                if (!$variant) {
                    throw new RuntimeException("Missing canonical variant: {$routeName} {$direction}.");
                }

                $coordinates = $this->validatedCoordinates($variant, $routeName, $direction);
                $geometry = [
                    'type' => 'LineString',
                    'coordinates' => $coordinates,
                    'coordinate_order' => 'lat_lng',
                ];
                $hash = $this->hashCoordinates($coordinates);

                $plans[] = [
                    'route_id' => $variant->route_id,
                    'route_name' => $routeName,
                    'route_variant_id' => $variant->id,
                    'direction' => $direction,
                    'geometry' => $geometry,
                    'geometry_hash' => $hash,
                    'coordinate_count' => count($coordinates),
                    'first_coordinate' => $coordinates[0],
                    'last_coordinate' => $coordinates[count($coordinates) - 1],
                    'existing_corridor_id' => $variant->corridor?->id,
                    'existing_hash' => $variant->corridor?->geometry_hash,
                    'changed' => $variant->corridor?->geometry_hash !== $hash,
                ];
            }
        }

        return $plans;
    }

    public function apply(): array
    {
        $plans = $this->buildPlans();
        $generatedAt = now();

        DB::transaction(function () use ($plans, $generatedAt): void {
            foreach ($plans as $plan) {
                RouteVariantCorridor::updateOrCreate(
                    ['route_variant_id' => $plan['route_variant_id']],
                    [
                        'geometry' => $plan['geometry'],
                        'geometry_hash' => $plan['geometry_hash'],
                        'coordinate_count' => $plan['coordinate_count'],
                        'generated_at' => $generatedAt,
                        'generation_source' => self::GENERATION_SOURCE,
                    ]
                );
            }
        });

        return $this->buildPlans();
    }

    private function validatedCoordinates(RouteVariant $variant, string $routeName, string $direction): array
    {
        $coordinates = $variant->polyline_coordinates ?: [];
        if (!is_array($coordinates) || count($coordinates) < 2) {
            throw new RuntimeException("{$routeName} {$direction} must have at least two stored polyline coordinates.");
        }

        $normalized = [];
        foreach ($coordinates as $index => $point) {
            if (!is_array($point) || count($point) < 2 || !is_numeric($point[0]) || !is_numeric($point[1])) {
                throw new RuntimeException("{$routeName} {$direction} has an invalid coordinate at index {$index}.");
            }

            $lat = (float) $point[0];
            $lng = (float) $point[1];
            if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                throw new RuntimeException("{$routeName} {$direction} has an out-of-range coordinate at index {$index}.");
            }

            $normalized[] = [$lat, $lng];
        }

        return $normalized;
    }

    private function hashCoordinates(array $coordinates): string
    {
        return hash('sha256', json_encode($coordinates, JSON_PRESERVE_ZERO_FRACTION));
    }
}
