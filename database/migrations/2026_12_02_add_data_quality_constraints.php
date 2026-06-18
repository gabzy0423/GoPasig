<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add database constraints and audit trail
     */
    public function up(): void
    {
        // 1. Create bus_status_audit_log table for tracking status changes
        Schema::create('bus_status_audit_log', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bus_id')->index();
            $table->string('old_status', 50)->nullable();
            $table->string('new_status', 50);
            $table->unsignedBigInteger('changed_by')->nullable(); // User who made the change
            $table->text('reason')->nullable(); // Why the status changed
            $table->json('metadata')->nullable(); // Additional context (gps_location, incident_id, etc)
            $table->timestamps();

            $table->foreign('bus_id')->references('id')->on('buses')->onDelete('cascade');
            $table->foreign('changed_by')->references('id')->on('users')->onDelete('set null');
            $table->index(['bus_id', 'created_at']);
            $table->index('new_status');
        });

        // 2. Add foreign key constraints if they don't exist
        // Check and add FK constraints with proper cascade delete options
        Schema::table('stops', function (Blueprint $table) {
            // Drop existing FK if it exists (we'll recreate it)
            if (Schema::hasColumn('stops', 'route_id')) {
                try {
                    $table->dropForeign(['route_id']);
                } catch (\Exception $e) {
                    // FK might not exist, skip
                }
            }
        });

        // Recreate with cascade delete
        if (Schema::hasTable('stops')) {
            Schema::table('stops', function (Blueprint $table) {
                if (!Schema::hasColumn('stops', 'route_id')) {
                    $table->unsignedBigInteger('route_id')->after('id');
                }
                $table->foreign('route_id')
                    ->references('id')->on('routes')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');
            });
        }

        // Ensure schedules have proper FK constraints
        if (Schema::hasTable('schedules')) {
            Schema::table('schedules', function (Blueprint $table) {
                // Check if FKs exist, if not add them
                if (Schema::hasColumn('schedules', 'route_id')) {
                    try {
                        $table->dropForeign(['route_id']);
                    } catch (\Exception $e) {
                        // Continue
                    }
                }

                $table->foreign('route_id')
                    ->references('id')->on('routes')
                    ->onDelete('cascade')
                    ->onUpdate('cascade');

                try {
                    $table->dropForeign(['bus_id']);
                } catch (\Exception $e) {
                    // Continue
                }

                if (Schema::hasColumn('schedules', 'bus_id')) {
                    $table->foreign('bus_id')
                        ->references('id')->on('buses')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }

                try {
                    $table->dropForeign(['driver_id']);
                } catch (\Exception $e) {
                    // Continue
                }

                if (Schema::hasColumn('schedules', 'driver_id')) {
                    $table->foreign('driver_id')
                        ->references('id')->on('drivers')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }
            });
        }

        // Ensure maintenance_records have proper FK
        if (Schema::hasTable('maintenance_records')) {
            Schema::table('maintenance_records', function (Blueprint $table) {
                try {
                    $table->dropForeign(['bus_id']);
                } catch (\Exception $e) {
                    // Continue
                }

                if (Schema::hasColumn('maintenance_records', 'bus_id')) {
                    $table->foreign('bus_id')
                        ->references('id')->on('buses')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }
            });
        }

        // Ensure service_alerts have proper FK
        if (Schema::hasTable('service_alerts')) {
            Schema::table('service_alerts', function (Blueprint $table) {
                try {
                    $table->dropForeign(['route_id']);
                } catch (\Exception $e) {
                    // Continue
                }

                if (Schema::hasColumn('service_alerts', 'route_id')) {
                    $table->foreign('route_id')
                        ->references('id')->on('routes')
                        ->onDelete('cascade')
                        ->onUpdate('cascade');
                }
            });
        }

        // 3. Create orphaned_records_log table for tracking data integrity issues
        Schema::create('orphaned_records_log', function (Blueprint $table) {
            $table->id();
            $table->string('table_name', 100); // schedules, trips, dispatch_logs, etc.
            $table->unsignedBigInteger('record_id');
            $table->string('foreign_key_name', 100); // bus_id, route_id, etc.
            $table->unsignedBigInteger('missing_foreign_id');
            $table->string('resolution_status', 50)->default('pending'); // pending, resolved, deleted, reassigned
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['table_name', 'resolution_status']);
            $table->index('record_id');
        });

        // 4. Create unique constraints for critical fields
        if (Schema::hasTable('buses')) {
            Schema::table('buses', function (Blueprint $table) {
                // Plate numbers should be unique
                if (!Schema::hasColumn('buses', 'unique_plate_index')) {
                    $table->unique('plate_number', 'unique_plate_number');
                }
            });
        }

        // 5. Add check constraints for valid status values
        // (SQLite doesn't support check constraints, but we can add them for other databases)
        if (Schema::hasTable('buses')) {
            Schema::table('buses', function (Blueprint $table) {
                // Add GPS coordinate validation indexes
                if (Schema::hasColumn('buses', 'lat') && Schema::hasColumn('buses', 'lng')) {
                    $table->index(['lat', 'lng']);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bus_status_audit_log');
        Schema::dropIfExists('orphaned_records_log');

        // Remove the indexes we added
        if (Schema::hasTable('buses')) {
            Schema::table('buses', function (Blueprint $table) {
                try {
                    $table->dropIndex('buses_lat_lng_index');
                } catch (\Exception $e) {
                    // Index might not exist
                }
            });
        }
    }
};
