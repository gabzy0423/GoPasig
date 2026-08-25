<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demand_forecast_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->foreignId('route_variant_id')->constrained('route_variants')->cascadeOnDelete();
            $table->date('target_date');
            $table->string('day_of_week', 12);
            $table->string('time_slot', 32);
            $table->string('direction', 20);
            $table->string('direction_label');
            $table->decimal('expected_commuters', 8, 1)->nullable();
            $table->unsignedSmallInteger('sample_count')->default(0);
            $table->unsignedSmallInteger('minimum_samples')->default(0);
            $table->string('confidence', 20)->default('insufficient');
            $table->unsignedSmallInteger('minimum_buses')->nullable();
            $table->unsignedSmallInteger('reference_bus_capacity');
            $table->string('forecast_status', 40);
            $table->string('forecast_version', 50);
            $table->boolean('advisory_only')->default(true);
            $table->timestamp('captured_at');
            $table->unsignedInteger('actual_commuters')->nullable();
            $table->string('actual_source', 40)->nullable();
            $table->timestamp('actual_finalized_at')->nullable();
            $table->decimal('error_delta', 8, 1)->nullable();
            $table->decimal('absolute_error', 8, 1)->nullable();
            $table->decimal('percentage_error', 8, 2)->nullable();
            $table->timestamp('evaluated_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['target_date', 'route_variant_id', 'time_slot', 'forecast_version'],
                'demand_forecast_snapshot_identity_unique'
            );
            $table->index(
                ['target_date', 'forecast_status', 'evaluated_at'],
                'demand_forecast_snapshot_evaluation_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demand_forecast_snapshots');
    }
};
