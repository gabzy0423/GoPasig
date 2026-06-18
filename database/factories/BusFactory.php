<?php

namespace Database\Factories;

use App\Models\Bus;
use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

class BusFactory extends Factory
{
    protected $model = Bus::class;

    public function definition(): array
    {
        return [
            'plate_number' => 'PAS-' . $this->faker->numerify('###'),
            'route_id' => null,
            'driver_name' => null,
            'capacity' => 45,
            'speed' => 0,
            'passengers' => 0,
            'next_stop' => null,
            'eta' => 0,
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'status' => 'inactive',
            'is_simulated' => false,
        ];
    }
}
