<?php

namespace App\Events;

use App\Services\ValueObjects\Polyline;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RouteGeometryCreated
{
    use Dispatchable, SerializesModels;

    public int $routeId;
    public Polyline $polyline;
    public array $metrics;

    public function __construct(int $routeId, Polyline $polyline, array $metrics)
    {
        $this->routeId = $routeId;
        $this->polyline = $polyline;
        $this->metrics = $metrics;
    }
}
