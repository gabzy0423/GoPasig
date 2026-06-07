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
        Schema::create('trips', function (Blueprint $table) {
            $table->id();

            $table->foreignId('bus_id')
                ->constrained('buses')
                ->onDelete('cascade');

            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->onDelete('cascade');

            $table->foreignId('route_id')
                ->constrained('routes')
                ->onDelete('cascade');

            $table->enum('status', [
                'pending',
                'ongoing',
                'completed',
                'cancelled'
            ])->default('pending');

            $table->timestamp('started_at')->nullable();

            $table->timestamp('ended_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
