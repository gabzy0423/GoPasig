<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DispatcherProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected $dispatcherUser;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->dispatcherUser = User::factory()->create([
            'name' => 'Dispatcher Photo Test',
            'email' => 'dispatcher.photo@gopasig.gov.ph',
            'role' => 'fleet_manager',
        ]);
    }

    /** @test */
    public function test_guest_cannot_upload_or_delete_dispatcher_profile_photo()
    {
        $file = UploadedFile::fake()->create('test.jpg', 10, 'image/jpeg');

        $uploadResponse = $this->postJson('/fleet/api/profile/photo', ['photo' => $file]);
        $uploadResponse->assertStatus(401);

        $deleteResponse = $this->deleteJson('/fleet/api/profile/photo');
        $deleteResponse->assertStatus(401);
    }

    /** @test */
    public function test_valid_dispatcher_photo_upload_stores_file_and_returns_root_relative_url()
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 10, 'image/jpeg');

        $response = $this->actingAs($this->dispatcherUser)->postJson('/fleet/api/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile photo uploaded successfully.',
            ]);

        $profile = $this->dispatcherUser->fresh()->staffProfile;
        $this->assertNotNull($profile->profile_photo_path);
        Storage::disk('public')->assertExists($profile->profile_photo_path);

        $photoUrl = $response->json('user.profile_photo_url');
        $this->assertStringStartsWith('/storage/profile-photos/', $photoUrl);
    }

    /** @test */
    public function test_dispatcher_photo_deletion_removes_file_and_resets_database_path()
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 10, 'image/jpeg');
        $this->actingAs($this->dispatcherUser)->postJson('/fleet/api/profile/photo', ['photo' => $file]);

        $path = $this->dispatcherUser->fresh()->staffProfile->profile_photo_path;
        Storage::disk('public')->assertExists($path);

        $response = $this->deleteJson('/fleet/api/profile/photo');
        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile photo removed successfully.',
            ]);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($this->dispatcherUser->fresh()->staffProfile->profile_photo_path);
    }

    /** @test */
    public function test_activity_log_records_dispatcher_photo_actions()
    {
        $file = UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg');
        $this->actingAs($this->dispatcherUser)->postJson('/fleet/api/profile/photo', ['photo' => $file]);

        $log = ActivityLog::where('user_id', $this->dispatcherUser->id)->latest('id')->first();
        $this->assertEquals('Profile', $log->type);
        $this->assertEquals('Photo uploaded', $log->description);

        $this->deleteJson('/fleet/api/profile/photo');

        $logRemove = ActivityLog::where('user_id', $this->dispatcherUser->id)->latest('id')->first();
        $this->assertEquals('Profile', $logRemove->type);
        $this->assertEquals('Photo removed', $logRemove->description);
    }
}
