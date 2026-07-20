<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_positions', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_positions', 'movement_state')) {
                $table->string('movement_state', 20)->default('UNKNOWN')->after('corridor_distance');
            }

            if (!Schema::hasColumn('vehicle_positions', 'movement_confidence')) {
                $table->float('movement_confidence')->nullable()->after('movement_state');
            }

            if (!Schema::hasColumn('vehicle_positions', 'movement_reason')) {
                $table->string('movement_reason', 100)->nullable()->after('movement_confidence');
            }

            if (!Schema::hasColumn('vehicle_positions', 'movement_state_updated_at')) {
                $table->timestamp('movement_state_updated_at')->nullable()->after('movement_reason');
            }

            if (!Schema::hasColumn('vehicle_positions', 'movement_positive_samples')) {
                $table->unsignedSmallInteger('movement_positive_samples')->default(0)->after('movement_state_updated_at');
            }

            if (!Schema::hasColumn('vehicle_positions', 'movement_negative_samples')) {
                $table->unsignedSmallInteger('movement_negative_samples')->default(0)->after('movement_positive_samples');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_positions', function (Blueprint $table) {
            foreach ([
                'movement_negative_samples',
                'movement_positive_samples',
                'movement_state_updated_at',
                'movement_reason',
                'movement_confidence',
                'movement_state',
            ] as $column) {
                if (Schema::hasColumn('vehicle_positions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
