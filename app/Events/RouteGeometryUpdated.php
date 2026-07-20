<?php

namespace App\Events;

use App\Services\ValueObjects\Polyline;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RouteGeometryUpdated
{
    use Dispatchable, SerializesModels;

    public int $routeId;
    public Polyline $previous;
    public Polyline $polyline;
    public array $metrics;

    public function __construct(int $routeId, Polyline $previous, Polyline $polyline, array $metrics)
    {
        $this->routeId = $routeId;
        $this->previous = $previous;
        $this->polyline = $polyline;
        $this->metrics = $metrics;
    }
}
