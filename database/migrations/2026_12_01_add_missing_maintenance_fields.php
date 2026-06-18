<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add missing fields that the model and controller expect
     */
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            // Add completed_at timestamp
            if (!Schema::hasColumn('maintenance_records', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('status');
            }
            
            // Add technician_notes for work details
            if (!Schema::hasColumn('maintenance_records', 'technician_notes')) {
                $table->text('technician_notes')->nullable()->after('cost_php');
            }
            
            // Add actual_duration_minutes for tracking
            if (!Schema::hasColumn('maintenance_records', 'actual_duration_minutes')) {
                $table->integer('actual_duration_minutes')->nullable()->after('expected_duration_minutes');
            }
            
            // Add workflow_status for additional state tracking
            if (!Schema::hasColumn('maintenance_records', 'workflow_status')) {
                $table->string('workflow_status')->nullable()->after('status');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumnIfExists(['completed_at', 'technician_notes', 'actual_duration_minutes', 'workflow_status']);
        });
    }
};
