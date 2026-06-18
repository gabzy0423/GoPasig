<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Driver;
use App\Models\SystemSetting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DriverLicenseExpiryThresholdTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('db:seed', ['--class' => 'SystemSettingSeeder']);
        User::factory()->create(['role' => 'admin']);
    }

    /**
     * Test 1: Verify SystemSetting license_expiry_warning_threshold_days exists
     */
    public function test_license_expiry_threshold_setting_exists()
    {
        $thresholdDays = SystemSetting::where('key', 'license_expiry_warning_threshold_days')->first();
        
        $this->assertNotNull($thresholdDays);
        $this->assertEquals('30', $thresholdDays->value);
    }

    /**
     * Test 2: Verify threshold setting can be retrieved dynamically
     */
    public function test_license_expiry_threshold_retrieved_dynamically()
    {
        $thresholdDays = SystemSetting::get('license_expiry_warning_threshold_days', 30);
        $this->assertEquals(30, $thresholdDays);
    }

    /**
     * Test 3: License expiring in 25 days should be in expiring count
     * (Default threshold is 30, so 25 is within threshold)
     */
    public function test_driver_with_license_expiring_in_25_days_shows_warning()
    {
        $this->actingAs(User::first(), 'web');
        
        $today = Carbon::today();
        $expiryDate = $today->copy()->addDays(25);

        Driver::create([
            'user_id' => User::first()->id,
            'first_name' => 'Juan',
            'last_name' => 'Test',
            'emp_id' => 'EMP-001',
            'license_number' => 'N01-23-456789',
            'license_expiry' => $expiryDate,
            'status' => 'active',
            'contact_number' => '09171234567',
            'address' => 'Test Address',
        ]);

        $response = $this->get(route('admin.api.drivers.index'));
        $data = $response->json();

        // Check that driver is counted in expiring list
        $this->assertGreaterThanOrEqual(1, $data['stats']['expiring']);
    }

    /**
     * Test 4: License expiring in 35 days should NOT show warning
     * (Default threshold is 30, so 35 is outside threshold)
     */
    public function test_driver_with_license_expiring_in_35_days_no_warning()
    {
        $this->actingAs(User::first(), 'web');
        
        $today = Carbon::today();
        $expiryDate = $today->copy()->addDays(35);

        Driver::create([
            'user_id' => User::first()->id,
            'first_name' => 'Maria',
            'last_name' => 'Test',
            'emp_id' => 'EMP-002',
            'license_number' => 'N02-23-456789',
            'license_expiry' => $expiryDate,
            'status' => 'active',
            'contact_number' => '09171234567',
            'address' => 'Test Address',
        ]);

        $response = $this->get(route('admin.api.drivers.index'));
        $data = $response->json();

        // Driver should NOT be in expiring count
        $this->assertEquals(0, $data['stats']['expiring']);
    }

    /**
     * Test 5: License already expired should show warning
     */
    public function test_driver_with_expired_license_shows_warning()
    {
        $this->actingAs(User::first(), 'web');
        
        $today = Carbon::today();
        $expiryDate = $today->copy()->subDays(5);

        Driver::create([
            'user_id' => User::first()->id,
            'first_name' => 'Pedro',
            'last_name' => 'Test',
            'emp_id' => 'EMP-003',
            'license_number' => 'N03-23-456789',
            'license_expiry' => $expiryDate,
            'status' => 'active',
            'contact_number' => '09171234567',
            'address' => 'Test Address',
        ]);

        $response = $this->get(route('admin.api.drivers.index'));
        $data = $response->json();

        // Driver should be in expiring count (already expired)
        $this->assertGreaterThanOrEqual(1, $data['stats']['expiring']);
    }

    /**
     * Test 6: Change threshold to 20 days and verify logic updates
     */
    public function test_dynamic_threshold_change_affects_warning()
    {
        $this->actingAs(User::first(), 'web');
        
        // Create driver expiring in 25 days
        $today = Carbon::today();
        $expiryDate = $today->copy()->addDays(25);

        Driver::create([
            'user_id' => User::first()->id,
            'first_name' => 'Carlos',
            'last_name' => 'Test',
            'emp_id' => 'EMP-004',
            'license_number' => 'N04-23-456789',
            'license_expiry' => $expiryDate,
            'status' => 'active',
            'contact_number' => '09171234567',
            'address' => 'Test Address',
        ]);

        // With default threshold of 30, driver should be in expiring count
        $response = $this->get(route('admin.api.drivers.index'));
        $data = $response->json();
        $expiringCountWithThreshold30 = $data['stats']['expiring'];
        $this->assertGreaterThanOrEqual(1, $expiringCountWithThreshold30);

        // Change threshold to 20 days
        SystemSetting::updateOrCreate(
            ['key' => 'license_expiry_warning_threshold_days'],
            ['value' => '20']
        );

        // Clear cache so new value is fetched
        \Illuminate\Support\Facades\Cache::forget('system_setting_license_expiry_warning_threshold_days');

        // With new threshold of 20, driver (expiring in 25) should NOT be in expiring count
        $response = $this->get(route('admin.api.drivers.index'));
        $data = $response->json();
        $expiringCountWithThreshold20 = $data['stats']['expiring'];
        $this->assertEquals(0, $expiringCountWithThreshold20);
    }

    /**
     * Test 7: Verify driver penalty settings exist
     */
    public function test_driver_penalty_settings_exist()
    {
        $incidentPenalty = SystemSetting::where('key', 'driver_score_incident_penalty')->first();
        $delayPenalty = SystemSetting::where('key', 'driver_score_delay_penalty')->first();
        
        $this->assertNotNull($incidentPenalty);
        $this->assertEquals('10', $incidentPenalty->value);
        
        $this->assertNotNull($delayPenalty);
        $this->assertEquals('5', $delayPenalty->value);
    }

    /**
     * Test 8: License within threshold (29 days with threshold 30)
     */
    public function test_license_within_threshold_day_shows_warning()
    {
        $this->actingAs(User::first(), 'web');
        
        $today = Carbon::today();
        $expiryDate = $today->copy()->addDays(29);

        Driver::create([
            'user_id' => User::first()->id,
            'first_name' => 'Rosa',
            'last_name' => 'Test',
            'emp_id' => 'EMP-005',
            'license_number' => 'N05-23-456789',
            'license_expiry' => $expiryDate,
            'status' => 'active',
            'contact_number' => '09171234567',
            'address' => 'Test Address',
        ]);

        $response = $this->get(route('admin.api.drivers.index'));
        $data = $response->json();

        // Driver within threshold (29 days, threshold 30) should be included in expiring
        $this->assertGreaterThanOrEqual(1, $data['stats']['expiring']);
    }

    /**
     * Test 9: License expiring tomorrow should always show warning
     */
    public function test_license_expiring_tomorrow_shows_warning()
    {
        $this->actingAs(User::first(), 'web');
        
        $today = Carbon::today();
        $expiryDate = $today->copy()->addDay();

        Driver::create([
            'user_id' => User::first()->id,
            'first_name' => 'Anna',
            'last_name' => 'Test',
            'emp_id' => 'EMP-006',
            'license_number' => 'N06-23-456789',
            'license_expiry' => $expiryDate,
            'status' => 'active',
            'contact_number' => '09171234567',
            'address' => 'Test Address',
        ]);

        $response = $this->get(route('admin.api.drivers.index'));
        $data = $response->json();

        // License expiring tomorrow should definitely be in expiring count
        $this->assertGreaterThanOrEqual(1, $data['stats']['expiring']);
    }
}
