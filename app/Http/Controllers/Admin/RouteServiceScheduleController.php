<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use Illuminate\Http\JsonResponse;

class RouteServiceScheduleController extends Controller
{
    public function index(): JsonResponse
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can view route service schedules',
            ], 403);
        }

        $routes = Route::with([
                'variants' => fn ($query) => $query->orderBy('direction')->orderBy('id'),
                'variants.serviceSchedules' => fn ($query) => $query
                    ->orderByDesc('is_active')
                    ->orderBy('first_trip_time')
                    ->orderBy('id'),
            ])
            ->canonicalProduction()
            ->get()
            ->map(fn (Route $route) => $this->formatRoute($route));

        return response()->json([
            'success' => true,
            'routes' => $routes,
        ]);
    }

    public function show(RouteServiceSchedule $routeServiceSchedule): JsonResponse
    {
        if (auth()->user()->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: Only admins can view route service schedules',
            ], 403);
        }

        $routeServiceSchedule->load(['route', 'routeVariant']);

        return response()->json([
            'success' => true,
            'serviceSchedule' => $this->formatServiceSchedule($routeServiceSchedule),
        ]);
    }

    private function formatRoute(Route $route): array
    {
        return [
            'id' => $route->id,
            'name' => $route->name,
            'variants' => $route->variants->map(fn (RouteVariant $variant) => $this->formatVariant($variant))->values(),
        ];
    }

    private function formatVariant(RouteVariant $variant): array
    {
        $schedules = $variant->serviceSchedules
            ->sortBy([
                ['is_active', 'desc'],
                ['first_trip_time', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
        $activeSchedules = $schedules->where('is_active', true)->values();
        $summarySchedules = $activeSchedules->isNotEmpty() ? $activeSchedules : $schedules;

        return [
            'id' => $variant->id,
            'direction' => $variant->direction,
            'directionLabel' => ucfirst((string) $variant->direction),
            'originName' => $variant->origin_name,
            'destinationName' => $variant->destination_name,
            'serviceSchedule' => $summarySchedules->isNotEmpty()
                ? $this->formatServiceScheduleSummary($summarySchedules)
                : null,
            'serviceSchedules' => $schedules
                ->map(fn (RouteServiceSchedule $schedule) => $this->formatServiceSchedule($schedule))
                ->values(),
        ];
    }

    private function formatServiceScheduleSummary($schedules): array
    {
        $firstSchedule = $schedules->sortBy('first_trip_time')->first();
        $lastSchedule = $schedules->sortByDesc('last_trip_time')->first();
        $baseSchedule = $firstSchedule;

        $summary = $this->formatServiceSchedule($baseSchedule);
        $summary['id'] = null;
        $summary['firstTripTime'] = $this->formatTime($firstSchedule->first_trip_time);
        $summary['lastTripTime'] = $this->formatTime($lastSchedule->last_trip_time);
        $summary['firstTripRaw'] = $firstSchedule->first_trip_time;
        $summary['lastTripRaw'] = $lastSchedule->last_trip_time;
        $summary['windowCount'] = $schedules->count();
        $summary['statusLabel'] = $schedules->contains(fn (RouteServiceSchedule $schedule) => $schedule->is_active)
            ? 'Active'
            : 'Inactive';
        $summary['isActive'] = $schedules->contains(fn (RouteServiceSchedule $schedule) => $schedule->is_active);

        return $summary;
    }

    private function formatServiceSchedule(RouteServiceSchedule $schedule): array
    {
        return [
            'id' => $schedule->id,
            'routeId' => $schedule->route_id,
            'routeVariantId' => $schedule->route_variant_id,
            'routeName' => $schedule->route?->name,
            'direction' => $schedule->routeVariant?->direction,
            'directionLabel' => ucfirst((string) $schedule->routeVariant?->direction),
            'originName' => $schedule->routeVariant?->origin_name,
            'destinationName' => $schedule->routeVariant?->destination_name,
            'firstTripTime' => $this->formatTime($schedule->first_trip_time),
            'lastTripTime' => $this->formatTime($schedule->last_trip_time),
            'firstTripRaw' => $schedule->first_trip_time,
            'lastTripRaw' => $schedule->last_trip_time,
            'serviceConfiguration' => $schedule->service_configuration,
            'serviceConfigurationLabel' => $this->humanize((string) $schedule->service_configuration),
            'serviceDays' => $schedule->service_days ?: [],
            'serviceDaysLabel' => $this->formatServiceDays($schedule->service_days ?: []),
            'isActive' => (bool) $schedule->is_active,
            'statusLabel' => $schedule->is_active ? 'Active' : 'Inactive',
            'source' => $schedule->source,
            'sourceLabel' => $this->humanize((string) $schedule->source),
            'effectiveFrom' => $schedule->effective_from?->toDateString(),
            'effectiveUntil' => $schedule->effective_until?->toDateString(),
            'effectiveRangeLabel' => $this->formatEffectiveRange($schedule),
        ];
    }

    private function formatTime(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return \Carbon\Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time . ':00' : $time)
            ->format('g:i A');
    }

    private function formatServiceDays(array $days): string
    {
        if ($days === []) {
            return 'No service days configured';
        }

        $normalized = array_map(fn ($day) => strtolower((string) $day), $days);
        $weekdaySet = ['mon', 'tue', 'wed', 'thu', 'fri'];
        $fullWeekSet = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        if ($normalized === $weekdaySet) {
            return 'Monday - Friday';
        }

        if ($normalized === $fullWeekSet) {
            return 'Daily';
        }

        $labels = [
            'mon' => 'Monday',
            'tue' => 'Tuesday',
            'wed' => 'Wednesday',
            'thu' => 'Thursday',
            'fri' => 'Friday',
            'sat' => 'Saturday',
            'sun' => 'Sunday',
        ];

        return collect($normalized)
            ->map(fn ($day) => $labels[$day] ?? ucfirst($day))
            ->implode(', ');
    }

    private function formatEffectiveRange(RouteServiceSchedule $schedule): string
    {
        if (! $schedule->effective_from && ! $schedule->effective_until) {
            return 'No effective date limit';
        }

        $from = $schedule->effective_from?->toDateString() ?? 'Start';
        $until = $schedule->effective_until?->toDateString() ?? 'Open ended';

        return $from . ' to ' . $until;
    }

    private function humanize(string $value): string
    {
        return str($value)->replace('_', ' ')->title()->toString();
    }
}
