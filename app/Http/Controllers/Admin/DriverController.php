<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

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
    public function index()
    {
        $drivers = Driver::orderBy('created_at', 'desc')->get();

        // Calculate Stats
        $onDuty = Driver::where('status', 'active')->count();
        $offDuty = Driver::where('status', 'inactive')->count();
        $suspended = Driver::where('status', 'suspended')->count();

        // License Expiring in <= threshold days (Urgent or Expired)
        $today = Carbon::today();
        $thresholdDays = (int) SystemSetting::get('license_expiry_warning_threshold_days', 30);
        $thresholdDate = Carbon::today()->addDays($thresholdDays);
        $expiring = Driver::whereBetween('license_expiry', [$today->toDateString(), $thresholdDate->toDateString()])
            ->orWhere('license_expiry', '<', $today->toDateString())
            ->count();

        return response()->json([
            'success' => true,
            'drivers' => $drivers,
            'stats' => [
                'on_duty' => $onDuty,
                'off_duty' => $offDuty,
                'suspended' => $suspended,
                'expiring' => $expiring,
            ]
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

        if ($request->status === 'active' && Carbon::parse($request->license_expiry)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot set driver active: license is expired.',
            ], 422);
        }

        // Create corresponding user account
        $firstNameClean = \Illuminate\Support\Str::slug($request->first_name, '');
        $email = $firstNameClean . '@gopasig.com';

        // Ensure email uniqueness
        $counter = 1;
        while (\App\Models\User::where('email', $email)->exists()) {
            $email = $firstNameClean . $counter . '@gopasig.com';
            $counter++;
        }

        $user = \App\Models\User::create([
            'name' => trim($request->first_name . ' ' . $request->last_name),
            'email' => $email,
            'role' => 'driver',
            'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(16)),
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
            'driver' => $driver
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

        if ($request->status === 'active' && Carbon::parse($request->license_expiry)->isPast()) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot set driver active: license is expired.',
            ], 422);
        }

        $driver->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'license_number' => $request->license_number,
            'license_expiry' => $request->license_expiry,
            'status' => $request->status,
            'contact_number' => $request->contact_number,
            'address' => $request->address,
            'emergency_contact' => $request->emergency_contact,
        ]);

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
        $willSuspend = $driver->status !== 'suspended';
        if ($willSuspend) {
            $driver->previous_status = $driver->status;
            $driver->status = 'suspended';
        } else {
            $driver->status = $driver->previous_status ?: 'inactive';
            $driver->previous_status = null;
        }
        $driver->save();

        $action = $willSuspend ? 'suspended' : 'unsuspended';
        return response()->json([
            'success' => true,
            'message' => "Driver {$driver->first_name} {$driver->last_name} {$action} successfully!",
            'driver' => $driver
        ]);
    }
}
