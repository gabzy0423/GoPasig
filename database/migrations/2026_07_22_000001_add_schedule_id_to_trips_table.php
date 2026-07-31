<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('schedule_id')
                ->nullable()
                ->after('route_variant_id')
                ->constrained('schedules')
                ->nullOnDelete();

            $table->unique('schedule_id', 'trips_schedule_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropUnique('trips_schedule_id_unique');
            $table->dropConstrainedForeignId('schedule_id');
        });
    }
};
