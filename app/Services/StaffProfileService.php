<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class StaffProfileService
{
    /**
     * Get complete modular profile payload for a user.
     */
    public function getProfilePayload(User $user): array
    {
        if ($user->isStaff() && ! $user->staffProfile) {
            $user->staffProfile()->create([]);
            $user->unsetRelation('staffProfile');
        }

        $user->load('staffProfile');

        $completion = $this->calculateProfileCompletion($user);
        $accountInfo = $this->getAccountInformation($user, $completion);
        $recentActivity = $this->getRecentActivity($user);

        return [
            'success' => true,
            'user' => $this->formatUser($user),
            'staff_profile' => $user->staffProfile ? [
                'contact_number' => $user->staffProfile->contact_number,
                'address' => $user->staffProfile->address,
                'emergency_contact' => $user->staffProfile->emergency_contact,
                'profile_photo_path' => $user->staffProfile->profile_photo_path,
                'profile_photo_url' => $this->getProfilePhotoUrl($user->staffProfile->profile_photo_path),
            ] : null,
            'account_information' => $accountInfo,
            'profile_completion' => $completion,
            'recent_activity' => $recentActivity,
        ];
    }

    /**
     * Update user details and staff profile text fields.
     */
    public function updateProfile(User $user, array $validatedData): array
    {
        if ($user->isStaff() && ! $user->staffProfile) {
            $user->staffProfile()->create([]);
            $user->unsetRelation('staffProfile');
        }

        $profile = $user->staffProfile;

        DB::transaction(function () use ($user, $profile, $validatedData) {
            $user->update([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
            ]);

            if ($profile) {
                $profile->update([
                    'contact_number' => $validatedData['contact_number'] ?? null,
                    'address' => $validatedData['address'] ?? null,
                    'emergency_contact' => $validatedData['emergency_contact'] ?? null,
                ]);
            }

            ActivityLog::create([
                'type' => 'Profile',
                'description' => 'Profile updated',
                'user_id' => $user->id,
            ]);
        });

        return $this->getProfilePayload($user->fresh());
    }

    /**
     * Upload and store a new profile photo for user.
     */
    public function uploadPhoto(User $user, UploadedFile $photo): array
    {
        if ($user->isStaff() && ! $user->staffProfile) {
            $user->staffProfile()->create([]);
            $user->unsetRelation('staffProfile');
        }

        $profile = $user->staffProfile;

        $path = $photo->store('profile-photos', 'public');

        DB::transaction(function () use ($user, $profile, $path) {
            if ($profile->profile_photo_path && Storage::disk('public')->exists($profile->profile_photo_path)) {
                Storage::disk('public')->delete($profile->profile_photo_path);
            }

            $profile->update([
                'profile_photo_path' => $path,
            ]);

            ActivityLog::create([
                'type' => 'Profile',
                'description' => 'Photo uploaded',
                'user_id' => $user->id,
            ]);
        });

        return $this->getProfilePayload($user->fresh());
    }

    /**
     * Delete user profile photo.
     */
    public function deletePhoto(User $user): array
    {
        $profile = $user->staffProfile;

        if ($profile && $profile->profile_photo_path) {
            if (Storage::disk('public')->exists($profile->profile_photo_path)) {
                Storage::disk('public')->delete($profile->profile_photo_path);
            }

            $profile->update([
                'profile_photo_path' => null,
            ]);

            ActivityLog::create([
                'type' => 'Profile',
                'description' => 'Photo removed',
                'user_id' => $user->id,
            ]);
        }

        return $this->getProfilePayload($user->fresh());
    }

    /**
     * Update user password and record activity log & session regeneration.
     */
    public function updatePassword(User $user, string $newPassword, Request $request): array
    {
        DB::transaction(function () use ($user, $newPassword) {
            $user->password = Hash::make($newPassword);
            $user->password_changed_at = now();
            $user->save();

            ActivityLog::create([
                'type' => 'Security',
                'description' => ($user->role === 'fleet_manager' ? 'Fleet Manager' : ucfirst((string) $user->role)) . ' password updated.',
                'user_id' => $user->id,
            ]);
        });

        $request->session()->regenerate();

        return [
            'success' => true,
            'message' => 'Password updated successfully.',
        ];
    }

    /**
     * Calculate dynamic profile completion across the 6 active profile fields.
     */
    public function calculateProfileCompletion(User $user): array
    {
        $profile = $user->staffProfile;

        $fields = [
            ['label' => 'Full Name', 'value' => $user->name],
            ['label' => 'Email Address', 'value' => $user->email],
            ['label' => 'Contact Number', 'value' => $profile?->contact_number ?? null],
            ['label' => 'Address', 'value' => $profile?->address ?? null],
            ['label' => 'Emergency Contact', 'value' => $profile?->emergency_contact ?? null],
            ['label' => 'Profile Photo', 'value' => $profile?->profile_photo_path ?? null],
        ];

        $completed = 0;
        $missing = [];

        foreach ($fields as $field) {
            $val = is_string($field['value']) ? trim($field['value']) : $field['value'];
            if (! empty($val)) {
                $completed++;
            } else {
                $missing[] = $field['label'];
            }
        }

        $total = count($fields); // 6
        $percentage = (int) round(($completed / $total) * 100);

        return [
            'percentage' => $percentage,
            'completed' => $completed,
            'total' => $total,
            'missing' => $missing,
        ];
    }

    /**
     * Calculate and return read-only account security & activity metadata.
     */
    public function getAccountInformation(User $user, array $completion): array
    {
        $userUpdated = $user->updated_at;
        $staffUpdated = $user->staffProfile?->updated_at;

        $lastProfileUpdate = ($staffUpdated && $staffUpdated > $userUpdated) ? $staffUpdated : $userUpdated;

        return [
            'user_id' => $user->id,
            'role' => $user->role,
            'role_display' => ucfirst((string) $user->role),
            'created_at' => $user->created_at ? $user->created_at->toIso8601String() : null,
            'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
            'last_profile_update' => $lastProfileUpdate ? $lastProfileUpdate->toIso8601String() : null,
            'last_password_change' => $user->password_changed_at ? $user->password_changed_at->toIso8601String() : null,
            'profile_completion_percentage' => $completion['percentage'],
        ];
    }

    /**
     * Fetch latest 10 activity logs for the given user.
     */
    public function getRecentActivity(User $user): array
    {
        return ActivityLog::where('user_id', $user->id)
            ->latest('created_at')
            ->latest('id')
            ->take(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'type' => $log->type,
                    'description' => $log->description,
                    'created_at' => $log->created_at ? $log->created_at->toIso8601String() : null,
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Format user instance to a safe JSON representation.
     */
    public function formatUser(User $user): array
    {
        $profile = $user->staffProfile;
        $photoUrl = $this->getProfilePhotoUrl($profile?->profile_photo_path);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'email_verified_at' => $user->email_verified_at ? $user->email_verified_at->toIso8601String() : null,
            'created_at' => $user->created_at ? $user->created_at->toIso8601String() : null,
            'profile_photo_url' => $photoUrl,
            'staff_profile' => $profile ? [
                'contact_number' => $profile->contact_number,
                'address' => $profile->address,
                'emergency_contact' => $profile->emergency_contact,
                'profile_photo_path' => $profile->profile_photo_path,
                'profile_photo_url' => $photoUrl,
            ] : null,
        ];
    }

    /**
     * Get root-relative profile photo URL (e.g. /storage/profile-photos/abc.jpg).
     */
    public function getProfilePhotoUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $url = Storage::url($path);

        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $parsed = parse_url($url, PHP_URL_PATH);
            return $parsed ? '/' . ltrim($parsed, '/') : '/storage/' . ltrim($path, '/');
        }

        return '/' . ltrim($url, '/');
    }
}
