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
        Schema::create('time_slot_configurations', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g., "Morning Rush"
            $table->time('start_time'); // e.g., 06:00
            $table->time('end_time'); // e.g., 08:00
            $table->string('time_slot_display'); // e.g., "06:00-08:00"
            $table->integer('order')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_slot_configurations');
    }
};
