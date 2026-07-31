<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DispatcherProfileHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected $dispatcherUser;
    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dispatcherUser = User::factory()->create([
            'name' => 'Dispatcher Hardening',
            'email' => 'dispatcher.hardening@gopasig.gov.ph',
            'role' => 'fleet_manager',
            'password' => Hash::make('HardeningPass123!'),
        ]);

        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin.hardening@gopasig.gov.ph',
            'role' => 'admin',
        ]);
    }

    /** @test */
    public function test_dispatcher_profile_completion_full_6_fields()
    {
        $this->dispatcherUser->staffProfile()->create([
            'contact_number' => '09173334444',
            'address' => 'Pasig Central Terminal',
            'emergency_contact' => 'Jane Smith - 09183334444',
            'profile_photo_path' => 'profile-photos/dispatcher.jpg',
        ]);

        $response = $this->actingAs($this->dispatcherUser)->getJson('/fleet/api/profile');

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
    public function test_dispatcher_profile_completion_partial_fields()
    {
        $this->dispatcherUser->staffProfile()->create([
            'contact_number' => '09173334444',
            'profile_photo_path' => 'profile-photos/dispatcher2.jpg',
            'address' => null,
            'emergency_contact' => null,
        ]);

        $response = $this->actingAs($this->dispatcherUser)->getJson('/fleet/api/profile');

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
    public function test_recent_activity_only_returns_dispatcher_logs()
    {
        ActivityLog::create([
            'type' => 'Profile',
            'description' => 'Dispatcher log entry',
            'user_id' => $this->dispatcherUser->id,
        ]);

        ActivityLog::create([
            'type' => 'Profile',
            'description' => 'Admin log entry',
            'user_id' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->dispatcherUser)->getJson('/fleet/api/profile');

        $response->assertStatus(200);
        $recent = $response->json('recent_activity');

        $this->assertCount(1, $recent);
        $this->assertEquals('Dispatcher log entry', $recent[0]['description']);
    }

    /** @test */
    public function test_dispatcher_payload_does_not_expose_password_or_tokens()
    {
        $response = $this->actingAs($this->dispatcherUser)->getJson('/fleet/api/profile');

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
        $this->assertStringNotContainsString($this->dispatcherUser->password, $content);
        $this->assertStringNotContainsString('remember_token', $content);
    }
}
