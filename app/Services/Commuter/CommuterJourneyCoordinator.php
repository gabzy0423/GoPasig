<?php

namespace App\Services\Commuter;

use App\Data\ArrivalDetectionResult;
use App\Data\BoardingDetectionResult;
use App\Data\CommuterJourneyContext;
use App\Data\CommuterLocation;
use App\Data\StopGeofenceEvaluation;
use App\Data\WaitingRuntimeContext;
use App\Enums\TripStatus;
use App\Models\Bus;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Stop;
use App\Models\SystemSetting;
use App\Services\CommuterDashboardCacheService;
use App\Services\Contracts\GeospatialServiceInterface;
use App\Services\DemandHistoryBridgeService;
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
        private readonly VariantAwareCommuterJourneyResolver $variantJourneyResolver,
        private readonly GeospatialServiceInterface $geospatial,
        private readonly DemandHistoryBridgeService $demandHistoryBridge,
    ) {}

    public function context(?string $sessionToken, ?CommuterLocation $location): CommuterJourneyContext
    {
        $session = $this->activeSession($sessionToken);
        $trip = $this->activeJourney($sessionToken);
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

    public function variantOriginsAtLocation(CommuterLocation $location): Collection
    {
        return $this->variantJourneyResolver->originsAtLocation($location);
    }

    public function destinationOptionsAtLocation(CommuterLocation $location): Collection
    {
        return $this->variantJourneyResolver->destinationOptionsAtLocation($location);
    }

    public function destinationOptionsForVariantOrigins(Collection $origins): Collection
    {
        return $this->variantJourneyResolver->destinationOptionsForOrigins($origins);
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

        $resolution = $this->variantJourneyResolver->resolveLegacyStops($origin, $destination);

        if (! $resolution) {
            throw ValidationException::withMessages([
                'destination' => 'Hindi matukoy nang ligtas ang direksyon ng napiling byahe.',
            ]);
        }

        return $this->createWaitingJourney($session, $resolution);
    }

    public function initializeVariantWaitingJourney(
        ?string $sessionToken,
        ?string $selectionKey,
        ?CommuterLocation $location
    ): CommuterTrip {
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

        if (! $location) {
            throw ValidationException::withMessages([
                'origin' => 'Pumunta muna sa loob ng valid stop geofence bago mag-track ng byahe.',
            ]);
        }

        if (! $selectionKey) {
            throw ValidationException::withMessages([
                'destination' => 'Pumili muna ng destinasyon.',
            ]);
        }

        $resolution = $this->variantJourneyResolver->resolveSelection($selectionKey, $location);

        if (! $resolution) {
            throw ValidationException::withMessages([
                'destination' => 'Hindi matukoy nang ligtas ang direksyon ng napiling byahe. Piliin muli ang destinasyon.',
            ]);
        }

        return $this->createWaitingJourney($session, $resolution);
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

        $origin = $this->validOriginForJourney($trip);

        if (! $origin || ! $runtime->route) {
            return BoardingDetectionResult::none('missing_origin_or_route');
        }

        if (! Route::publicCommuterActiveService()->whereKey($runtime->route->id)->exists()) {
            return BoardingDetectionResult::none('route_not_active');
        }

        $originGeofence = $this->stopGeofenceEvaluator->evaluate($location->coordinate(), collect([$origin]));
        if (! $originGeofence->isInsideStop()) {
            return BoardingDetectionResult::none('outside_origin_geofence');
        }

        $radius = (float) SystemSetting::get('boarding_geofence_radius_meters', 15);
        $commuterCoordinate = $location->coordinate();

        $candidates = Bus::query()
            ->where('route_id', $runtime->route->id)
            ->whereIn('status', Bus::commuterServiceStatuses())
            ->whereHas('trips', function ($query) use ($runtime) {
                $query->where('status', TripStatus::ONGOING->value)
                    ->where('route_id', $runtime->route->id);

                if ($runtime->journey?->route_variant_id) {
                    $query->where('route_variant_id', $runtime->journey->route_variant_id);
                }
            })
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
            $trip->refresh()->load($this->journeyRelations()),
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

    public function completeOnboardJourney(
        CommuterTrip $trip,
        Stop|RouteVariantStop $destination,
        int $busId
    ): ArrivalDetectionResult {
        if ($trip->status === 'ARRIVED') {
            return ArrivalDetectionResult::none('already_arrived', $trip);
        }

        if ($trip->status !== 'ON_BUS') {
            return ArrivalDetectionResult::none('not_on_bus', $trip);
        }

        if (! $this->journeyDestinationMatches($trip, $destination)) {
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

        $completedTrip = $trip->refresh()->load($this->journeyRelations());

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

        return CommuterTrip::with($this->journeyRelations())
            ->where('session_token', $sessionToken)
            ->whereIn('status', ['WAITING', 'ON_BUS'])
            ->first();
    }

    private function currentJourney(?string $sessionToken): ?CommuterTrip
    {
        if (! $sessionToken) {
            return null;
        }

        return CommuterTrip::with($this->journeyRelations())
            ->where('session_token', $sessionToken)
            ->whereIn('status', ['WAITING', 'ON_BUS', 'ARRIVED'])
            ->latest('updated_at')
            ->latest('id')
            ->first();
    }

    private function validDestinationForJourney(CommuterTrip $trip): Stop|RouteVariantStop|null
    {
        if (! $trip->route_id) {
            return null;
        }

        if ($trip->destination_route_variant_stop_id) {
            $destination = $trip->destinationRouteVariantStop;

            if (! $destination || ! $trip->route_variant_id) {
                return null;
            }

            if ((int) $destination->route_variant_id !== (int) $trip->route_variant_id) {
                return null;
            }

            if ((int) $trip->routeVariant?->route_id !== (int) $trip->route_id) {
                return null;
            }

            return $destination;
        }

        if (! $trip->destination_stop_id) {
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

    private function validOriginForJourney(CommuterTrip $trip): Stop|RouteVariantStop|null
    {
        if ($trip->origin_route_variant_stop_id) {
            $origin = $trip->originRouteVariantStop;

            if (! $origin || ! $trip->route_variant_id) {
                return null;
            }

            return (int) $origin->route_variant_id === (int) $trip->route_variant_id
                ? $origin
                : null;
        }

        return $trip->originStop;
    }

    private function journeyDestinationMatches(
        CommuterTrip $trip,
        Stop|RouteVariantStop $destination
    ): bool {
        $resolved = $this->validDestinationForJourney($trip);

        if (! $resolved || $resolved::class !== $destination::class) {
            return false;
        }

        return (int) $resolved->id === (int) $destination->id;
    }

    private function createWaitingJourney(CommuterSession $session, array $resolution): CommuterTrip
    {
        /** @var Route $route */
        $route = $resolution['route'];
        /** @var RouteVariant $variant */
        $variant = $resolution['variant'];
        /** @var RouteVariantStop $origin */
        $origin = $resolution['origin'];
        /** @var RouteVariantStop $destination */
        $destination = $resolution['destination'];

        $trip = CommuterTrip::create([
            'session_token' => $session->session_token,
            'origin_stop_id' => $origin->canonical_stop_id,
            'origin_route_variant_stop_id' => $origin->id,
            'destination_stop_id' => $destination->canonical_stop_id,
            'destination_route_variant_stop_id' => $destination->id,
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'status' => 'WAITING',
            'is_simulated' => false,
            'bus_id' => null,
            'boarded_at' => null,
            'arrived_at' => null,
        ]);

        $this->demandHistoryBridge->recordCommuterCheckIn($trip);

        return $trip->load($this->journeyRelations());
    }

    private function journeyRelations(): array
    {
        return [
            'originStop',
            'destinationStop',
            'originRouteVariantStop',
            'destinationRouteVariantStop',
            'route',
            'routeVariant',
            'bus',
        ];
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
