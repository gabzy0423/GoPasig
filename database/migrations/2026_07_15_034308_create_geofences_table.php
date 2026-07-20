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
        Schema::create('geofences', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type'); // STOP, TERMINAL, DEPOT, GARAGE
            $table->json('geometry'); // coordinates: polygon coordinates [[lat, lng], ...] or circle center [lat, lng]
            $table->float('radius')->nullable(); // in meters (if geometry type is Point/Circle)
            $table->integer('priority')->default(100); // priority ranking for resolving overlapping fences
            
            // Centroid coordinates for fast bounding box filtering
            $table->decimal('lat', 10, 7)->index();
            $table->decimal('lng', 10, 7)->index();
            
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('geofences');
    }
};
