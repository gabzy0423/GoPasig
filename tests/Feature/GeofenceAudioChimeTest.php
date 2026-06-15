<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GeofenceAudioChimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_geofence_detector_uses_config_fallbacks(): void
    {
        // Assert that the config fallbacks are set correctly in our newly created config file
        $this->assertEquals(1318.51, config('geofence.chime_freq_1'));
        $this->assertEquals(1760.00, config('geofence.chime_freq_2'));
        $this->assertEquals(0.12, config('geofence.chime_delay'));

        // Render the component and assert that it sees the config fallback values since there are no system settings in DB
        Livewire::test(\App\Livewire\Commuter\GeofenceDetector::class)
            ->assertViewHas('chimeFreq1', 1318.51)
            ->assertViewHas('chimeFreq2', 1760.00)
            ->assertViewHas('chimeDelay', 0.12);
    }

    public function test_geofence_detector_respects_database_overrides(): void
    {
        // Add override values in database settings table
        SystemSetting::updateOrCreate(['key' => 'chime_freq_1'], ['value' => '1500.00']);
        SystemSetting::updateOrCreate(['key' => 'chime_freq_2'], ['value' => '1800.00']);
        SystemSetting::updateOrCreate(['key' => 'chime_delay'], ['value' => '0.25']);

        // Render the component and assert that it uses the database setting values over config fallbacks
        Livewire::test(\App\Livewire\Commuter\GeofenceDetector::class)
            ->assertViewHas('chimeFreq1', '1500.00')
            ->assertViewHas('chimeFreq2', '1800.00')
            ->assertViewHas('chimeDelay', '0.25');
    }
}
