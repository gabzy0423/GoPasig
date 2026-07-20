<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\Stop;
use App\Models\Bus;
use App\Models\Route;

class CommuterStops extends Component
{
    public $search = '';
    public $selectedStopId = null;

    // Commuter Geolocation coordinates
    public $lat = null;
    public $lng = null;

    protected $listeners = ['locationUpdated' => 'updateLocation'];

    public function updateLocation($lat, $lng)
    {
        $this->lat = (float) $lat;
        $this->lng = (float) $lng;
    }

    public function selectStop($stopId)
    {
        $this->selectedStopId = $stopId;
    }

    public function closeDrawer()
    {
        $this->selectedStopId = null;
    }

    public function render()
    {
        // Query stops with their route relationships
        $stopsQuery = Stop::with('route');

        // Apply search filter if specified
        if (!empty(trim($this->search))) {
            $s = '%' . trim($this->search) . '%';
            $stopsQuery->where(function ($q) use ($s) {
                $q->where('name', 'like', $s)
                    ->orWhereHas('route', function ($rq) use ($s) {
                        $rq->where('name', 'like', $s);
                    });
            });
        }

        $stops = $stopsQuery->get();

        if ($this->lat && $this->lng) {
            $stops = $stops->map(function ($stop) {
                $distMeters = \App\Services\GPSKalmanFilter::calculateDistance(
                    $this->lat,
                    $this->lng,
                    $stop->lat,
                    $stop->lng
                );
                $stop->distance = round($distMeters / 1000, 2); // rounded to km
                return $stop;
            });

            // Sort by proximity distance in ascending order (closest stops first)
            $stops = $stops->sortBy('distance')->values();
        } else {
            $stops = $stops->map(function ($stop) {
                $stop->distance = null;
                return $stop;
            });
            // Default sorting by route sequence
            $stops = $stops->sortBy('name')->values();
        }

        // Fetch detailed data for selected stop
        $selectedStop = null;
        $nextBus = null;
        $servicingRoutes = [];

        if ($this->selectedStopId) {
            $selectedStop = Stop::with('route')->find($this->selectedStopId);

            if ($selectedStop) {
                // Proximity distance check using centralized Haversine helper (ISSUE-052)
                if ($this->lat && $this->lng) {
                    $distMeters = \App\Services\GPSKalmanFilter::calculateDistance(
                        $this->lat,
                        $this->lng,
                        $selectedStop->lat,
                        $selectedStop->lng
                    );
                    $selectedStop->distance = round($distMeters / 1000, 2);
                } else {
                    $selectedStop->distance = null;
                }

                // Servicing routes: in this capstone, find routes passing this stop landmark.
                // We'll show the direct parent route and look for routes with the same stop name for a unified view.
                $servicingRoutes = Route::whereNotIn('status', ['suspended', 'inactive', 'Suspended', 'Inactive'])
                    ->whereHas('stops', function ($q) use ($selectedStop) {
                        $q->where('name', $selectedStop->name);
                    })->get();

                // Find next arriving bus for this route (ISSUE-052: Sort active buses by physical proximity/distance to the stop)
                $buses = Bus::where('route_id', $selectedStop->route_id)
                    ->where('status', 'active')
                    ->get();

                $nextBus = $buses->map(function ($bus) use ($selectedStop) {
                    $bus->distance_to_stop = \App\Services\GPSKalmanFilter::calculateDistance(
                        $bus->lat,
                        $bus->lng,
                        $selectedStop->lat,
                        $selectedStop->lng
                    );
                    return $bus;
                })->sortBy('distance_to_stop')->first();

                if (!$nextBus) {
                    // Fallback to active bus on any servicing route if primary is empty (ISSUE-052: Sort active fallback buses by physical proximity/distance to the stop)
                    $routeIds = $servicingRoutes->pluck('id')->toArray();
                    $busesFallback = Bus::whereIn('route_id', $routeIds)
                        ->where('status', 'active')
                        ->get();

                    $nextBus = $busesFallback->map(function ($bus) use ($selectedStop) {
                        $bus->distance_to_stop = \App\Services\GPSKalmanFilter::calculateDistance(
                            $bus->lat,
                            $bus->lng,
                            $selectedStop->lat,
                            $selectedStop->lng
                        );
                        return $bus;
                    })->sortBy('distance_to_stop')->first();
                }
            }
        }

        // All routes for coordinate rendering
        $routes = Route::getAllCached()->whereNotIn('status', ['suspended', 'inactive', 'Suspended', 'Inactive']);

        return view('livewire.commuter.commuter-stops', [
            'stops' => $stops,
            'selectedStop' => $selectedStop,
            'nextBus' => $nextBus,
            'servicingRoutes' => $servicingRoutes,
            'routes' => $routes,
        ]);
    }
}
