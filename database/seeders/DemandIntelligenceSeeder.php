<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Route;
use App\Models\DemandThreshold;

class DemandIntelligenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Seed Dispatch Intelligence thresholds only for active official public routes.
        $routes = Route::publicCommuterActiveService()->get();
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $timeSlots = ['06:00-08:00', '08:00-10:00', '10:00-12:00', '12:00-14:00', '14:00-16:00', '16:00-18:00', '18:00-20:00', '20:00-22:00'];

        $thresholdCounts = [
            'Route 2' => 20,
            'Route 3' => 12,
            'Route 4' => 25,
        ];

        foreach ($routes as $route) {
            $baseThreshold = $thresholdCounts[$route->name] ?? 20;
            
            foreach ($days as $day) {
                foreach ($timeSlots as $slot) {
                    DemandThreshold::updateOrCreate(
                        [
                            'route_id' => $route->id,
                            'time_slot' => $slot,
                            'day_of_week' => $day,
                        ],
                        [
                            'threshold_count' => $baseThreshold,
                        ]
                    );
                }
            }
        }
    }
}
