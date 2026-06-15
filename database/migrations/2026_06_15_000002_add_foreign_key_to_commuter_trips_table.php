<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safety: Purge any orphaned commuter_trips records where session_token does not exist in commuter_sessions
        DB::table('commuter_trips')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('commuter_sessions')
                    ->whereColumn('commuter_sessions.session_token', 'commuter_trips.session_token');
            })
            ->delete();

        Schema::table('commuter_trips', function (Blueprint $table) {
            $table->foreign('session_token')
                ->references('session_token')
                ->on('commuter_sessions')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commuter_trips', function (Blueprint $table) {
            $table->dropForeign(['session_token']);
        });
    }
};
