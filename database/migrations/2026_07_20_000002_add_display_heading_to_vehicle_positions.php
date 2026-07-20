<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vehicle_positions', function (Blueprint $table) {
            if (!Schema::hasColumn('vehicle_positions', 'display_heading')) {
                $table->float('display_heading')->nullable()->after('heading');
            }

            if (!Schema::hasColumn('vehicle_positions', 'heading_source')) {
                $table->string('heading_source', 20)->default('unavailable')->after('display_heading');
            }

            if (!Schema::hasColumn('vehicle_positions', 'heading_updated_at')) {
                $table->timestamp('heading_updated_at')->nullable()->after('heading_source');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_positions', function (Blueprint $table) {
            foreach (['heading_updated_at', 'heading_source', 'display_heading'] as $column) {
                if (Schema::hasColumn('vehicle_positions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
