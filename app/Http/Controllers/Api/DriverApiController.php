<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Trip;
use App\Models\GPSLog;
use App\Services\TelemetryProcessingService;
use App\Events\TripStarted;
use App\Events\TripCompleted;
use App\Services\TripLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DriverApiController extends Controller
{
    /**
     * Start a new active trip.
     */
    public function startTrip(int $tripId, TripLifecycleService $tripLifecycleService)
    {
        $trip = Trip::findOrFail($tripId);

        try {
            $tripLifecycleService->startTrip($trip);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }

        event(new TripStarted($tripId));

        return response()->json([
            'success' => true,
            'message' => 'Trip started successfully.',
            'trip_id' => $tripId
        ]);
    }

    /**
     * Complete an ongoing trip.
     */
    public function completeTrip(int $tripId, TripLifecycleService $tripLifecycleService)
    {
        $trip = Trip::findOrFail($tripId);

        try {
            $tripLifecycleService->completeTrip($trip);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 422);
        }

        event(new TripCompleted($tripId));

        return response()->json([
            'success' => true,
            'message' => 'Trip completed successfully.',
            'trip_id' => $tripId
        ]);
    }

    /**
     * Ingest location telemetry for an active trip.
     */
    public function updateLocation(Request $request, int $tripId, TelemetryProcessingService $telemetry)
    {
        $trip = Trip::findOrFail($tripId);

        // Reject if trip is not ongoing or GPS session is not active
        if ($trip->status !== 'ongoing' || $trip->gps_session !== 'ACTIVE') {
            return response()->json([
                'success' => false,
                'message' => 'Live trip session has not started or is closed.'
            ], 409);
        }

        $validator = Validator::make($request->all(), [
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'speed' => 'required|numeric|min:0',
            'heading' => 'nullable|numeric|between:0,360',
            'accuracy' => 'nullable|numeric|min:0',
            'timestamp' => 'required',
            'gps_fix_timestamp' => 'nullable|date',
            'gps_fix_age_ms' => 'nullable|integer|min:0',
            'is_cached_fix' => 'nullable|boolean',
            'speed_source' => 'nullable|string|in:native,calculated,cached',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Log the raw telemetry entry
        $log = GPSLog::create([
            'trip_id' => $tripId,
            'lat' => (float) $request->lat,
            'lng' => (float) $request->lng,
            'speed' => (float) $request->speed,
            'heading' => $request->input('heading') !== null ? (float) $request->input('heading') : null,
            'accuracy' => $request->has('accuracy') ? (float) $request->accuracy : null,
            'timestamp' => \Carbon\Carbon::parse($request->timestamp),
            'received_at' => \Carbon\CarbonImmutable::now('UTC'),
            'gps_fix_timestamp' => $request->filled('gps_fix_timestamp') ? \Carbon\Carbon::parse($request->input('gps_fix_timestamp')) : null,
            'gps_fix_age_ms' => $request->has('gps_fix_age_ms') ? (int) $request->input('gps_fix_age_ms') : null,
            'is_cached_fix' => $request->boolean('is_cached_fix'),
            'speed_source' => $request->input('speed_source'),
            'processing_status' => 'pending',
        ]);

        $result = $telemetry->processGpsLog($log->id);

        if (($result['status'] ?? null) !== 'processed') {
            return response()->json([
                'success' => false,
                'message' => 'GPS telemetry was received but failed live processing.',
                'status' => $result['status'] ?? 'unknown',
                'error' => $result['error'] ?? null,
                'log_id' => $log->id,
                'processing_ms' => $result['processing_ms'] ?? null,
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => 'GPS telemetry processed.',
            'log_id' => $log->id,
            'processing_ms' => $result['processing_ms'] ?? null,
            'speed_unit' => 'm/s',
        ]);
    }
}





