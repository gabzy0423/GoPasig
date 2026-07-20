<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commuter_trips', function (Blueprint $table) {
            $table->index('is_simulated', 'idx_commuter_trips_is_simulated');
            $table->index('status', 'idx_commuter_trips_status');
        });

        Schema::table('buses', function (Blueprint $table) {
            $table->index('status', 'idx_buses_status');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->index('status', 'idx_schedules_status');
            $table->index('service_date', 'idx_schedules_service_date');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->index('status', 'idx_trips_status');
            $table->index(['driver_id', 'status'], 'idx_trips_driver_status');
            $table->index(['bus_id', 'status'], 'idx_trips_bus_status');
        });

        Schema::table('gps_logs', function (Blueprint $table) {
            // Note: gps_logs table links to trips via trip_id. There is no bus_id column.
            // Composite index on [trip_id, created_at] covers the latest-position checks.
            $table->index(['trip_id', 'created_at'], 'idx_gps_logs_trip_created');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commuter_trips', function (Blueprint $table) {
            $table->dropIndex('idx_commuter_trips_is_simulated');
            $table->dropIndex('idx_commuter_trips_status');
        });

        Schema::table('buses', function (Blueprint $table) {
            $table->dropIndex('idx_buses_status');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex('idx_schedules_status');
            $table->dropIndex('idx_schedules_service_date');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex('idx_trips_status');
            $table->dropIndex('idx_trips_driver_status');
            $table->dropIndex('idx_trips_bus_status');
        });

        Schema::table('gps_logs', function (Blueprint $table) {
            $table->dropIndex('idx_gps_logs_trip_created');
        });
    }
};
