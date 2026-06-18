<?php

namespace Database\Factories;

use App\Models\Route;
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
}
