<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_passenger_events', function (Blueprint $table) {
            $table->uuid('request_id')->nullable()->after('route_variant_stop_id');
            $table->unique('request_id');
        });
    }

    public function down(): void
    {
        Schema::table('trip_passenger_events', function (Blueprint $table) {
            $table->dropUnique(['request_id']);
            $table->dropColumn('request_id');
        });
    }
};
