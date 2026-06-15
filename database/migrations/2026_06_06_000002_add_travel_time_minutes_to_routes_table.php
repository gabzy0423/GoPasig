<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            if (!Schema::hasColumn('routes', 'travel_time_minutes')) {
                $table->integer('travel_time_minutes')->default(30)->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('routes', function (Blueprint $table) {
            $table->dropColumn('travel_time_minutes');
        });
    }
};