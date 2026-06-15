<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Create the dedicated terminals table.
     *
     * Terminals are named physical endpoints (e.g. "SPED Terminal (Caruncho Ave.)")
     * that act as the system-wide fallback when route/stop data is absent.
     * Previously these names were hardcoded as string literals in PHP classes;
     * this table makes them fully database-driven and rename-safe.
     */
    public function up(): void
    {
        Schema::create('terminals', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('description')->nullable();
            // Marks the single default terminal used as the system-wide fallback.
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('terminals');
    }
};
