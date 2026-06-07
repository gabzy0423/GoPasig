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
        Schema::create('safety_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')
                ->constrained('buses')
                ->onDelete('cascade');
            $table->foreignId('driver_id')
                ->constrained('drivers')
                ->onDelete('cascade');

            $table->boolean('oil_ok')->default(false);
            $table->boolean('brakes_ok')->default(false);
            $table->boolean('ac_ok')->default(false);
            $table->boolean('lights_ok')->default(false);
            $table->boolean('tires_ok')->default(false);

            $table->string('status'); // 'passed' or 'failed'
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('safety_inspections');
    }
};
