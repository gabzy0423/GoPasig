<?php

namespace App\Data;

use App\Models\Route;
use App\Models\RouteVariant;
use Illuminate\Support\Collection;

final class AuthoritativeRoutePlan
{
    public function __construct(
        public readonly Route $route,
        public readonly ?RouteVariant $variant,
        public readonly array $polylineCoordinates,
        public readonly Collection $orderedStops,
        public readonly string $source
    ) {}

    public function usesVariant(): bool
    {
        return $this->variant !== null;
    }
}
