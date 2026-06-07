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
        Schema::create('dispatch_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('trip_id')
                ->constrained('trips')
                ->onDelete('cascade');

            $table->foreignId('dispatched_by')
                ->constrained('users')
                ->onDelete('cascade');

            $table->timestamp('dispatched_at')->useCurrent();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_logs');
    }
};
