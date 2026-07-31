<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminProfileHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'name' => 'Hardening Admin',
            'email' => 'admin.hardening@gopasig.gov.ph',
            'role' => 'admin',
            'password' => Hash::make('OldSecret123!'),
        ]);

        $this->otherUser = User::factory()->create([
            'name' => 'Other Admin',
            'email' => 'other.admin@gopasig.gov.ph',
            'role' => 'admin',
            'password' => Hash::make('OtherSecret123!'),
        ]);
    }

    /** @test */
    public function test_password_changed_at_updates_on_password_change()
    {
        $this->assertNull($this->adminUser->password_changed_at);

        $response = $this->actingAs($this->adminUser)->putJson('/admin/api/profile/password', [
            'current_password' => 'OldSecret123!',
            'new_password' => 'NewSecret456!',
            'new_password_confirmation' => 'NewSecret456!',
        ]);

        $response->assertStatus(200);

        $freshUser = $this->adminUser->fresh();
        $this->assertNotNull($freshUser->password_changed_at);
        $this->assertTrue($freshUser->password_changed_at->isToday());
    }

    /** @test */
    public function test_profile_completion_calculation_full_completion()
    {
        $this->adminUser->staffProfile()->create([
            'contact_number' => '09171234567',
            'address' => 'Pasig City Hall, Caruncho Ave, Pasig',
            'emergency_contact' => 'Jane Doe - 09181234567',
            'profile_photo_path' => 'profile-photos/admin.jpg',
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'profile_completion' => [
                    'percentage' => 100,
                    'completed' => 6,
                    'total' => 6,
                    'missing' => [],
                ],
            ]);
    }

    /** @test */
    public function test_profile_completion_calculation_partial_completion()
    {
        $this->adminUser->staffProfile()->create([
            'contact_number' => '09179876543',
            'profile_photo_path' => 'profile-photos/admin2.jpg',
            'address' => null,
            'emergency_contact' => null,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'profile_completion' => [
                    'percentage' => 67,
                    'completed' => 4,
                    'total' => 6,
                    'missing' => [
                        'Address',
                        'Emergency Contact',
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_last_profile_update_takes_latest_of_user_or_staff_profile_timestamp()
    {
        $profile = $this->adminUser->staffProfile()->create([
            'contact_number' => '09170001111',
        ]);

        // Manually push staffProfile updated_at ahead of user updated_at
        $futureTime = now()->addMinutes(15);
        $profile->timestamps = false;
        $profile->updated_at = $futureTime;
        $profile->save();

        $response = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');

        $response->assertStatus(200);

        $json = $response->json();
        $this->assertEquals(
            $futureTime->toIso8601String(),
            $json['account_information']['last_profile_update']
        );
    }

    /** @test */
    public function test_recent_activity_returns_latest_ten_records_ordered_by_created_at()
    {
        for ($i = 1; $i <= 15; $i++) {
            ActivityLog::create([
                'type' => 'Profile',
                'description' => "Activity event #{$i}",
                'user_id' => $this->adminUser->id,
                'created_at' => now()->addMinutes($i),
            ]);
        }

        $response = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');

        $response->assertStatus(200);
        $recent = $response->json('recent_activity');

        $this->assertCount(10, $recent);
        $this->assertEquals('Activity event #15', $recent[0]['description']);
        $this->assertEquals('Activity event #6', $recent[9]['description']);
    }

    /** @test */
    public function test_recent_activity_only_includes_current_user_logs()
    {
        ActivityLog::create([
            'type' => 'Security',
            'description' => 'Current admin action',
            'user_id' => $this->adminUser->id,
        ]);

        ActivityLog::create([
            'type' => 'Security',
            'description' => 'Other admin action',
            'user_id' => $this->otherUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');

        $response->assertStatus(200);
        $recent = $response->json('recent_activity');

        $this->assertCount(1, $recent);
        $this->assertEquals('Current admin action', $recent[0]['description']);
    }

    /** @test */
    public function test_account_information_payload_does_not_expose_password_hashes_or_tokens()
    {
        $response = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'user',
                'staff_profile',
                'account_information' => [
                    'user_id',
                    'role',
                    'role_display',
                    'created_at',
                    'email_verified_at',
                    'last_profile_update',
                    'last_password_change',
                    'profile_completion_percentage',
                ],
                'profile_completion' => [
                    'percentage',
                    'completed',
                    'total',
                    'missing',
                ],
                'recent_activity',
            ]);

        $content = $response->getContent();
        $this->assertStringNotContainsString($this->adminUser->password, $content);
        $this->assertStringNotContainsString('remember_token', $content);
    }
}
