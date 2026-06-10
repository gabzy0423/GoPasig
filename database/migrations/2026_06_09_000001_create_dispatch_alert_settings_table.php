<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispatch_alert_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->unsignedInteger('yellow_percentage')->default(50);
            $table->unsignedInteger('red_percentage')->default(100);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispatch_alert_settings');
    }
};
