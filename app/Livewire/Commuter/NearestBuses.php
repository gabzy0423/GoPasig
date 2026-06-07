<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\Bus;

class NearestBuses extends Component
{
    public function render()
    {
        $buses = Bus::with('route')
            ->where('status', 'active')
            ->orderBy('eta')
            ->get()
            ->map(function ($bus) {
                // Route color: read from the database column
                $color = $bus->route?->color ?: '#003F87';

                // Determine arrival status dynamically
                $fillRatio = $bus->passengers / max(1, $bus->capacity);

                if ($fillRatio > 0.8) {
                    $status = 'Full';
                } elseif ($bus->eta >= Bus::getDelayThreshold()) {
                    $status = 'Delayed';
                } else {
                    $status = 'On Time';
                }

                return (object) [
                    'id' => $bus->id,
                    'plate' => $bus->plate_number,
                    'status' => $status,
                    'route_name' => $bus->route?->name ?? 'Unassigned',
                    'route_color' => $color,
                    'next_stop' => $bus->next_stop ?: 'Terminal',
                    'eta_minutes' => $bus->eta,
                    'onboard' => $bus->passengers,
                    'capacity' => $bus->capacity,
                ];
            });

        return view('livewire.commuter.nearest-buses', [
            'nearestBuses' => $buses,
        ]);
    }
}
