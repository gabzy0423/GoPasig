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
        Schema::table('service_alerts', function (Blueprint $table) {
            $table->string('estimated_resumption')->nullable()->after('affected_routes');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->unsignedInteger('delay_minutes')->default(0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_alerts', function (Blueprint $table) {
            $table->dropColumn('estimated_resumption');
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->dropColumn('delay_minutes');
        });
    }
};
