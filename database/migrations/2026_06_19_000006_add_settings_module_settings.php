<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SystemSetting;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'schedule_default_departure_time',
                'value' => '08:00',
                'description' => 'Default departure time for route schedules',
            ],
            [
                'key' => 'route_min_capacity_default',
                'value' => '30',
                'description' => 'Default minimum capacity requirement for route bus allocation',
            ],
            [
                'key' => 'system_setting_cache_ttl_seconds',
                'value' => '30',
                'description' => 'System settings cache TTL duration in seconds',
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
            'schedule_default_departure_time',
            'route_min_capacity_default',
            'system_setting_cache_ttl_seconds',
        ])->delete();
    }
};
