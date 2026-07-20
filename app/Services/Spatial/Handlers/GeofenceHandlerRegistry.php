<?php

namespace App\Services\Spatial\Handlers;

use App\Enums\GeofenceType;
use InvalidArgumentException;

class GeofenceHandlerRegistry
{
    protected array $handlers = [];

    public function register(GeofenceType $type, GeofenceHandlerInterface $handler): void
    {
        $this->handlers[$type->value] = $handler;
    }

    public function get(GeofenceType $type): GeofenceHandlerInterface
    {
        if (!isset($this->handlers[$type->value])) {
            throw new InvalidArgumentException("No handler registered for geofence type: {$type->value}");
        }
        return $this->handlers[$type->value];
    }
}
