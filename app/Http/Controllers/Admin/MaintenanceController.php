<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use App\Models\Bus;
use App\Models\SystemSetting;
use App\Services\BusinessLogicService;
use App\Services\MaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
    /**
     * Show the form for creating a new maintenance record.
     */
    public function create()
    {
        return redirect('/admin/dashboard#maintenance');
    }

    /**
     * Display a listing of the maintenance records.
     */
    public function index()
    {
        $records = MaintenanceRecord::with('bus')
            ->orderBy('scheduled_at', 'desc')
            ->get();

        return response()->json($records);
    }

    /**
     * Get a single maintenance record by ID.
     */
    public function show($id)
    {
        $record = MaintenanceRecord::with('bus')->findOrFail($id);
        return response()->json($record);
    }

    /**
     * Store a newly created maintenance record in database.
     */
    public function store(Request $request)
    {
        // Admin only
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can create maintenance'
            ], 403);
        }

        $minDuration = (int) SystemSetting::get('maintenance_duration_min_minutes', 15);
        $maxDuration = (int) SystemSetting::get('maintenance_duration_max_minutes', 480);

        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'scheduled_at' => 'required|date',
            'expected_duration_minutes' => "nullable|integer|min:{$minDuration}|max:{$maxDuration}",
        ]);

        $duration = $validated['expected_duration_minutes'] 
            ?? (int) SystemSetting::get('default_maintenance_duration_minutes', 120);

        // Validate duration bounds
        $durationValidation = BusinessLogicService::validateMaintenanceDuration($duration);
        if (!$durationValidation['valid']) {
            return response()->json([
                'success' => false,
                'message' => $durationValidation['error']
            ], 422);
        }

        $record = DB::transaction(function () use ($validated, $duration) {
            $rec = MaintenanceRecord::create([
                'bus_id' => $validated['bus_id'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? '',
                'scheduled_at' => $validated['scheduled_at'],
                'status' => 'scheduled',
                'expected_duration_minutes' => $duration,
            ]);

            // Status Lock: Automatically locks the bus to maintenance
            $bus = Bus::find($validated['bus_id']);
            if ($bus) {
                $bus->lockToMaintenance();
            }

            return $rec;
        });

        return response()->json([
            'success' => true,
            'message' => 'Maintenance scheduled successfully. Bus locked to maintenance status.',
            'record' => $record->load('bus')
        ], 201);
    }

    /**
     * Update maintenance record (work in progress).
     */
    public function update(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $record = MaintenanceRecord::findOrFail($id);

        if ($record->status !== 'scheduled') {
            return response()->json([
                'success' => false,
                'message' => 'Can only update scheduled maintenance'
            ], 422);
        }

        $validated = $request->validate([
            'technician_name' => 'required|string|max:100',
            'technician_notes' => 'nullable|string',
            'cost_php' => 'nullable|numeric|min:0',
        ]);

        $record->update([
            'status' => 'in_progress',
            'technician_name' => $validated['technician_name'],
            'technician_notes' => $validated['technician_notes'] ?? '',
            'cost_php' => $validated['cost_php'] ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Maintenance work started',
            'record' => $record->load('bus')
        ]);
    }

    /**
     * Perform safety inspection on maintenance record.
     * This is the key method: PASSED → can complete, FAILED → stays in_progress
     */
    public function performInspection(Request $request, $id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can perform inspections'
            ], 403);
        }

        $record = MaintenanceRecord::findOrFail($id);

        if ($record->status !== 'in_progress') {
            return response()->json([
                'success' => false,
                'message' => 'Can only inspect in_progress maintenance',
                'current_status' => $record->status
            ], 422);
        }

        $validated = $request->validate([
            'inspection_passed' => 'required|boolean',
            'inspected_by' => 'required|string|max:100',
            'inspection_notes' => 'nullable|string|max:1000',
        ]);

        $passed = $validated['inspection_passed'];

        DB::transaction(function () use ($record, $passed, $validated) {
            $record->update([
                'inspection_passed' => $passed,
                'inspected_by' => $validated['inspected_by'],
                'inspection_notes' => $validated['inspection_notes'] ?? '',
                'inspected_at' => now(),
            ]);

            // CONDITIONAL LOGIC:
            // If PASSED → can now be completed (no status change yet, just unlocked for completion)
            // If FAILED → stays in_progress, bus STAYS maintenance
            if (!$passed) {
                // Inspection failed - bus stays locked, record stays in_progress
                // Admin must go back and do more work
                return;
            }

            // If passed, inspection is complete - ready for final completion
            // Bus still locked until complete() is called
        });

        return response()->json([
            'success' => true,
            'message' => $passed 
                ? '✅ Inspection PASSED - Bus ready to return to service. Click "Complete" button to finish.'
                : '❌ Inspection FAILED - Additional work required before re-inspection',
            'inspection_passed' => $passed,
            'record' => $record->load('bus')
        ]);
    }

    /**
     * Complete maintenance (only allowed if inspection passed).
     * Safety Unlock: Returns bus to active status
     */
    public function complete($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $record = MaintenanceRecord::findOrFail($id);
        
        // Use shared service to complete maintenance
        $result = MaintenanceService::completeMaintenance($record);
        
        return response()->json($result);
    }

    /**
     * Remove the specified maintenance record from storage.
     */
    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        $record = MaintenanceRecord::findOrFail($id);
        
        DB::transaction(function () use ($record) {
            // If deleting uncompleted record, restore bus to prior status
            if ($record->status !== 'completed') {
                $bus = Bus::find($record->bus_id);
                if ($bus) {
                    $restoreStatus = $bus->previous_status ?? 'active';
                    $bus->update(['status' => $restoreStatus, 'previous_status' => null]);
                }
            }
            $record->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Maintenance record deleted successfully!'
        ]);
    }
}
