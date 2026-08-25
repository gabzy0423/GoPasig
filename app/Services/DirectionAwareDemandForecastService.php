<?php

namespace App\Services;

use App\Models\Bus;
use App\Models\DemandHistory;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\SystemSetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DirectionAwareDemandForecastService
{
    public const DEFAULT_LOOKBACK_WEEKS = 8;

    public const DEFAULT_MINIMUM_SAMPLES = 3;

    public function __construct(
        private readonly DemandHistoryTimeSlotService $timeSlots,
        private readonly RouteServiceScheduleEvaluator $serviceSchedules
    ) {}

    public function forecastForDate(CarbonInterface|string $targetDate): array
    {
        $target = $this->timeSlots->asManila($targetDate)->startOfDay();
        $lookbackWeeks = $this->boundedSetting('demand_forecast_lookback_weeks', self::DEFAULT_LOOKBACK_WEEKS, 1, 52);
        $minimumSamples = $this->boundedSetting('demand_forecast_minimum_samples', self::DEFAULT_MINIMUM_SAMPLES, 1, $lookbackWeeks);
        $referenceCapacity = max(1, Bus::getDefaultCapacity());
        $historyStart = $target->subWeeks($lookbackWeeks);
        $historyEnd = $target->subDay();

        $routes = Route::publicCommuterActiveService()
            ->with(['variants' => fn ($query) => $query->orderBy('id')])
            ->get();
        $routeIds = $routes->pluck('id');

        $histories = $routeIds->isEmpty()
            ? collect()
            : DemandHistory::forecastEligible()
                ->whereIn('route_id', $routeIds)
                ->where('day_of_week', $target->englishDayOfWeek)
                ->whereBetween('date', [$historyStart->toDateString(), $historyEnd->toDateString()])
                ->where('finalized_at', '<', $target->setTimezone('UTC'))
                ->get()
                ->groupBy(fn (DemandHistory $history) => $this->historyKey(
                    (int) $history->route_variant_id,
                    $history->time_slot
                ));

        $slotWindows = $this->timeSlots->windowsForDate($target);
        $rows = collect();

        foreach ($routes as $route) {
            foreach ($route->variants as $variant) {
                $officialWindows = $this->serviceSchedules->activeWindowsForVariantOn($variant, $target);

                foreach ($slotWindows as $slotWindow) {
                    $overlaps = $this->officialOverlaps($target, $slotWindow, $officialWindows);

                    if ($overlaps->isEmpty()) {
                        continue;
                    }

                    $samples = $histories->get(
                        $this->historyKey((int) $variant->id, $slotWindow['label']),
                        collect()
                    );
                    $sampleCount = $samples->count();
                    $confidence = $this->confidence($sampleCount, $minimumSamples, $lookbackWeeks);
                    $isReady = $sampleCount >= $minimumSamples;
                    $expectedCommuters = $isReady
                        ? round((float) $samples->avg('total_commuters'), 1)
                        : null;

                    $rows->push([
                        'target_date' => $target->toDateString(),
                        'day_of_week' => $target->englishDayOfWeek,
                        'route_id' => (int) $route->id,
                        'route_name' => $route->name,
                        'route_color' => $route->color,
                        'route_variant_id' => (int) $variant->id,
                        'direction' => strtolower((string) $variant->direction),
                        'direction_label' => $this->directionLabel($variant),
                        'origin_name' => $variant->origin_name,
                        'destination_name' => $variant->destination_name,
                        'time_slot' => $slotWindow['label'],
                        'official_windows' => $overlaps->pluck('official_window')->unique()->values()->all(),
                        'service_periods' => $overlaps->pluck('service_period')->unique()->values()->all(),
                        'expected_commuters' => $expectedCommuters,
                        'sample_count' => $sampleCount,
                        'minimum_samples' => $minimumSamples,
                        'confidence' => $confidence['key'],
                        'confidence_label' => $confidence['label'],
                        'minimum_buses' => $isReady
                            ? ($expectedCommuters > 0 ? (int) ceil($expectedCommuters / $referenceCapacity) : 0)
                            : null,
                        'reference_bus_capacity' => $referenceCapacity,
                        'status' => $isReady ? 'ready' : 'insufficient_history',
                        'status_label' => $isReady ? 'Advisory ready' : 'Insufficient finalized history',
                        'basis' => $isReady
                            ? "Average of {$sampleCount} same-weekday finalized buckets"
                            : "{$sampleCount} of {$minimumSamples} required finalized buckets",
                        'advisory_only' => true,
                    ]);
                }
            }
        }

        $rows = $rows
            ->sortBy(fn (array $row) => sprintf(
                '%04d-%s-%s',
                $row['route_id'],
                $row['time_slot'],
                $row['direction'] === 'outbound' ? '0' : '1'
            ))
            ->values();

        $routeSummaries = $routes->map(fn (Route $route) => $this->summarize(
            $rows->where('route_id', (int) $route->id)->values(),
            $target,
            (int) $route->id,
            $route->name
        ))->values();

        return [
            'target_date' => $target->toDateString(),
            'day_of_week' => $target->englishDayOfWeek,
            'lookback_weeks' => $lookbackWeeks,
            'minimum_samples' => $minimumSamples,
            'reference_bus_capacity' => $referenceCapacity,
            'advisory_only' => true,
            'rows' => $rows->all(),
            'route_summaries' => $routeSummaries->all(),
            'overall_summary' => $this->summarize($rows, $target),
        ];
    }

    private function summarize(
        Collection $rows,
        CarbonImmutable $target,
        ?int $routeId = null,
        ?string $routeName = null
    ): array {
        $readyRows = $rows->where('status', 'ready')->values();
        $serviceSlots = $rows->count();
        $readySlots = $readyRows->count();

        if ($serviceSlots === 0) {
            $status = 'no_official_service';
            $statusLabel = 'No official service';
        } elseif ($readySlots === 0) {
            $status = 'insufficient_history';
            $statusLabel = 'Insufficient finalized history';
        } elseif ($readySlots < $serviceSlots) {
            $status = 'partial';
            $statusLabel = 'Partial advisory forecast';
        } else {
            $status = 'ready';
            $statusLabel = 'Advisory forecast ready';
        }

        $peak = $readyRows
            ->sortByDesc('expected_commuters')
            ->first();

        return [
            'target_date' => $target->toDateString(),
            'day_of_week' => $target->englishDayOfWeek,
            'route_id' => $routeId,
            'route_name' => $routeName,
            'status' => $status,
            'status_label' => $statusLabel,
            'service_slots' => $serviceSlots,
            'ready_slots' => $readySlots,
            'expected_commuters' => $readyRows->isEmpty()
                ? null
                : round((float) $readyRows->sum('expected_commuters'), 1),
            'minimum_bus_slots' => $readyRows->isEmpty()
                ? null
                : (int) $readyRows->sum('minimum_buses'),
            'peak_minimum_buses' => $peak['minimum_buses'] ?? null,
            'peak' => $peak ? [
                'route_name' => $peak['route_name'],
                'route_variant_id' => $peak['route_variant_id'],
                'direction' => $peak['direction'],
                'direction_label' => $peak['direction_label'],
                'time_slot' => $peak['time_slot'],
                'expected_commuters' => $peak['expected_commuters'],
                'minimum_buses' => $peak['minimum_buses'],
                'confidence' => $peak['confidence'],
                'confidence_label' => $peak['confidence_label'],
            ] : null,
            'advisory_only' => true,
        ];
    }

    private function officialOverlaps(
        CarbonImmutable $target,
        array $slotWindow,
        Collection $officialWindows
    ): Collection {
        return $officialWindows
            ->map(function (RouteServiceSchedule $schedule) use ($target, $slotWindow) {
                $officialStart = $this->timeOnDate($target, $schedule->first_trip_time);
                $officialEnd = $this->timeOnDate($target, $schedule->last_trip_time);

                if ($officialEnd->lessThanOrEqualTo($officialStart)) {
                    $officialEnd = $officialEnd->addDay();
                }

                $overlapStart = $slotWindow['start']->greaterThan($officialStart)
                    ? $slotWindow['start']
                    : $officialStart;
                $overlapEnd = $slotWindow['end']->lessThan($officialEnd)
                    ? $slotWindow['end']
                    : $officialEnd;

                if ($overlapEnd->lessThanOrEqualTo($overlapStart)) {
                    return null;
                }

                return [
                    'official_window' => $officialStart->format('H:i').'-'.$officialEnd->format('H:i'),
                    'service_period' => $overlapStart->format('H:i').'-'.$overlapEnd->format('H:i'),
                ];
            })
            ->filter()
            ->values();
    }

    private function confidence(int $sampleCount, int $minimumSamples, int $lookbackWeeks): array
    {
        if ($sampleCount < $minimumSamples) {
            return ['key' => 'insufficient', 'label' => 'Insufficient'];
        }

        if ($sampleCount >= $lookbackWeeks) {
            return ['key' => 'high', 'label' => 'High'];
        }

        $mediumThreshold = (int) ceil(($minimumSamples + $lookbackWeeks) / 2);

        if ($sampleCount >= $mediumThreshold) {
            return ['key' => 'medium', 'label' => 'Medium'];
        }

        return ['key' => 'low', 'label' => 'Low'];
    }

    private function directionLabel(RouteVariant $variant): string
    {
        $direction = ucfirst(strtolower((string) $variant->direction));

        if ($variant->origin_name && $variant->destination_name) {
            return "{$direction}: {$variant->origin_name} -> {$variant->destination_name}";
        }

        return $direction;
    }

    private function historyKey(int $variantId, string $timeSlot): string
    {
        return $variantId.'|'.$timeSlot;
    }

    private function boundedSetting(string $key, int $fallback, int $minimum, int $maximum): int
    {
        return max($minimum, min($maximum, (int) SystemSetting::get($key, $fallback)));
    }

    private function timeOnDate(CarbonImmutable $date, mixed $time): CarbonImmutable
    {
        $timeString = $time instanceof CarbonInterface
            ? $time->format('H:i:s')
            : substr((string) $time, 0, 8);
        [$hour, $minute, $second] = array_pad(
            array_map('intval', explode(':', $timeString)),
            3,
            0
        );

        return $date->setTime($hour, $minute, $second);
    }
}
