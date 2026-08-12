<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\SystemSetting;
use App\Models\Trip;
use App\Services\AdminDriverManagementService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DriverController extends Controller
{
    /**
     * Show the form for creating a new driver.
     */
    public function create()
    {
        $licenseWarningDays = SystemSetting::get('license_expiry_warning_threshold_days', 30);
        return view('admin.drivers.create', compact('licenseWarningDays'));
    }

    /**
     * Display the specified driver profile.
     */
    public function show(Driver $driver)
    {
        return redirect('/admin/dashboard#drivers-show-' . $driver->id);
    }

    /**
     * Show the form for editing the specified driver.
     */
    public function edit(Driver $driver)
    {
        return redirect('/admin/dashboard#drivers-edit-' . $driver->id);
    }

    /**
     * Display a listing of drivers and associated stats.
     */
    public function index(AdminDriverManagementService $driverManagement)
    {
        $payload = $driverManagement->build();

        return response()->json([
            'success' => true,
            'drivers' => $payload['drivers'],
            'stats' => $payload['stats'],
        ]);
    }

    /**
     * Store a newly created driver in storage.
     */
    public function store(Request $request)
    {
        // Authorization check
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only administrators can create drivers.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|min:2',
            'last_name' => 'required|string|min:2',
            'emp_id' => 'required|string|unique:drivers,emp_id',
            'license_number' => 'required|string|unique:drivers,license_number',
            'license_expiry' => 'required|date',
            'status' => 'required|in:active,inactive,suspended',
            'contact_number' => 'nullable|string',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error. Please verify input formats.',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->status === 'active' && now('Asia/Manila')->greaterThan(Carbon::parse($request->license_expiry)->timezone('Asia/Manila')->endOfDay())) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot set driver active: license is expired.',
            ], 422);
        }

        // Create corresponding user account
        $domain = \App\Models\SystemSetting::get('driver_email_domain', 'gopasig.com');
        $firstNameClean = \Illuminate\Support\Str::slug($request->first_name, '');
        $email = $firstNameClean . '@' . $domain;

        // Ensure email uniqueness
        $counter = 1;
        while (\App\Models\User::where('email', $email)->exists()) {
            $email = $firstNameClean . $counter . '@' . $domain;
            $counter++;
        }

        $defaultPassword = SystemSetting::get('driver_default_password', 'password123');

        $user = \App\Models\User::create([
            'name' => trim($request->first_name . ' ' . $request->last_name),
            'email' => $email,
            'role' => 'driver',
            'password' => \Illuminate\Support\Facades\Hash::make($defaultPassword),
            'email_verified_at' => now(),
        ]);

        $driver = Driver::create([
            'user_id' => $user->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'emp_id' => $request->emp_id,
            'license_number' => $request->license_number,
            'license_expiry' => $request->license_expiry,
            'status' => $request->status,
            'contact_number' => $request->contact_number,
            'address' => $request->address,
            'emergency_contact' => $request->emergency_contact,
            'trips_today' => 0,
            'pax_today' => 0,
            'performance_score' => (int) SystemSetting::get('driver_initial_performance_score', 80),
            'incidents_30' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => "Driver {$driver->first_name} {$driver->last_name} registered successfully!",
            'driver' => $driver,
            'login_credentials' => [
                'email' => $email,
                'password' => $defaultPassword,
            ],
        ]);
    }

    /**
     * Update the specified driver details.
     */
    public function update(Request $request, Driver $driver)
    {
        // Authorization check
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only administrators can update drivers.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|min:2',
            'last_name' => 'required|string|min:2',
            'license_number' => 'required|string|unique:drivers,license_number,' . $driver->id,
            'license_expiry' => 'required|date',
            'status' => 'required|in:active,inactive,suspended',
            'contact_number' => 'nullable|string',
            'address' => 'nullable|string',
            'emergency_contact' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error. Please verify input formats.',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->status === 'active' && now('Asia/Manila')->greaterThan(Carbon::parse($request->license_expiry)->timezone('Asia/Manila')->endOfDay())) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot set driver active: license is expired.',
            ], 422);
        }

        $driver = DB::transaction(function () use ($driver, $request) {
            $lockedDriver = Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();
            $hasActiveTrip = $this->hasActiveTrip($lockedDriver);

            if ($hasActiveTrip && $request->status !== $lockedDriver->status) {
                abort(422, 'Cannot change driver employment status while a dispatched or ongoing trip is active. End or cancel the trip first.');
            }

            $lockedDriver->update([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'license_number' => $request->license_number,
                'license_expiry' => $request->license_expiry,
                'status' => $request->status,
                'contact_number' => $request->contact_number,
                'address' => $request->address,
                'emergency_contact' => $request->emergency_contact,
            ]);

            if ($lockedDriver->user_id) {
                \App\Models\User::where('id', $lockedDriver->user_id)->update([
                    'name' => trim($request->first_name . ' ' . $request->last_name),
                ]);
            }

            return $lockedDriver->fresh();
        });

        return response()->json([
            'success' => true,
            'message' => "Driver {$driver->first_name} {$driver->last_name} updated successfully!",
            'driver' => $driver
        ]);
    }

    /**
     * Remove the specified driver from storage.
     */
    public function destroy(Driver $driver)
    {
        // Authorization check
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only administrators can delete drivers.'
            ], 403);
        }

        // Check for ongoing trip
        $hasOngoingTrip = \App\Models\Trip::where('driver_id', $driver->id)
            ->where('status', 'ongoing')
            ->exists();

        if ($hasOngoingTrip) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete driver with an ongoing trip. End the trip first.'
            ], 422);
        }

        // Also check active status
        if ($driver->status === 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete an active driver. Set status to inactive first.'
            ], 422);
        }

        $name = "{$driver->first_name} {$driver->last_name}";

        // Delete associated user account if it exists
        if ($driver->user_id) {
            \App\Models\User::destroy($driver->user_id);
        }

        $driver->delete();

        return response()->json([
            'success' => true,
            'message' => "Driver record {$name} deleted successfully!"
        ]);
    }

    /**
     * Toggle suspend / unsuspend operational state.
     */
    public function toggleSuspend(Driver $driver)
    {
        [$driver, $willSuspend] = DB::transaction(function () use ($driver) {
            $lockedDriver = Driver::query()->whereKey($driver->id)->lockForUpdate()->firstOrFail();

            if ($this->hasActiveTrip($lockedDriver)) {
                abort(422, 'Cannot suspend or reinstate a driver while a dispatched or ongoing trip is active. End or cancel the trip first.');
            }

            $willSuspend = $lockedDriver->status !== 'suspended';
            if ($willSuspend) {
                $lockedDriver->previous_status = $lockedDriver->status;
                $lockedDriver->status = 'suspended';
            } else {
                $lockedDriver->status = $lockedDriver->previous_status ?: 'inactive';
                $lockedDriver->previous_status = null;
            }
            $lockedDriver->save();

            return [$lockedDriver->fresh(), $willSuspend];
        });

        $action = $willSuspend ? 'suspended' : 'unsuspended';
        return response()->json([
            'success' => true,
            'message' => "Driver {$driver->first_name} {$driver->last_name} {$action} successfully!",
            'driver' => $driver
        ]);
    }

    private function hasActiveTrip(Driver $driver): bool
    {
        return Trip::query()
            ->where('driver_id', $driver->id)
            ->whereIn('status', ['dispatched', 'ongoing'])
            ->exists();
    }
}
