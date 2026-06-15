<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('trips:cleanup-orphaned', function () {
    $expiredTokens = CommuterSession::whereNotNull('expires_at')
        ->where('expires_at', '<=', now())
        ->pluck('session_token');

    // Find WAITING or ON_BUS trips that are expired or orphaned
    $affectedTrips = CommuterTrip::whereIn('status', ['WAITING', 'ON_BUS'])
        ->where(function ($query) use ($expiredTokens) {
            $query->whereIn('session_token', $expiredTokens)
                  ->orWhereNotExists(function ($q) {
                      $q->select(DB::raw(1))
                        ->from('commuter_sessions')
                        ->whereColumn('commuter_sessions.session_token', 'commuter_trips.session_token');
                  });
        })
        ->update([
            'status' => 'CANCELLED',
            'updated_at' => now(),
        ]);

    $this->info("Successfully cancelled {$affectedTrips} orphaned commuter trips.");
})->purpose('Auto-cancel commuter trips with expired sessions');

// Schedule the command to run every 30 minutes
Schedule::command('trips:cleanup-orphaned')->everyThirtyMinutes();
