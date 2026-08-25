<?php

namespace App\Services;

use App\Models\DemandForecastSnapshot;
use App\Models\DemandHistory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class DemandForecastShadowService
{
    public const FORECAST_VERSION = 'same_weekday_average_v1';

    public function __construct(
        private readonly DirectionAwareDemandForecastService $forecasts,
        private readonly DemandHistoryTimeSlotService $timeSlots
    ) {}

    public function capture(CarbonInterface|string $targetDate): array
    {
        $capturedAt = CarbonImmutable::now(DemandHistoryTimeSlotService::TIMEZONE);
        $target = $this->timeSlots->asManila($targetDate)->startOfDay();

        if ($target->lessThanOrEqualTo($capturedAt->startOfDay())) {
            throw new InvalidArgumentException(
                'A shadow forecast must be captured before its target date.'
            );
        }

        $forecast = $this->forecasts->forecastForDate($target);
        $captured = 0;
        $existing = 0;

        DB::transaction(function () use ($forecast, $capturedAt, &$captured, &$existing): void {
            foreach ($forecast['rows'] as $row) {
                $snapshot = DemandForecastSnapshot::firstOrCreate(
                    [
                        'target_date' => $row['target_date'],
                        'route_variant_id' => $row['route_variant_id'],
                        'time_slot' => $row['time_slot'],
                        'forecast_version' => self::FORECAST_VERSION,
                    ],
                    [
                        'route_id' => $row['route_id'],
                        'day_of_week' => $row['day_of_week'],
                        'direction' => $row['direction'],
                        'direction_label' => $row['direction_label'],
                        'expected_commuters' => $row['expected_commuters'],
                        'sample_count' => $row['sample_count'],
                        'minimum_samples' => $row['minimum_samples'],
                        'confidence' => $row['confidence'],
                        'minimum_buses' => $row['minimum_buses'],
                        'reference_bus_capacity' => $row['reference_bus_capacity'],
                        'forecast_status' => $row['status'],
                        'advisory_only' => true,
                        'captured_at' => $capturedAt->setTimezone('UTC'),
                    ]
                );

                if ($snapshot->wasRecentlyCreated) {
                    $captured++;
                } else {
                    $existing++;
                }
            }
        });

        $rows = collect($forecast['rows']);

        return [
            'target_date' => $target->toDateString(),
            'captured' => $captured,
            'existing_skipped' => $existing,
            'ready' => $rows->where('status', DemandForecastSnapshot::STATUS_READY)->count(),
            'insufficient_history' => $rows
                ->where('status', DemandForecastSnapshot::STATUS_INSUFFICIENT_HISTORY)
                ->count(),
            'advisory_only' => true,
        ];
    }

    public function evaluate(CarbonInterface|string $targetDate): array
    {
        $target = $this->timeSlots->asManila($targetDate)->startOfDay();
        $evaluatedAt = CarbonImmutable::now(DemandHistoryTimeSlotService::TIMEZONE);
        $snapshots = DemandForecastSnapshot::query()
            ->whereDate('target_date', $target->toDateString())
            ->where('forecast_version', self::FORECAST_VERSION)
            ->get();

        $actuals = DemandHistory::finalizedActual()
            ->whereDate('date', $target->toDateString())
            ->whereIn('route_variant_id', $snapshots->pluck('route_variant_id')->unique())
            ->where('finalized_at', '<=', $evaluatedAt->setTimezone('UTC'))
            ->get()
            ->keyBy(fn (DemandHistory $history) => $this->bucketKey(
                (int) $history->route_variant_id,
                $history->time_slot
            ));

        $summary = [
            'target_date' => $target->toDateString(),
            'snapshots' => $snapshots->count(),
            'evaluated' => 0,
            'actual_without_forecast' => 0,
            'pending_actual' => 0,
            'existing_skipped' => 0,
            'advisory_only' => true,
        ];

        DB::transaction(function () use ($snapshots, $actuals, $evaluatedAt, &$summary): void {
            foreach ($snapshots as $snapshot) {
                if ($snapshot->evaluated_at || $snapshot->actual_commuters !== null) {
                    $summary['existing_skipped']++;
                    continue;
                }

                $actual = $actuals->get($this->bucketKey(
                    (int) $snapshot->route_variant_id,
                    $snapshot->time_slot
                ));

                if (! $actual) {
                    $summary['pending_actual']++;
                    continue;
                }

                $snapshot->fill([
                    'actual_commuters' => (int) $actual->total_commuters,
                    'actual_source' => $actual->source,
                    'actual_finalized_at' => $actual->finalized_at,
                ]);

                if ($snapshot->expected_commuters === null) {
                    $snapshot->save();
                    $summary['actual_without_forecast']++;
                    continue;
                }

                $expected = (float) $snapshot->expected_commuters;
                $observed = (float) $actual->total_commuters;
                $delta = round($observed - $expected, 1);
                $absoluteError = round(abs($delta), 1);
                $percentageError = $observed > 0
                    ? round(($absoluteError / $observed) * 100, 2)
                    : ($absoluteError === 0.0 ? 0.0 : null);

                $snapshot->fill([
                    'error_delta' => $delta,
                    'absolute_error' => $absoluteError,
                    'percentage_error' => $percentageError,
                    'evaluated_at' => $evaluatedAt->setTimezone('UTC'),
                ])->save();
                $summary['evaluated']++;
            }
        });

        return $summary;
    }

    public function dashboard(int $days = 30): array
    {
        $today = CarbonImmutable::now(DemandHistoryTimeSlotService::TIMEZONE)->startOfDay();
        $snapshots = DemandForecastSnapshot::query()
            ->with(['route:id,name', 'routeVariant:id,route_id,direction,origin_name,destination_name'])
            ->whereDate('target_date', '>=', $today->subDays(max(1, $days))->toDateString())
            ->orderByDesc('target_date')
            ->orderByDesc('id')
            ->get();
        $evaluated = $snapshots->whereNotNull('evaluated_at')->values();
        $unevaluatedReady = $snapshots
            ->where('forecast_status', DemandForecastSnapshot::STATUS_READY)
            ->whereNull('actual_commuters')
            ->values();
        $awaitingTarget = $unevaluatedReady
            ->filter(fn (DemandForecastSnapshot $snapshot) => $snapshot->target_date->greaterThan($today))
            ->values();
        $pending = $unevaluatedReady
            ->reject(fn (DemandForecastSnapshot $snapshot) => $snapshot->target_date->greaterThan($today))
            ->values();
        $actualWithoutForecast = $snapshots
            ->where('forecast_status', '!=', DemandForecastSnapshot::STATUS_READY)
            ->whereNotNull('actual_commuters')
            ->values();
        $displayRows = $evaluated->take(4)
            ->concat($snapshots->whereNull('evaluated_at')->take(4))
            ->unique('id')
            ->take(8)
            ->values();
        $percentageErrors = $evaluated->whereNotNull('percentage_error');

        return [
            'forecast_version' => self::FORECAST_VERSION,
            'advisory_only' => true,
            'latest_target_date' => $snapshots->first()?->target_date?->toDateString(),
            'summary' => [
                'captured' => $snapshots->count(),
                'forecast_ready' => $snapshots
                    ->where('forecast_status', DemandForecastSnapshot::STATUS_READY)
                    ->count(),
                'insufficient_history' => $snapshots
                    ->where('forecast_status', DemandForecastSnapshot::STATUS_INSUFFICIENT_HISTORY)
                    ->count(),
                'evaluated' => $evaluated->count(),
                'awaiting_target' => $awaitingTarget->count(),
                'pending_actual' => $pending->count(),
                'actual_without_forecast' => $actualWithoutForecast->count(),
                'mean_absolute_error' => $evaluated->isEmpty()
                    ? null
                    : round((float) $evaluated->avg('absolute_error'), 1),
                'mean_absolute_percentage_error' => $percentageErrors->isEmpty()
                    ? null
                    : round((float) $percentageErrors->avg('percentage_error'), 1),
            ],
            'rows' => $displayRows->map(fn (DemandForecastSnapshot $snapshot) => $this->rowPayload($snapshot))->all(),
        ];
    }

    private function rowPayload(DemandForecastSnapshot $snapshot): array
    {
        [$status, $statusLabel] = $this->evaluationStatus($snapshot);

        return [
            'id' => $snapshot->id,
            'target_date' => $snapshot->target_date->toDateString(),
            'route_id' => (int) $snapshot->route_id,
            'route_name' => $snapshot->route?->name ?: 'Route '.$snapshot->route_id,
            'route_variant_id' => (int) $snapshot->route_variant_id,
            'direction' => $snapshot->direction,
            'direction_label' => $snapshot->direction_label,
            'time_slot' => $snapshot->time_slot,
            'predicted_commuters' => $snapshot->expected_commuters,
            'actual_commuters' => $snapshot->actual_commuters,
            'error_delta' => $snapshot->error_delta,
            'absolute_error' => $snapshot->absolute_error,
            'percentage_error' => $snapshot->percentage_error,
            'sample_count' => $snapshot->sample_count,
            'confidence' => $snapshot->confidence,
            'minimum_buses' => $snapshot->minimum_buses,
            'status' => $status,
            'status_label' => $statusLabel,
            'captured_at' => $snapshot->captured_at?->toIso8601String(),
            'evaluated_at' => $snapshot->evaluated_at?->toIso8601String(),
            'advisory_only' => true,
        ];
    }

    private function evaluationStatus(DemandForecastSnapshot $snapshot): array
    {
        if ($snapshot->evaluated_at) {
            return ['evaluated', 'Evaluated'];
        }

        if ($snapshot->actual_commuters !== null) {
            return ['actual_without_forecast', 'Actual recorded; no forecast'];
        }

        if ($snapshot->forecast_status !== DemandForecastSnapshot::STATUS_READY) {
            return ['insufficient_history', 'Insufficient history'];
        }

        if ($snapshot->target_date->greaterThan(
            CarbonImmutable::now(DemandHistoryTimeSlotService::TIMEZONE)->startOfDay()
        )) {
            return ['awaiting_target', 'Awaiting target date'];
        }

        return ['pending_actual', 'Pending finalized actual'];
    }

    private function bucketKey(int $routeVariantId, string $timeSlot): string
    {
        return $routeVariantId.'|'.$timeSlot;
    }
}
