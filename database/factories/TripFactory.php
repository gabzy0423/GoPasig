<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

class TripFactory extends Factory
{
    protected $model = Trip::class;

    public function definition(): array
    {
        return [
            'bus_id' => Bus::factory(),
            'driver_id' => Driver::factory(),
            'route_id' => Route::factory(),
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'peak_passengers' => $this->faker->numberBetween(0, 150),
            'started_at' => $this->faker->dateTime(),
            'ended_at' => null,
        ];
    }
}
