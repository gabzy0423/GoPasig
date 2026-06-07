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
        Schema::create('route_durations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('route_id');
            $table->integer('duration_minutes'); // Travel time sa minutes
            $table->string('day_of_week')->nullable(); // 'Monday', 'Tuesday', etc. - NULL = lahat ng araw
            $table->string('time_slot')->nullable(); // '06:00-08:00' format - NULL = lahat ng time
            $table->text('notes')->nullable();
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
        Schema::dropIfExists('route_durations');
    }
};
