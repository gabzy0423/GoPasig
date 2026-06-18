<?php

namespace Database\Factories;

use App\Models\MaintenanceRecord;
use App\Models\Bus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Carbon\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaintenanceRecord>
 */
class MaintenanceRecordFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = MaintenanceRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['oil_change', 'tire_replacement', 'brake_service', 'engine_checkup', 'general_maintenance'];
        $statuses = ['scheduled', 'in_progress', 'completed', 'cancelled'];

        return [
            'bus_id' => Bus::factory(),
            'type' => $this->faker->randomElement($types),
            'description' => $this->faker->sentence(),
            'scheduled_at' => $this->faker->dateTimeBetween('-30 days', '+30 days'),
            'status' => $this->faker->randomElement($statuses),
            'workflow_status' => 'pending',
            'expected_duration_minutes' => $this->faker->numberBetween(30, 480),
            'technician_name' => $this->faker->name(),
            'cost_php' => $this->faker->randomFloat(2, 500, 50000),
            'completed_at' => $this->faker->optional()->dateTime(),
            'technician_notes' => $this->faker->optional()->sentence(),
            'actual_duration_minutes' => $this->faker->optional()->numberBetween(30, 480),
            'inspection_passed' => $this->faker->optional()->boolean(),
            'inspection_notes' => $this->faker->optional()->sentence(),
            'inspected_by' => $this->faker->optional()->name(),
            'inspected_at' => $this->faker->optional()->dateTime(),
        ];
    }

    /**
     * Indicate that the maintenance is completed.
     */
    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'completed',
                'completed_at' => now(),
                'inspection_passed' => true,
                'inspected_at' => now(),
            ];
        });
    }

    /**
     * Indicate that the maintenance is scheduled.
     */
    public function scheduled(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'scheduled',
                'scheduled_at' => Carbon::now()->addDays($this->faker->numberBetween(1, 14)),
            ];
        });
    }

    /**
     * Indicate that the maintenance is in progress.
     */
    public function inProgress(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'in_progress',
            ];
        });
    }
}
