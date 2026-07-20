<?php

namespace App\Events;

use App\Models\GPSLog;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GPSReceived
{
    use Dispatchable, SerializesModels;

    public function __construct(public GPSLog $log) {}
}
