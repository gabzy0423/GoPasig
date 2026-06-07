<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use Carbon\Carbon;

class GeofenceDetector extends Component
{
    public $lat = null;
    public $lng = null;
    public $activeStop = null; // Currently inside this stop (if within 150m)
    public $nearestStop = null; // Nearest stop
    public $distanceToNearest = null; // In meters


    protected $listeners = ['updateLocation' => 'updateLocation'];

    public function updateLocation($lat, $lng)
    {
        $this->lat = floatval($lat);
        $this->lng = floatval($lng);

        $minDistance = null;
        $closest = null;
        $insideStop = null;

        // Fetch stops dynamically from database
        $dbStops = \App\Models\Stop::all();

        foreach ($dbStops as $dbStop) {
            $dist = $this->calculateDistance($this->lat, $this->lng, $dbStop->lat, $dbStop->lng); // in meters

            $stopData = [
                'id' => $dbStop->id,
                'name' => $dbStop->name,
                'lat' => floatval($dbStop->lat),
                'lng' => floatval($dbStop->lng),
                'amenities' => $dbStop->amenities ?: \App\Models\SystemSetting::get('default_amenity', 'Shelter'),
                'schedule' => $this->getCombinedSchedule($dbStop)
            ];

            if ($minDistance === null || $dist < $minDistance) {
                $minDistance = $dist;
                $closest = $stopData;
            }

            // Geofence radius is 150 meters
            if ($dist <= 150) {
                $insideStop = $stopData;
            }
        }

        $this->nearestStop = $closest;
        $this->distanceToNearest = round($minDistance);
        
        if ($insideStop) {
            if (!$this->activeStop || $this->activeStop['id'] !== $insideStop['id']) {
                $this->activeStop = $insideStop;
                $this->dispatch('geofenceEntered', stopName: $insideStop['name']);
            }
        } else {
            if ($this->activeStop) {
                $this->dispatch('geofenceExited', stopName: $this->activeStop['name']);
                $this->activeStop = null;
            }
        }
    }

    /**
     * Dynamically calculate combined arrivals for all routes passing through this stop location.
     */
    private function getCombinedSchedule($targetStop)
    {
        // Find all stops with the same name or within 50 meters of this stop
        $stops = \App\Models\Stop::all()->filter(function ($s) use ($targetStop) {
            return $s->name === $targetStop->name || 
                   $this->calculateDistance($s->lat, $s->lng, $targetStop->lat, $targetStop->lng) <= 50;
        });

        $arrivals = [];

        foreach ($stops as $stop) {
            $route = $stop->route;
            if (!$route) continue;

            $routeArrivalMinutes = null;

            // Calculate dynamic travel interval per stop
            $routeStops = $route->stops;
            $totalStopsCount = $routeStops->count();
            $routeTravelTime = $route->travel_time_minutes ?: 25;
            $averageInterval = $totalStopsCount > 1 ? ($routeTravelTime / ($totalStopsCount - 1)) : 5;

            // 1. Look for active buses on this route
            $buses = \App\Models\Bus::where('route_id', $stop->route_id)
                ->where('status', 'active')
                ->get();

            $incomingEtas = [];
            foreach ($buses as $bus) {
                $busNextStopName = $bus->next_stop;
                $busNextStop = \App\Models\Stop::where('route_id', $stop->route_id)
                    ->where('name', 'like', '%' . $busNextStopName . '%')
                    ->first();
                
                $busNextSeq = $busNextStop ? $busNextStop->sequence : 1;
                $targetSeq = $stop->sequence;
                
                if ($targetSeq >= $busNextSeq) {
                    $seqDiff = $targetSeq - $busNextSeq;
                    $incomingEtas[] = max(1, round($bus->eta + ($seqDiff * $averageInterval)));
                }
            }

            if (!empty($incomingEtas)) {
                $routeArrivalMinutes = min($incomingEtas);
            } else {
                // 2. No active buses, check schedules
                $nextSched = \App\Models\Schedule::where('route_id', $stop->route_id)
                    ->where('departure_time', '>', now()->toTimeString())
                    ->orderBy('departure_time')
                    ->first();
                if ($nextSched) {
                    $departure = \Carbon\Carbon::parse($nextSched->departure_time);
                    // Add transit time based on sequence using dynamic average interval
                    $transitMins = ($stop->sequence - 1) * $averageInterval;
                    $expectedDepartureTime = $departure->addMinutes(round($transitMins));
                    
                    $diff = now()->diffInMinutes($expectedDepartureTime, false);
                    if ($diff > 0) {
                        $routeArrivalMinutes = $diff;
                    }
                }
            }

            if ($routeArrivalMinutes !== null) {
                // Map route name to friendly code representation
                $arrivals[] = "{$routeArrivalMinutes} mins (" . $route->name . ")";
            }
        }

        if (empty($arrivals)) {
            return "No upcoming Libreng Sakay trips at this time.";
        }

        return "Next Libreng Sakay: " . implode(', ', $arrivals);
    }

    // Haversine formula to calculate distance in meters
    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371000; // in meters

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function render()
    {
        $stops = \App\Models\Stop::orderBy('name')->get();

        // Load settings from database
        $simNearLat = \App\Models\SystemSetting::get('sim_near_lat', '14.5685');
        $simNearLng = \App\Models\SystemSetting::get('sim_near_lng', '121.0650');
        $simNearLabel = \App\Models\SystemSetting::get('sim_near_label', 'Malapit sa Kapitolyo (280m)');
        
        $simFarLat = \App\Models\SystemSetting::get('sim_far_lat', '14.5000');
        $simFarLng = \App\Models\SystemSetting::get('sim_far_lng', '121.0000');
        $simFarLabel = \App\Models\SystemSetting::get('sim_far_label', 'Walang kalapit na stop');
        
        $chimeFreq1 = \App\Models\SystemSetting::get('chime_freq_1', '1318.51');
        $chimeFreq2 = \App\Models\SystemSetting::get('chime_freq_2', '1760.00');
        $chimeDelay = \App\Models\SystemSetting::get('chime_delay', '0.12');

        return view('livewire.commuter.geofence-detector', [
            'stops' => $stops,
            'simNearLat' => $simNearLat,
            'simNearLng' => $simNearLng,
            'simNearLabel' => $simNearLabel,
            'simFarLat' => $simFarLat,
            'simFarLng' => $simFarLng,
            'simFarLabel' => $simFarLabel,
            'chimeFreq1' => $chimeFreq1,
            'chimeFreq2' => $chimeFreq2,
            'chimeDelay' => $chimeDelay,
        ]);
    }
}
