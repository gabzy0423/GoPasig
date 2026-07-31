<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('service_alert_reads')) {
            Schema::create('service_alert_reads', function (Blueprint $table) {
                $table->id();
                $table->foreignId('service_alert_id')->constrained('service_alerts')->onDelete('cascade');
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
                $table->string('session_id')->nullable()->index();
                $table->timestamp('read_at')->useCurrent();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // This migration repairs drift from a manually removed table. Do not
        // drop an existing table on rollback because it may have been created
        // by the original historical migration in healthy databases.
    }
};
