<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('commuter_trips', 'is_simulated')) {
            Schema::table('commuter_trips', function (Blueprint $table) {
                $table->boolean('is_simulated')->default(false)->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('commuter_trips', 'is_simulated')) {
            Schema::table('commuter_trips', function (Blueprint $table) {
                $table->dropColumn('is_simulated');
            });
        }
    }
};
