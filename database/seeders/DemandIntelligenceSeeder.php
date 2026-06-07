<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Route;
use App\Models\Stop;
use App\Models\CommuterTrip;
use App\Models\DemandThreshold;
use App\Models\DemandHistory;
use Carbon\Carbon;

class DemandIntelligenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Seed Thresholds
        // Let's seed default thresholds for all routes for peak and off-peak slots.
        $routes = Route::all();
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $timeSlots = ['06:00-08:00', '08:00-10:00', '10:00-12:00', '12:00-14:00', '14:00-16:00', '16:00-18:00', '18:00-20:00', '20:00-22:00'];

        // Seed default thresholds
        // Route 1 SPED to City Hall - threshold 15
        // Route 2 SPED to Ligaya - threshold 20
        // Route 3 SPED to One San Miguel - threshold 12
        // Route 4 SPED to Nagpayong - threshold 25
        $thresholdCounts = [
            1 => 15,
            2 => 20,
            3 => 12,
            4 => 25,
        ];

        foreach ($routes as $route) {
            $baseThreshold = $thresholdCounts[$route->id] ?? 20;
            
            // Seed a default threshold for each day and time slot
            foreach ($days as $day) {
                foreach ($timeSlots as $slot) {
                    DemandThreshold::create([
                        'route_id' => $route->id,
                        'time_slot' => $slot,
                        'day_of_week' => $day,
                        'threshold_count' => $baseThreshold,
                    ]);
                }
            }
        }

        // 2. Seed Demand History (simulate 4 weeks of historical data)
        // We will seed passenger spikes matching the system patterns:
        // - Monday 7:00 AM - 9:00 AM (time slot "06:00-08:00" and "08:00-10:00") -> Route 2 expects 25 pax
        // - Friday 5:00 PM - 7:00 PM (time slot "16:00-18:00" and "18:00-20:00") -> Route 2 expects 35 pax
        // - Saturday 10:00 AM - 12:00 PM (time slot "10:00-12:00") -> Route 4 expects 20 pax
        $now = Carbon::now();
        
        for ($week = 1; $week <= 4; $week++) {
            $baseDate = $now->copy()->subWeeks($week);

            // Monday spikes
            $monday = $baseDate->copy()->startOfWeek(Carbon::MONDAY);
            // Route 2: 25 pax at 06:00-08:00
            DemandHistory::create([
                'route_id' => 2,
                'date' => $monday->toDateString(),
                'time_slot' => '06:00-08:00',
                'day_of_week' => 'Monday',
                'total_commuters' => 25,
                'buses_dispatched' => 2,
            ]);
            // Route 1: 18 pax at 08:00-10:00
            DemandHistory::create([
                'route_id' => 1,
                'date' => $monday->toDateString(),
                'time_slot' => '08:00-10:00',
                'day_of_week' => 'Monday',
                'total_commuters' => 18,
                'buses_dispatched' => 1,
            ]);

            // Friday spikes
            $friday = $baseDate->copy()->startOfWeek(Carbon::FRIDAY);
            // Route 2: 35 pax at 16:00-18:00
            DemandHistory::create([
                'route_id' => 2,
                'date' => $friday->toDateString(),
                'time_slot' => '16:00-18:00',
                'day_of_week' => 'Friday',
                'total_commuters' => 35,
                'buses_dispatched' => 3,
            ]);
            // Route 2: 30 pax at 18:00-20:00
            DemandHistory::create([
                'route_id' => 2,
                'date' => $friday->toDateString(),
                'time_slot' => '18:00-20:00',
                'day_of_week' => 'Friday',
                'total_commuters' => 30,
                'buses_dispatched' => 2,
            ]);

            // Saturday spikes
            $saturday = $baseDate->copy()->startOfWeek(Carbon::SATURDAY);
            // Route 4: 20 pax at 10:00-12:00
            DemandHistory::create([
                'route_id' => 4,
                'date' => $saturday->toDateString(),
                'time_slot' => '10:00-12:00',
                'day_of_week' => 'Saturday',
                'total_commuters' => 20,
                'buses_dispatched' => 1,
            ]);

            // Off-peak background noise
            foreach ($routes as $route) {
                DemandHistory::create([
                    'route_id' => $route->id,
                    'date' => $monday->toDateString(),
                    'time_slot' => '12:00-14:00',
                    'day_of_week' => 'Monday',
                    'total_commuters' => rand(2, 8),
                    'buses_dispatched' => 1,
                ]);
            }
        }

        // 3. Seed active waiting commuters (status = 'pending')
        // We will seed different numbers of pending commuter trips per route.
        // Route 1: 5 pax
        // Route 2: 12 pax
        // Route 3: 3 pax
        // Route 4: 8 pax
        $commuterDistribution = [
            1 => 5,
            2 => 12,
            3 => 3,
            4 => 8,
        ];

        foreach ($commuterDistribution as $routeId => $count) {
            $routeStops = Stop::where('route_id', $routeId)->orderBy('sequence')->get();
            if ($routeStops->count() < 2) {
                continue;
            }

            $origin = $routeStops->first();
            $destination = $routeStops->last();

            for ($i = 0; $i < $count; $i++) {
                CommuterTrip::create([
                    'origin_stop_id' => $origin->id,
                    'destination_stop_id' => $destination->id,
                    'route_id' => $routeId,
                    'status' => 'pending',
                    'timestamp' => now()->subMinutes(rand(2, 30)),
                ]);
            }
        }
    }
}
