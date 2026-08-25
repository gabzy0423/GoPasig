<?php

namespace App\Services;

use App\Models\DemandHistory;
use App\Models\Route;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class DemandHistoryRebuildService
{
    public function __construct(
        private readonly DemandHistoryBridgeService $bridge,
        private readonly DemandHistoryTimeSlotService $timeSlots
    ) {}

    public function rebuild(
        CarbonInterface|string $from,
        CarbonInterface|string $to,
        CarbonInterface|string|null $through = null,
        bool $onlyUnfinalized = false,
        bool $trainingEligible = false
    ): array {
        $startDate = $this->timeSlots->asManila($from)->startOfDay();
        $endDate = $this->timeSlots->asManila($to)->startOfDay();
        $cutoff = $through
            ? $this->timeSlots->asManila($through)
            : CarbonImmutable::now(DemandHistoryTimeSlotService::TIMEZONE);

        if ($endDate->lessThan($startDate)) {
            throw new InvalidArgumentException('Demand history rebuild end date must be on or after its start date.');
        }

        $routes = Route::publicCommuterActiveService()
            ->with(['variants' => fn ($query) => $query->orderBy('id')])
            ->get();
        $summary = [
            'finalized' => 0,
            'skipped_open' => 0,
            'skipped_existing' => 0,
            'failed' => 0,
        ];

        for ($date = $startDate; $date->lessThanOrEqualTo($endDate); $date = $date->addDay()) {
            foreach ($this->timeSlots->windowsForDate($date) as $window) {
                foreach ($routes as $route) {
                    foreach ($route->variants as $variant) {
                        if ($window['end']->greaterThan($cutoff)) {
                            $summary['skipped_open']++;
                            continue;
                        }

                        $existing = DemandHistory::directionAware()
                            ->where('route_id', $route->id)
                            ->where('route_variant_id', $variant->id)
                            ->whereDate('date', $window['date'])
                            ->where('time_slot', $window['label'])
                            ->first();

                        if ($onlyUnfinalized
                            && $existing?->finalized_at
                            && (! $trainingEligible || $existing->is_training_eligible)) {
                            $summary['skipped_existing']++;
                            continue;
                        }

                        try {
                            $history = $this->bridge->finalizeBucket(
                                (int) $route->id,
                                (int) $variant->id,
                                $window['start']->addSecond(),
                                $window['end'],
                                $trainingEligible
                            );
                        } catch (Throwable $exception) {
                            Log::warning('Demand history rebuild failed for a direction bucket.', [
                                'route_id' => $route->id,
                                'route_variant_id' => $variant->id,
                                'date' => $window['date'],
                                'time_slot' => $window['label'],
                                'error' => $exception->getMessage(),
                            ]);
                            $summary['failed']++;
                            continue;
                        }

                        if ($history) {
                            $summary['finalized']++;
                        } else {
                            $summary['failed']++;
                        }
                    }
                }
            }
        }

        return $summary;
    }
}
