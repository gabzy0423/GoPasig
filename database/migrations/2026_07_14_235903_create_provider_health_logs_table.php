<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_health_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50);
            $table->float('latency_ms');
            $table->boolean('success');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_health_logs');
    }
};
