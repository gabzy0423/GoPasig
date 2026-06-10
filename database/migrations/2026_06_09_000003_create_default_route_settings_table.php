<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('default_route_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('default_latitude', 10, 6)->nullable();
            $table->decimal('default_longitude', 10, 6)->nullable();
            $table->string('default_origin_label')->nullable();
            $table->string('default_destination_label')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('default_route_settings');
    }
};
