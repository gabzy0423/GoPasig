<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            if (!Schema::hasColumn('routes', 'target_on_time_rate')) {
                $table->integer('target_on_time_rate')->nullable()->default(85)->after('max_speed');
            }
            if (!Schema::hasColumn('routes', 'target_headway_minutes')) {
                $table->integer('target_headway_minutes')->nullable()->default(15)->after('target_on_time_rate');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn(['target_on_time_rate', 'target_headway_minutes']);
        });
    }
};
