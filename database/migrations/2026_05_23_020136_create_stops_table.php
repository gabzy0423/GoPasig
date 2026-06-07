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
        Schema::create('stops', function (Blueprint $table) {
            $table->id();

            $table->foreignId('route_id')
                ->constrained('routes')
                ->onDelete('cascade');

            $table->string('name');

            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);

            $table->integer('radius_meters')->default(100);

            $table->integer('sequence');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stops');
    }
};
