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
        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE trips MODIFY COLUMN status ENUM('pending', 'dispatched', 'ongoing', 'completed', 'cancelled') DEFAULT 'pending'");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE buses MODIFY COLUMN status ENUM('active', 'inactive', 'maintenance', 'breakdown', 'ready', 'operating', 'available') DEFAULT 'inactive'");
        } else {
            Schema::table('trips', function (Blueprint $table) {
                $table->string('status')->default('pending')->change();
            });
            Schema::table('buses', function (Blueprint $table) {
                $table->string('status')->default('inactive')->change();
            });
        }

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('operational_status')->default('available')->after('status');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->string('gps_session')->default('OFF')->after('status');
            $table->timestamp('dispatched_at')->nullable()->after('started_at');
            $table->timestamp('gps_session_started_at')->nullable()->after('dispatched_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn('operational_status');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn(['gps_session', 'dispatched_at', 'gps_session_started_at']);
        });
    }
};
