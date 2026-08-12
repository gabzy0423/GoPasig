<?php

namespace App\Livewire\Commuter;

use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Services\RouteServiceScheduleEvaluator;
use Carbon\Carbon;
use Livewire\Component;

class CommuterSchedule extends Component
{
    public $routes = [];
    public $activeRouteFilter = null;

    public function mount()
    {
        $this->loadRoutes();
    }

    public function loadRoutes()
    {
        $this->routes = Route::getCanonicalProductionCached()
            ->values()
            ->map(fn (Route $route) => [
                'id' => $route->id,
                'name' => $route->name,
            ])
            ->toArray();
    }

    public function filterByRoute($routeId)
    {
        $this->activeRouteFilter = $routeId ? (int) $routeId : null;
    }

    public function render()
    {
        $routeIds = collect($this->routes)->pluck('id');

        $query = Route::query()
            ->publicCommuterVisible()
            ->with([
                'variants' => fn ($query) => $query->orderByRaw("case direction when 'outbound' then 0 when 'inbound' then 1 else 2 end")->orderBy('id'),
                'variants.serviceSchedules' => fn ($query) => $query
                    ->orderByDesc('is_active')
                    ->orderBy('first_trip_time')
                    ->orderBy('id'),
            ])
            ->whereIn('id', $routeIds)
            ->orderBy('id');

        if ($this->activeRouteFilter) {
            $query->where('id', $this->activeRouteFilter);
        }

        $serviceRoutes = $query->get()
            ->map(fn (Route $route) => $this->formatRoute($route))
            ->filter(fn (array $route) => count($route['directions']) > 0)
            ->values()
            ->toArray();

        return view('livewire.commuter.commuter-schedule', [
            'serviceRoutes' => $serviceRoutes,
        ]);
    }

    private function formatRoute(Route $route): array
    {
        return [
            'id' => $route->id,
            'name' => $route->name,
            'description' => $route->description,
            'directions' => $route->variants
                ->sortBy(fn (RouteVariant $variant) => $this->directionSortValue($variant->direction))
                ->map(fn (RouteVariant $variant) => $this->formatDirection($route, $variant))
                ->values()
                ->toArray(),
        ];
    }

    private function formatDirection(Route $route, RouteVariant $variant): array
    {
        $evaluator = app(RouteServiceScheduleEvaluator::class);
        $now = Carbon::now('Asia/Manila');
        $currentWindow = $evaluator->currentWindowForVariant($variant, $now);
        $nextWindow = $evaluator->nextWindowForVariant($variant, $now);
        $serviceSchedules = $variant->serviceSchedules
            ->sortBy([
                ['is_active', 'desc'],
                ['first_trip_time', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
        $activeSchedules = $serviceSchedules->where('is_active', true)->values();
        $summarySchedules = $activeSchedules->isNotEmpty() ? $activeSchedules : $serviceSchedules;
        $firstSchedule = $summarySchedules->sortBy('first_trip_time')->first();
        $lastSchedule = $summarySchedules->sortByDesc('last_trip_time')->first();
        $operatingStatus = $this->formatOperatingStatus($currentWindow, $nextWindow, $activeSchedules, $now);

        return [
            'route_id' => $route->id,
            'route_name' => $route->name,
            'route_description' => $route->description,
            'route_variant_id' => $variant->id,
            'direction' => $variant->direction,
            'direction_label' => str((string) $variant->direction)->title()->toString(),
            'origin' => $variant->origin_name,
            'destination' => $variant->destination_name,
            'status_label' => $summarySchedules->isNotEmpty()
                ? ($summarySchedules->contains(fn (RouteServiceSchedule $schedule) => $schedule->is_active) ? 'Active' : 'Inactive')
                : 'Missing Configuration',
            'is_operating_now' => $operatingStatus['is_operating_now'],
            'operating_status_label' => $operatingStatus['operating_status_label'],
            'current_window' => $this->formatRuntimeWindow($currentWindow),
            'next_window' => $this->formatRuntimeWindow($nextWindow),
            'has_configuration' => $summarySchedules->isNotEmpty(),
            'first_trip_time' => $firstSchedule ? $this->formatTime($firstSchedule->first_trip_time) : null,
            'last_trip_time' => $lastSchedule ? $this->formatTime($lastSchedule->last_trip_time) : null,
            'service_days_label' => $firstSchedule ? $this->formatServiceDays($firstSchedule->service_days ?: []) : null,
            'service_configuration_label' => $firstSchedule ? $this->humanize((string) $firstSchedule->service_configuration) : null,
            'effective_range_label' => $firstSchedule ? $this->formatEffectiveRange($firstSchedule) : null,
            'source_label' => $firstSchedule ? $this->humanize((string) $firstSchedule->source) : null,
            'service_windows' => $serviceSchedules
                ->map(fn (RouteServiceSchedule $schedule) => [
                    'id' => $schedule->id,
                    'first_trip_time' => $this->formatTime($schedule->first_trip_time),
                    'last_trip_time' => $this->formatTime($schedule->last_trip_time),
                    'status_label' => $schedule->is_active ? 'Active' : 'Inactive',
                    'service_days_label' => $this->formatServiceDays($schedule->service_days ?: []),
                    'service_configuration_label' => $this->humanize((string) $schedule->service_configuration),
                    'effective_range_label' => $this->formatEffectiveRange($schedule),
                    'source_label' => $this->humanize((string) $schedule->source),
                ])
                ->values()
                ->toArray(),
        ];
    }

    private function formatOperatingStatus(
        ?RouteServiceSchedule $currentWindow,
        ?RouteServiceSchedule $nextWindow,
        $activeSchedules,
        Carbon $now
    ): array {
        if ($currentWindow) {
            return [
                'is_operating_now' => true,
                'operating_status_label' => 'In service',
            ];
        }

        if ($nextWindow) {
            $nextStart = $now->copy()->setTimeFromTimeString($this->normalizeTime($nextWindow->first_trip_time));
            $minutes = max(1, (int) ceil($now->diffInMinutes($nextStart, false)));

            return [
                'is_operating_now' => false,
                'operating_status_label' => "Starts in {$minutes} min",
            ];
        }

        return [
            'is_operating_now' => false,
            'operating_status_label' => $activeSchedules->isNotEmpty() ? 'Service ended' : 'Missing configuration',
        ];
    }

    private function formatRuntimeWindow(?RouteServiceSchedule $schedule): ?array
    {
        if (! $schedule) {
            return null;
        }

        return [
            'id' => $schedule->id,
            'first_trip_time' => $this->formatTime($schedule->first_trip_time),
            'last_trip_time' => $this->formatTime($schedule->last_trip_time),
            'first_trip_raw' => $this->normalizeTime($schedule->first_trip_time),
            'last_trip_raw' => $this->normalizeTime($schedule->last_trip_time),
        ];
    }

    private function directionSortValue(?string $direction): string
    {
        return match ($direction) {
            'outbound' => '0',
            'inbound' => '1',
            default => '2' . (string) $direction,
        };
    }

    private function formatTime(?string $time): ?string
    {
        if (! $time) {
            return null;
        }

        return Carbon::createFromFormat('H:i:s', strlen($time) === 5 ? $time . ':00' : $time)
            ->format('g:i A');
    }

    private function normalizeTime(?string $time): string
    {
        if (! $time) {
            return '00:00:00';
        }

        return strlen($time) === 5 ? $time . ':00' : substr($time, 0, 8);
    }

    private function formatServiceDays(array $days): string
    {
        if ($days === []) {
            return 'No service days configured';
        }

        $normalized = array_map(fn ($day) => strtolower((string) $day), $days);

        if ($normalized === ['mon', 'tue', 'wed', 'thu', 'fri']) {
            return 'Monday - Friday';
        }

        if ($normalized === ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun']) {
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
