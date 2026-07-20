<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gps_logs', function (Blueprint $table) {
            $table->float('heading')->default(0.0)->after('speed');
            $table->float('accuracy')->nullable()->after('heading');
            $table->string('processing_status', 50)->default('pending')->after('timestamp');
            $table->timestamp('processed_at')->nullable()->after('processing_status');
            $table->decimal('filtered_lat', 10, 7)->nullable()->after('processed_at');
            $table->decimal('filtered_lng', 10, 7)->nullable()->after('filtered_lat');
        });

        Schema::create('trip_progresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained('trips')->onDelete('cascade');
            $table->foreignId('current_stop_id')->nullable()->constrained('stops')->onDelete('set null');
            $table->foreignId('next_stop_id')->nullable()->constrained('stops')->onDelete('set null');
            $table->foreignId('last_completed_stop_id')->nullable()->constrained('stops')->onDelete('set null');
            $table->integer('completed_stops_count')->default(0);
            $table->integer('remaining_stops_count')->default(0);
            $table->float('trip_percentage')->default(0.0);
            $table->string('route_adherence', 50)->default('On Route');
            $table->integer('current_delay_minutes')->default(0);
            $table->json('upcoming_etas')->nullable();
            $table->timestamps();
        });

        Schema::create('vehicle_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bus_id')->unique()->constrained('buses')->onDelete('cascade');
            $table->foreignId('trip_id')->nullable()->constrained('trips')->onDelete('set null');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->float('heading')->default(0.0);
            $table->float('speed')->default(0.0);
            $table->string('status', 50)->default('Unknown');
            $table->timestamp('last_updated_at');
            $table->timestamps();
        });

        Schema::create('stop_arrivals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->foreignId('stop_id')->constrained('stops')->onDelete('cascade');
            $table->timestamp('arrival_time');
            $table->timestamp('departure_time')->nullable();
            $table->string('arrival_source', 50)->default('GPS');
            $table->timestamps();

            $table->unique(['trip_id', 'stop_id']);
        });

        Schema::create('route_deviations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained('trips')->onDelete('cascade');
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->float('distance_meters');
            $table->string('severity', 50)->default('Minor');
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_deviations');
        Schema::dropIfExists('stop_arrivals');
        Schema::dropIfExists('vehicle_positions');
        Schema::dropIfExists('trip_progresses');

        Schema::table('gps_logs', function (Blueprint $table) {
            $table->dropColumn(['heading', 'accuracy', 'processing_status', 'processed_at', 'filtered_lat', 'filtered_lng']);
        });
    }
};
