<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'bus_id' => Bus::factory(),
            'driver_id' => Driver::factory(),
            'departure_time' => $this->faker->time('H:i'),
            'arrival_time' => $this->faker->time('H:i'),
            'passengers' => $this->faker->numberBetween(0, 100),
            'status' => 'On time',
        ];
    }
}
