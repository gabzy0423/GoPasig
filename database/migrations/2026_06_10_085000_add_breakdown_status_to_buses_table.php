<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Add 'breakdown' to the buses.status enum.
     *
     * The DriverController already sets bus status to 'breakdown' when an
     * incident of type 'Breakdown' is reported, but the original schema only
     * allowed: active | inactive | maintenance.
     * This migration extends the allowed values to include 'breakdown' so that
     * the constraint is consistent with application behaviour.
     */
    public function up(): void
    {
        // SQLite does not support ALTER COLUMN for enums; the check constraint
        // must be dropped and recreated. Laravel's enum()->change() handles
        // this transparently across both MySQL and SQLite.
        Schema::table('buses', function (Blueprint $table) {
            $table->enum('status', [
                'active',
                'inactive',
                'maintenance',
                'breakdown',
            ])->default('active')->change();
        });
    }

    /**
     * Reverse the migration.
     *
     * NOTE: Any existing rows with status = 'breakdown' must be resolved
     * before running this rollback, otherwise the constraint will reject them.
     */
    public function down(): void
    {
        Schema::table('buses', function (Blueprint $table) {
            $table->enum('status', [
                'active',
                'inactive',
                'maintenance',
            ])->default('active')->change();
        });
    }
};
