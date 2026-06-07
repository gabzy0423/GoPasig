<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
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
            [
                'name' => 'Morning Rush',
                'start_time' => '06:00:00',
                'end_time' => '08:00:00',
                'time_slot_display' => '06:00-08:00',
                'order' => 1,
            ],
            [
                'name' => 'Morning Peak',
                'start_time' => '08:00:00',
                'end_time' => '12:00:00',
                'time_slot_display' => '08:00-10:00',
                'order' => 2,
            ],
            [
                'name' => 'Midday',
                'start_time' => '12:00:00',
                'end_time' => '16:00:00',
                'time_slot_display' => '12:00-14:00',
                'order' => 3,
            ],
            [
                'name' => 'Evening Rush',
                'start_time' => '16:00:00',
                'end_time' => '18:00:00',
                'time_slot_display' => '16:00-18:00',
                'order' => 4,
            ],
            [
                'name' => 'Evening / Night',
                'start_time' => '18:00:00',
                'end_time' => '23:59:00',
                'time_slot_display' => '18:00-20:00',
                'order' => 5,
            ],
        ];

        foreach ($timeSlots as $slot) {
            TimeSlotConfiguration::updateOrCreate(
                ['time_slot_display' => $slot['time_slot_display']],
                $slot
            );
        }
    }
}

