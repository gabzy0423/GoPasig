<?php

namespace App\Services\Commuter;

use App\Data\ArrivalDetectionResult;
use App\Data\BoardingDetectionResult;
use App\Data\CommuterJourneyContext;
use App\Data\CommuterLocation;
use App\Data\StopGeofenceEvaluation;
use App\Data\WaitingRuntimeContext;
use App\Models\Bus;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Stop;
use App\Models\SystemSetting;
use App\Services\CommuterDashboardCacheService;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\ValueObjects\Coordinate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CommuterJourneyCoordinator
{
    public function __construct(
        private readonly CommuterDashboardCacheService $dashboardCache,
        private readonly StopGeofenceEvaluator $stopGeofenceEvaluator,
        private readonly GeospatialServiceInterface $geospatial,
    ) {}

    public function context(?string $sessionToken, ?CommuterLocation $location): CommuterJourneyContext
    {
        $session = $this->activeSession($sessionToken);
        $trip = $this->currentJourney($sessionToken);
        $stops = $this->stopsForEvaluation();

        $stopGeofence = $location
            ? $this->stopGeofenceEvaluator->evaluate($location->coordinate(), $stops)
            : new StopGeofenceEvaluation(null, null, null);

        return new CommuterJourneyContext($session, $trip, $location, $stopGeofence);
    }

    public function destinationStopsForOrigin(int $originStopId): Collection
    {
        $origin = $this->commuterVisibleStop($originStopId);

        if (! $origin) {
            return collect();
        }

        return $this->stopsForEvaluation()
            ->where('route_id', $origin->route_id)
            ->where('sequence', '>', $origin->sequence)
            ->reject(fn ($stop) => (int) $stop->id === (int) $origin->id)
            ->sortBy('sequence')
            ->values();
    }

    public function recoverWaitingRuntime(?string $sessionToken, ?CommuterLocation $location = null): WaitingRuntimeContext
    {
        return WaitingRuntimeContext::fromJourneyContext(
            $this->context($sessionToken, $location),
            CarbonImmutable::now()
        );
    }

    public function initializeWaitingJourney(?string $sessionToken, ?int $originStopId, ?int $destinationStopId): CommuterTrip
    {
        $session = $this->activeSession($sessionToken);

        if (! $session) {
            throw ValidationException::withMessages([
                'journey' => 'Hindi mahanap ang commuter session. I-refresh ang pahina at subukan muli.',
            ]);
        }

        $existingTrip = $this->activeJourney($sessionToken);

        if ($existingTrip) {
            return $existingTrip;
        }

        if (! $originStopId) {
            throw ValidationException::withMessages([
                'origin' => 'Pumunta muna sa loob ng valid stop geofence bago mag-track ng byahe.',
            ]);
        }

        if (! $destinationStopId) {
            throw ValidationException::withMessages([
                'destination' => 'Pumili muna ng destinasyon.',
            ]);
        }

        if ((int) $originStopId === (int) $destinationStopId) {
            throw ValidationException::withMessages([
                'destination' => 'Hindi maaaring pareho ang origin at destination.',
            ]);
        }

        $origin = $this->commuterVisibleStop($originStopId);
        $destination = $this->commuterVisibleStop($destinationStopId);

        if (! $origin || ! $destination) {
            throw ValidationException::withMessages([
                'destination' => 'Hindi valid ang napiling stop para sa commuter route.',
            ]);
        }

        if ((int) $origin->route_id !== (int) $destination->route_id) {
            throw ValidationException::withMessages([
                'destination' => 'Walang direktang ruta na nagkokonekta sa dalawang stop na ito.',
            ]);
        }

        if ($destination->sequence <= $origin->sequence) {
            throw ValidationException::withMessages([
                'destination' => 'Pumili ng destinasyon na kasunod ng kasalukuyang stop.',
            ]);
        }

        if (! $origin->route) {
            throw ValidationException::withMessages([
                'route' => 'Hindi mahanap ang route para sa napiling byahe.',
            ]);
        }

        return CommuterTrip::create([
            'session_token' => $session->session_token,
            'origin_stop_id' => $origin->id,
            'destination_stop_id' => $destination->id,
            'route_id' => $origin->route->id,
            'status' => 'WAITING',
            'bus_id' => null,
            'boarded_at' => null,
            'arrived_at' => null,
        ])->load(['originStop', 'destinationStop', 'route', 'bus']);
    }

    public function detectBoardingCandidate(?string $sessionToken, CommuterLocation $location): BoardingDetectionResult
    {
        $runtime = $this->recoverWaitingRuntime($sessionToken, $location);
        $trip = $runtime->journey;

        if (! $trip) {
            return BoardingDetectionResult::none('no_active_journey');
        }

        if ($trip->status !== 'WAITING') {
            return BoardingDetectionResult::none('not_waiting');
        }

        if (! $runtime->originStop || ! $runtime->route) {
            return BoardingDetectionResult::none('missing_origin_or_route');
        }

        $originGeofence = $this->stopGeofenceEvaluator->evaluate($location->coordinate(), collect([$runtime->originStop]));
        if (! $originGeofence->isInsideStop()) {
            return BoardingDetectionResult::none('outside_origin_geofence');
        }

        $radius = (float) SystemSetting::get('boarding_geofence_radius_meters', 15);
        $commuterCoordinate = $location->coordinate();

        $candidates = Bus::query()
            ->where('route_id', $runtime->route->id)
            ->where('status', Bus::STATUS_ACTIVE)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->get()
            ->map(function (Bus $bus) use ($commuterCoordinate) {
                $distance = $this->geospatial->calculateDistance(
                    $commuterCoordinate,
                    new Coordinate((float) $bus->lat, (float) $bus->lng)
                );

                return ['bus' => $bus, 'distance' => $distance];
            })
            ->filter(fn (array $candidate) => $candidate['distance'] <= $radius)
            ->sortBy([
                fn (array $a, array $b) => $a['distance'] <=> $b['distance'],
                fn (array $a, array $b) => $a['bus']->id <=> $b['bus']->id,
            ])
            ->values();

        if ($candidates->isEmpty()) {
            return BoardingDetectionResult::none('no_eligible_bus');
        }

        $nearest = $candidates->first();

        return BoardingDetectionResult::candidate($nearest['bus'], $nearest['distance']);
    }

    public function boardWaitingJourney(?string $sessionToken, int $busId, CommuterLocation $location): BoardingDetectionResult
    {
        $candidate = $this->detectBoardingCandidate($sessionToken, $location);

        if (! $candidate->candidateBus || (int) $candidate->candidateBus->id !== (int) $busId) {
            return BoardingDetectionResult::none('candidate_not_confirmed');
        }

        $trip = $this->activeJourney($sessionToken);
        if (! $trip || $trip->status !== 'WAITING') {
            return BoardingDetectionResult::none('not_waiting');
        }

        $trip->update([
            'status' => 'ON_BUS',
            'bus_id' => $candidate->candidateBus->id,
            'boarded_at' => now(),
        ]);

        return BoardingDetectionResult::boarded(
            $trip->refresh()->load(['originStop', 'destinationStop', 'route', 'bus']),
            $candidate->candidateBus,
            $candidate->distanceMeters ?? 0.0
        );
    }

    public function detectArrival(
        ?string $sessionToken,
        CommuterLocation $location,
        ?int $pendingJourneyId = null,
        ?int $pendingDestinationStopId = null,
        ?int $pendingBusId = null,
        int $pendingConfirmations = 0,
    ): ArrivalDetectionResult {
        $trip = $this->currentJourney($sessionToken);

        if (! $trip) {
            return ArrivalDetectionResult::none('no_active_journey');
        }

        if ($trip->status === 'ARRIVED') {
            return ArrivalDetectionResult::none('already_arrived', $trip);
        }

        if ($trip->status !== 'ON_BUS') {
            return ArrivalDetectionResult::none('not_on_bus', $trip);
        }

        $destination = $this->validDestinationForJourney($trip);
        if (! $destination) {
            return ArrivalDetectionResult::none('invalid_destination', $trip);
        }

        if (! $trip->bus_id || ! $trip->bus) {
            return ArrivalDetectionResult::none('assignment_invalid', $trip);
        }

        $destinationGeofence = $this->stopGeofenceEvaluator->evaluate($location->coordinate(), collect([$destination]));
        if (! $destinationGeofence->isInsideStop()) {
            return ArrivalDetectionResult::none('outside_destination_geofence', $trip);
        }

        $samePendingArrival = (int) $pendingJourneyId === (int) $trip->id
            && (int) $pendingDestinationStopId === (int) $destination->id
            && (int) $pendingBusId === (int) $trip->bus_id;

        if (! $samePendingArrival || $pendingConfirmations < 1) {
            return ArrivalDetectionResult::firstConfirmation($trip, $destination, (int) $trip->bus_id);
        }

        return $this->completeOnboardJourney($trip, $destination, (int) $trip->bus_id);
    }

    public function completeOnboardJourney(CommuterTrip $trip, Stop $destination, int $busId): ArrivalDetectionResult
    {
        if ($trip->status === 'ARRIVED') {
            return ArrivalDetectionResult::none('already_arrived', $trip);
        }

        if ($trip->status !== 'ON_BUS') {
            return ArrivalDetectionResult::none('not_on_bus', $trip);
        }

        if (! $this->validDestinationForJourney($trip) || (int) $trip->destination_stop_id !== (int) $destination->id) {
            return ArrivalDetectionResult::none('invalid_destination', $trip);
        }

        if (! $trip->bus_id || (int) $trip->bus_id !== (int) $busId || ! $trip->bus) {
            return ArrivalDetectionResult::none('assignment_invalid', $trip);
        }

        DB::transaction(function () use ($trip) {
            CommuterTrip::query()
                ->whereKey($trip->id)
                ->where('status', 'ON_BUS')
                ->whereNull('arrived_at')
                ->update([
                    'status' => 'ARRIVED',
                    'arrived_at' => now(),
                ]);
        });

        $completedTrip = $trip->refresh()->load(['originStop', 'destinationStop', 'route', 'bus']);

        if ($completedTrip->status !== 'ARRIVED') {
            return ArrivalDetectionResult::none('already_arrived', $completedTrip);
        }

        return ArrivalDetectionResult::arrived($completedTrip, $destination, $busId);
    }

    private function activeSession(?string $sessionToken): ?CommuterSession
    {
        if (! $sessionToken) {
            return null;
        }

        return CommuterSession::where('session_token', $sessionToken)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    private function activeJourney(?string $sessionToken): ?CommuterTrip
    {
        if (! $sessionToken) {
            return null;
        }

        return CommuterTrip::with(['originStop', 'destinationStop', 'route', 'bus'])
            ->where('session_token', $sessionToken)
            ->whereIn('status', ['WAITING', 'ON_BUS'])
            ->first();
    }

    private function currentJourney(?string $sessionToken): ?CommuterTrip
    {
        if (! $sessionToken) {
            return null;
        }

        return CommuterTrip::with(['originStop', 'destinationStop', 'route', 'bus'])
            ->where('session_token', $sessionToken)
            ->whereIn('status', ['WAITING', 'ON_BUS', 'ARRIVED'])
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    private function validDestinationForJourney(CommuterTrip $trip): ?Stop
    {
        if (! $trip->destination_stop_id || ! $trip->route_id) {
            return null;
        }

        $destination = $trip->destinationStop;
        if (! $destination) {
            return null;
        }

        if ((int) $destination->route_id !== (int) $trip->route_id) {
            return null;
        }

        return $destination;
    }

    private function stopsForEvaluation(): Collection
    {
        return $this->dashboardCache->routeStops();
    }

    private function commuterVisibleStop(int $stopId)
    {
        return $this->stopsForEvaluation()
            ->first(fn ($stop) => (int) $stop->id === (int) $stopId);
    }
}