<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\StaffProfile;
use App\Models\ActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class StaffProfileTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $dispatcherUser;
    protected $driverUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'name' => 'Admin Staff User',
            'email' => 'admin.staff@gopasig.gov.ph',
            'role' => 'admin',
        ]);

        $this->dispatcherUser = User::factory()->create([
            'name' => 'Dispatcher Staff User',
            'email' => 'dispatcher.staff@gopasig.gov.ph',
            'role' => 'fleet_manager',
        ]);

        $this->driverUser = User::factory()->create([
            'name' => 'Driver User',
            'email' => 'driver.staff@gopasig.gov.ph',
            'role' => 'driver',
        ]);
    }

    /** @test */
    public function test_staff_profiles_table_has_expected_fields()
    {
        $this->assertTrue(Schema::hasTable('staff_profiles'));
        $this->assertTrue(Schema::hasColumns('staff_profiles', [
            'id', 'user_id',
            'contact_number', 'address', 'emergency_contact', 'profile_photo_path',
            'created_at', 'updated_at'
        ]));
        $this->assertFalse(Schema::hasColumn('staff_profiles', 'employee_id'));
        $this->assertFalse(Schema::hasColumn('staff_profiles', 'position'));
        $this->assertFalse(Schema::hasColumn('staff_profiles', 'department'));
    }

    /** @test */
    public function test_user_id_is_unique_in_staff_profiles()
    {
        StaffProfile::create(['user_id' => $this->adminUser->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        StaffProfile::create(['user_id' => $this->adminUser->id]);
    }

    /** @test */
    public function test_staff_profile_belongs_to_user()
    {
        $profile = StaffProfile::create([
            'user_id' => $this->adminUser->id,
            'contact_number' => '+63 917 123 4567'
        ]);

        $this->assertInstanceOf(User::class, $profile->user);
        $this->assertEquals($this->adminUser->id, $profile->user->id);
    }

    /** @test */
    public function test_user_has_one_staff_profile()
    {
        $profile = StaffProfile::create(['user_id' => $this->adminUser->id]);

        $this->assertInstanceOf(StaffProfile::class, $this->adminUser->staffProfile);
        $this->assertEquals($profile->id, $this->adminUser->staffProfile->id);
    }

    /** @test */
    public function test_admin_and_dispatcher_are_staff_driver_is_not()
    {
        $this->assertTrue($this->adminUser->isStaff());
        $this->assertTrue($this->dispatcherUser->isStaff());
        $this->assertFalse($this->driverUser->isStaff());
    }

    public function test_backfill_artisan_command_creates_profiles_for_staff_and_skips_drivers()
    {
        StaffProfile::query()->delete();
        Artisan::call('app:backfill-staff-profiles');

        $this->assertDatabaseHas('staff_profiles', ['user_id' => $this->adminUser->id]);
        $this->assertDatabaseHas('staff_profiles', ['user_id' => $this->dispatcherUser->id]);
        $this->assertDatabaseMissing('staff_profiles', ['user_id' => $this->driverUser->id]);

        // Idempotency
        Artisan::call('app:backfill-staff-profiles');
        $this->assertEquals(2, StaffProfile::count());
    }

    /** @test */
    public function test_admin_get_response_includes_staff_profile()
    {
        StaffProfile::create([
            'user_id' => $this->adminUser->id,
            'contact_number' => '+63 917 123 4567',
            'address' => 'Pasig City Hall Complex',
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $this->adminUser->id,
                    'staff_profile' => [
                        'contact_number' => '+63 917 123 4567',
                        'address' => 'Pasig City Hall Complex',
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_missing_staff_profile_created_automatically_on_show()
    {
        $this->assertDatabaseMissing('staff_profiles', ['user_id' => $this->adminUser->id]);

        $response = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');

        $response->assertStatus(200);
        $this->assertDatabaseHas('staff_profiles', ['user_id' => $this->adminUser->id]);
    }

    /** @test */
    public function test_admin_can_update_staff_contact_address_and_emergency_fields()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Updated Admin Staff',
            'email' => 'admin.staff@gopasig.gov.ph',
            'contact_number' => '+63 917 999 8888',
            'address' => 'Pasig City Hall Complex',
            'emergency_contact' => 'Emergency Person - 09181234567',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'user' => [
                    'name' => 'Updated Admin Staff',
                    'staff_profile' => [
                        'contact_number' => '+63 917 999 8888',
                        'address' => 'Pasig City Hall Complex',
                        'emergency_contact' => 'Emergency Person - 09181234567',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $this->adminUser->id,
            'contact_number' => '+63 917 999 8888',
            'address' => 'Pasig City Hall Complex',
        ]);
    }

    /** @test */
    public function test_empty_optional_fields_persist_as_null()
    {
        StaffProfile::create([
            'user_id' => $this->adminUser->id,
            'contact_number' => '+63 917 000 0000',
        ]);

        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Admin Staff User',
            'email' => 'admin.staff@gopasig.gov.ph',
            'contact_number' => '', // Blank input
            'address' => '   ', // Whitespace input
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $this->adminUser->id,
            'contact_number' => null,
            'address' => null,
        ]);
    }

    /** @test */
    public function test_admin_cannot_update_another_user_or_staff_profile()
    {
        $otherProfile = StaffProfile::create([
            'user_id' => $this->dispatcherUser->id,
            'contact_number' => '+63 918 000 0000'
        ]);

        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'id' => $this->dispatcherUser->id,
            'name' => 'Hacked Name',
            'email' => 'admin.staff@gopasig.gov.ph',
            'contact_number' => '+63 999 999 9999',
        ]);

        $response->assertStatus(200);

        $otherProfile->refresh();
        $this->assertEquals('+63 918 000 0000', $otherProfile->contact_number);
    }

    /** @test */
    public function test_admin_cannot_update_profile_photo_path_through_text_endpoint()
    {
        StaffProfile::create(['user_id' => $this->adminUser->id, 'profile_photo_path' => null]);

        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Admin Staff User',
            'email' => 'admin.staff@gopasig.gov.ph',
            'profile_photo_path' => 'hacked/path/photo.jpg',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('staff_profiles', [
            'user_id' => $this->adminUser->id,
            'profile_photo_path' => null,
        ]);
    }

    /** @test */
    public function test_admin_cannot_update_role_or_password()
    {
        $origRole = $this->adminUser->role;
        $origPassword = $this->adminUser->password;

        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Admin Staff User',
            'email' => 'admin.staff@gopasig.gov.ph',
            'role' => 'driver',
            'password' => 'HackedPassword123!',
        ]);

        $response->assertStatus(200);

        $this->adminUser->refresh();
        $this->assertEquals($origRole, $this->adminUser->role);
        $this->assertEquals($origPassword, $this->adminUser->password);
    }

    /** @test */
    public function test_activity_log_is_recorded_without_sensitive_field_values()
    {
        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile', [
            'name' => 'Admin Staff User',
            'email' => 'admin.staff@gopasig.gov.ph',
            'address' => 'Secret Home Address 123',
            'emergency_contact' => 'Secret Contact Info',
        ]);

        $response->assertStatus(200);

        $log = ActivityLog::where('user_id', $this->adminUser->id)->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('Secret Home Address', $log->description);
        $this->assertStringNotContainsString('Secret Contact Info', $log->description);
    }

    /** @test */
    public function test_profile_blade_renders_contact_fields_without_employee_id_position_department()
    {
        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');

        $response->assertStatus(200);
        $response->assertSee('id="screen-profile"', false);
        $response->assertSee('id="admin-profile-contact-number"', false);
        $response->assertSee('id="admin-profile-address"', false);
        $response->assertSee('id="admin-profile-emergency-contact"', false);

        // Verify omitted fields are absent from Blade UI
        $blade = file_get_contents(resource_path('views/admin/profile/index.blade.php'));
        $this->assertStringNotContainsString('admin-profile-employee-id', $blade);
        $this->assertStringNotContainsString('admin-profile-position', $blade);
        $this->assertStringNotContainsString('admin-profile-department', $blade);
        $this->assertStringNotContainsString('type="password"', $blade);
    }
}
