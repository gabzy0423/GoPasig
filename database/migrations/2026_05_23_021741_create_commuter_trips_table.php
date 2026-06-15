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
        Schema::create('commuter_trips', function (Blueprint $table) {
            $table->id();
            $table->string('session_token');
            $table->foreignId('origin_stop_id')
                ->constrained('stops')
                ->onDelete('cascade');

            $table->foreignId('destination_stop_id')
                ->constrained('stops')
                ->onDelete('cascade');

            $table->foreignId('route_id')
                ->constrained('routes')
                ->onDelete('cascade');

            $table->enum('status', [
                'WAITING',
                'ON_BUS',
                'ARRIVED',
                'CANCELLED'
            ])->default('WAITING');

            $table->timestamp('boarded_at')->nullable();

            $table->timestamp('arrived_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commuter_trips');
    }
};
