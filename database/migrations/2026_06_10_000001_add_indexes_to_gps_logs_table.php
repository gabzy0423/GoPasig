<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add indexes to gps_logs to support time-windowed speed queries.
     *
     * The updateGPS endpoint is called every ~6 seconds per active bus, so
     * gps_logs accumulates rows rapidly. Without indexes, queries such as:
     *   WHERE speed > 5 AND created_at >= now() - INTERVAL 1 HOUR
     * result in full-table scans that become blocking as the table grows.
     *
     * Indexes added:
     *   - created_at  — supports the 1-hour lookback WHERE clause
     *   - speed       — supports the speed > 5 filter
     *   - (created_at, speed) composite — covers both columns in one index
     *                    for the combined WHERE predicate, avoiding a merge
     */
    public function up(): void
    {
        Schema::table('gps_logs', function (Blueprint $table) {
            // Composite index covering the exact query pattern used in updateGPS:
            // WHERE speed > 5 AND created_at >= ?
            $table->index(['created_at', 'speed'], 'gps_logs_created_at_speed_index');
        });
    }

    public function down(): void
    {
        Schema::table('gps_logs', function (Blueprint $table) {
            $table->dropIndex('gps_logs_created_at_speed_index');
        });
    }
};
