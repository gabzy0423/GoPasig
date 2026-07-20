<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_generation_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 50);
            $table->json('generated_geometry'); // Raw coordinates list: [[lat, lng], ...]
            $table->json('comparison_metrics'); // Metrics calculated
            $table->string('status', 20)->default('pending'); // pending, accepted, rejected
            $table->timestamp('expires_at');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['route_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_generation_sessions');
    }
};
