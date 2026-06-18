<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Issue 3.1.3: Remove denormalized trip_history JSON, use TripLog table instead
        Schema::table('drivers', function (Blueprint $table) {
            if (Schema::hasColumn('drivers', 'trip_history')) {
                $table->dropColumn('trip_history');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->json('trip_history')->nullable()->after('incidents_30');
        });
    }
};
