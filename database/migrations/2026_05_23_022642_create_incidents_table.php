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
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trip_id')
                ->constrained('trips')
                ->onDelete('cascade');

            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->onDelete('cascade');

            $table->string('type');
            // example: "Breakdown", "Accident", "Delay", "Route Issue"

            $table->text('description');

            $table->enum('status', [
                'reported',
                'under_review',
                'resolved'
            ])->default('reported');

            $table->timestamp('reported_at')->useCurrent();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};
