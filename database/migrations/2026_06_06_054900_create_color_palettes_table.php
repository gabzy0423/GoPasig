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
        Schema::create('color_palettes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'default', 'charts', 'routes'
            $table->string('hex_color'); // #003F87 format
            $table->integer('order')->default(1);
            $table->text('description')->nullable();
            $table->string('usage')->nullable(); // 'analytics', 'driver_performance', 'route', etc.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('color_palettes');
    }
};
