<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $existing = \App\Models\SystemSetting::pluck('key')->toArray();

        $settings = [
            [
                'key' => 'maintenance_duration_min_minutes',
                'value' => '15',
                'description' => 'Minimum maintenance duration in minutes',
            ],
            [
                'key' => 'maintenance_duration_max_minutes',
                'value' => '480',
                'description' => 'Maximum maintenance duration in minutes (8 hours)',
            ],
        ];

        foreach ($settings as $setting) {
            if (!in_array($setting['key'], $existing)) {
                \App\Models\SystemSetting::create($setting);
            }
        }
    }

    public function down(): void
    {
        // Don't delete on rollback to preserve production data
    }
};
