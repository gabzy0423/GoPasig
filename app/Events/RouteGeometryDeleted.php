<?php

namespace App\Events;

use App\Services\ValueObjects\Polyline;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RouteGeometryDeleted
{
    use Dispatchable, SerializesModels;

    public int $routeId;
    public Polyline $previous;

    public function __construct(int $routeId, Polyline $previous)
    {
        $this->routeId = $routeId;
        $this->previous = $previous;
    }
}
