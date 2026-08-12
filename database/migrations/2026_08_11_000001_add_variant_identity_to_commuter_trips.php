<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('commuter_trips', function (Blueprint $table) {
            $table->foreignId('origin_stop_id')->nullable()->change();
            $table->foreignId('destination_stop_id')->nullable()->change();

            $table->foreignId('route_variant_id')
                ->nullable()
                ->after('route_id')
                ->constrained('route_variants')
                ->nullOnDelete();
            $table->foreignId('origin_route_variant_stop_id')
                ->nullable()
                ->after('origin_stop_id')
                ->constrained('route_variant_stops')
                ->nullOnDelete();
            $table->foreignId('destination_route_variant_stop_id')
                ->nullable()
                ->after('destination_stop_id')
                ->constrained('route_variant_stops')
                ->nullOnDelete();

            $table->index(
                ['route_variant_id', 'status', 'is_simulated'],
                'commuter_trips_reactive_variant_index'
            );
        });

        $this->backfillUnambiguousVariantIdentity();
    }

    public function down(): void
    {
        if (DB::table('commuter_trips')->whereNull('origin_stop_id')->orWhereNull('destination_stop_id')->exists()) {
            throw new RuntimeException(
                'Cannot remove commuter route-variant identity while variant-only journeys exist.'
            );
        }

        Schema::table('commuter_trips', function (Blueprint $table) {
            $table->dropIndex('commuter_trips_reactive_variant_index');
            $table->dropConstrainedForeignId('destination_route_variant_stop_id');
            $table->dropConstrainedForeignId('origin_route_variant_stop_id');
            $table->dropConstrainedForeignId('route_variant_id');

            $table->foreignId('origin_stop_id')->nullable(false)->change();
            $table->foreignId('destination_stop_id')->nullable(false)->change();
        });
    }

    private function backfillUnambiguousVariantIdentity(): void
    {
        DB::table('commuter_trips')
            ->whereNotNull('origin_stop_id')
            ->whereNotNull('destination_stop_id')
            ->orderBy('id')
            ->chunkById(200, function ($trips): void {
                foreach ($trips as $trip) {
                    $matches = DB::table('route_variants as variants')
                        ->join('route_variant_stops as origins', 'origins.route_variant_id', '=', 'variants.id')
                        ->join('route_variant_stops as destinations', 'destinations.route_variant_id', '=', 'variants.id')
                        ->where('variants.route_id', $trip->route_id)
                        ->where('origins.canonical_stop_id', $trip->origin_stop_id)
                        ->where('destinations.canonical_stop_id', $trip->destination_stop_id)
                        ->whereColumn('destinations.sequence', '>', 'origins.sequence')
                        ->select([
                            'variants.id as route_variant_id',
                            'origins.id as origin_route_variant_stop_id',
                            'destinations.id as destination_route_variant_stop_id',
                        ])
                        ->limit(2)
                        ->get();

                    if ($matches->count() !== 1) {
                        continue;
                    }

                    $match = $matches->first();
                    DB::table('commuter_trips')->where('id', $trip->id)->update([
                        'route_variant_id' => $match->route_variant_id,
                        'origin_route_variant_stop_id' => $match->origin_route_variant_stop_id,
                        'destination_route_variant_stop_id' => $match->destination_route_variant_stop_id,
                    ]);
                }
            });
    }
};
