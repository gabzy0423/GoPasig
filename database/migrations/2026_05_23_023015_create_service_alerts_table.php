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
        Schema::create('service_alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')
                ->nullable()
                ->constrained('routes')
                ->onDelete('cascade');

            $table->string('title');

            $table->text('message');

            $table->enum('severity', [
                'info',
                'warning',
                'critical'
            ])->default('info');

            $table->string('type')->default('info');

            $table->string('affected_routes')->nullable();

            $table->enum('status', [
                'active',
                'resolved'
            ])->default('active');

            $table->timestamp('created_at')->useCurrent();

            $table->timestamp('updated_at')->useCurrent()->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_alerts');
    }
};
