<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->date('service_date')->nullable()->after('route_id');
            $table->index(['driver_id', 'service_date']);
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropIndex(['driver_id', 'service_date']);
            $table->dropColumn('service_date');
        });
    }
};
