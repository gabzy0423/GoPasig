<?php

namespace App\Jobs;

use App\Services\TelemetryProcessingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessGPSJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $gpsLogId) {}

    public function handle(TelemetryProcessingService $telemetry): void
    {
        Log::info('[GPS_TRACE] F - ProcessGPSJob delegating to TelemetryProcessingService', [
            'gps_log_id'       => $this->gpsLogId,
            'queue_connection' => config('queue.default'),
        ]);

        $telemetry->processGpsLog($this->gpsLogId);
    }
}