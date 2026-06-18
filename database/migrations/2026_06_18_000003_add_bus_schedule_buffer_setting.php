<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')->updateOrInsert(
            ['key' => 'bus_schedule_buffer_minutes'],
            [
                'value' => '15',
                'description' => 'Bus turnaround buffer in minutes between schedules to prevent immediate reuse (default: 15)',
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'bus_schedule_buffer_minutes')
            ->delete();
    }
};
