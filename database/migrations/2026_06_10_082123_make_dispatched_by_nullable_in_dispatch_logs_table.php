<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make dispatch_logs.dispatched_by nullable so that system-triggered
     * or unauthenticated dispatches do not falsely record under user ID 1.
     */
    public function up(): void
    {
        Schema::table('dispatch_logs', function (Blueprint $table) {
            // Drop the existing non-nullable FK constraint first
            $table->dropForeign(['dispatched_by']);

            // Re-add as nullable with nullOnDelete so cascade doesn't orphan rows
            $table->unsignedBigInteger('dispatched_by')->nullable()->change();

            $table->foreign('dispatched_by')
                  ->references('id')->on('users')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse: restore the strict non-nullable FK.
     * NOTE: any existing NULL rows must be resolved before running this rollback.
     */
    public function down(): void
    {
        Schema::table('dispatch_logs', function (Blueprint $table) {
            $table->dropForeign(['dispatched_by']);

            $table->unsignedBigInteger('dispatched_by')->nullable(false)->change();

            $table->foreign('dispatched_by')
                  ->references('id')->on('users')
                  ->onDelete('cascade');
        });
    }
};

