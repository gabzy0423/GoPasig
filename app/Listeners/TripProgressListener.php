<?php

namespace App\Listeners;

use App\Events\PositionUpdated;
use App\Services\Routing\TripProgressService;
use App\Services\ValueObjects\Coordinate;

class TripProgressListener
{
    public function __construct(protected TripProgressService $service) {}

    public function handle(PositionUpdated $event): void
    {
        $position = $event->position;
        if (!$position->trip_id) {
            return;
        }

        $coord = new Coordinate($position->lat, $position->lng);
        $this->service->updateProgress($position->trip_id, $coord);
    }
}
