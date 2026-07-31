<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('routes')->cascadeOnDelete();
            $table->string('direction', 32);
            $table->string('origin_name')->nullable();
            $table->string('destination_name')->nullable();
            $table->json('polyline_coordinates')->nullable();
            $table->unsignedInteger('geometry_version')->default(0);
            $table->string('geometry_status', 32)->default('valid');
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->index(['route_id', 'direction']);
            $table->index(['route_id', 'is_default']);
            $table->unique(['route_id', 'direction']);
        });

        Schema::create('route_variant_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_variant_id')->constrained('route_variants')->cascadeOnDelete();
            $table->foreignId('canonical_stop_id')->nullable()->constrained('stops')->nullOnDelete();
            $table->string('name');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->integer('radius_meters')->default(100);
            $table->integer('sequence');
            $table->timestamps();

            $table->index(['route_variant_id', 'sequence']);
            $table->index('canonical_stop_id');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('route_variant_id')
                ->nullable()
                ->after('route_id')
                ->constrained('route_variants')
                ->nullOnDelete();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->foreignId('route_variant_id')
                ->nullable()
                ->after('route_id')
                ->constrained('route_variants')
                ->nullOnDelete();
        });

        $this->backfillDefaultVariants();
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('route_variant_id');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropConstrainedForeignId('route_variant_id');
        });

        Schema::dropIfExists('route_variant_stops');
        Schema::dropIfExists('route_variants');
    }

    private function backfillDefaultVariants(): void
    {
        $now = now();
        $routes = DB::table('routes')->orderBy('id')->get();

        foreach ($routes as $route) {
            $existing = DB::table('route_variants')
                ->where('route_id', $route->id)
                ->where('direction', 'outbound')
                ->first();

            if ($existing) {
                continue;
            }

            $stops = DB::table('stops')
                ->where('route_id', $route->id)
                ->orderBy('sequence')
                ->orderBy('id')
                ->get();

            $variantId = DB::table('route_variants')->insertGetId([
                'route_id' => $route->id,
                'direction' => 'outbound',
                'origin_name' => $stops->first()?->name,
                'destination_name' => $stops->last()?->name,
                'polyline_coordinates' => $route->polyline_coordinates,
                'geometry_version' => (int) ($route->geometry_version ?? 0),
                'geometry_status' => empty($route->polyline_coordinates) ? 'pending' : 'valid',
                'is_default' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($stops as $stop) {
                DB::table('route_variant_stops')->insert([
                    'route_variant_id' => $variantId,
                    'canonical_stop_id' => $stop->id,
                    'name' => $stop->name,
                    'lat' => $stop->lat,
                    'lng' => $stop->lng,
                    'radius_meters' => $stop->radius_meters,
                    'sequence' => $stop->sequence,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
