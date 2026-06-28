<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            if (!Schema::hasColumn('maintenance_records', 'failed_inspections_count')) {
                $table->integer('failed_inspections_count')->default(0)->after('inspection_passed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_records', function (Blueprint $table) {
            $table->dropColumnIfExists(['failed_inspections_count']);
        });
    }
};
