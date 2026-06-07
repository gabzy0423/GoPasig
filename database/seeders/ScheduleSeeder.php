<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Schedule;
use App\Models\Route;
use App\Models\Bus;
use App\Models\Driver;

class ScheduleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Schedule::query()->delete();

        // Fetch active drivers
        $driverJD = Driver::where('first_name', 'Juan')->where('last_name', 'dela Cruz')->first();
        $driverMS = Driver::where('first_name', 'Maria')->where('last_name', 'Santos')->first();
        $driverAF = Driver::where('first_name', 'Ana')->where('last_name', 'Flores')->first();
        $driverCB = Driver::where('first_name', 'Carlos')->where('last_name', 'Bautista')->first();
        $driverRS = Driver::where('first_name', 'Roberto')->where('last_name', 'Santos')->first();

        // Fallbacks
        $activeDrivers = Driver::where('status', 'active')->get();
        $getDriver = function ($preferred) use ($activeDrivers) {
            return $preferred ?: ($activeDrivers->isNotEmpty() ? $activeDrivers->random() : Driver::first());
        };

        // Fetch buses
        $busesRoute1 = Bus::where('route_id', 1)->get();
        $busesRoute2 = Bus::where('route_id', 2)->get();
        $busesRoute3 = Bus::where('route_id', 3)->get();
        $busesRoute4 = Bus::where('route_id', 4)->get();

        $getBus = function ($routeId, $index = 0) use ($busesRoute1, $busesRoute2, $busesRoute3, $busesRoute4) {
            $list = match ($routeId) {
                1 => $busesRoute1,
                2 => $busesRoute2,
                3 => $busesRoute3,
                4 => $busesRoute4,
                default => collect()
            };
            if ($list->isEmpty()) {
                return Bus::where('route_id', $routeId)->first() ?: Bus::first();
            }
            return $list[$index % $list->count()];
        };

        // Data matching requirements:
        // FIRST TRIP Outbound 05:30AM for all, Inbound 06:00AM (R1) / 06:30AM (R2/3/4)
        // LAST TRIP Outbound 04:00PM (R1) / 05:30PM (R2/3/4), Inbound 05:00PM (R1) / 06:30PM (R2/3/4)
        
        $schedules = [
            ['route_id' => 1, 'departure_time' => '05:30', 'arrival_time' => '05:55', 'driver' => $getDriver($driverJD), 'bus' => $getBus(1, 0), 'pax' => 38, 'status' => 'On time'],
            ['route_id' => 1, 'departure_time' => '06:00', 'arrival_time' => '06:25', 'driver' => $getDriver($driverCB), 'bus' => $getBus(1, 1), 'pax' => 42, 'status' => 'On time'],
            ['route_id' => 1, 'departure_time' => '07:00', 'arrival_time' => '07:25', 'driver' => $getDriver($driverJD), 'bus' => $getBus(1, 0), 'pax' => 45, 'status' => 'On time'],
            ['route_id' => 1, 'departure_time' => '08:30', 'arrival_time' => '08:55', 'driver' => $getDriver($driverCB), 'bus' => $getBus(1, 1), 'pax' => 32, 'status' => 'Delayed', 'delay_minutes' => 15],
            ['route_id' => 1, 'departure_time' => '10:00', 'arrival_time' => '10:25', 'driver' => $getDriver($driverJD), 'bus' => $getBus(1, 0), 'pax' => 28, 'status' => 'On time'],
            ['route_id' => 1, 'departure_time' => '12:00', 'arrival_time' => '12:25', 'driver' => $getDriver($driverRS), 'bus' => $getBus(1, 1), 'pax' => 35, 'status' => 'On time'],
            ['route_id' => 1, 'departure_time' => '14:30', 'arrival_time' => '14:55', 'driver' => $getDriver($driverRS), 'bus' => $getBus(1, 1), 'pax' => 29, 'status' => 'On time'],
            ['route_id' => 1, 'departure_time' => '16:00', 'arrival_time' => '16:25', 'driver' => $getDriver($driverJD), 'bus' => $getBus(1, 0), 'pax' => 41, 'status' => 'On time'], // Outbound last
            ['route_id' => 1, 'departure_time' => '17:00', 'arrival_time' => '17:25', 'driver' => $getDriver($driverRS), 'bus' => $getBus(1, 1), 'pax' => 45, 'status' => 'On time'], // Inbound last
 
            // Route 2 (Outbound / Inbound)
            ['route_id' => 2, 'departure_time' => '05:30', 'arrival_time' => '06:15', 'driver' => $getDriver($driverMS), 'bus' => $getBus(2, 0), 'pax' => 29, 'status' => 'On time'],
            ['route_id' => 2, 'departure_time' => '06:30', 'arrival_time' => '07:15', 'driver' => $getDriver($driverAF), 'bus' => $getBus(2, 1), 'pax' => 34, 'status' => 'On time'],
            ['route_id' => 2, 'departure_time' => '08:30', 'arrival_time' => '09:15', 'driver' => $getDriver($driverMS), 'bus' => $getBus(2, 0), 'pax' => 39, 'status' => 'On time'],
            ['route_id' => 2, 'departure_time' => '12:00', 'arrival_time' => '12:45', 'driver' => $getDriver($driverAF), 'bus' => $getBus(2, 1), 'pax' => 43, 'status' => 'On time'],
            ['route_id' => 2, 'departure_time' => '15:30', 'arrival_time' => '16:15', 'driver' => $getDriver($driverMS), 'bus' => $getBus(2, 0), 'pax' => 43, 'status' => 'Delayed', 'delay_minutes' => 20],
            ['route_id' => 2, 'departure_time' => '17:30', 'arrival_time' => '18:15', 'driver' => $getDriver($driverMS), 'bus' => $getBus(2, 0), 'pax' => 38, 'status' => 'On time'], // Outbound last
            ['route_id' => 2, 'departure_time' => '18:30', 'arrival_time' => '19:15', 'driver' => $getDriver($driverAF), 'bus' => $getBus(2, 1), 'pax' => 42, 'status' => 'On time'], // Inbound last
 
            // Route 3 (Outbound / Inbound)
            ['route_id' => 3, 'departure_time' => '05:30', 'arrival_time' => '06:05', 'driver' => $getDriver($driverRS), 'bus' => $getBus(3, 0), 'pax' => 22, 'status' => 'On time'],
            ['route_id' => 3, 'departure_time' => '06:30', 'arrival_time' => '07:05', 'driver' => $getDriver($driverRS), 'bus' => $getBus(3, 0), 'pax' => 31, 'status' => 'On time'],
            ['route_id' => 3, 'departure_time' => '08:30', 'arrival_time' => '09:05', 'driver' => $getDriver($driverRS), 'bus' => $getBus(3, 0), 'pax' => 41, 'status' => 'On time'],
            ['route_id' => 3, 'departure_time' => '12:00', 'arrival_time' => '12:35', 'driver' => $getDriver($driverRS), 'bus' => $getBus(3, 0), 'pax' => 36, 'status' => 'On time'],
            ['route_id' => 3, 'departure_time' => '15:30', 'arrival_time' => '16:05', 'driver' => $getDriver($driverRS), 'bus' => $getBus(3, 0), 'pax' => 39, 'status' => 'On time'],
            ['route_id' => 3, 'departure_time' => '17:30', 'arrival_time' => '18:05', 'driver' => $getDriver($driverRS), 'bus' => $getBus(3, 0), 'pax' => 44, 'status' => 'On time'], // Outbound last
            ['route_id' => 3, 'departure_time' => '18:30', 'arrival_time' => '19:05', 'driver' => $getDriver($driverRS), 'bus' => $getBus(3, 0), 'pax' => 40, 'status' => 'On time'], // Inbound last
 
            // Route 4 (Outbound / Inbound)
            ['route_id' => 4, 'departure_time' => '05:30', 'arrival_time' => '06:10', 'driver' => $getDriver($driverAF), 'bus' => $getBus(4, 0), 'pax' => 44, 'status' => 'On time'],
            ['route_id' => 4, 'departure_time' => '06:30', 'arrival_time' => '07:10', 'driver' => $getDriver($driverAF), 'bus' => $getBus(4, 0), 'pax' => 38, 'status' => 'On time'],
            ['route_id' => 4, 'departure_time' => '08:00', 'arrival_time' => '08:40', 'driver' => $getDriver($driverAF), 'bus' => $getBus(4, 0), 'pax' => 39, 'status' => 'On time'],
            ['route_id' => 4, 'departure_time' => '11:30', 'arrival_time' => '12:10', 'driver' => $getDriver($driverAF), 'bus' => $getBus(4, 0), 'pax' => 42, 'status' => 'On time'],
            ['route_id' => 4, 'departure_time' => '14:00', 'arrival_time' => '14:40', 'driver' => $getDriver($driverAF), 'bus' => $getBus(4, 0), 'pax' => 40, 'status' => 'On time'],
            ['route_id' => 4, 'departure_time' => '17:30', 'arrival_time' => '18:10', 'driver' => $getDriver($driverAF), 'bus' => $getBus(4, 0), 'pax' => 45, 'status' => 'On time'], // Outbound last
            ['route_id' => 4, 'departure_time' => '18:30', 'arrival_time' => '19:10', 'driver' => $getDriver($driverAF), 'bus' => $getBus(4, 0), 'pax' => 41, 'status' => 'On time'], // Inbound last
        ];
 
        foreach ($schedules as $item) {
            Schedule::create([
                'route_id' => $item['route_id'],
                'departure_time' => $item['departure_time'],
                'arrival_time' => $item['arrival_time'],
                'bus_id' => $item['bus']->id,
                'driver_id' => $item['driver']->id,
                'passengers' => $item['pax'],
                'status' => $item['status'],
                'delay_minutes' => $item['delay_minutes'] ?? 0,
            ]);
        }
    }
}
