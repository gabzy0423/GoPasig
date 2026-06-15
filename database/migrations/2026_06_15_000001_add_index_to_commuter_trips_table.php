<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('commuter_trips', function (Blueprint $table) {
            $table->index('session_token', 'commuter_trips_session_token_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commuter_trips', function (Blueprint $table) {
            $table->dropIndex('commuter_trips_session_token_index');
        });
    }
};
