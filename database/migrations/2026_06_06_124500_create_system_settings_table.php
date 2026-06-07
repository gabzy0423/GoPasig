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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Seed default values
        DB::table('system_settings')->insert([
            [
                'key' => 'default_amenity',
                'value' => 'Shelter',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'sim_near_lat',
                'value' => '14.5685',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'sim_near_lng',
                'value' => '121.0650',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'sim_near_label',
                'value' => 'Malapit sa Kapitolyo (280m)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'sim_far_lat',
                'value' => '14.5000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'sim_far_lng',
                'value' => '121.0000',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'sim_far_label',
                'value' => 'Walang kalapit na stop',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'chime_freq_1',
                'value' => '1318.51',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'chime_freq_2',
                'value' => '1760.00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'chime_delay',
                'value' => '0.12',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
