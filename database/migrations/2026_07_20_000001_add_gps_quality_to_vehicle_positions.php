<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_positions', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_positions', 'gps_quality_state')) {
                $table->string('gps_quality_state', 20)->default('UNKNOWN')->after('movement_negative_samples');
            }

            if (!Schema::hasColumn('vehicle_positions', 'gps_quality_reason')) {
                $table->string('gps_quality_reason', 120)->nullable()->after('gps_quality_state');
            }

            if (!Schema::hasColumn('vehicle_positions', 'gps_quality_updated_at')) {
                $table->timestamp('gps_quality_updated_at')->nullable()->after('gps_quality_reason');
            }

            if (!Schema::hasColumn('vehicle_positions', 'gps_fix_age_seconds')) {
                $table->unsignedInteger('gps_fix_age_seconds')->nullable()->after('gps_quality_updated_at');
            }

            if (!Schema::hasColumn('vehicle_positions', 'last_gps_fix_at')) {
                $table->timestamp('last_gps_fix_at')->nullable()->after('gps_fix_age_seconds');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_positions', function (Blueprint $table) {
            foreach ([
                'last_gps_fix_at',
                'gps_fix_age_seconds',
                'gps_quality_updated_at',
                'gps_quality_reason',
                'gps_quality_state',
            ] as $column) {
                if (Schema::hasColumn('vehicle_positions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
