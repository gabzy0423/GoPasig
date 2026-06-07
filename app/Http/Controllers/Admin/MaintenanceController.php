<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaintenanceRecord;
use App\Models\Bus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceController extends Controller
{
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
     * Store a newly created maintenance record in database.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'bus_id' => 'required|exists:buses,id',
            'type' => 'required|string|max:100',
            'description' => 'nullable|string',
            'scheduled_at' => 'required|date',
            'status' => 'nullable|in:scheduled,in_progress,completed,cancelled',
        ]);

        $status = $validated['status'] ?? 'scheduled';

        $record = DB::transaction(function () use ($validated, $status) {
            $rec = MaintenanceRecord::create([
                'bus_id' => $validated['bus_id'],
                'type' => $validated['type'],
                'description' => $validated['description'] ?? '',
                'scheduled_at' => $validated['scheduled_at'],
                'status' => $status,
            ]);

            // Status Lock: Automatically locks the bus operational status (sets to inactive)
            if (in_array($status, ['scheduled', 'in_progress'])) {
                $bus = Bus::find($validated['bus_id']);
                if ($bus) {
                    $bus->update(['status' => 'inactive']);
                }
            }

            return $rec;
        });

        return response()->json([
            'success' => true,
            'message' => 'Maintenance scheduled successfully. Bus status locked to inactive!',
            'record' => $record->load('bus')
        ], 201);
    }

    /**
     * Mark a maintenance record as completed.
     */
    public function complete($id)
    {
        $record = MaintenanceRecord::findOrFail($id);

        DB::transaction(function () use ($record) {
            $record->update(['status' => 'completed']);

            // Safety Unlock: Restores the assigned bus status back to 'active'
            $bus = Bus::find($record->bus_id);
            if ($bus) {
                $bus->update(['status' => 'active']);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Maintenance completed successfully. Bus status unlocked to active!',
            'record' => $record->load('bus')
        ]);
    }

    /**
     * Remove the specified maintenance record from storage.
     */
    public function destroy($id)
    {
        $record = MaintenanceRecord::findOrFail($id);
        
        DB::transaction(function () use ($record) {
            // If deleting an uncompleted record, unlock the bus to be safe
            if (in_array($record->status, ['scheduled', 'in_progress'])) {
                $bus = Bus::find($record->bus_id);
                if ($bus) {
                    $bus->update(['status' => 'active']);
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
