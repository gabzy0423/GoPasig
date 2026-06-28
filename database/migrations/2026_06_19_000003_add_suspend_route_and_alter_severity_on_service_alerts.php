<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_alerts', function (Blueprint $table) {
            if (!Schema::hasColumn('service_alerts', 'suspend_route')) {
                $table->boolean('suspend_route')->default(false)->after('status');
            }
            $table->string('severity')->default('info')->change();
        });
    }

    public function down(): void
    {
        Schema::table('service_alerts', function (Blueprint $table) {
            $table->dropColumnIfExists(['suspend_route']);
        });
    }
};
