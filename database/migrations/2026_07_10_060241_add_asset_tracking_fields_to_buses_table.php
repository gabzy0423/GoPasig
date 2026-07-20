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
        Schema::table('buses', function (Blueprint $table) {
            $table->string('fleet_number')->nullable()->unique();
            $table->string('vin')->nullable()->unique();
            $table->string('manufacturer')->nullable();
            $table->string('model')->nullable();
            $table->integer('year_model')->nullable();
            $table->decimal('battery_capacity_kwh', 6, 2)->nullable();
            $table->string('charging_port_type')->nullable();
            $table->decimal('max_charging_power_kw', 6, 2)->nullable();

            // Future-proof purchase fields
            $table->date('purchase_date')->nullable();
            $table->string('supplier')->nullable();
            $table->date('warranty_expiry')->nullable();
            $table->string('serial_number')->nullable();
            $table->decimal('acquisition_cost', 10, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->dropColumn([
                'fleet_number',
                'vin',
                'manufacturer',
                'model',
                'year_model',
                'battery_capacity_kwh',
                'charging_port_type',
                'max_charging_power_kw',
                'purchase_date',
                'supplier',
                'warranty_expiry',
                'serial_number',
                'acquisition_cost',
            ]);
        });
    }
};
