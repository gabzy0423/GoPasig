<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Seed the two driver performance scoring penalty settings.
     *
     * These values are read at runtime by DriverPerformanceService::calculateScore().
     * They can be updated via the Admin → Settings panel without a code deploy.
     */
    public function up(): void
    {
        $settings = [
            [
                'key'         => 'driver_score_incident_penalty',
                'value'       => '10',
                'description' => 'Points deducted from a driver performance score per logged incident (default: 10)',
            ],
            [
                'key'         => 'driver_score_delay_penalty',
                'value'       => '5',
                'description' => 'Points deducted from a driver performance score per delayed schedule (default: 5)',
            ],
        ];

        foreach ($settings as $setting) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $setting['key']],
                [
                    'value'       => $setting['value'],
                    'description' => $setting['description'],
                    'updated_at'  => now(),
                    'created_at'  => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('system_settings')->whereIn('key', [
            'driver_score_incident_penalty',
            'driver_score_delay_penalty',
        ])->delete();
    }
};
