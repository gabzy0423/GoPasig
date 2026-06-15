<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('incident_types')) {
            Schema::create('incident_types', function (Blueprint $table) {
                $table->id();
                $table->string('name')->unique();
                $table->string('severity_level')->default('Low'); // 'Low', 'Medium', 'High'
                $table->boolean('triggers_maintenance')->default(false);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Seed initial values
        $types = [
            ['name' => 'Breakdown', 'severity_level' => 'High', 'triggers_maintenance' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Accident', 'severity_level' => 'High', 'triggers_maintenance' => true, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Delay', 'severity_level' => 'Medium', 'triggers_maintenance' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'Route Issue', 'severity_level' => 'Medium', 'triggers_maintenance' => false, 'created_at' => now(), 'updated_at' => now()],
            ['name' => 'General Issue', 'severity_level' => 'Low', 'triggers_maintenance' => false, 'created_at' => now(), 'updated_at' => now()],
        ];

        foreach ($types as $type) {
            DB::table('incident_types')->updateOrInsert(
                ['name' => $type['name']],
                $type
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incident_types');
    }
};
