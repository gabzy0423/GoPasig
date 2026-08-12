<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RouteServiceScheduleEvaluator
{
    private const TIMEZONE = 'Asia/Manila';

    public function isVariantOperating(RouteVariant|int $variant, CarbonInterface $at): bool
    {
        return $this->currentWindowForVariant($variant, $at) !== null;
    }

    public function isRouteOperating(Route|int $route, CarbonInterface $at): bool
    {
        return $this->currentWindowForRoute($route, $at) !== null;
    }

    public function currentWindowForVariant(RouteVariant|int $variant, CarbonInterface $at): ?RouteServiceSchedule
    {
        $variant = $this->resolveVariant($variant);

        if (! $variant) {
            return null;
        }

        $at = $this->manila($at);
        $time = $at->format('H:i:s');

        return $this->candidateWindowsForVariant($variant, $at)
            ->first(fn (RouteServiceSchedule $schedule) => $this->normalizeTime($schedule->first_trip_time) <= $time
                && $this->normalizeTime($schedule->last_trip_time) >= $time);
    }

    public function nextWindowForVariant(RouteVariant|int $variant, CarbonInterface $at): ?RouteServiceSchedule
    {
        $variant = $this->resolveVariant($variant);

        if (! $variant) {
            return null;
        }

        $at = $this->manila($at);
        $time = $at->format('H:i:s');

        return $this->candidateWindowsForVariant($variant, $at)
            ->first(fn (RouteServiceSchedule $schedule) => $this->normalizeTime($schedule->first_trip_time) > $time);
    }

    public function statusForRoute(Route|int $route, CarbonInterface $at): array
    {
        $route = $this->resolveRoute($route);

        if (! $route) {
            return $this->status(false, 'Missing configuration');
        }

        $at = $this->manila($at);
        $allActiveWindows = $this->activeWindowsForRoute($route);

        if ($allActiveWindows->isEmpty()) {
            return $this->status(false, 'Missing configuration');
        }

        $currentWindow = $this->currentWindowForRoute($route, $at);

        if ($currentWindow) {
            return $this->status(true, 'In service', $currentWindow, $this->nextWindowForRoute($route, $at));
        }

        $todayWindows = $allActiveWindows
            ->filter(fn (RouteServiceSchedule $schedule) => $this->isEffectiveOn($schedule, $at)
                && $this->servesDay($schedule, $at))
            ->sortBy(fn (RouteServiceSchedule $schedule) => $this->normalizeTime($schedule->first_trip_time))
            ->values();

        if ($todayWindows->isEmpty()) {
            return $this->status(false, 'No service today');
        }

        $nextWindow = $this->nextWindowForRoute($route, $at);

        if ($nextWindow) {
            $minutes = max(1, (int) ceil($at->diffInMinutes($this->timeOnDate($at, $nextWindow->first_trip_time), false)));

            return $this->status(false, "Starts in {$minutes} min", null, $nextWindow);
        }

        return $this->status(false, 'Service ended');
    }

    public function activeWindowsForRouteOn(Route|int $route, CarbonInterface $at): Collection
    {
        $route = $this->resolveRoute($route);

        if (! $route) {
            return collect();
        }

        $at = $this->manila($at);

        return $this->activeWindowsForRoute($route)
            ->filter(fn (RouteServiceSchedule $schedule) => $this->isEffectiveOn($schedule, $at)
                && $this->servesDay($schedule, $at))
            ->sortBy(fn (RouteServiceSchedule $schedule) => $this->normalizeTime($schedule->first_trip_time))
            ->values();
    }

    private function currentWindowForRoute(Route|int $route, CarbonInterface $at): ?RouteServiceSchedule
    {
        $route = $this->resolveRoute($route);

        if (! $route) {
            return null;
        }

        return $this->variantsForRoute($route)
            ->map(fn (RouteVariant $variant) => $this->currentWindowForVariant($variant, $at))
            ->filter()
            ->sortBy(fn (RouteServiceSchedule $schedule) => $this->normalizeTime($schedule->first_trip_time))
            ->first();
    }

    private function nextWindowForRoute(Route|int $route, CarbonInterface $at): ?RouteServiceSchedule
    {
        $route = $this->resolveRoute($route);

        if (! $route) {
            return null;
        }

        return $this->variantsForRoute($route)
            ->map(fn (RouteVariant $variant) => $this->nextWindowForVariant($variant, $at))
            ->filter()
            ->sortBy(fn (RouteServiceSchedule $schedule) => $this->normalizeTime($schedule->first_trip_time))
            ->first();
    }

    private function candidateWindowsForVariant(RouteVariant $variant, CarbonInterface $at): Collection
    {
        $variant->loadMissing('serviceSchedules');

        return $variant->serviceSchedules
            ->filter(fn (RouteServiceSchedule $schedule) => $schedule->is_active
                && $this->isEffectiveOn($schedule, $at)
                && $this->servesDay($schedule, $at))
            ->sortBy(fn (RouteServiceSchedule $schedule) => $this->normalizeTime($schedule->first_trip_time))
            ->values();
    }

    private function activeWindowsForRoute(Route $route): Collection
    {
        return RouteServiceSchedule::where('route_id', $route->id)
            ->where('is_active', true)
            ->orderBy('first_trip_time')
            ->get();
    }

    private function variantsForRoute(Route $route): Collection
    {
        $route->loadMissing('variants.serviceSchedules');

        return $route->variants;
    }

    private function servesDay(RouteServiceSchedule $schedule, CarbonInterface $at): bool
    {
        $days = collect($schedule->service_days ?: [])
            ->map(fn ($day) => strtolower((string) $day))
            ->filter()
            ->values();

        if ($days->isEmpty()) {
            return false;
        }

        return $days->contains(strtolower($at->format('D')));
    }

    private function isEffectiveOn(RouteServiceSchedule $schedule, CarbonInterface $at): bool
    {
        $date = $this->manila($at)->toDateString();

        if ($schedule->effective_from && $date < $schedule->effective_from->toDateString()) {
            return false;
        }

        if ($schedule->effective_until && $date > $schedule->effective_until->toDateString()) {
            return false;
        }

        return true;
    }

    private function resolveRoute(Route|int $route): ?Route
    {
        return $route instanceof Route ? $route : Route::find($route);
    }

    private function resolveVariant(RouteVariant|int $variant): ?RouteVariant
    {
        return $variant instanceof RouteVariant ? $variant : RouteVariant::find($variant);
    }

    private function manila(CarbonInterface $at): Carbon
    {
        return Carbon::instance($at)->copy()->timezone(self::TIMEZONE);
    }

    private function normalizeTime(?string $time): string
    {
        if (! $time) {
            return '00:00:00';
        }

        return strlen($time) === 5 ? $time . ':00' : substr($time, 0, 8);
    }

    private function timeOnDate(CarbonInterface $date, ?string $time): Carbon
    {
        return $this->manila($date)->setTimeFromTimeString($this->normalizeTime($time));
    }

    private function status(
        bool $isOperating,
        string $label,
        ?RouteServiceSchedule $currentWindow = null,
        ?RouteServiceSchedule $nextWindow = null
    ): array {
        return [
            'is_operating' => $isOperating,
            'status_label' => $label,
            'current_window' => $currentWindow,
            'next_window' => $nextWindow,
        ];
    }
}
