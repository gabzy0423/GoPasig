<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\SystemSetting;

return new class extends Migration
{
    public function up(): void
    {
        $settings = [
            [
                'key' => 'maintenance_type_options',
                'value' => 'Preventive Maintenance,Corrective Maintenance',
                'description' => 'Available options for maintenance type dropdown, comma-separated',
            ],
            [
                'key' => 'maintenance_max_failed_inspections',
                'value' => '3',
                'description' => 'Maximum number of failed safety inspections allowed before a maintenance record is escalated',
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
        SystemSetting::whereIn('key', ['maintenance_type_options', 'maintenance_max_failed_inspections'])->delete();
    }
};
