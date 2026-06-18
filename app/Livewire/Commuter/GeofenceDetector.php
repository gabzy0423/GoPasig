<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use Carbon\Carbon;
use App\Services\GPSKalmanFilter;
use App\Services\CommuterDashboardCacheService;

class GeofenceDetector extends Component
{
    public $lat = null;
    public $lng = null;
    public $activeStop = null; // Currently inside this stop (within its configured radius_meters)
    public $nearestStop = null; // Nearest stop
    public $distanceToNearest = null; // In meters
    public $selectedDestinationId = null;
    public $activeTrip = null;
    public $destinationStops = [];

    protected $listeners = ['updateLocation' => 'updateLocation'];

    public function updateLocation($lat, $lng)
    {
        $this->lat = floatval($lat);
        $this->lng = floatval($lng);

        $minDistance = null;
        $closest = null;
        $insideStop = null;

        // Fetch all route stops once, then do geofence math in memory.
        $dbStops = app(CommuterDashboardCacheService::class)->routeStops();

        foreach ($dbStops as $dbStop) {
            $dist = $this->calculateDistance($this->lat, $this->lng, $dbStop->lat, $dbStop->lng); // in meters

            $stopData = [
                'id' => $dbStop->id,
                'name' => $dbStop->name,
                'lat' => floatval($dbStop->lat),
                'lng' => floatval($dbStop->lng),
                'amenities' => $dbStop->amenities ?: \App\Models\SystemSetting::get('default_amenity', 'Shelter'),
                'schedule' => $this->getCombinedSchedule($dbStop),
                'route_id' => $dbStop->route_id,
                'sequence' => $dbStop->sequence
            ];

            if ($minDistance === null || $dist < $minDistance) {
                $minDistance = $dist;
                $closest = $stopData;
            }

            // Use each stop's own configurable radius (falls back to 100 m if not set)
            $geofenceRadius = $dbStop->radius_meters ?? 100;
            if ($dist <= $geofenceRadius) {
                $insideStop = $stopData;
            }
        }

        $this->nearestStop = $closest;
        $this->distanceToNearest = round($minDistance);
        
        if ($insideStop) {
            if (!$this->activeStop || $this->activeStop['id'] !== $insideStop['id']) {
                $this->activeStop = $insideStop;
                $this->destinationStops = $this->getDestinationStopsFor($insideStop);
                $this->dispatch('geofenceEntered', stopName: $insideStop['name']);
            }
        } else {
            if ($this->activeStop) {
                $this->dispatch('geofenceExited', stopName: $this->activeStop['name']);
                $this->activeStop = null;
                $this->destinationStops = [];
            }
        }

        $this->checkActiveTripTransitions();
    }

    public function checkActiveTripTransitions()
    {
        $sessionToken = request()->cookie('commuter_session_token');
        if (!$sessionToken) {
            $this->activeTrip = null;
            return;
        }

        $trip = \App\Models\CommuterTrip::with(['originStop', 'destinationStop', 'route'])
            ->where('session_token', $sessionToken)
            ->whereIn('status', ['WAITING', 'ON_BUS'])
            ->first();

        if (!$trip) {
            $this->activeTrip = null;
            return;
        }

        // Commuter coordinates must be available
        if ($this->lat && $this->lng) {
            if ($trip->status === 'WAITING') {
                // Check if they step outside geofence while waiting
                $originStop = $trip->originStop;
                if ($originStop) {
                    $distToOrigin = $this->calculateDistance($this->lat, $this->lng, $originStop->lat, $originStop->lng);
                    $radius = $originStop->radius_meters ?? 100;
                    if ($distToOrigin > $radius) {
                        if (!session()->get('prompted_cancel_' . $trip->id)) {
                            session()->put('prompted_cancel_' . $trip->id, true);
                            $this->dispatch('promptCancelTrip');
                        }
                    } else {
                        session()->forget('prompted_cancel_' . $trip->id);
                    }
                }

                // Check if near any active bus on this route
                $activeBuses = app(CommuterDashboardCacheService::class)
                    ->routeStops()
                    ->firstWhere('route_id', $trip->route_id)
                    ?->route
                    ?->buses ?? collect();

                foreach ($activeBuses as $bus) {
                    $dist = $this->calculateDistance($this->lat, $this->lng, $bus->lat, $bus->lng);
                    if ($dist <= 15) { // 15 meters boarding threshold
                        $trip->update([
                            'status' => 'ON_BUS',
                            'boarded_at' => now(),
                            'bus_id' => $bus->id,
                        ]);
                        $this->dispatch('commuterBoarded', busPlate: $bus->plate_number);
                        break;
                    }
                }
            } elseif ($trip->status === 'ON_BUS') {
                // Check if arrived at destination stop
                $destStop = $trip->destinationStop;
                if ($destStop) {
                    $dist = $this->calculateDistance($this->lat, $this->lng, $destStop->lat, $destStop->lng);
                    $radius = $destStop->radius_meters ?? 100;
                    if ($dist <= $radius) {
                        $trip->update([
                            'status' => 'ARRIVED',
                            'arrived_at' => now(),
                        ]);
                        $this->dispatch('commuterArrived', stopName: $destStop->name);
                    }
                }
            }
        }

        // Refresh trip data for UI
        $trip->refresh();
        $this->activeTrip = [
            'id' => $trip->id,
            'status' => $trip->status,
            'origin_stop_name' => $trip->originStop?->name ?? 'Unknown',
            'destination_stop_name' => $trip->destinationStop?->name ?? 'Unknown',
            'route_name' => $trip->route?->name ?? 'Unknown',
            'route_color' => $trip->route?->color ?? '#003F87',
        ];
    }

    public function loadActiveTrip()
    {
        $sessionToken = request()->cookie('commuter_session_token');
        if (!$sessionToken) {
            $this->activeTrip = null;
            return;
        }

        $trip = \App\Models\CommuterTrip::with(['originStop', 'destinationStop', 'route'])
            ->where('session_token', $sessionToken)
            ->whereIn('status', ['WAITING', 'ON_BUS'])
            ->first();

        if ($trip) {
            $this->activeTrip = [
                'id' => $trip->id,
                'status' => $trip->status,
                'origin_stop_name' => $trip->originStop?->name ?? 'Unknown',
                'destination_stop_name' => $trip->destinationStop?->name ?? 'Unknown',
                'route_name' => $trip->route?->name ?? 'Unknown',
                'route_color' => $trip->route?->color ?? '#003F87',
            ];
        } else {
            $this->activeTrip = null;
        }
    }

    public function startCommuterTrip()
    {
        $sessionToken = request()->cookie('commuter_session_token');
        if (!$sessionToken || !$this->activeStop || !$this->selectedDestinationId) {
            return;
        }

        $originStopId = $this->activeStop['id'];
        $destinationStopId = (int) $this->selectedDestinationId;

        $matchingRoute = app(CommuterDashboardCacheService::class)
            ->routeStops()
            ->first(function ($stop) use ($originStopId, $destinationStopId) {
                if ((int) $stop->id !== (int) $originStopId || !$stop->route) {
                    return false;
                }

                return $stop->route->stops->contains(fn ($routeStop) => (int) $routeStop->id === (int) $destinationStopId);
            })
            ?->route;

        if ($matchingRoute) {
            \Illuminate\Support\Facades\DB::table('commuter_trips')->insert([
                'session_token' => $sessionToken,
                'origin_stop_id' => $originStopId,
                'destination_stop_id' => $destinationStopId,
                'route_id' => $matchingRoute->id,
                'status' => 'WAITING',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->selectedDestinationId = null;
            $this->loadActiveTrip();
        } else {
            session()->flash('error', 'Walang direktang ruta na nagkokonekta sa dalawang stop na ito.');
        }
    }

    public function cancelCommuterTrip()
    {
        $sessionToken = request()->cookie('commuter_session_token');
        if (!$sessionToken) return;

        \App\Models\CommuterTrip::where('session_token', $sessionToken)
            ->whereIn('status', ['WAITING', 'ON_BUS'])
            ->update([
                'status' => 'CANCELLED',
            ]);

        $this->activeTrip = null;
    }

    /**
     * Dynamically calculate combined arrivals for all routes passing through this stop location.
     */
    private function getCombinedSchedule($targetStop)
    {
        // Find all stops with the same name or within the configurable grouping radius
        $groupingRadius = (int) \App\Models\SystemSetting::get('stop_grouping_radius', 50);
        $stops = app(CommuterDashboardCacheService::class)->routeStops()->filter(function ($s) use ($targetStop, $groupingRadius) {
            return $s->name === $targetStop->name ||
                   $this->calculateDistance($s->lat, $s->lng, $targetStop->lat, $targetStop->lng) <= $groupingRadius;
        });

        $arrivals = [];

        foreach ($stops as $stop) {
            $route = $stop->route;
            if (!$route) continue;

            $routeArrivalMinutes = null;

            // Build distance-weighted cumulative time offsets for each stop on the route.
            // Offset[i] = minutes from departure to reach stop at index i (0-based).
            $routeStops      = $route->stops->sortBy('sequence')->values();
            $totalStopsCount = $routeStops->count();
            $routeTravelTime = $route->durations
                ->first(fn ($duration) => $duration->day_of_week === null && $duration->time_slot === null)
                ?->duration_minutes
                ?? (int) \App\Models\SystemSetting::get('default_travel_time_minutes', 45);
            $offsets = \App\Models\Stop::getDistanceWeightedOffsets($routeStops, $routeTravelTime);

            // Map sequence number → offset minutes (sequence is 1-based)
            $seqToOffset = [];
            foreach ($routeStops as $idx => $rs) {
                $seqToOffset[$rs->sequence] = $offsets[$idx] ?? ($idx * ($routeTravelTime / max(1, $totalStopsCount - 1)));
            }

            // 1. Look for active buses on this route
            $buses = $route->buses ?? collect();

            $incomingEtas = [];
            foreach ($buses as $bus) {
                $busNextStopName = $bus->next_stop;
                $busNextStop = $routeStops->first(function ($routeStop) use ($busNextStopName) {
                    return stripos($routeStop->name, (string) $busNextStopName) !== false
                        || stripos((string) $busNextStopName, $routeStop->name) !== false;
                });

                $busNextSeq = $busNextStop ? $busNextStop->sequence : 1;
                $targetSeq  = $stop->sequence;

                if ($targetSeq >= $busNextSeq) {
                    // Extra minutes = distance-weighted offset difference between target and bus's next stop
                    $extraMins = ($seqToOffset[$targetSeq] ?? 0) - ($seqToOffset[$busNextSeq] ?? 0);
                    $incomingEtas[] = max(1, round($bus->eta + $extraMins));
                }
            }

            if (!empty($incomingEtas)) {
                $routeArrivalMinutes = min($incomingEtas);
            } else {
                // 2. No active buses — check scheduled departure
                $nextSched = $route->schedules
                    ->where('departure_time', '>', now()->toTimeString())
                    ->sortBy('departure_time')
                    ->first();
                if ($nextSched) {
                    $departure   = \Carbon\Carbon::parse($nextSched->departure_time);
                    // Transit time = cumulative offset at this stop's 0-based index
                    $stopIndex   = $stop->sequence - 1;
                    $transitMins = $offsets[$stopIndex] ?? ($stopIndex * ($routeTravelTime / max(1, $totalStopsCount - 1)));
                    $expectedArrival = $departure->copy()->addMinutes(round($transitMins));

                    $diff = now()->diffInMinutes($expectedArrival, false);
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

    // Delegate to the canonical Haversine implementation (returns meters)
    private function calculateDistance($lat1, $lon1, $lat2, $lon2): float
    {
        return GPSKalmanFilter::calculateDistance($lat1, $lon1, $lat2, $lon2);
    }

    private function getDestinationStopsFor(array $activeStop): array
    {
        return app(CommuterDashboardCacheService::class)
            ->routeStops()
            ->where('route_id', $activeStop['route_id'])
            ->where('sequence', '>', $activeStop['sequence'])
            ->sortBy('sequence')
            ->map(fn ($stop) => (object) [
                'id' => $stop->id,
                'name' => $stop->name,
            ])
            ->values()
            ->all();
    }

    public function render()
    {
        $this->loadActiveTrip();
        $stops = app(CommuterDashboardCacheService::class)->routeStops()->sortBy('name');

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

        // Load settings from database
        $simNearLat = \App\Models\SystemSetting::get('sim_near_lat', '14.5685');
        $simNearLng = \App\Models\SystemSetting::get('sim_near_lng', '121.0650');
        $simNearLabel = \App\Models\SystemSetting::get('sim_near_label', 'Malapit sa Kapitolyo (280m)');
        
        $simFarLat = \App\Models\SystemSetting::get('sim_far_lat', '14.5000');
        $simFarLng = \App\Models\SystemSetting::get('sim_far_lng', '121.0000');
        $simFarLabel = \App\Models\SystemSetting::get('sim_far_label', 'Walang kalapit na stop');
        
        $chimeFreq1 = \App\Models\SystemSetting::get('chime_freq_1', config('geofence.chime_freq_1'));
        $chimeFreq2 = \App\Models\SystemSetting::get('chime_freq_2', config('geofence.chime_freq_2'));
        $chimeDelay = \App\Models\SystemSetting::get('chime_delay', config('geofence.chime_delay'));

        return view('livewire.commuter.geofence-detector', [
            'stops' => $stops,
            'destinationStops' => collect($this->destinationStops),
            'breakdownAlert' => $breakdownAlert,
            'maintenanceAlert' => $maintenanceAlert,
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
