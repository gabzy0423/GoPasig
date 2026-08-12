<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_variant_corridors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_variant_id')->unique()->constrained('route_variants')->cascadeOnDelete();
            $table->json('geometry');
            $table->string('geometry_hash', 64);
            $table->unsignedInteger('coordinate_count');
            $table->timestamp('generated_at')->nullable();
            $table->string('generation_source');
            $table->timestamps();

            $table->index('geometry_hash');
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_variant_corridors');
    }
};
