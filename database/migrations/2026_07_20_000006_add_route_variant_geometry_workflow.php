<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('route_generation_sessions', 'route_variant_id')) {
            Schema::table('route_generation_sessions', function (Blueprint $table) {
                $table->foreignId('route_variant_id')->nullable()->after('route_id')->constrained('route_variants')->nullOnDelete();
            });
        }

        $variantSessionIndexes = Schema::getIndexes('route_generation_sessions');
        $hasVariantSessionIndex = collect($variantSessionIndexes)->contains(
            fn (array $index): bool => ($index['name'] ?? null) === 'rgs_variant_status_expiry_index'
        );
        if (! $hasVariantSessionIndex) {
            Schema::table('route_generation_sessions', function (Blueprint $table) {
                $table->index(['route_variant_id', 'status', 'expires_at'], 'rgs_variant_status_expiry_index');
            });
        }

        if (! Schema::hasTable('route_variant_geometry_versions')) {
            Schema::create('route_variant_geometry_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('route_variant_id')->constrained()->cascadeOnDelete();
                $table->json('polyline_coordinates');
                $table->unsignedInteger('vertex_count')->default(0);
                $table->decimal('length_km', 8, 4)->default(0);
                $table->string('label', 100)->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedBigInteger('restored_from_version')->nullable();
                $table->timestamps();
                $table->index(['route_variant_id', 'created_at'], 'rvgv_variant_created_index');
                $table->index(['route_variant_id', 'id'], 'rvgv_variant_id_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('route_variant_geometry_versions');
        if (Schema::hasColumn('route_generation_sessions', 'route_variant_id')) {
            Schema::table('route_generation_sessions', function (Blueprint $table) {
                $table->dropForeign(['route_variant_id']);
                $table->dropIndex('rgs_variant_status_expiry_index');
                $table->dropColumn('route_variant_id');
            });
        }
    }
};