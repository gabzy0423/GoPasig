<?php

namespace Database\Factories;

use App\Models\Incident;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncidentFactory extends Factory
{
    protected $model = Incident::class;

    public function definition(): array
    {
        return [
            'driver_id' => Driver::factory(),
            'trip_id' => Trip::factory(),
            'type' => $this->faker->randomElement(['Breakdown', 'Accident']),
            'description' => $this->faker->sentence(),
            'status' => 'reported',
            'reported_at' => $this->faker->dateTime(),
        ];
    }
}
