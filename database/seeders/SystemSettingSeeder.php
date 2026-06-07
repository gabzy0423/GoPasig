<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'delay_threshold', 'value' => '10'],
            ['key' => 'occupancy_warning_threshold', 'value' => '50'],
            ['key' => 'occupancy_critical_threshold', 'value' => '85'],
            ['key' => 'gps_sync_interval_ms', 'value' => '6000'],
            ['key' => 'speed_simulation_interval_ms', 'value' => '1500'],
            ['key' => 'sim_speed_min', 'value' => '18'],
            ['key' => 'sim_speed_max', 'value' => '43'],
            ['key' => 'speed_fast_threshold', 'value' => '30'],
        ];

        foreach ($settings as $setting) {
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}