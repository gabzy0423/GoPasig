<?php

namespace App\Services;

use App\Models\TimeSlotConfiguration;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class DemandHistoryTimeSlotService
{
    public const TIMEZONE = 'Asia/Manila';

    public function windowAt(CarbonInterface|string $at): ?array
    {
        $localAt = $this->asManila($at);

        foreach ($this->activeSlots() as $slot) {
            $window = $this->windowForDateAndSlot($localAt, $slot);

            if ($localAt->greaterThanOrEqualTo($window['start'])
                && $localAt->lessThan($window['end'])) {
                return $window;
            }
        }

        return null;
    }

    public function windowsForDate(CarbonInterface|string $date): Collection
    {
        $localDate = $this->asManila($date)->startOfDay();

        return $this->activeSlots()
            ->map(fn (TimeSlotConfiguration $slot) => $this->windowForDateAndSlot($localDate, $slot));
    }

    public function asManila(CarbonInterface|string $value): CarbonImmutable
    {
        if ($value instanceof CarbonInterface) {
            return CarbonImmutable::instance($value)->setTimezone(self::TIMEZONE);
        }

        return CarbonImmutable::parse($value, self::TIMEZONE)
            ->setTimezone(self::TIMEZONE);
    }

    private function activeSlots(): Collection
    {
        return TimeSlotConfiguration::query()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    private function windowForDateAndSlot(
        CarbonImmutable $localDate,
        TimeSlotConfiguration $slot
    ): array {
        $start = $this->atLocalTime($localDate, $slot->start_time);
        $end = $this->atLocalTime($localDate, $slot->end_time);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return [
            'date' => $start->toDateString(),
            'day_of_week' => $start->englishDayOfWeek,
            'label' => $slot->time_slot_display,
            'start' => $start,
            'end' => $end,
        ];
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
