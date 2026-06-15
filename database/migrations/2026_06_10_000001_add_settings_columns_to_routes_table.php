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
             if (!Schema::hasColumn('routes', 'delay_threshold_minutes')) {
                 $table->integer('delay_threshold_minutes')->nullable()->default(10)->after('travel_time_minutes');
             }
             if (!Schema::hasColumn('routes', 'min_speed')) {
                 $table->integer('min_speed')->nullable()->default(18)->after('delay_threshold_minutes');
             }
             if (!Schema::hasColumn('routes', 'max_speed')) {
                 $table->integer('max_speed')->nullable()->default(43)->after('min_speed');
             }
         });
     }

     /**
      * Reverse the migrations.
      */
     public function down(): void
     {
         Schema::table('routes', function (Blueprint $table) {
             $table->dropColumn(['delay_threshold_minutes', 'min_speed', 'max_speed']);
         });
     }
};
