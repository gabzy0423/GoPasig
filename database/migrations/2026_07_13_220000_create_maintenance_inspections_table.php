<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_inspections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_record_id')
                ->constrained('maintenance_records')
                ->onDelete('cascade');

            // Sequential attempt number within a maintenance ticket (1, 2, 3, ...)
            $table->unsignedInteger('attempt_no')->default(1);

            // Inspector details
            $table->string('inspector_name');
            $table->string('bus_condition'); // Excellent | Good | Fair | Needs Follow-up

            // Maintenance Result — single source of truth
            $table->string('maintenance_result'); // Passed Inspection | Passed with Observation | Failed Inspection
            $table->boolean('roadworthy');         // derived from maintenance_result
            $table->boolean('inspection_passed');  // derived from maintenance_result

            // Checklist and parts
            $table->json('inspection_checklist')->nullable();
            $table->text('parts_replaced')->nullable();

            // Cost breakdown (captured per-attempt for accurate cost tracking)
            $table->decimal('labor_cost', 10, 2)->default(0.00);
            $table->decimal('parts_cost', 10, 2)->default(0.00);
            $table->decimal('other_cost', 10, 2)->default(0.00);
            $table->decimal('cost_php', 10, 2)->default(0.00);

            // Notes
            $table->text('technician_notes');
            $table->text('recommendation')->nullable();

            // Timestamp of when this inspection was performed
            $table->timestamp('inspected_at');

            $table->timestamps();

            // Index for efficient lookup
            $table->index(['maintenance_record_id', 'attempt_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_inspections');
    }
};
