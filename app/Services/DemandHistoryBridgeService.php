<?php

namespace App\Services;

use App\Models\CommuterTrip;
use App\Models\DemandHistory;
use App\Models\Route;
use App\Models\TimeSlotConfiguration;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class DemandHistoryBridgeService
{
    private const TIMEZONE = 'Asia/Manila';

    public function recordCommuterCheckIn(CommuterTrip $trip): ?DemandHistory
    {
        if ($trip->is_simulated || ! $trip->route_id) {
            return null;
        }

        return $this->safelyRefreshBucket(
            (int) $trip->route_id,
            $trip->created_at,
            'commuter_check_in'
        );
    }

    public function recordDispatch(Trip $trip): ?DemandHistory
    {
        if (! $trip->route_id || ! $trip->dispatched_at) {
            return null;
        }

        return $this->safelyRefreshBucket(
            (int) $trip->route_id,
            $trip->dispatched_at,
            'trip_dispatch'
        );
    }

    public function refreshBucket(int $routeId, CarbonInterface|string $at): ?DemandHistory
    {
        if (! Route::publicCommuterActiveService()->whereKey($routeId)->exists()) {
            return null;
        }

        $localAt = $this->asManila($at);
        $window = $this->timeSlotWindow($localAt);

        if (! $window) {
            return null;
        }

        $startUtc = $window['start']->setTimezone('UTC');
        $endUtc = $window['end']->setTimezone('UTC');

        $totalCommuters = CommuterTrip::query()
            ->where('route_id', $routeId)
            ->where('is_simulated', false)
            ->where('created_at', '>=', $startUtc)
            ->where('created_at', '<', $endUtc)
            ->count();

        $busesDispatched = Trip::query()
            ->where('route_id', $routeId)
            ->whereNotNull('dispatched_at')
            ->where('dispatched_at', '>=', $startUtc)
            ->where('dispatched_at', '<', $endUtc)
            ->distinct()
            ->count('bus_id');

        $identity = [
            'route_id' => $routeId,
            'date' => $window['date'],
            'time_slot' => $window['label'],
        ];

        $history = DemandHistory::query()
            ->where('route_id', $routeId)
            ->whereDate('date', $window['date'])
            ->where('time_slot', $window['label'])
            ->orderBy('id')
            ->first();

        $values = [
            'day_of_week' => $window['day_of_week'],
            'total_commuters' => $totalCommuters,
            'buses_dispatched' => $busesDispatched,
        ];

        if ($history) {
            $history->fill($values);
            $history->save();

            return $history->fresh();
        }

        return DemandHistory::create(array_merge($identity, $values));
    }

    private function safelyRefreshBucket(
        int $routeId,
        CarbonInterface|string|null $at,
        string $source
    ): ?DemandHistory {
        try {
            return $this->refreshBucket(
                $routeId,
                $at ?: CarbonImmutable::now(self::TIMEZONE)
            );
        } catch (Throwable $exception) {
            Log::warning('Demand history bridge could not refresh a bucket.', [
                'route_id' => $routeId,
                'source' => $source,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    private function asManila(CarbonInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE);
        }

        return CarbonImmutable::parse($value, 'UTC')->setTimezone(self::TIMEZONE);
    }

    private function timeSlotWindow(CarbonImmutable $localAt): ?array
    {
        $slots = TimeSlotConfiguration::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        foreach ($slots as $slot) {
            $start = $this->atLocalTime($localAt, $slot->start_time);
            $end = $this->atLocalTime($localAt, $slot->end_time);

            if ($end->lessThanOrEqualTo($start)) {
                $end = $end->addDay();
            }

            if ($localAt->greaterThanOrEqualTo($start) && $localAt->lessThan($end)) {
                return [
                    'date' => $start->toDateString(),
                    'day_of_week' => $start->englishDayOfWeek,
                    'label' => $slot->time_slot_display,
                    'start' => $start,
                    'end' => $end,
                ];
            }
        }

        return null;
    }

    private function atLocalTime(CarbonImmutable $date, mixed $time): CarbonImmutable
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
