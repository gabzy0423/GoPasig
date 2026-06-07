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
        Schema::create('dispatch_simulator_counts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->integer('manual_count')->default(0);
            $table->string('day_of_week')->nullable();
            $table->string('time_slot')->nullable();
            $table->timestamps();

            $table->foreign('route_id')->references('id')->on('routes')->onDelete('cascade');
            $table->unique(['route_id', 'day_of_week', 'time_slot']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_simulator_counts');
    }
};
