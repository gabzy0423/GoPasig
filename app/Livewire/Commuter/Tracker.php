<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\Route;
use App\Models\Bus;
use App\Models\ServiceAlert;
use App\Models\Schedule;

class Tracker extends Component
{
    public $selectedRouteId = null;
    public $lat = null;
    public $lng = null;

    protected $listeners = ['locationUpdated' => 'updateLocation'];

    public function mount()
    {
        // Try to read lat/lng if passed via query parameters or mount
    }

    public function setRoute($routeId)
    {
        $this->selectedRouteId = $routeId ? (int) $routeId : null;
        $this->dispatch('route-selected', routeId: $this->selectedRouteId);
    }

    public function updateLocation($lat, $lng)
    {
        $this->lat = $lat;
        $this->lng = $lng;
    }

    public function render()
    {
        // 1. Fetch active alerts   
        $activeAlerts = ServiceAlert::where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Fetch routes
        $routes = Route::all();

        // 3. Fetch active buses
        $busesQuery = Bus::with(['route', 'route.stops'])
            ->where('status', 'active');

        if ($this->selectedRouteId) {
            $busesQuery->where('route_id', $this->selectedRouteId);
        }

        $activeBuses = $busesQuery->get()->map(function ($bus) {
            // Route color: read from the database column
            $color = $bus->route?->color ?: '#888780';

            // Determine status
            $status = 'active';
            if ($bus->eta >= Bus::getDelayThreshold() && $bus->speed > 0) {
                $status = 'delayed';
            } elseif ($bus->speed == 0 && $bus->passengers == 0) {
                $status = 'idle';
            } elseif ($bus->status === 'maintenance') {
                $status = 'breakdown';
            }

            // Driver name: prefer bus->driver_name, then look up Driver model by plate
            $driverName = $bus->driver_name;
            if (!$driverName) {
                $driver = \App\Models\Driver::where('assigned_bus', $bus->plate_number)->first();
                $driverName = $driver?->name ?? 'No Driver Assigned';
            }

            return (object) [
                'bus_id'         => $bus->id,
                'plate_number'   => $bus->plate_number,
                'route_name'     => $bus->route?->name ?? 'Unassigned',
                'route_color'    => $color,
                'status'         => $status,
                'next_stop_name' => $bus->next_stop ?: 'Terminal',
                'eta_minutes'    => $bus->eta,
                'passenger_count'=> $bus->passengers,
                'capacity'       => $bus->capacity,
                'lat'            => (float) $bus->lat,
                'lng'            => (float) $bus->lng,
                'driver_name'    => $driverName,
                'speed'          => $bus->speed ?: 0,
            ];
        });

        // 4. Calculate nearest bus
        $nearestBus = null;
        if ($this->lat && $this->lng && $activeBuses->isNotEmpty()) {
            $minDistance = null;
            $closest = null;

            foreach ($activeBuses as $bus) {
                if ($bus->lat && $bus->lng) {
                    $distance = $this->calculateDistance($this->lat, $this->lng, $bus->lat, $bus->lng);
                    if ($minDistance === null || $distance < $minDistance) {
                        $minDistance = $distance;
                        $closest = $bus;
                    }
                }
            }

            if ($closest) {
                $nearestBus = (object) [
                    'bus_id' => $closest->bus_id,
                    'plate_number' => $closest->plate_number,
                    'route_name' => $closest->route_name,
                    'route_color' => $closest->route_color,
                    'eta_minutes' => $closest->eta_minutes,
                    'distance_km' => $minDistance,
                    'passenger_count' => $closest->passenger_count,
                    'capacity' => $closest->capacity,
                ];
            }
        }

        // Dispatch updated bus details to client-side JS Map listeners (Livewire v3 standard)
        $this->dispatch('buses-updated', buses: $activeBuses);

        return view('livewire.commuter.tracker', [
            'activeAlerts' => $activeAlerts,
            'routes' => $routes,
            'activeBuses' => $activeBuses,
            'nearestBus' => $nearestBus,
            'selectedRoute' => $this->selectedRouteId ? Route::find($this->selectedRouteId) : null,
        ]);
    }

    private function calculateDistance($lat1, $lng1, $lat2, $lng2)
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng/2) * sin($dLng/2);
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        return round($earthRadius * $c, 1);
    }
}
