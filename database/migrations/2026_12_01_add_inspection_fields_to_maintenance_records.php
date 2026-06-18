<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds basic inspection fields for safety validation
     * Maintains separation between technician_name (who did work) 
     * and inspected_by (who verified it's safe)
     */
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            // Safety validation fields
            $table->boolean('inspection_passed')->nullable()->after('status');
            $table->text('inspection_notes')->nullable()->after('inspection_passed');
            $table->string('inspected_by')->nullable()->after('inspection_notes')->comment('Name of person who verified safety');
            $table->timestamp('inspected_at')->nullable()->after('inspected_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn(['inspection_passed', 'inspection_notes', 'inspected_by', 'inspected_at']);
        });
    }
};
