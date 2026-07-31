<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\Route;
use App\Models\Bus;
use App\Models\ServiceAlert;
use App\Services\CommuterEtaProvenanceService;
use App\Services\GPSKalmanFilter;
use Carbon\CarbonInterface;

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
        if ($this->selectedRouteId) {
            $selectedRouteObj = Route::find($this->selectedRouteId);
            if ($selectedRouteObj && ! Route::getCanonicalProductionCached()->contains('id', $selectedRouteObj->id)) {
                $this->dispatch('route-suspended', [
                    'message' => 'This route has been suspended due to an operational issue. Please select another route.'
                ]);
                $this->selectedRouteId = null;
            }
        }

        // 1. Fetch active alerts   
        $activeAlerts = ServiceAlert::activeAlerts()
            ->publicCommuterVisible()
            ->orderBy('created_at', 'desc')
            ->get();

        // 2. Fetch routes
        $routes = Route::getCanonicalProductionCached();

        // 3. Fetch active buses
        $activeBusIds = \App\Models\Trip::where('status', 'ongoing')
            ->whereHas('route', fn ($query) => $query->publicCommuterActiveService())
            ->pluck('bus_id')
            ->toArray();

        $busesQuery = Bus::with(['route', 'route.stops', 'vehiclePosition'])
            ->whereHas('route', fn ($query) => $query->publicCommuterActiveService())
            ->where('status', '!=', 'inactive')
            ->where('status', '!=', 'maintenance')
            ->where(function($q) use ($activeBusIds) {
                $q->where(function($sub) use ($activeBusIds) {
                    $sub->where('status', 'active')
                        ->whereIn('id', $activeBusIds);
                })->orWhere('status', 'breakdown');
            });

        if ($this->selectedRouteId) {
            $busesQuery->where('route_id', $this->selectedRouteId);
        }

        $etaProvenanceService = app(CommuterEtaProvenanceService::class);

        $activeBuses = $busesQuery->get()->map(function ($bus) use ($etaProvenanceService) {
            // Route color: read from the database column
            $color = $bus->route?->color ?: config('brand.route_color_unassigned', '#888780');

            // Determine commuter-visible status
            $status = 'active';
            if ($bus->status === 'breakdown') {
                $status = 'breakdown';
            } elseif ($bus->eta >= $bus->getRouteDelayThreshold() && $bus->speed > 0) {
                $status = 'delayed';
            } elseif ($bus->speed == 0 && $bus->passengers == 0) {
                // ISSUE-040 FIX: 'idle' only assigned to active buses stopped with no passengers.
                // Terminal-waiting buses also match this condition but are now excluded by
                // the 'active'-only query above (inactive/maintenance buses never reach here).
                $status = 'idle';
            }

            // Driver name: prefer bus->driver_name, then look up Driver model by plate
            $driverName = $bus->driver_name;
            if (!$driverName) {
                $driver = \App\Models\Driver::where('assigned_bus', $bus->plate_number)->first();
                $driverName = $driver ? (trim(($driver->first_name ?? '') . ' ' . ($driver->last_name ?? '')) ?: 'No Driver Assigned') : 'No Driver Assigned';
            }

            $lastGpsFixAt = $bus->vehiclePosition?->last_gps_fix_at;
            $freshness = $this->gpsFreshness($lastGpsFixAt);
            $etaProvenance = $etaProvenanceService->forBus($bus);

            return (object) [
                'bus_id' => $bus->id,
                'plate_number' => $bus->plate_number,
                'route_name' => $bus->route?->name ?? 'Unassigned',
                'route_color' => $color,
                'status' => $status,
                'next_stop_name' => $bus->next_stop ?: 'Terminal',
                'eta_minutes' => $etaProvenance->minutes,
                'eta_provenance_state' => $etaProvenance->state,
                'eta_label' => $etaProvenance->label,
                'eta_description' => $etaProvenance->description,
                'eta_is_authoritative' => $etaProvenance->is_authoritative,
                'passenger_count' => $bus->passengers,
                'capacity' => $bus->capacity,
                'lat' => (float) $bus->lat,
                'lng' => (float) $bus->lng,
                'driver_name' => $driverName,
                'speed' => $bus->speed ?: 0,
                'is_simulated' => (bool) $bus->is_simulated,
                'last_gps_fix_at' => $lastGpsFixAt?->toIso8601String(),
                'gps_freshness_state' => $freshness['state'],
                'gps_freshness_age_seconds' => $freshness['age_seconds'],
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
                    'eta_provenance_state' => $closest->eta_provenance_state,
                    'eta_label' => $closest->eta_label,
                    'eta_description' => $closest->eta_description,
                    'eta_is_authoritative' => $closest->eta_is_authoritative,
                    'distance_km' => $minDistance,
                    'passenger_count' => $closest->passenger_count,
                    'capacity' => $closest->capacity,
                    'is_simulated' => $closest->is_simulated,
                    'last_gps_fix_at' => $closest->last_gps_fix_at,
                    'gps_freshness_state' => $closest->gps_freshness_state,
                    'gps_freshness_age_seconds' => $closest->gps_freshness_age_seconds,
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

    private function gpsFreshness(?CarbonInterface $lastGpsFixAt): array
    {
        if (! $lastGpsFixAt) {
            return [
                'state' => 'UNKNOWN',
                'age_seconds' => null,
            ];
        }

        $ageSeconds = max(0, (int) $lastGpsFixAt->diffInSeconds(now()));

        return [
            'state' => match (true) {
                $ageSeconds < 30 => 'LIVE',
                $ageSeconds < 120 => 'STALE',
                default => 'OFFLINE',
            },
            'age_seconds' => $ageSeconds,
        ];
    }
}




