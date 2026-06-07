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
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');

            $table->string('first_name');
            $table->string('last_name');
            $table->string('emp_id')->unique();
            $table->string('license_number')->unique();
            $table->date('license_expiry');

            $table->enum('status', [
                'active',
                'inactive',
                'suspended'
            ])->default('active');

            $table->string('assigned_bus')->nullable();
            $table->string('assigned_route')->nullable();
            $table->integer('trips_today')->default(0);
            $table->integer('pax_today')->default(0);
            $table->string('address')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('emergency_contact')->nullable();
            $table->integer('performance_score')->default(100);
            $table->integer('incidents_30')->default(0);
            $table->json('trip_history')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('drivers');
    }
};
