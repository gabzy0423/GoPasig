<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations to drop the standalone announcements table.
     */
    public function up(): void
    {
        Schema::dropIfExists('announcements');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('headline', 100);
            $table->text('body');
            $table->enum('priority', ['Low', 'Medium', 'High'])->default('Medium');
            $table->enum('audience', ['Commuters', 'Drivers', 'Administrators', 'All Users'])->default('All Users');
            $table->string('affected_route')->nullable();
            $table->string('posted_by')->default('Danielle Dispatcher');
            $table->boolean('is_draft')->default(false);
            $table->boolean('is_scheduled')->default(false);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
};
