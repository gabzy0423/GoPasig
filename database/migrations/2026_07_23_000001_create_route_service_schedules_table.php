<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_service_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('route_variant_id')->constrained('route_variants')->cascadeOnDelete();
            $table->time('first_trip_time');
            $table->time('last_trip_time');
            $table->string('service_configuration', 64)->default('continuous');
            $table->json('service_days');
            $table->boolean('is_active')->default(true);
            $table->string('source', 64)->default('beneficiary_official');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();

            $table->index(['route_id', 'route_variant_id', 'is_active'], 'rss_route_variant_active_idx');
            $table->index(['effective_from', 'effective_until'], 'rss_effective_range_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_service_schedules');
    }
};
