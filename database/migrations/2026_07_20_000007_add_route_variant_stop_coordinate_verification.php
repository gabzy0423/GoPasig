<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('route_variant_stops', function (Blueprint $table) {
            $table->string('coordinate_status', 16)->default('pending')->after('stop_type');
            $table->string('coordinate_source', 100)->nullable()->after('coordinate_status');
            $table->timestamp('coordinates_verified_at')->nullable()->after('coordinate_source');
            $table->foreignId('coordinates_verified_by_user_id')->nullable()->after('coordinates_verified_at')
                ->constrained('users')->nullOnDelete();
            $table->text('coordinate_notes')->nullable()->after('coordinates_verified_by_user_id');
            $table->index(['route_variant_id', 'coordinate_status'], 'rvst_variant_coord_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('route_variant_stops', function (Blueprint $table) {
            $table->dropIndex('rvst_variant_coord_status_index');
            $table->dropConstrainedForeignId('coordinates_verified_by_user_id');
            $table->dropColumn([
                'coordinate_status',
                'coordinate_source',
                'coordinates_verified_at',
                'coordinate_notes',
            ]);
        });
    }
};
