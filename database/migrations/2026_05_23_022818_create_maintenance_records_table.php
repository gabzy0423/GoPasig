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
        Schema::create('maintenance_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')
                ->constrained('buses')
                ->onDelete('cascade');

            $table->string('type');
            // example: "Engine Check", "Oil Change", "Tire Replacement"

            $table->text('description')->nullable();

            $table->timestamp('scheduled_at');

            $table->enum('status', [
                'scheduled',
                'in_progress',
                'completed',
                'cancelled'
            ])->default('scheduled');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_records');
    }
};
