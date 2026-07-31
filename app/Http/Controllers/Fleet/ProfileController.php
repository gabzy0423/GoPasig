<?php

namespace App\Http\Controllers\Fleet;

use App\Http\Controllers\Controller;
use App\Services\StaffProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    protected StaffProfileService $staffProfileService;

    public function __construct(StaffProfileService $staffProfileService)
    {
        $this->staffProfileService = $staffProfileService;
    }

    /**
     * Display the authenticated Fleet Operations Manager's profile details.
     */
    public function show(Request $request)
    {
        $payload = $this->staffProfileService->getProfilePayload($request->user());
        return response()->json($payload);
    }

    /**
     * Update the authenticated Fleet Operations Manager's account name, email, and staff profile fields.
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
        ]);

        $payload = $this->staffProfileService->updateProfile($user, $validated);
        $payload['message'] = 'Profile updated successfully.';

        return response()->json($payload);
    }

    /**
     * Upload profile photo for authenticated Fleet Operations Manager.
     */
    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
        ]);

        $payload = $this->staffProfileService->uploadPhoto($request->user(), $request->file('photo'));
        $payload['message'] = 'Profile photo uploaded successfully.';

        return response()->json($payload);
    }

    /**
     * Remove profile photo for authenticated Fleet Operations Manager.
     */
    public function deletePhoto(Request $request)
    {
        $payload = $this->staffProfileService->deletePhoto($request->user());
        $payload['message'] = 'Profile photo removed successfully.';

        return response()->json($payload);
    }

    /**
     * Update the authenticated Fleet Operations Manager's account password.
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed', 'different:current_password'],
            'new_password_confirmation' => ['required', 'string'],
        ], [
            'current_password.required' => 'Current password is required.',
            'new_password.required' => 'New password is required.',
            'new_password.min' => 'The new password must be at least 8 characters.',
            'new_password.confirmed' => 'New password confirmation does not match.',
            'new_password.different' => 'The new password must be different from your current password.',
            'new_password_confirmation.required' => 'Please confirm your new password.',
        ]);

        if (! Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password provided is incorrect.'],
            ]);
        }

        $result = $this->staffProfileService->updatePassword($user, $request->new_password, $request);

        return response()->json($result);
    }
}
