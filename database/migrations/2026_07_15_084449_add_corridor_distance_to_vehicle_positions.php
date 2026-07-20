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
        Schema::table('vehicle_positions', function (Blueprint $table) {
            $table->float('corridor_distance')->default(0.0)->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_positions', function (Blueprint $table) {
            $table->dropColumn('corridor_distance');
        });
    }
};
