<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            if (Schema::hasTable('gps_logs') && Schema::hasColumn('gps_logs', 'heading')) {
                DB::statement('ALTER TABLE gps_logs MODIFY heading FLOAT NULL');
            }

            if (Schema::hasTable('vehicle_positions') && Schema::hasColumn('vehicle_positions', 'heading')) {
                DB::statement('ALTER TABLE vehicle_positions MODIFY heading FLOAT NULL');
            }

            if (Schema::hasTable('buses') && Schema::hasColumn('buses', 'speed')) {
                DB::statement('ALTER TABLE buses MODIFY speed FLOAT NOT NULL DEFAULT 0');
            }
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            if (Schema::hasTable('gps_logs') && Schema::hasColumn('gps_logs', 'heading')) {
                DB::statement('ALTER TABLE gps_logs MODIFY heading FLOAT NOT NULL DEFAULT 0');
            }

            if (Schema::hasTable('vehicle_positions') && Schema::hasColumn('vehicle_positions', 'heading')) {
                DB::statement('ALTER TABLE vehicle_positions MODIFY heading FLOAT NOT NULL DEFAULT 0');
            }

            if (Schema::hasTable('buses') && Schema::hasColumn('buses', 'speed')) {
                DB::statement('ALTER TABLE buses MODIFY speed INT NOT NULL DEFAULT 0');
            }
        }
    }
};
