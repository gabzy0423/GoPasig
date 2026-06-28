<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SystemSetting;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'service_alert_severity_options',
                'value' => 'Low,Medium,High,Emergency',
                'description' => 'Available options for service alert severity level, comma-separated',
            ],
            [
                'key' => 'service_alert_type_options',
                'value' => 'Delay,Route change,Suspension,Breakdown,Weather,Emergency',
                'description' => 'Available options for service alert types, comma-separated',
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
        SystemSetting::whereIn('key', ['service_alert_severity_options', 'service_alert_type_options'])->delete();
    }
};
