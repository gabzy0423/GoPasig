<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('route_corridors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->unique()->constrained('routes')->onDelete('cascade');
            $table->float('buffer_width')->default(20.0); // meters
            $table->string('source_type')->default('AUTO_BUFFER'); // AUTO_BUFFER, CUSTOM_POLYGON
            $table->string('measurement_method')->default('NEAREST_SEGMENT'); // NEAREST_SEGMENT, PERPENDICULAR
            $table->json('geometry')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_corridors');
    }
};
