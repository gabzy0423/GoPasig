<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add min_buses_required to routes table.
     *
     * Replaces the hardcoded threshold of 2 in RouteStatusService::getFleetRouteHealth()
     * so that feeder/secondary routes that only need a single bus are not permanently
     * shown as "Low Coverage", reducing alert fatigue.
     *
     * Default is 2 to preserve existing behaviour for main-line routes.
     */
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->unsignedTinyInteger('min_buses_required')
                ->default(2)
                ->after('max_speed')
                ->comment('Minimum active buses needed before route shows Low Coverage status');
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn('min_buses_required');
        });
    }
};
