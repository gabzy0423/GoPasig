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
                $color = $bus->route?->color ?: config('brand.route_color_unassigned', '#888780');

                // Determine arrival status dynamically
                $fillRatio = $bus->passengers / max(1, $bus->capacity);

                $statusFull = \App\Models\SystemSetting::get('label_bus_status_full', 'Full');
                $statusDelayed = \App\Models\SystemSetting::get('label_bus_status_delayed', 'Delayed');
                $statusOnTime = \App\Models\SystemSetting::get('label_bus_status_on_time', 'On Time');

                if ($fillRatio > 0.8) {
                    $status = $statusFull;
                } elseif ($bus->eta >= $bus->getRouteDelayThreshold()) {
                    $status = $statusDelayed;
                } else {
                    $status = $statusOnTime;
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
