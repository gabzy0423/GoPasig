<?php

namespace App\Livewire\Commuter;

use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
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
                'variants.serviceSchedules' => fn ($query) => $query->orderByDesc('is_active')->orderByDesc('effective_from')->orderBy('id'),
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
        $serviceSchedule = $variant->serviceSchedules->first();

        return [
            'route_id' => $route->id,
            'route_name' => $route->name,
            'route_description' => $route->description,
            'route_variant_id' => $variant->id,
            'direction' => $variant->direction,
            'direction_label' => str((string) $variant->direction)->title()->toString(),
            'origin' => $variant->origin_name,
            'destination' => $variant->destination_name,
            'status_label' => $serviceSchedule ? ($serviceSchedule->is_active ? 'Active' : 'Inactive') : 'Missing Configuration',
            'has_configuration' => (bool) $serviceSchedule,
            'first_trip_time' => $serviceSchedule ? $this->formatTime($serviceSchedule->first_trip_time) : null,
            'last_trip_time' => $serviceSchedule ? $this->formatTime($serviceSchedule->last_trip_time) : null,
            'service_days_label' => $serviceSchedule ? $this->formatServiceDays($serviceSchedule->service_days ?: []) : null,
            'service_configuration_label' => $serviceSchedule ? $this->humanize((string) $serviceSchedule->service_configuration) : null,
            'effective_range_label' => $serviceSchedule ? $this->formatEffectiveRange($serviceSchedule) : null,
            'source_label' => $serviceSchedule ? $this->humanize((string) $serviceSchedule->source) : null,
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
