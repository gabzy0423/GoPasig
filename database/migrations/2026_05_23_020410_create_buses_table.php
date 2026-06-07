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
        Schema::create('buses', function (Blueprint $table) {
            $table->id();
            $table->string('plate_number')->unique();
            $table->foreignId('route_id')->nullable()->constrained('routes')->onDelete('set null');
            $table->string('driver_name')->nullable();
            $table->integer('capacity')->default(45);
            $table->integer('speed')->default(0);
            $table->integer('passengers')->default(0);
            $table->string('next_stop')->nullable();
            $table->integer('eta')->default(0);
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();

            $table->enum('status', [
                'active',
                'inactive',
                'maintenance'
            ])->default('active');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buses');
    }
};
