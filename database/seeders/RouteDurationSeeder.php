<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\RouteDuration;

class RouteDurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed default route durations (based sa dati na hardcoded values)
        // General durations (NULL day_of_week at time_slot)
        $defaultDurations = [
            1 => 25,  // Route 1 = 25 minutes
            2 => 45,  // Route 2 = 45 minutes
            3 => 35,  // Route 3 = 35 minutes
            4 => 40,  // Route 4 = 40 minutes
        ];

        foreach ($defaultDurations as $routeId => $minutes) {
            RouteDuration::updateOrCreate(
                [
                    'route_id' => $routeId,
                    'day_of_week' => null,
                    'time_slot' => null,
                ],
                [
                    'duration_minutes' => $minutes,
                    'notes' => 'Default duration para sa route ' . $routeId,
                ]
            );
        }

        // Optional: Pwedeng magdagdag ng specific durations para sa rush hours
        // Example:
        // RouteDuration::create([
        //     'route_id' => 1,
        //     'duration_minutes' => 35,  // Mas matagal sa rush hour
        //     'day_of_week' => 'Monday',
        //     'time_slot' => '06:00-08:00',
        //     'notes' => 'Morning rush hour duration'
        // ]);
    }
}

