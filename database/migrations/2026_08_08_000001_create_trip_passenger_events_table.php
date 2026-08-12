<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_passenger_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->cascadeOnDelete();
            $table->foreignId('driver_id')->constrained('drivers')->cascadeOnDelete();
            $table->foreignId('bus_id')->constrained('buses')->cascadeOnDelete();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->string('event_type');
            $table->unsignedInteger('passenger_delta');
            $table->unsignedInteger('onboard_after');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['trip_id', 'event_type']);
            $table->index(['driver_id', 'recorded_at']);
            $table->index(['route_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_passenger_events');
    }
};
