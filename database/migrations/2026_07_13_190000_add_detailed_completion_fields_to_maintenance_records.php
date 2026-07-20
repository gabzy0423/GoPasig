<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->string('inspector_name')->nullable()->after('id');
            $table->string('bus_condition')->nullable()->after('inspector_name'); // Excellent, Good, Fair, Needs Follow-up
            $table->boolean('roadworthy')->nullable()->default(false)->after('bus_condition'); // Ready for Service
            $table->text('recommendation')->nullable()->after('roadworthy');
            $table->string('maintenance_result')->nullable()->after('recommendation'); // passed, passed_with_observation, failed
            $table->json('inspection_checklist')->nullable()->after('maintenance_result'); // json of checkboxes
            
            $table->text('parts_replaced')->nullable();
            $table->decimal('labor_cost', 10, 2)->default(0.00);
            $table->decimal('parts_cost', 10, 2)->default(0.00);
            $table->decimal('other_cost', 10, 2)->default(0.00);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumn([
                'inspector_name',
                'bus_condition',
                'roadworthy',
                'recommendation',
                'maintenance_result',
                'inspection_checklist',
                'parts_replaced',
                'labor_cost',
                'parts_cost',
                'other_cost',
            ]);
        });
    }
};
