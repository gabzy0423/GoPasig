<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SystemSetting;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'analytics_fallback_peak_hour',
                'value' => '7–8 AM',
                'description' => 'Fallback peak hour display string when no ridership data is found',
            ],
            [
                'key' => 'analytics_top_stops_limit',
                'value' => '10',
                'description' => 'Maximum number of top stops to display in the stop boarding flow chart',
            ],
            [
                'key' => 'analytics_top_drivers_limit',
                'value' => '5',
                'description' => 'Maximum number of top drivers to display in the driver performance table',
            ],
            [
                'key' => 'analytics_historical_trend_limit',
                'value' => '30',
                'description' => 'Default range in days to show in the historical ridership trend chart when viewing a single date',
            ],
        ];

        foreach ($settings as $setting) {
            if (!SystemSetting::where('key', $setting['key'])->exists()) {
                SystemSetting::create($setting);
            }
        }
    }

    public function down(): void
    {
        SystemSetting::whereIn('key', [
            'analytics_fallback_peak_hour',
            'analytics_top_stops_limit',
            'analytics_top_drivers_limit',
            'analytics_historical_trend_limit'
        ])->delete();
    }
};
