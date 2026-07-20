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
            'plate_number' => $this->faker->unique()->numerify('PAS-####'),
            'fleet_number' => 'BUS-' . $this->faker->unique()->numberBetween(100, 99999),
            'vin' => $this->faker->unique()->regexify('^[A-HJ-NPR-Z0-9]{17}$'),
            'manufacturer' => 'BYD',
            'model' => 'K9',
            'year_model' => 2024,
            'battery_capacity_kwh' => 350.00,
            'charging_port_type' => 'CCS2',
            'max_charging_power_kw' => 150.00,
            'route_id' => null,
            'driver_name' => null,
            'capacity' => 45,
            'speed' => 0,
            'passengers' => 0,
            'next_stop' => null,
            'eta' => 0,
            'lat' => $this->faker->latitude(),
            'lng' => $this->faker->longitude(),
            'status' => 'available',
            'is_simulated' => false,
        ];
    }
}
