<?php

namespace Database\Factories;

use App\Models\ServiceAlert;
use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ServiceAlert>
 */
class ServiceAlertFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ServiceAlert::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $severities = ['low', 'medium', 'high', 'critical'];
        $statuses = ['active', 'resolved', 'archived'];
        $types = ['delay', 'breakdown', 'traffic', 'weather', 'maintenance', 'incident'];

        return [
            'route_id' => Route::factory(),
            'title' => $this->faker->sentence(),
            'message' => $this->faker->paragraph(),
            'severity' => $this->faker->randomElement($severities),
            'status' => $this->faker->randomElement($statuses),
            'type' => $this->faker->randomElement($types),
            'affected_routes' => json_encode(['Route 1', 'Route 2']),
            'estimated_resumption' => $this->faker->optional()->dateTimeBetween('now', '+1 day'),
        ];
    }

    /**
     * Indicate that the alert is active.
     */
    public function active(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'active',
            ];
        });
    }

    /**
     * Indicate that the alert is resolved.
     */
    public function resolved(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'resolved',
            ];
        });
    }

    /**
     * Indicate that the alert is critical.
     */
    public function critical(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'severity' => 'critical',
            ];
        });
    }
}
