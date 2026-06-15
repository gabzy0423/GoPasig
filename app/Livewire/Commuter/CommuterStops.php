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
        $this->lat = (float)$lat;
        $this->lng = (float)$lng;
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
            $stopsQuery->where(function($q) use ($s) {
                $q->where('name', 'like', $s)
                  ->orWhereHas('route', function($rq) use ($s) {
                      $rq->where('name', 'like', $s);
                  });
            });
        }

        $stops = $stopsQuery->get();

        // Compute Proximity Distance using Haversine Formula
        if ($this->lat && $this->lng) {
            $stops = $stops->map(function($stop) {
                $earthRadius = 6371; // Earth radius in Kilometers
                
                $dLat = deg2rad($stop->lat - $this->lat);
                $dLng = deg2rad($stop->lng - $this->lng);
                
                $a = sin($dLat / 2) * sin($dLat / 2) +
                     cos(deg2rad($this->lat)) * cos(deg2rad($stop->lat)) *
                     sin($dLng / 2) * sin($dLng / 2);
                     
                $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                
                $stop->distance = round($earthRadius * $c, 2); // rounded to 2 decimal places
                return $stop;
            });

            // Sort by proximity distance in ascending order (closest stops first)
            $stops = $stops->sortBy('distance')->values();
        } else {
            $stops = $stops->map(function($stop) {
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
                // Proximity distance check
                if ($this->lat && $this->lng) {
                    $earthRadius = 6371;
                    $dLat = deg2rad($selectedStop->lat - $this->lat);
                    $dLng = deg2rad($selectedStop->lng - $this->lng);
                    $a = sin($dLat / 2) * sin($dLat / 2) +
                         cos(deg2rad($this->lat)) * cos(deg2rad($selectedStop->lat)) *
                         sin($dLng / 2) * sin($dLng / 2);
                    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
                    $selectedStop->distance = round($earthRadius * $c, 2);
                } else {
                    $selectedStop->distance = null;
                }

                // Servicing routes: in this capstone, find routes passing this stop landmark.
                // We'll show the direct parent route and look for routes with the same stop name for a unified view.
                $servicingRoutes = Route::whereHas('stops', function($q) use ($selectedStop) {
                    $q->where('name', $selectedStop->name);
                })->get();

                // Find next arriving bus for this route
                $nextBus = Bus::where('route_id', $selectedStop->route_id)
                    ->where('status', 'active')
                    ->first();
                
                if (!$nextBus) {
                    // Fallback to active bus on any servicing route if primary is empty
                    $routeIds = $servicingRoutes->pluck('id')->toArray();
                    $nextBus = Bus::whereIn('route_id', $routeIds)
                        ->where('status', 'active')
                        ->first();
                }
            }
        }

        // All routes for coordinate rendering
        $routes = Route::getAllCached();

        return view('livewire.commuter.commuter-stops', [
            'stops' => $stops,
            'selectedStop' => $selectedStop,
            'nextBus' => $nextBus,
            'servicingRoutes' => $servicingRoutes,
            'routes' => $routes,
        ]);
    }
}
