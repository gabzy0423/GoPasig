<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_progresses', function (Blueprint $table) {
            $table->foreignId('current_route_variant_stop_id')
                ->nullable()
                ->after('current_stop_id')
                ->constrained('route_variant_stops')
                ->nullOnDelete();
            $table->foreignId('next_route_variant_stop_id')
                ->nullable()
                ->after('next_stop_id')
                ->constrained('route_variant_stops')
                ->nullOnDelete();
            $table->foreignId('last_completed_route_variant_stop_id')
                ->nullable()
                ->after('last_completed_stop_id')
                ->constrained('route_variant_stops')
                ->nullOnDelete();
        });

        Schema::table('stop_arrivals', function (Blueprint $table) {
            $table->unsignedBigInteger('stop_id')->nullable()->change();
            $table->foreignId('route_variant_stop_id')
                ->nullable()
                ->after('stop_id')
                ->constrained('route_variant_stops')
                ->nullOnDelete();
            $table->unique(['trip_id', 'route_variant_stop_id']);
        });
    }

    public function down(): void
    {
        Schema::table('stop_arrivals', function (Blueprint $table) {
            $table->dropUnique(['trip_id', 'route_variant_stop_id']);
            $table->dropConstrainedForeignId('route_variant_stop_id');
            $table->unsignedBigInteger('stop_id')->nullable(false)->change();
        });

        Schema::table('trip_progresses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('last_completed_route_variant_stop_id');
            $table->dropConstrainedForeignId('next_route_variant_stop_id');
            $table->dropConstrainedForeignId('current_route_variant_stop_id');
        });
    }
};