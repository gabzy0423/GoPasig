<?php

namespace App\Jobs;

use App\Models\GPSLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CleanupGPSLogsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $days = (int) config('fleet.gps.retention_days', 30);
        $threshold = now()->subDays($days);

        GPSLog::where('timestamp', '<', $threshold)->delete();
    }
}
