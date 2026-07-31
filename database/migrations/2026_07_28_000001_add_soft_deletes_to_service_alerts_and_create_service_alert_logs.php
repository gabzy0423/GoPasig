<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_alerts', function (Blueprint $table) {
            if (! Schema::hasColumn('service_alerts', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::create('service_alert_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_alert_id')
                ->nullable()
                ->constrained('service_alerts')
                ->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('type')->nullable();
            $table->string('severity')->default('info');
            $table->string('affected_routes')->nullable();
            $table->string('status')->default('resolved');
            $table->boolean('suspend_route')->default(false);
            $table->timestamp('alert_created_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('archived_at')->useCurrent();
            $table->timestamps();

            $table->unique('service_alert_id');
            $table->index('archived_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_alert_logs');

        Schema::table('service_alerts', function (Blueprint $table) {
            if (Schema::hasColumn('service_alerts', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
