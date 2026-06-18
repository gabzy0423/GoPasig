<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\Route;
use App\Models\Bus;
use App\Models\ServiceAlert;
use App\Models\Schedule;
use App\Services\GPSKalmanFilter;

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
        $activeAlerts = ServiceAlert::activeAlerts()
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Fetch routes
        $routes = Route::getAllCached();

        // 3. Fetch active buses
        $busesQuery = Bus::with(['route', 'route.stops'])
            ->whereIn('status', ['active', 'breakdown', 'maintenance']);

        if ($this->selectedRouteId) {
            $busesQuery->where('route_id', $this->selectedRouteId);
        }

        $activeBuses = $busesQuery->get()->map(function ($bus) {
            // Route color: read from the database column
            $color = $bus->route?->color ?: config('brand.route_color_unassigned', '#888780');

            // Determine status
            $status = $bus->status;
            if ($status === 'active') {
                if ($bus->eta >= $bus->getRouteDelayThreshold() && $bus->speed > 0) {
                    $status = 'delayed';
                } elseif ($bus->speed == 0 && $bus->passengers == 0) {
                    $status = 'idle';
                }
            }

            // Driver name: prefer bus->driver_name, then look up Driver model by plate
            $driverName = $bus->driver_name;
            if (!$driverName) {
                $driver = \App\Models\Driver::where('assigned_bus', $bus->plate_number)->first();
                $driverName = $driver ? (trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? '')) ?: 'No Driver Assigned') : 'No Driver Assigned';
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
                'is_simulated'   => (bool) $bus->is_simulated,
                'updated_at'     => $bus->updated_at ? $bus->updated_at->toIso8601String() : now()->toIso8601String(),
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
                    'is_simulated' => $closest->is_simulated,
                    'updated_at' => $closest->updated_at,
                ];
            }
        }

        // 5. Calculate breakdown and maintenance alerts for active commuter session
        $sessionToken = request()->cookie('commuter_session_token');
        $breakdownAlert = null;
        $maintenanceAlert = null;
        if ($sessionToken) {
            $tripObj = \App\Models\CommuterTrip::where('session_token', $sessionToken)
                ->whereIn('status', ['WAITING', 'ON_BUS'])
                ->first();
            if ($tripObj) {
                if ($tripObj->status === 'ON_BUS') {
                    if ($tripObj->bus_id) {
                        $busObj = \App\Models\Bus::find($tripObj->bus_id);
                        if ($busObj) {
                            if ($busObj->status === 'breakdown') {
                                $breakdownAlert = "Breakdown detected — please alight safely. Rescue bus incoming.";
                            } elseif ($busObj->status === 'maintenance') {
                                $maintenanceAlert = "Pasensya na — ang inyong bus ay may maintenance issue. Mangyaring bumaba sa susunod na hintuan.";
                            }
                        }
                    } else {
                        $anyBroken = \App\Models\Bus::where('route_id', $tripObj->route_id)->where('status', 'breakdown')->exists();
                        $anyMaint = \App\Models\Bus::where('route_id', $tripObj->route_id)->where('status', 'maintenance')->exists();
                        if ($anyBroken) {
                            $breakdownAlert = "Breakdown detected — please alight safely. Rescue bus incoming.";
                        } elseif ($anyMaint) {
                            $maintenanceAlert = "Pasensya na — ang inyong bus ay may maintenance issue. Mangyaring bumaba sa susunod na hintuan.";
                        }
                    }
                } elseif ($tripObj->status === 'WAITING') {
                    $isAnyBusBroken = \App\Models\Bus::where('route_id', $tripObj->route_id)->where('status', 'breakdown')->exists();
                    if ($isAnyBusBroken) {
                        $breakdownAlert = "Bus breakdown — please wait for next available bus";
                    }
                }
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
            'breakdownAlert' => $breakdownAlert,
            'maintenanceAlert' => $maintenanceAlert,
        ]);
    }

    // Delegate to GPSKalmanFilter; convert meters to km to match the distance_km usage in nearestBus.
    private function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        return round(GPSKalmanFilter::calculateDistance($lat1, $lng1, $lat2, $lng2) / 1000, 1);
    }
}
