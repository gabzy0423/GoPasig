<?php

namespace Database\Factories;

use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteFactory extends Factory
{
    protected $model = Route::class;

    public function definition(): array
    {
        return [
            'name' => 'Route ' . $this->faker->randomElement(['A', 'B', 'C', 'D']),
            'color' => $this->faker->hexColor(),
            'description' => $this->faker->sentence(),
            'polyline_coordinates' => [
                [14.5593, 121.0805],
                [14.5650, 121.0750],
                [14.5690, 121.0700],
            ],
            'travel_time_minutes' => $this->faker->numberBetween(20, 45),
            'delay_threshold_minutes' => $this->faker->numberBetween(5, 15),
            'min_speed' => $this->faker->numberBetween(15, 25),
            'max_speed' => $this->faker->numberBetween(35, 50),
            'target_on_time_rate' => $this->faker->numberBetween(80, 95),
            'target_headway_minutes' => $this->faker->numberBetween(10, 20),
        ];
    }

    public function official(string $name = 'Route 2'): static
    {
        return $this->state(fn () => [
            'name' => $name,
            'status' => 'Active',
        ]);
    }

    public function withUsableVariant(string $direction = 'outbound'): static
    {
        return $this->afterCreating(function (Route $route) use ($direction) {
            $isOutbound = $direction === 'outbound';
            $origin = $isOutbound ? 'SPED' : 'Ligaya';
            $destination = $isOutbound ? 'Ligaya' : 'SPED';
            $coordinates = $isOutbound
                ? [[14.5602934, 121.0797616], [14.6185612, 121.0925442]]
                : [[14.6182022, 121.0924001], [14.5603845, 121.0798618]];

            $variant = RouteVariant::create([
                'route_id' => $route->id,
                'direction' => $direction,
                'origin_name' => $origin,
                'destination_name' => $destination,
                'polyline_coordinates' => $coordinates,
                'geometry_status' => 'valid',
                'is_default' => true,
            ]);

            RouteVariantStop::create([
                'route_variant_id' => $variant->id,
                'name' => $origin,
                'lat' => $coordinates[0][0],
                'lng' => $coordinates[0][1],
                'radius_meters' => 50,
                'sequence' => 1,
            ]);
            RouteVariantStop::create([
                'route_variant_id' => $variant->id,
                'name' => $destination,
                'lat' => $coordinates[1][0],
                'lng' => $coordinates[1][1],
                'radius_meters' => 50,
                'sequence' => 2,
            ]);
        });
    }
}
