<?php

namespace App\Services;

use App\Models\DispatchLog;

class DispatchLogService
{
    public static function createDispatchLog(int $tripId, ?int $dispatcherId, string $notes): DispatchLog
    {
        return DispatchLog::create([
            'trip_id'       => $tripId,
            'dispatched_by' => $dispatcherId,
            'dispatched_at' => now(),
            'notes'         => $notes,
        ]);
    }
}
