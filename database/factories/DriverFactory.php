<?php

namespace Database\Factories;

use App\Models\Driver;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class DriverFactory extends Factory
{
    protected $model = Driver::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'emp_id' => 'EMP' . $this->faker->unique()->numerify('######'),
            'license_number' => $this->faker->unique()->numerify('LIC######'),
            'license_expiry' => $this->faker->dateTimeBetween('+1 day', '+1 year'),
            'status' => 'active',
            'operational_status' => 'available',
            'assigned_bus' => null,
            'assigned_route' => null,
            'trips_today' => 0,
            'pax_today' => 0,
            'address' => $this->faker->address(),
            'contact_number' => $this->faker->unique()->phoneNumber(),
            'emergency_contact' => $this->faker->unique()->phoneNumber(),
            'performance_score' => $this->faker->numberBetween(70, 100),
            'incidents_30' => $this->faker->numberBetween(0, 5),
        ];
    }
}
