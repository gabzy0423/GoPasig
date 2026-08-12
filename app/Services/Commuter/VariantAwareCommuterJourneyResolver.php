<?php

namespace App\Services\Commuter;

use App\Data\CommuterLocation;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;
use Illuminate\Support\Collection;

class VariantAwareCommuterJourneyResolver
{
    public function __construct(
        private readonly GeospatialServiceInterface $geospatial,
    ) {}

    public function originsAtLocation(CommuterLocation $location): Collection
    {
        $coordinate = $location->coordinate();

        return $this->publicVariants()
            ->flatMap(function (RouteVariant $variant) use ($coordinate) {
                return $variant->stops
                    ->filter(fn (RouteVariantStop $stop) => $this->isInsideStop($coordinate, $stop))
                    ->map(fn (RouteVariantStop $stop) => [
                        'route' => $variant->route,
                        'variant' => $variant,
                        'origin' => $stop,
                        'distance_meters' => $this->distanceToStop($coordinate, $stop),
                    ]);
            })
            ->sortBy(fn (array $candidate) => sprintf(
                '%012.4f:%010d:%010d',
                $candidate['distance_meters'],
                $candidate['variant']->id,
                $candidate['origin']->sequence
            ))
            ->values();
    }

    public function destinationOptionsAtLocation(CommuterLocation $location): Collection
    {
        return $this->destinationOptionsForOrigins($this->originsAtLocation($location));
    }

    public function destinationOptionsForOrigins(Collection $origins): Collection
    {
        return $origins
            ->flatMap(function (array $candidate) {
                /** @var RouteVariant $variant */
                $variant = $candidate['variant'];
                /** @var RouteVariantStop $origin */
                $origin = $candidate['origin'];
                $terminalSequence = (int) $variant->stops->max('sequence');

                return $variant->stops
                    ->where('sequence', '>', $origin->sequence)
                    ->sortBy('sequence')
                    ->map(fn (RouteVariantStop $destination) => (object) [
                        'id' => $this->selectionKey($origin, $destination),
                        'selection_key' => $this->selectionKey($origin, $destination),
                        'route_id' => (int) $variant->route_id,
                        'route_name' => $variant->route?->name ?? 'Route',
                        'route_variant_id' => (int) $variant->id,
                        'direction' => strtolower((string) $variant->direction),
                        'origin_route_variant_stop_id' => (int) $origin->id,
                        'origin_name' => $origin->name,
                        'destination_route_variant_stop_id' => (int) $destination->id,
                        'name' => $destination->name,
                        'sequence' => (int) $destination->sequence,
                        'is_terminal' => (int) $destination->sequence === $terminalSequence,
                    ]);
            })
            ->unique('selection_key')
            ->sortBy(fn (object $option) => sprintf(
                '%s:%s:%010d',
                $option->route_name,
                $option->direction,
                $option->sequence
            ))
            ->values();
    }

    public function resolveSelection(string $selectionKey, CommuterLocation $location): ?array
    {
        if (! preg_match('/^(\d+):(\d+)$/', $selectionKey, $matches)) {
            return null;
        }

        $originId = (int) $matches[1];
        $destinationId = (int) $matches[2];

        $originCandidate = $this->originsAtLocation($location)
            ->first(fn (array $candidate) => (int) $candidate['origin']->id === $originId);

        if (! $originCandidate) {
            return null;
        }

        /** @var RouteVariant $variant */
        $variant = $originCandidate['variant'];
        /** @var RouteVariantStop $origin */
        $origin = $originCandidate['origin'];
        $destination = $variant->stops->first(fn (RouteVariantStop $stop) => (int) $stop->id === $destinationId);

        if (! $destination || (int) $destination->sequence <= (int) $origin->sequence) {
            return null;
        }

        return $this->resolution($variant, $origin, $destination);
    }

    public function resolveLegacyStops(Stop $origin, Stop $destination): ?array
    {
        if ((int) $origin->route_id !== (int) $destination->route_id) {
            return null;
        }

        if (! Route::publicCommuterActiveService()->whereKey($origin->route_id)->exists()) {
            return null;
        }

        $matches = RouteVariant::query()
            ->with(['route', 'stops.canonicalStop'])
            ->where('route_id', $origin->route_id)
            ->get()
            ->flatMap(function (RouteVariant $variant) use ($origin, $destination) {
                $origins = $variant->stops->where('canonical_stop_id', $origin->id);
                $destinations = $variant->stops->where('canonical_stop_id', $destination->id);

                return $origins->flatMap(function (RouteVariantStop $variantOrigin) use ($variant, $destinations) {
                    return $destinations
                        ->filter(fn (RouteVariantStop $variantDestination) => $variantDestination->sequence > $variantOrigin->sequence)
                        ->map(fn (RouteVariantStop $variantDestination) => $this->resolution(
                            $variant,
                            $variantOrigin,
                            $variantDestination
                        ));
                });
            })
            ->values();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function publicVariants(): Collection
    {
        $routeIds = Route::publicCommuterActiveService()->pluck('id');

        if ($routeIds->isEmpty()) {
            return collect();
        }

        return RouteVariant::query()
            ->with(['route', 'stops.canonicalStop'])
            ->whereIn('route_id', $routeIds)
            ->orderBy('route_id')
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();
    }

    private function resolution(
        RouteVariant $variant,
        RouteVariantStop $origin,
        RouteVariantStop $destination
    ): array {
        return [
            'route' => $variant->route,
            'variant' => $variant,
            'origin' => $origin,
            'destination' => $destination,
        ];
    }

    private function selectionKey(RouteVariantStop $origin, RouteVariantStop $destination): string
    {
        return $origin->id.':'.$destination->id;
    }

    private function isInsideStop(Coordinate $coordinate, RouteVariantStop $stop): bool
    {
        if ($stop->lat === null || $stop->lng === null) {
            return false;
        }

        return $this->distanceToStop($coordinate, $stop) <= (float) ($stop->radius_meters ?? 100);
    }

    private function distanceToStop(Coordinate $coordinate, RouteVariantStop $stop): float
    {
        return $this->geospatial->calculateDistance(
            $coordinate,
            new Coordinate((float) $stop->lat, (float) $stop->lng)
        );
    }
}
