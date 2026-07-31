<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;
    protected $nonAdminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin.photo@gopasig.gov.ph',
            'role' => 'admin',
        ]);

        $this->nonAdminUser = User::factory()->create([
            'name' => 'Driver User',
            'email' => 'driver.photo@gopasig.gov.ph',
            'role' => 'driver',
        ]);

        Storage::fake('public');
    }

    /** @test */
    public function test_guest_cannot_upload_or_delete_profile_photo()
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $uploadResponse = $this->postJson('/admin/api/profile/photo', [
            'photo' => $file,
        ]);
        $uploadResponse->assertStatus(401);

        $deleteResponse = $this->deleteJson('/admin/api/profile/photo');
        $deleteResponse->assertStatus(401);
    }

    /** @test */
    public function test_non_admin_cannot_upload_or_delete_profile_photo()
    {
        $file = UploadedFile::fake()->create('avatar.jpg', 100, 'image/jpeg');

        $uploadResponse = $this->actingAs($this->nonAdminUser)->postJson('/admin/api/profile/photo', [
            'photo' => $file,
        ]);
        $uploadResponse->assertStatus(403);

        $deleteResponse = $this->actingAs($this->nonAdminUser)->deleteJson('/admin/api/profile/photo');
        $deleteResponse->assertStatus(403);
    }

    /** @test */
    public function test_invalid_mime_types_are_rejected()
    {
        $invalidTypes = [
            'document.pdf' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
            'archive.zip' => UploadedFile::fake()->create('archive.zip', 100, 'application/zip'),
            'graphic.svg' => UploadedFile::fake()->create('graphic.svg', 100, 'image/svg+xml'),
            'animation.gif' => UploadedFile::fake()->create('animation.gif', 100, 'image/gif'),
            'script.php' => UploadedFile::fake()->create('script.php', 100, 'text/x-php'),
            'code.js' => UploadedFile::fake()->create('code.js', 100, 'text/javascript'),
        ];

        foreach ($invalidTypes as $fileName => $file) {
            $response = $this->actingAs($this->adminUser)->postJson('/admin/api/profile/photo', [
                'photo' => $file,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['photo']);
        }
    }

    /** @test */
    public function test_oversized_image_is_rejected()
    {
        $oversizedFile = UploadedFile::fake()->create('huge.jpg', 2049, 'image/jpeg');

        $response = $this->actingAs($this->adminUser)->postJson('/admin/api/profile/photo', [
            'photo' => $oversizedFile,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['photo']);
    }

    /** @test */
    public function test_valid_photo_upload_stores_file_and_returns_root_relative_url()
    {
        $file = UploadedFile::fake()->create('profile.jpg', 200, 'image/jpeg');

        $response = $this->actingAs($this->adminUser)->postJson('/admin/api/profile/photo', [
            'photo' => $file,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile photo uploaded successfully.',
            ]);

        $this->adminUser->refresh()->load('staffProfile');
        $photoPath = $this->adminUser->staffProfile->profile_photo_path;

        $this->assertNotNull($photoPath);
        $this->assertStringStartsWith('profile-photos/', $photoPath);
        Storage::disk('public')->assertExists($photoPath);

        $photoUrl = $response->json('user.profile_photo_url');
        $this->assertStringStartsWith('/storage/profile-photos/', $photoUrl);
        $this->assertStringNotContainsString(config('app.url'), $photoUrl);
        $this->assertStringNotContainsString('ngrok', $photoUrl);
    }

    /** @test */
    public function test_profile_photo_url_is_root_relative_and_does_not_contain_app_url_hostname()
    {
        $file = UploadedFile::fake()->create('test_avatar.jpg', 120, 'image/jpeg');
        $this->actingAs($this->adminUser)->postJson('/admin/api/profile/photo', ['photo' => $file]);

        $getResponse = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');
        $getResponse->assertStatus(200);

        $photoUrl = $getResponse->json('user.profile_photo_url');
        $staffPhotoUrl = $getResponse->json('user.staff_profile.profile_photo_url');

        $this->assertStringStartsWith('/storage/profile-photos/', $photoUrl);
        $this->assertStringStartsWith('/storage/profile-photos/', $staffPhotoUrl);

        $appUrlHost = parse_url(config('app.url'), PHP_URL_HOST);
        if ($appUrlHost) {
            $this->assertStringNotContainsString($appUrlHost, $photoUrl);
        }
    }

    /** @test */
    public function test_null_profile_photo_path_returns_null_url()
    {
        $getResponse = $this->actingAs($this->adminUser)->getJson('/admin/api/profile');
        $getResponse->assertStatus(200)
            ->assertJson([
                'user' => [
                    'profile_photo_url' => null,
                    'staff_profile' => [
                        'profile_photo_path' => null,
                        'profile_photo_url' => null,
                    ],
                ],
            ]);
    }

    /** @test */
    public function test_topbar_blade_renders_root_relative_photo_url()
    {
        $file = UploadedFile::fake()->create('topbar_photo.png', 100, 'image/png');
        $this->actingAs($this->adminUser)->postJson('/admin/api/profile/photo', ['photo' => $file]);

        $response = $this->actingAs($this->adminUser)->get('/admin/dashboard');
        $response->assertStatus(200);

        $this->adminUser->refresh()->load('staffProfile');
        $filename = basename($this->adminUser->staffProfile->profile_photo_path);

        $response->assertSee('/storage/profile-photos/' . $filename, false);
        $response->assertDontSee(config('app.url') . '/storage/profile-photos/' . $filename, false);
    }

    /** @test */
    public function test_uploading_new_photo_deletes_old_photo_from_storage()
    {
        $firstFile = UploadedFile::fake()->create('first.png', 100, 'image/png');
        $this->actingAs($this->adminUser)->postJson('/admin/api/profile/photo', ['photo' => $firstFile]);

        $this->adminUser->refresh()->load('staffProfile');
        $firstPath = $this->adminUser->staffProfile->profile_photo_path;
        Storage::disk('public')->assertExists($firstPath);

        $secondFile = UploadedFile::fake()->create('second.webp', 100, 'image/webp');
        $this->actingAs($this->adminUser)->postJson('/admin/api/profile/photo', ['photo' => $secondFile]);

        $this->adminUser->refresh()->load('staffProfile');
        $secondPath = $this->adminUser->staffProfile->profile_photo_path;

        $this->assertNotEquals($firstPath, $secondPath);
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
    }

    /** @test */
    public function test_delete_photo_removes_db_path_and_deletes_physical_file()
    {
        $file = UploadedFile::fake()->create('photo.jpg', 150, 'image/jpeg');
        $this->actingAs($this->adminUser)->postJson('/admin/api/profile/photo', ['photo' => $file]);

        $this->adminUser->refresh()->load('staffProfile');
        $photoPath = $this->adminUser->staffProfile->profile_photo_path;
        Storage::disk('public')->assertExists($photoPath);

        $response = $this->actingAs($this->adminUser)->deleteJson('/admin/api/profile/photo');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile photo removed successfully.',
                'user' => [
                    'profile_photo_url' => null,
                    'staff_profile' => [
                        'profile_photo_path' => null,
                        'profile_photo_url' => null,
                    ],
                ],
            ]);

        $this->adminUser->refresh()->load('staffProfile');
        $this->assertNull($this->adminUser->staffProfile->profile_photo_path);
        Storage::disk('public')->assertMissing($photoPath);
    }

    /** @test */
    public function test_delete_photo_returns_success_when_photo_is_already_null()
    {
        $this->assertNull($this->adminUser->staffProfile?->profile_photo_path);

        $response = $this->actingAs($this->adminUser)->deleteJson('/admin/api/profile/photo');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Profile photo removed successfully.',
                'user' => [
                    'profile_photo_url' => null,
                ],
            ]);
    }

    /** @test */
    public function test_activity_log_records_photo_upload_and_removal_without_filenames()
    {
        $file = UploadedFile::fake()->create('private_filename_xyz.jpg', 100, 'image/jpeg');
        $this->actingAs($this->adminUser)->postJson('/admin/api/profile/photo', ['photo' => $file]);

        $uploadLog = ActivityLog::where('user_id', $this->adminUser->id)->latest('id')->first();
        $this->assertNotNull($uploadLog);
        $this->assertEquals('Photo uploaded', $uploadLog->description);
        $this->assertStringNotContainsString('private_filename_xyz', $uploadLog->description);

        $this->actingAs($this->adminUser)->deleteJson('/admin/api/profile/photo');

        $deleteLog = ActivityLog::where('user_id', $this->adminUser->id)->latest('id')->first();
        $this->assertNotNull($deleteLog);
        $this->assertEquals('Photo removed', $deleteLog->description);
        $this->assertStringNotContainsString('private_filename_xyz', $deleteLog->description);
    }
}
