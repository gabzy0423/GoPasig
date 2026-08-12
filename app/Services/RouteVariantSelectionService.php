<?php

namespace App\Services;

use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\Schedule;
use Illuminate\Validation\ValidationException;

class RouteVariantSelectionService
{
    public const USABLE_GEOMETRY_STATUSES = ['valid', 'authoritative', 'active'];

    public function isOperationalRoute(Route $route): bool
    {
        return $route->isCanonicalProduction();
    }

    public function resolveForSchedule(Route $route, ?int $routeVariantId): ?RouteVariant
    {
        $this->assertOperationalRoute($route);

        if ($routeVariantId === null) {
            throw ValidationException::withMessages([
                'route_variant_id' => 'Select an official route direction before creating a schedule.',
            ]);
        }

        return $this->findForRoute($route, $routeVariantId, true);
    }

    public function resolveForDispatch(Route $route, ?int $routeVariantId = null, ?Schedule $schedule = null): ?RouteVariant
    {
        $this->assertOperationalRoute($route);

        if ($schedule && $schedule->route_id !== $route->id) {
            throw ValidationException::withMessages([
                'schedule_id' => 'Selected schedule does not belong to the dispatch route.',
            ]);
        }

        if ($schedule && $schedule->route_variant_id) {
            return $this->findForRoute($route, (int) $schedule->route_variant_id, true);
        }

        if ($routeVariantId !== null) {
            return $this->findForRoute($route, $routeVariantId, true);
        }

        $allVariants = $route->variants()->withCount('stops')->get();

        if ($allVariants->count() === 0) {
            return null;
        }

        $usableVariants = $allVariants
            ->filter(fn (RouteVariant $variant) => $this->isUsableForLiveDispatch($variant))
            ->values();

        if ($usableVariants->count() === 0) {
            throw ValidationException::withMessages([
                'route_variant_id' => 'Select a usable direction for this route before dispatching.',
            ]);
        }

        if ($usableVariants->count() === 1) {
            return $usableVariants->first();
        }

        throw ValidationException::withMessages([
            'route_variant_id' => 'Select a direction for this route before dispatching.',
        ]);
    }

    public function resolveOppositeForNextTrip(Route $route, ?RouteVariant $previousVariant): ?RouteVariant
    {
        $this->assertOperationalRoute($route);
        $allVariants = $route->variants()->withCount('stops')->get();

        if ($allVariants->count() === 0) {
            if ($previousVariant === null) {
                return null;
            }

            throw ValidationException::withMessages([
                'route_variant_id' => 'Previous trip direction no longer belongs to the assigned route.',
            ]);
        }

        if ($previousVariant === null) {
            throw ValidationException::withMessages([
                'route_variant_id' => 'Previous trip has no direction. Dispatcher review is required before starting the next trip.',
            ]);
        }

        if ((int) $previousVariant->route_id !== (int) $route->id) {
            throw ValidationException::withMessages([
                'route_variant_id' => 'Previous trip direction does not belong to the assigned route.',
            ]);
        }

        $direction = strtolower(trim((string) $previousVariant->direction));
        $opposites = [
            'outbound' => 'inbound',
            'inbound' => 'outbound',
        ];

        if (! isset($opposites[$direction])) {
            throw ValidationException::withMessages([
                'route_variant_id' => 'Previous trip direction is ambiguous. Dispatcher review is required before starting the next trip.',
            ]);
        }

        $candidateVariants = $allVariants
            ->filter(fn (RouteVariant $variant) => (int) $variant->id !== (int) $previousVariant->id)
            ->filter(fn (RouteVariant $variant) => strtolower(trim((string) $variant->direction)) === $opposites[$direction])
            ->values();

        if ($candidateVariants->count() === 0) {
            throw ValidationException::withMessages([
                'route_variant_id' => 'No stored opposite direction exists for this route. Dispatcher review is required.',
            ]);
        }

        $usableCandidates = $candidateVariants
            ->filter(fn (RouteVariant $variant) => $this->isUsableForLiveDispatch($variant))
            ->values();

        if ($usableCandidates->count() === 0) {
            throw ValidationException::withMessages([
                'route_variant_id' => 'Opposite direction does not have usable live geometry yet.',
            ]);
        }

        if ($usableCandidates->count() > 1) {
            throw ValidationException::withMessages([
                'route_variant_id' => 'Multiple possible opposite directions exist. Dispatcher review is required.',
            ]);
        }

        return $usableCandidates->first();
    }

    public function isUsableForLiveDispatch(RouteVariant $variant): bool
    {
        $route = $variant->relationLoaded('route')
            ? $variant->route
            : $variant->route()->first();

        if (! $route || ! $this->isOperationalRoute($route)) {
            return false;
        }

        $status = strtolower((string) $variant->geometry_status);
        $polyline = $variant->polyline_coordinates ?: [];
        $stops = $variant->relationLoaded('stops')
            ? $variant->stops
            : $variant->stops()->get();

        $stopsCount = $stops->count();
        $stopsHaveCoordinates = $stops->every(fn ($stop) => $stop->lat !== null && $stop->lng !== null);
        $statusAllowed = in_array($status, self::USABLE_GEOMETRY_STATUSES, true)
            || $status === 'schematic';

        return $statusAllowed
            && is_array($polyline)
            && count($polyline) >= 2
            && $stopsCount >= 2
            && $stopsHaveCoordinates;
    }

    public function label(RouteVariant $variant): string
    {
        $direction = ucfirst((string) $variant->direction);
        $origin = $variant->origin_name ?: 'Origin';
        $destination = $variant->destination_name ?: 'Destination';

        return "{$direction} - {$origin} -> {$destination}";
    }

    private function findForRoute(Route $route, int $routeVariantId, bool $requireUsable): RouteVariant
    {
        $variant = RouteVariant::withCount('stops')->find($routeVariantId);

        if (! $variant || (int) $variant->route_id !== (int) $route->id) {
            throw ValidationException::withMessages([
                'route_variant_id' => 'Selected direction does not belong to the selected route.',
            ]);
        }

        if ($requireUsable && ! $this->isUsableForLiveDispatch($variant)) {
            throw ValidationException::withMessages([
                'route_variant_id' => 'Selected direction does not have usable live geometry yet.',
            ]);
        }

        return $variant;
    }

    private function assertOperationalRoute(Route $route): void
    {
        if (! $this->isOperationalRoute($route)) {
            throw ValidationException::withMessages([
                'route_id' => 'Only official production routes are available for new operations.',
            ]);
        }
    }
}
