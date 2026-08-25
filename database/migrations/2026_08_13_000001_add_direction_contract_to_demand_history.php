<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('demand_history', function (Blueprint $table) {
            $table->foreignId('route_variant_id')
                ->nullable()
                ->after('route_id')
                ->constrained('route_variants')
                ->nullOnDelete();
            $table->string('source', 40)
                ->default('legacy_unknown')
                ->after('buses_dispatched');
            $table->boolean('is_training_eligible')
                ->default(false)
                ->after('source');
            $table->timestamp('finalized_at')
                ->nullable()
                ->after('is_training_eligible');

            $table->unique(
                ['route_id', 'route_variant_id', 'date', 'time_slot'],
                'demand_history_variant_bucket_unique'
            );
            $table->index(
                ['is_training_eligible', 'day_of_week', 'time_slot'],
                'demand_history_forecast_lookup_index'
            );
        });

        DB::table('demand_history')->update([
            'source' => 'legacy_unknown',
            'is_training_eligible' => false,
            'finalized_at' => null,
        ]);
    }

    public function down(): void
    {
        Schema::table('demand_history', function (Blueprint $table) {
            $table->dropIndex('demand_history_forecast_lookup_index');
            $table->dropUnique('demand_history_variant_bucket_unique');
            $table->dropConstrainedForeignId('route_variant_id');
            $table->dropColumn(['source', 'is_training_eligible', 'finalized_at']);
        });
    }
};
