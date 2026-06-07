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
        Schema::create('demand_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')
                ->constrained('routes')
                ->onDelete('cascade');

            $table->date('date');

            $table->string('time_slot');
            // example: "06:00-08:00"

            $table->string('day_of_week');
            // example: "Monday"

            $table->integer('total_commuters');
            // actual recorded commuters

            $table->integer('buses_dispatched');
            // number of buses assigned
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('demand_history');
    }
};
