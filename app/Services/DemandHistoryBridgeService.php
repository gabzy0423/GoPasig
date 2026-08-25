<?php

namespace App\Services;

use App\Models\CommuterTrip;
use App\Models\DemandHistory;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Throwable;

class DemandHistoryBridgeService
{
    public function __construct(
        private readonly DemandHistoryTimeSlotService $timeSlots
    ) {}

    public function recordCommuterCheckIn(CommuterTrip $trip): ?DemandHistory
    {
        if ($trip->is_simulated || ! $trip->route_id || ! $trip->route_variant_id) {
            return null;
        }

        return $this->safelyRefreshBucket(
            (int) $trip->route_id,
            (int) $trip->route_variant_id,
            $trip->created_at,
            'commuter_check_in'
        );
    }

    public function recordDispatch(Trip $trip): ?DemandHistory
    {
        $trip->loadMissing('bus');

        if (! $trip->route_id
            || ! $trip->route_variant_id
            || ! $trip->dispatched_at
            || ! $trip->bus
            || $trip->bus->is_simulated) {
            return null;
        }

        return $this->safelyRefreshBucket(
            (int) $trip->route_id,
            (int) $trip->route_variant_id,
            $trip->dispatched_at,
            'trip_dispatch'
        );
    }

    public function refreshBucket(
        int $routeId,
        int $routeVariantId,
        CarbonInterface|string $at
    ): ?DemandHistory {
        return $this->writeBucket(
            $routeId,
            $routeVariantId,
            $at,
            DemandHistory::SOURCE_ACTUAL_RUNTIME,
            false,
            null
        );
    }

    public function finalizeBucket(
        int $routeId,
        int $routeVariantId,
        CarbonInterface|string $at,
        CarbonInterface|string $finalizedAt,
        bool $trainingEligible = false
    ): ?DemandHistory {
        return $this->writeBucket(
            $routeId,
            $routeVariantId,
            $at,
            DemandHistory::SOURCE_ACTUAL_REBUILD,
            $trainingEligible,
            $this->timeSlots->asManila($finalizedAt)->setTimezone('UTC')
        );
    }

    private function writeBucket(
        int $routeId,
        int $routeVariantId,
        CarbonInterface|string $at,
        string $source,
        bool $trainingEligible,
        ?CarbonInterface $finalizedAt
    ): ?DemandHistory {
        if (! $this->officialVariant($routeId, $routeVariantId)) {
            return null;
        }

        $window = $this->timeSlots->windowAt($at);

        if (! $window) {
            return null;
        }

        $startUtc = $window['start']->setTimezone('UTC');
        $endUtc = $window['end']->setTimezone('UTC');

        $totalCommuters = CommuterTrip::query()
            ->where('route_id', $routeId)
            ->where('route_variant_id', $routeVariantId)
            ->where('is_simulated', false)
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc)
            ->distinct()
            ->count('session_token');

        $busesDispatched = Trip::query()
            ->where('route_id', $routeId)
            ->where('route_variant_id', $routeVariantId)
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '>=', $startUtc)
            ->where('dispatched_at', '<', $endUtc)
            ->whereHas('bus', fn ($query) => $query->where('is_simulated', false))
            ->distinct()
            ->count('bus_id');

        $identity = [
            'route_id' => $routeId,
            'route_variant_id' => $routeVariantId,
            'date' => $window['date'],
            'time_slot' => $window['label'],
        ];

        $values = [
            'day_of_week' => $window['day_of_week'],
            'total_commuters' => $totalCommuters,
            'buses_dispatched' => $busesDispatched,
            'source' => $source,
            'is_training_eligible' => $trainingEligible,
            'finalized_at' => $finalizedAt,
        ];

        $history = $this->findBucket($identity);

        if (! $history) {
            try {
                return DemandHistory::create(array_merge($identity, $values));
            } catch (UniqueConstraintViolationException $exception) {
                $history = $this->findBucket($identity);

                if (! $history) {
                    throw $exception;
                }
            }
        }

        if ($source === DemandHistory::SOURCE_ACTUAL_REBUILD
            && ! $trainingEligible
            && $history->is_training_eligible) {
            $values['source'] = $history->source;
            $values['is_training_eligible'] = true;
        }

        $history->fill($values);
        $history->save();

        return $history->fresh();
    }

    private function findBucket(array $identity): ?DemandHistory
    {
        return DemandHistory::query()
            ->where('route_id', $identity['route_id'])
            ->where('route_variant_id', $identity['route_variant_id'])
            ->whereDate('date', $identity['date'])
            ->where('time_slot', $identity['time_slot'])
            ->first();
    }

    private function officialVariant(int $routeId, int $routeVariantId): ?RouteVariant
    {
        if (! Route::publicCommuterActiveService()->whereKey($routeId)->exists()) {
            return null;
        }

        return RouteVariant::query()
            ->whereKey($routeVariantId)
            ->where('route_id', $routeId)
            ->first();
    }

    private function safelyRefreshBucket(
        int $routeId,
        int $routeVariantId,
        CarbonInterface|string|null $at,
        string $source
    ): ?DemandHistory {
        try {
            return $this->refreshBucket(
                $routeId,
                $routeVariantId,
                $at ?: CarbonImmutable::now(DemandHistoryTimeSlotService::TIMEZONE)
            );
        } catch (Throwable $exception) {
            Log::warning('Demand history bridge could not refresh a bucket.', [
                'route_id' => $routeId,
                'route_variant_id' => $routeVariantId,
                'source' => $source,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
