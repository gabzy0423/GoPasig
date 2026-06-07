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
        Schema::create('demand_thresholds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')
                ->constrained('routes')
                ->onDelete('cascade');

            $table->string('time_slot');
            // example: "06:00-08:00", "08:00-10:00"

            $table->string('day_of_week');
            // example: "Monday", "Tuesday"

            $table->integer('threshold_count');
            // expected passenger/load threshold
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_thresholds');
    }
};
