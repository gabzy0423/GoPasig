<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_passenger_events', function (Blueprint $table) {
            $table->foreignId('route_variant_stop_id')
                ->nullable()
                ->after('route_id')
                ->constrained('route_variant_stops')
                ->nullOnDelete();

            $table->index(['route_variant_stop_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::table('trip_passenger_events', function (Blueprint $table) {
            $table->dropIndex(['route_variant_stop_id', 'recorded_at']);
            $table->dropConstrainedForeignId('route_variant_stop_id');
        });
    }
};
