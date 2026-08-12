<?php

namespace App\Livewire\Commuter;

use App\Data\CommuterJourneyContext;
use App\Data\CommuterLocation;
use App\Data\WaitingRuntimeContext;
use App\Models\Bus;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Models\SystemSetting;
use App\Services\Commuter\CommuterJourneyCoordinator;
use App\Services\CommuterDashboardCacheService;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

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

    public $journeyRecovered = false;

    public $lastRecoveryAt = null;

    public $waitingDurationSeconds = null;

    public $pendingBoardingBusId = null;

    public $pendingBoardingConfirmations = 0;

    public $pendingArrivalJourneyId = null;

    public $pendingArrivalDestinationStopId = null;

    public $pendingArrivalBusId = null;

    public $pendingArrivalConfirmations = 0;

    public $completedJourneyPendingReset = false;

    protected $listeners = [
        'updateLocation' => 'updateLocation',
        'recoverJourney' => 'recoverJourney',
    ];

    public function mount()
    {
        $this->recoverJourney();
    }

    public function updateLocation($lat, $lng, $accuracy = null)
    {
        // Reject coordinates with poor GPS accuracy
        $maxAccuracy = Cache::remember('gps_max_accuracy_meters', 60, function () {
            return (int) SystemSetting::get('gps_max_accuracy_meters', 20);
        });
        if ($accuracy !== null && $accuracy > $maxAccuracy) {
            // GPS signal too inaccurate — skip journey context update
            $this->resetPendingBoarding();
            $this->resetPendingArrival();

            return;
        }

        $this->lat = floatval($lat);
        $this->lng = floatval($lng);

        $location = new CommuterLocation($this->lat, $this->lng, $accuracy);
        $coordinator = app(CommuterJourneyCoordinator::class);
        $context = $this->journeyContext($location);
        $closest = $context->nearestStop() ? $this->stopData($context->nearestStop()) : null;
        $variantOrigins = $coordinator->variantOriginsAtLocation($location);
        $destinationOptions = $coordinator->destinationOptionsForVariantOrigins($variantOrigins);
        $preferredOriginId = $destinationOptions->first()?->origin_route_variant_stop_id;
        $representativeOrigin = $variantOrigins
            ->first(fn (array $candidate) => (int) $candidate['origin']->id === (int) $preferredOriginId)
            ?? $variantOrigins->first();
        $insideStop = $representativeOrigin
            ? $this->variantStopData($representativeOrigin['origin'])
            : ($context->activeStop() ? $this->stopData($context->activeStop()) : null);

        $this->nearestStop = $closest;
        $this->distanceToNearest = $context->stopGeofence->distanceToNearestMeters !== null
            ? round($context->stopGeofence->distanceToNearestMeters)
            : null;

        if ($insideStop) {
            if (! $this->activeStop || $this->activeStop['id'] !== $insideStop['id']) {
                $this->activeStop = $insideStop;
                $this->dispatch('geofenceEntered', stopName: $insideStop['name']);
            }

            $this->destinationStops = $destinationOptions->all();
            if ($this->selectedDestinationId
                && ! $destinationOptions->contains('selection_key', (string) $this->selectedDestinationId)) {
                $this->selectedDestinationId = null;
            }
        } else {
            if ($this->activeStop) {
                $this->dispatch('geofenceExited', stopName: $this->activeStop['name']);
                $this->activeStop = null;
                $this->destinationStops = [];
            }
        }

        if ($this->completedJourneyPendingReset && ($this->activeTrip['status'] ?? null) === 'ARRIVED') {
            return;
        }

        $this->syncActiveTripFromContext($context);
        $this->evaluateSmartBoarding($location);
        $this->evaluateArrival($location);
    }

    public function checkActiveTripTransitions()
    {
        if ($this->completedJourneyPendingReset && ($this->activeTrip['status'] ?? null) === 'ARRIVED') {
            return;
        }

        $this->syncActiveTripFromContext($this->journeyContext(
            $this->lat && $this->lng ? new CommuterLocation((float) $this->lat, (float) $this->lng) : null
        ));
    }

    public function loadActiveTrip()
    {
        $this->recoverJourney(true);
    }

    public function recoverJourney(bool $force = false)
    {
        if ($this->completedJourneyPendingReset && ($this->activeTrip['status'] ?? null) === 'ARRIVED') {
            return;
        }

        if ($this->journeyRecovered && ! $force) {
            return;
        }

        $location = $this->lat !== null && $this->lng !== null
            ? new CommuterLocation((float) $this->lat, (float) $this->lng)
            : null;

        $this->syncWaitingRuntime($this->waitingRuntime($location));
        $this->resetPendingBoarding();
        $this->resetPendingArrival();
        $this->journeyRecovered = true;
    }

    public function startCommuterTrip()
    {
        $location = $this->lat !== null && $this->lng !== null
            ? new CommuterLocation((float) $this->lat, (float) $this->lng)
            : null;

        try {
            app(CommuterJourneyCoordinator::class)->initializeVariantWaitingJourney(
                request()->cookie('commuter_session_token'),
                $this->selectedDestinationId ? (string) $this->selectedDestinationId : null,
                $location
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $field => $messages) {
                $this->addError($field, $messages[0] ?? 'Hindi ma-track ang byahe sa ngayon.');
            }

            return;
        }

        $this->selectedDestinationId = null;
        $this->resetPendingBoarding();
        $this->resetPendingArrival();
        $this->loadActiveTrip();
    }

    public function cancelCommuterTrip()
    {
        $sessionToken = request()->cookie('commuter_session_token');
        if (! $sessionToken) {
            return;
        }

        CommuterTrip::where('session_token', $sessionToken)
            ->whereIn('status', ['WAITING', 'ON_BUS'])
            ->update([
                'status' => 'CANCELLED',
            ]);

        $this->activeTrip = null;
        $this->completedJourneyPendingReset = false;
        $this->resetPendingBoarding();
        $this->resetPendingArrival();
    }

    public function resetCompletedJourney(): void
    {
        if (($this->activeTrip['status'] ?? null) !== 'ARRIVED') {
            return;
        }

        $this->activeTrip = null;
        $this->selectedDestinationId = null;
        $this->waitingDurationSeconds = null;
        $this->completedJourneyPendingReset = false;
        $this->resetPendingBoarding();
        $this->resetPendingArrival();
    }

    /**
     * Dynamically calculate combined arrivals for all routes passing through this stop location.
     */
    private function getCombinedSchedule($targetStop)
    {
        // Find all stops with the same name or within the configurable grouping radius
        $groupingRadius = (int) SystemSetting::get('stop_grouping_radius', 50);
        $stops = app(CommuterDashboardCacheService::class)->routeStops()->filter(function ($s) use ($targetStop, $groupingRadius) {
            return $s->name === $targetStop->name ||
                   $this->distanceMeters($s->lat, $s->lng, $targetStop->lat, $targetStop->lng) <= $groupingRadius;
        });

        $arrivals = [];

        foreach ($stops as $stop) {
            $route = $stop->route;
            if (! $route) {
                continue;
            }

            $routeArrivalMinutes = null;

            // Build distance-weighted cumulative time offsets for each stop on the route.
            // Offset[i] = minutes from departure to reach stop at index i (0-based).
            $routeStops = $route->stops->sortBy('sequence')->values();
            $totalStopsCount = $routeStops->count();
            $routeTravelTime = $route->durations
                ->first(fn ($duration) => $duration->day_of_week === null && $duration->time_slot === null)
                ?->duration_minutes
                ?? (int) SystemSetting::get('default_travel_time_minutes', 45);
            $offsets = Stop::getDistanceWeightedOffsets($routeStops, $routeTravelTime);

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
                $targetSeq = $stop->sequence;

                if ($targetSeq >= $busNextSeq) {
                    // Extra minutes = distance-weighted offset difference between target and bus's next stop
                    $extraMins = ($seqToOffset[$targetSeq] ?? 0) - ($seqToOffset[$busNextSeq] ?? 0);
                    $incomingEtas[] = max(1, round($bus->eta + $extraMins));
                }
            }

            if (! empty($incomingEtas)) {
                $routeArrivalMinutes = min($incomingEtas);
            } else {
                // 2. No active buses — check scheduled departure
                $nextSched = $route->schedules
                    ->where('departure_time', '>', now()->toTimeString())
                    ->sortBy('departure_time')
                    ->first();
                if ($nextSched) {
                    $departure = Carbon::parse($nextSched->departure_time);
                    // Transit time = cumulative offset at this stop's 0-based index
                    $stopIndex = $stop->sequence - 1;
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
                $arrivals[] = "{$routeArrivalMinutes} mins (".$route->name.')';
            }
        }

        if (empty($arrivals)) {
            return 'No upcoming Libreng Sakay trips at this time.';
        }

        return 'Next Libreng Sakay: '.implode(', ', $arrivals);
    }

    private function distanceMeters($lat1, $lon1, $lat2, $lon2): float
    {
        $geospatial = app(GeospatialServiceInterface::class);

        return $geospatial->calculateDistance(
            new Coordinate((float) $lat1, (float) $lon1),
            new Coordinate((float) $lat2, (float) $lon2)
        );
    }

    private function evaluateSmartBoarding(CommuterLocation $location): void
    {
        if (($this->activeTrip['status'] ?? null) !== 'WAITING') {
            $this->resetPendingBoarding();

            return;
        }

        $coordinator = app(CommuterJourneyCoordinator::class);
        $candidate = $coordinator->detectBoardingCandidate(request()->cookie('commuter_session_token'), $location);

        if (! $candidate->candidateBus) {
            $this->resetPendingBoarding();

            return;
        }

        $candidateBusId = (int) $candidate->candidateBus->id;
        if ((int) $this->pendingBoardingBusId === $candidateBusId) {
            $this->pendingBoardingConfirmations++;
        } else {
            $this->pendingBoardingBusId = $candidateBusId;
            $this->pendingBoardingConfirmations = 1;
        }

        if ($this->pendingBoardingConfirmations < 2) {
            return;
        }

        $boarding = $coordinator->boardWaitingJourney(
            request()->cookie('commuter_session_token'),
            $candidateBusId,
            $location
        );

        $this->resetPendingBoarding();

        if ($boarding->boarded) {
            $this->syncWaitingRuntime($this->waitingRuntime($location));
        }
    }

    private function evaluateArrival(CommuterLocation $location): void
    {
        if (($this->activeTrip['status'] ?? null) !== 'ON_BUS') {
            $this->resetPendingArrival();

            return;
        }

        $arrival = app(CommuterJourneyCoordinator::class)->detectArrival(
            request()->cookie('commuter_session_token'),
            $location,
            $this->pendingArrivalJourneyId !== null ? (int) $this->pendingArrivalJourneyId : null,
            $this->pendingArrivalDestinationStopId !== null ? (int) $this->pendingArrivalDestinationStopId : null,
            $this->pendingArrivalBusId !== null ? (int) $this->pendingArrivalBusId : null,
            (int) $this->pendingArrivalConfirmations
        );

        if ($arrival->arrived) {
            $this->resetPendingArrival();
            if ($arrival->journey) {
                $this->syncCompletedJourney($arrival->journey);
                $this->completedJourneyPendingReset = true;
                $this->dispatch(
                    'commuter-arrived',
                    journeyId: $arrival->journey->id,
                    destinationStopName: $arrival->destinationStop?->name
                );
            }

            return;
        }

        if ($arrival->pending) {
            $this->pendingArrivalJourneyId = $arrival->pendingJourneyId;
            $this->pendingArrivalDestinationStopId = $arrival->pendingDestinationStopId;
            $this->pendingArrivalBusId = $arrival->pendingBusId;
            $this->pendingArrivalConfirmations = $arrival->confirmationCount;

            return;
        }

        $this->resetPendingArrival();
    }

    private function resetPendingBoarding(): void
    {
        $this->pendingBoardingBusId = null;
        $this->pendingBoardingConfirmations = 0;
    }

    private function resetPendingArrival(): void
    {
        $this->pendingArrivalJourneyId = null;
        $this->pendingArrivalDestinationStopId = null;
        $this->pendingArrivalBusId = null;
        $this->pendingArrivalConfirmations = 0;
    }

    private function waitingRuntime(?CommuterLocation $location): WaitingRuntimeContext
    {
        return app(CommuterJourneyCoordinator::class)
            ->recoverWaitingRuntime(request()->cookie('commuter_session_token'), $location);
    }

    private function journeyContext(?CommuterLocation $location): CommuterJourneyContext
    {
        return app(CommuterJourneyCoordinator::class)
            ->context(request()->cookie('commuter_session_token'), $location);
    }

    private function stopData(Stop $stop): array
    {
        $defaultAmenity = Cache::remember('default_amenity_setting', 60, function () {
            return SystemSetting::get('default_amenity', 'Shelter');
        });

        return [
            'id' => $stop->id,
            'name' => $stop->name,
            'lat' => (float) $stop->lat,
            'lng' => (float) $stop->lng,
            'amenities' => $stop->amenities ?: $defaultAmenity,
            'schedule' => $this->getCombinedSchedule($stop),
            'route_id' => $stop->route_id,
            'sequence' => $stop->sequence,
        ];
    }

    private function variantStopData(RouteVariantStop $stop): array
    {
        $stop->loadMissing(['canonicalStop', 'routeVariant.route']);
        $defaultAmenity = Cache::remember('default_amenity_setting', 60, function () {
            return SystemSetting::get('default_amenity', 'Shelter');
        });

        return [
            'id' => 'variant-'.$stop->id,
            'route_variant_stop_id' => (int) $stop->id,
            'name' => $stop->name,
            'lat' => (float) $stop->lat,
            'lng' => (float) $stop->lng,
            'amenities' => $stop->canonicalStop?->amenities ?: $defaultAmenity,
            'schedule' => $stop->canonicalStop
                ? $this->getCombinedSchedule($stop->canonicalStop)
                : "Check the Schedule page for today's official operating windows.",
            'route_id' => (int) $stop->routeVariant->route_id,
            'route_variant_id' => (int) $stop->route_variant_id,
            'direction' => strtolower((string) $stop->routeVariant->direction),
            'sequence' => (int) $stop->sequence,
        ];
    }

    private function syncWaitingRuntime(WaitingRuntimeContext $runtime): void
    {
        $this->waitingDurationSeconds = $runtime->waitingDurationSeconds;
        $this->lastRecoveryAt = $runtime->latestRecoveryTimestamp->toIso8601String();
        $this->syncActiveTripFromRuntime($runtime);
    }

    private function syncActiveTripFromContext(CommuterJourneyContext $context): void
    {
        $this->syncActiveTripFromRuntime(WaitingRuntimeContext::fromJourneyContext($context, now()->toImmutable()));
    }

    private function syncActiveTripFromRuntime(WaitingRuntimeContext $runtime): void
    {
        $trip = $runtime->journey;

        if (! $trip) {
            $this->activeTrip = null;

            return;
        }

        $this->activeTrip = $this->activeTripData(
            $trip,
            $runtime->route,
            $runtime->waitingDurationSeconds,
            $runtime->latestRecoveryTimestamp->toIso8601String()
        );
    }

    private function syncCompletedJourney(CommuterTrip $trip): void
    {
        $trip->loadMissing([
            'originStop',
            'destinationStop',
            'originRouteVariantStop',
            'destinationRouteVariantStop',
            'route',
            'routeVariant',
            'bus',
        ]);

        $this->activeTrip = $this->activeTripData($trip, $trip->route, null, now()->toIso8601String());
    }

    private function activeTripData(
        CommuterTrip $trip,
        ?Route $route,
        ?int $waitingDurationSeconds,
        string $recoveredAt
    ): array {
        return [
            'id' => $trip->id,
            'status' => $trip->status,
            'origin_stop_id' => $trip->origin_stop_id,
            'destination_stop_id' => $trip->destination_stop_id,
            'origin_route_variant_stop_id' => $trip->origin_route_variant_stop_id,
            'destination_route_variant_stop_id' => $trip->destination_route_variant_stop_id,
            'route_id' => $route?->id,
            'route_variant_id' => $trip->route_variant_id,
            'direction' => $trip->routeVariant?->direction,
            'origin_stop_name' => $trip->resolvedOriginStop()?->name ?? 'Unknown',
            'destination_stop_name' => $trip->resolvedDestinationStop()?->name ?? 'Unknown',
            'route_name' => $route?->name ?? 'Unknown',
            'route_color' => $route?->color ?? config('brand.route_color_default', '#003F87'),
            'bus_id' => $trip->bus_id,
            'bus_plate_number' => $trip->bus?->plate_number,
            'waiting_duration_seconds' => $waitingDurationSeconds,
            'recovered_at' => $recoveredAt,
            'arrived_at' => $trip->arrived_at?->toIso8601String(),
        ];
    }

    private function developerLocationPresets($stops): array
    {
        $presetNames = ['Pasig Rotonda', 'Ligaya', 'Rosario', 'San Joaquin'];

        return collect($presetNames)
            ->map(function (string $presetName) use ($stops) {
                $matchingStops = $stops->filter(function ($candidate) use ($presetName) {
                    return stripos((string) $candidate->name, $presetName) !== false;
                })->sortByDesc('sequence')->values();
                $stop = $matchingStops->first();

                return [
                    'label' => $presetName,
                    'lat' => $stop?->lat !== null ? (float) $stop->lat : null,
                    'lng' => $stop?->lng !== null ? (float) $stop->lng : null,
                    'stop_name' => $stop?->name,
                    'available' => $stop !== null && $stop->lat !== null && $stop->lng !== null,
                ];
            })
            ->values()
            ->all();
    }

    public function render()
    {
        $stops = app(CommuterDashboardCacheService::class)->routeStops()->sortBy('name');

        $sessionToken = request()->cookie('commuter_session_token');
        $breakdownAlert = null;
        $maintenanceAlert = null;
        if ($sessionToken) {
            $tripObj = CommuterTrip::where('session_token', $sessionToken)
                ->whereIn('status', ['WAITING', 'ON_BUS'])
                ->first();
            if ($tripObj) {
                if ($tripObj->status === 'ON_BUS') {
                    if ($tripObj->bus_id) {
                        $busObj = Bus::find($tripObj->bus_id);
                        if ($busObj) {
                            if ($busObj->status === 'breakdown') {
                                $breakdownAlert = 'Breakdown detected — please alight safely. Rescue bus incoming.';
                            } elseif ($busObj->status === 'maintenance') {
                                $maintenanceAlert = 'Pasensya na — ang inyong bus ay may maintenance issue. Mangyaring bumaba sa susunod na hintuan.';
                            }
                        }
                    } else {
                        $anyBroken = Bus::where('route_id', $tripObj->route_id)->where('status', 'breakdown')->exists();
                        $anyMaint = Bus::where('route_id', $tripObj->route_id)->where('status', 'maintenance')->exists();
                        if ($anyBroken) {
                            $breakdownAlert = 'Breakdown detected — please alight safely. Rescue bus incoming.';
                        } elseif ($anyMaint) {
                            $maintenanceAlert = 'Pasensya na — ang inyong bus ay may maintenance issue. Mangyaring bumaba sa susunod na hintuan.';
                        }
                    }
                } elseif ($tripObj->status === 'WAITING') {
                    $isAnyBusBroken = Bus::where('route_id', $tripObj->route_id)->where('status', 'breakdown')->exists();
                    if ($isAnyBusBroken) {
                        $breakdownAlert = 'Bus breakdown — please wait for next available bus';
                    }
                }
            }
        }

        $developerLocationPresets = app()->environment('local')
            ? $this->developerLocationPresets($stops)
            : [];

        $chimeFreq1 = SystemSetting::get('chime_freq_1', config('geofence.chime_freq_1'));
        $chimeFreq2 = SystemSetting::get('chime_freq_2', config('geofence.chime_freq_2'));
        $chimeDelay = SystemSetting::get('chime_delay', config('geofence.chime_delay'));

        return view('livewire.commuter.geofence-detector', [
            'stops' => $stops,
            'destinationStops' => collect($this->destinationStops),
            'breakdownAlert' => $breakdownAlert,
            'maintenanceAlert' => $maintenanceAlert,
            'developerLocationPresets' => $developerLocationPresets,
            'chimeFreq1' => $chimeFreq1,
            'chimeFreq2' => $chimeFreq2,
            'chimeDelay' => $chimeDelay,
        ]);
    }
}
