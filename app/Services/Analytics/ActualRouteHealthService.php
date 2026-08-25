<?php

namespace App\Services\Analytics;

use Illuminate\Support\Collection;

class ActualRouteHealthService
{
    public function calculate(
        Collection $trips,
        Collection $headwayIntervals,
        Collection $incidents
    ): array {
        $finalizedTrips = $trips
            ->whereIn('status', ['completed', 'cancelled'])
            ->values();
        $operatedTrips = $trips
            ->filter(fn ($trip) => in_array($trip->status, ['completed', 'ongoing', 'cancelled'], true)
                && $trip->started_at !== null)
            ->values();
        $operatedTripIds = $operatedTrips
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $completedCount = $finalizedTrips->where('status', 'completed')->count();
        $completionScore = $this->percentage($completedCount, $finalizedTrips->count());

        [$headwayScore, $headwayDirections, $headwayGapCount] = $this->headwayConsistency(
            $headwayIntervals
        );

        $incidentTripIds = $incidents
            ->pluck('trip_id')
            ->map(fn ($id) => (int) $id)
            ->intersect($operatedTripIds)
            ->unique()
            ->values();
        $incidentFreeCount = max(0, $operatedTrips->count() - $incidentTripIds->count());
        $incidentFreeScore = $this->percentage($incidentFreeCount, $operatedTrips->count());

        $missingEvidence = collect();
        if ($completionScore === null) {
            $missingEvidence->push('No finalized completed or cancelled Trips in the selected period.');
        }
        if ($headwayScore === null) {
            $missingEvidence->push('Headway consistency needs at least two actual gaps in one route direction.');
        }
        if ($incidentFreeScore === null) {
            $missingEvidence->push('No started Trips are available for recorded-incident assessment.');
        }
        $componentScores = collect([
            $completionScore,
            $headwayScore,
            $incidentFreeScore,
        ]);
        $isReady = $componentScores->every(fn ($score) => $score !== null);
        $overallScore = $isReady
            ? (int) round((float) $componentScores->avg())
            : null;

        return [
            'overall_score' => $overallScore,
            'score_label' => $this->scoreLabel($overallScore),
            'data_status' => $isReady ? 'ready' : ($componentScores->filter(fn ($score) => $score !== null)->isEmpty() ? 'empty' : 'partial'),
            'data_status_label' => $isReady ? 'Complete actual evidence' : ($componentScores->filter(fn ($score) => $score !== null)->isEmpty() ? 'No actual data' : 'Insufficient evidence'),
            'completion_score' => $completionScore,
            'headway_score' => $headwayScore,
            'incident_free_score' => $incidentFreeScore,
            'completion_evidence' => $finalizedTrips->isEmpty()
                ? 'No finalized Trips'
                : sprintf('%d of %d finalized Trips completed', $completedCount, $finalizedTrips->count()),
            'headway_evidence' => $headwayScore === null
                ? 'Insufficient same-direction gaps'
                : sprintf('%d direction(s), %d actual gaps assessed', $headwayDirections, $headwayGapCount),
            'incident_evidence' => $operatedTrips->isEmpty()
                ? 'No started Trips'
                : sprintf('%d of %d started Trips had no recorded incident', $incidentFreeCount, $operatedTrips->count()),
            'missing_evidence' => $missingEvidence->values()->all(),
            // Compatibility fields remain explicit while older consumers migrate.
            'on_time_score' => null,
            'stop_adherence_score' => null,
        ];
    }

    private function headwayConsistency(Collection $intervals): array
    {
        $relativeDeviations = collect();
        $assessedDirections = 0;

        $intervals
            ->filter(fn ($row) => isset($row['route_variant_id'], $row['minutes']) && (float) $row['minutes'] >= 0)
            ->groupBy('route_variant_id')
            ->each(function (Collection $directionIntervals) use ($relativeDeviations, &$assessedDirections): void {
                if ($directionIntervals->count() < 2) {
                    return;
                }

                $minutes = $directionIntervals->pluck('minutes')->map(fn ($value) => (float) $value);
                $mean = (float) $minutes->avg();
                if ($mean <= 0) {
                    return;
                }

                $assessedDirections++;
                $minutes->each(fn (float $value) => $relativeDeviations->push(abs($value - $mean) / $mean));
            });

        if ($relativeDeviations->isEmpty()) {
            return [null, 0, 0];
        }

        $score = (int) round(max(0, min(100, 100 - ((float) $relativeDeviations->avg() * 100))));

        return [$score, $assessedDirections, $relativeDeviations->count()];
    }

    private function percentage(int $numerator, int $denominator): ?int
    {
        if ($denominator === 0) {
            return null;
        }

        return (int) round(max(0, min(100, ($numerator / $denominator) * 100)));
    }

    private function scoreLabel(?int $score): string
    {
        if ($score === null) {
            return 'Insufficient evidence';
        }

        return match (true) {
            $score >= 85 => 'Healthy',
            $score >= 70 => 'Monitor',
            default => 'Needs attention',
        };
    }
}
