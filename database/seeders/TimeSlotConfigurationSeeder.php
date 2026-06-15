<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TimeSlotConfiguration;

class TimeSlotConfigurationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $timeSlots = [
            ['name' => 'Hour 05-06', 'start_time' => '05:00:00', 'end_time' => '06:00:00', 'time_slot_display' => '05:00-06:00', 'order' => 1],
            ['name' => 'Hour 06-07', 'start_time' => '06:00:00', 'end_time' => '07:00:00', 'time_slot_display' => '06:00-07:00', 'order' => 2],
            ['name' => 'Hour 07-08', 'start_time' => '07:00:00', 'end_time' => '08:00:00', 'time_slot_display' => '07:00-08:00', 'order' => 3],
            ['name' => 'Hour 08-09', 'start_time' => '08:00:00', 'end_time' => '09:00:00', 'time_slot_display' => '08:00-09:00', 'order' => 4],
            ['name' => 'Hour 09-10', 'start_time' => '09:00:00', 'end_time' => '10:00:00', 'time_slot_display' => '09:00-10:00', 'order' => 5],
            ['name' => 'Hour 10-11', 'start_time' => '10:00:00', 'end_time' => '11:00:00', 'time_slot_display' => '10:00-11:00', 'order' => 6],
            ['name' => 'Hour 11-12', 'start_time' => '11:00:00', 'end_time' => '12:00:00', 'time_slot_display' => '11:00-12:00', 'order' => 7],
            ['name' => 'Hour 12-13', 'start_time' => '12:00:00', 'end_time' => '13:00:00', 'time_slot_display' => '12:00-13:00', 'order' => 8],
            ['name' => 'Hour 13-14', 'start_time' => '13:00:00', 'end_time' => '14:00:00', 'time_slot_display' => '13:00-14:00', 'order' => 9],
            ['name' => 'Hour 14-15', 'start_time' => '14:00:00', 'end_time' => '15:00:00', 'time_slot_display' => '14:00-15:00', 'order' => 10],
            ['name' => 'Hour 15-16', 'start_time' => '15:00:00', 'end_time' => '16:00:00', 'time_slot_display' => '15:00-16:00', 'order' => 11],
            ['name' => 'Hour 16-17', 'start_time' => '16:00:00', 'end_time' => '17:00:00', 'time_slot_display' => '16:00-17:00', 'order' => 12],
            ['name' => 'Hour 17-18', 'start_time' => '17:00:00', 'end_time' => '18:00:00', 'time_slot_display' => '17:00-18:00', 'order' => 13],
            ['name' => 'Hour 18-19', 'start_time' => '18:00:00', 'end_time' => '19:00:00', 'time_slot_display' => '18:00-19:00', 'order' => 14],
            ['name' => 'Hour 19-20', 'start_time' => '19:00:00', 'end_time' => '20:00:00', 'time_slot_display' => '19:00-20:00', 'order' => 15],
            ['name' => 'Hour 20-21', 'start_time' => '20:00:00', 'end_time' => '21:00:00', 'time_slot_display' => '20:00-21:00', 'order' => 16],
            ['name' => 'Hour 21-22', 'start_time' => '21:00:00', 'end_time' => '22:00:00', 'time_slot_display' => '21:00-22:00', 'order' => 17],
        ];

        foreach ($timeSlots as $slot) {
            TimeSlotConfiguration::updateOrCreate(
                ['time_slot_display' => $slot['time_slot_display']],
                $slot
            );
        }
    }
}
