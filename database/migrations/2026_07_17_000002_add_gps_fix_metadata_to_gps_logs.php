<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('gps_logs', 'gps_fix_timestamp')) {
                $table->timestamp('gps_fix_timestamp')->nullable()->after('received_at');
            }

            if (!Schema::hasColumn('gps_logs', 'gps_fix_age_ms')) {
                $table->unsignedInteger('gps_fix_age_ms')->nullable()->after('gps_fix_timestamp');
            }

            if (!Schema::hasColumn('gps_logs', 'is_cached_fix')) {
                $table->boolean('is_cached_fix')->default(false)->after('gps_fix_age_ms');
            }

            if (!Schema::hasColumn('gps_logs', 'speed_source')) {
                $table->string('speed_source', 20)->nullable()->after('is_cached_fix');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gps_logs', function (Blueprint $table) {
            $columns = [];

            foreach (['speed_source', 'is_cached_fix', 'gps_fix_age_ms', 'gps_fix_timestamp'] as $column) {
                if (Schema::hasColumn('gps_logs', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
