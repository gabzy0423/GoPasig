<?php

namespace App\Services;

use App\Models\SystemSetting;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DriverPerformanceService
{
    /**
     * Calculate a driver's dynamic performance score for a given date range.
     *
     * Scoring rules (out of 100):
     *   - Deduct `driver_score_incident_penalty` points per incident (default: 10).
     *   - Deduct `driver_score_delay_penalty` points per delayed schedule (default: 5).
     *   - Both penalty values are configurable via Admin → Settings (system_settings table).
     *   - Result is clamped to [0, 100].
     *   - If no incidents and no delays exist in the period, fall back to the driver's
     *     stored profile score (baseline) so a "perfect" period doesn't artificially
     *     override a known long-term score.
     *
     * @param  int          $driverId        Driver primary key.
     * @param  Carbon       $start           Start of the evaluation window (startOfDay).
     * @param  Carbon       $end             End of the evaluation window (endOfDay).
     * @param  float|null   $baselineScore   The driver's stored `performance_score` column
     *                                       value; used as a fallback for zero-incident periods.
     * @return float  Performance score in [0, 100].
     */
    public static function calculateScore(
        int    $driverId,
        Carbon $start,
        Carbon $end,
        ?float $baselineScore = null
    ): float {
        $incidents = DB::table('incidents')
            ->where('driver_id', $driverId)
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $delays = DB::table('schedules')
            ->where('driver_id', $driverId)
            ->where('status', 'delayed')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        // If the period has no negative events, respect the stored baseline
        if ($incidents === 0 && $delays === 0) {
            return (float) ($baselineScore ?? 100.0);
        }

        // Penalty weights are configurable via Admin Settings (system_settings table).
        // Keys: driver_score_incident_penalty (default 10), driver_score_delay_penalty (default 5).
        $incidentPenalty = (float) SystemSetting::get('driver_score_incident_penalty', 10);
        $delayPenalty    = (float) SystemSetting::get('driver_score_delay_penalty', 5);

        $calculated = 100 - ($incidents * $incidentPenalty) - ($delays * $delayPenalty);

        return (float) max(0, min(100, $calculated));
    }
}
