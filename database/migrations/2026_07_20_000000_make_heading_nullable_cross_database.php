<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
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

            return;
        }

        if (Schema::hasTable('gps_logs') && Schema::hasColumn('gps_logs', 'heading')) {
            Schema::table('gps_logs', function (Blueprint $table) {
                $table->float('heading')->nullable()->default(null)->change();
            });
        }

        if (Schema::hasTable('vehicle_positions') && Schema::hasColumn('vehicle_positions', 'heading')) {
            Schema::table('vehicle_positions', function (Blueprint $table) {
                $table->float('heading')->nullable()->default(null)->change();
            });
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

            return;
        }

        if (Schema::hasTable('gps_logs') && Schema::hasColumn('gps_logs', 'heading')) {
            DB::table('gps_logs')->whereNull('heading')->update(['heading' => 0]);
            Schema::table('gps_logs', function (Blueprint $table) {
                $table->float('heading')->default(0.0)->nullable(false)->change();
            });
        }

        if (Schema::hasTable('vehicle_positions') && Schema::hasColumn('vehicle_positions', 'heading')) {
            DB::table('vehicle_positions')->whereNull('heading')->update(['heading' => 0]);
            Schema::table('vehicle_positions', function (Blueprint $table) {
                $table->float('heading')->default(0.0)->nullable(false)->change();
            });
        }
    }
};
