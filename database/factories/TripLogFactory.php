<?php

namespace Database\Factories;

use App\Models\TripLog;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\Bus;
use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripLogFactory extends Factory
{
    protected $model = TripLog::class;

    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'trip_id' => Trip::factory(),
            'bus_id' => Bus::factory(),
            'route_id' => Route::factory(),
            'started_at' => $this->faker->dateTimeThisMonth(),
            'completed_at' => $this->faker->dateTimeThisMonth(),
            'passengers' => $this->faker->numberBetween(10, 60),
            'peak_passengers' => $this->faker->numberBetween(20, 70),
            'status' => 'completed',
            'is_on_time' => $this->faker->boolean(80),
            'delay_minutes' => fn(array $attributes) => $attributes['is_on_time'] ? 0 : $this->faker->numberBetween(1, 30),
            'notes' => $this->faker->optional()->sentence(),
        ];
    }
}
