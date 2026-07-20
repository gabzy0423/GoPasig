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
        Schema::create('route_geometry_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->json('polyline_coordinates');
            $table->unsignedInteger('vertex_count')->default(0);
            $table->decimal('length_km', 8, 4)->default(0);
            $table->string('label', 100)->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('restored_from_version')->nullable();
            $table->timestamps();

            // Indexes for pagination and lookups
            $table->index(['route_id', 'created_at']);
            $table->index(['route_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('route_geometry_versions');
    }
};
