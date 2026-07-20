<?php

namespace App\Services\Testing;

use App\Models\Bus;
use App\Models\Driver;
use App\Models\Geofence;
use App\Models\GeofenceTransition;
use App\Models\GPSLog;
use App\Models\Route;
use App\Models\RouteDeviation;
use App\Models\StopArrival;
use App\Models\Trip;
use App\Models\TripProgress;
use App\Models\User;
use App\Models\VehiclePosition;
use Carbon\Carbon;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Testing\Assert as PHPUnit;
use Symfony\Component\HttpFoundation\Response;

class ControlledLocationIntelligenceHarness
{
    private const DRIVER_EMAIL = 'location-uat-driver@gopasig.test';
    private const DISPATCHER_EMAIL = 'location-uat-dispatcher@gopasig.test';
    private const ADMIN_EMAIL = 'location-uat-admin@gopasig.test';
    private const BUS_PLATE = 'UAT-LOC-C';

    /**
     * Execute the Route C diagnostic replay.
     *
     * @return array<string, mixed>
     */
    public function run(array $options = []): array
    {
        $this->assertSafeEnvironment();

        $context = $this->prepareContext((int) ($options['route_id'] ?? 3));
        if (($options['reset'] ?? true) === true) {
            $this->resetRunState($context);
        }

        $sequence = $this->buildRouteCSequence($context['route']);
        $startedAt = Carbon::parse($options['started_at'] ?? now('Asia/Manila'))->setTimezone('UTC');
        $stepSpacingSeconds = max(10, (int) ($options['step_spacing_seconds'] ?? 10));

        $results = [];
        $originalTestNow = Carbon::getTestNow();

        try {
            foreach ($sequence as $index => $step) {
                $stepTime = $startedAt->copy()->addSeconds($index * $stepSpacingSeconds);
                Carbon::setTestNow($stepTime);

                $payload = $this->payloadForStep($step, $stepTime, $index);
                $post = $this->postJsonAs($context['driverUser'], '/driver/trip/gps', $payload);
                $snapshot = $this->snapshot($context, $step, $payload, $post);
                $results[] = $snapshot;

                if (($post['status'] ?? 500) >= 400) {
                    break;
                }
            }
        } finally {
            Carbon::setTestNow($originalTestNow);
            Auth::logout();
        }

        return [
            'route' => [
                'id' => $context['route']->id,
                'name' => $context['route']->name,
                'polyline_points' => count($context['route']->polyline_coordinates ?? []),
            ],
            'trip' => [
                'id' => $context['trip']->id,
                'bus_id' => $context['bus']->id,
                'driver_id' => $context['driver']->id,
            ],
            'sequence' => array_map(fn (array $step) => [
                'key' => $step['key'],
                'label' => $step['label'],
                'lat' => $step['lat'],
                'lng' => $step['lng'],
                'is_cached_fix' => $step['is_cached_fix'],
            ], $sequence),
            'results' => $results,
            'mismatches' => array_values(array_filter($results, fn (array $result) => ! empty($result['fleet_admin_mismatches']))),
        ];
    }

    public function assertSafeEnvironment(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Controlled Location Intelligence UAT harness may only run in local or testing environments.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function prepareContext(int $routeId = 3): array
    {
        $route = Route::with(['stops' => fn ($query) => $query->orderBy('sequence')])->findOrFail($routeId);

        if ($route->name !== 'Route C') {
            throw new \RuntimeException("This controlled harness is currently scoped to Route C. Route {$route->id} is {$route->name}.");
        }

        if (count($route->polyline_coordinates ?? []) < 2 || $route->stops->count() < 3) {
            throw new \RuntimeException('Route C must have polyline geometry and at least three ordered stops.');
        }

        $driverUser = User::updateOrCreate(
            ['email' => self::DRIVER_EMAIL],
            ['name' => 'Location UAT Driver', 'password' => Hash::make('password'), 'role' => 'driver']
        );
        $dispatcherUser = User::updateOrCreate(
            ['email' => self::DISPATCHER_EMAIL],
            ['name' => 'Location UAT Dispatcher', 'password' => Hash::make('password'), 'role' => 'dispatcher']
        );
        $adminUser = User::updateOrCreate(
            ['email' => self::ADMIN_EMAIL],
            ['name' => 'Location UAT Admin', 'password' => Hash::make('password'), 'role' => 'admin']
        );

        $firstStop = $route->stops->first();
        $bus = Bus::updateOrCreate(
            ['plate_number' => self::BUS_PLATE],
            [
                'route_id' => $route->id,
                'driver_name' => 'Location UAT Driver',
                'capacity' => 45,
                'speed' => 0,
                'passengers' => 0,
                'next_stop' => null,
                'eta' => null,
                'lat' => $firstStop->lat,
                'lng' => $firstStop->lng,
                'status' => 'operating',
                'is_simulated' => false,
                'has_observation' => false,
            ]
        );

        $driver = Driver::updateOrCreate(
            ['user_id' => $driverUser->id],
            [
                'first_name' => 'Location',
                'last_name' => 'UAT',
                'emp_id' => 'LOC-UAT-001',
                'license_number' => 'LOC-UAT-001',
                'license_expiry' => now()->addYear(),
                'status' => 'active',
                'operational_status' => 'available',
                'assigned_bus' => $bus->plate_number,
                'assigned_route' => $route->name,
                'trips_today' => 0,
                'pax_today' => 0,
            ]
        );

        Trip::where(function ($query) use ($bus, $driver) {
            $query->where('bus_id', $bus->id)->orWhere('driver_id', $driver->id);
        })
            ->where('status', 'ongoing')
            ->update([
                'status' => 'cancelled',
                'gps_session' => 'INACTIVE',
                'ended_at' => now(),
            ]);

        $trip = Trip::create([
            'bus_id' => $bus->id,
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'status' => 'ongoing',
            'gps_session' => 'ACTIVE',
            'peak_passengers' => 0,
            'started_at' => now(),
            'gps_session_started_at' => now(),
        ]);

        return compact('route', 'bus', 'driver', 'trip', 'driverUser', 'dispatcherUser', 'adminUser');
    }

    /**
     * Reset only the dedicated UAT trip/bus context before or after a replay.
     *
     * @param array<string, mixed> $context
     */
    public function resetRunState(array $context): void
    {
        $trip = $context['trip'];
        $bus = $context['bus'];

        GPSLog::where('trip_id', $trip->id)->delete();
        VehiclePosition::where('bus_id', $bus->id)->delete();
        TripProgress::where('trip_id', $trip->id)->delete();
        StopArrival::where('trip_id', $trip->id)->delete();
        GeofenceTransition::where('bus_id', $bus->id)->where('trip_id', $trip->id)->delete();
        RouteDeviation::where('trip_id', $trip->id)->delete();

        $geofenceIds = Geofence::pluck('id');
        foreach ($geofenceIds as $geofenceId) {
            Cache::forget("bus:{$bus->id}:geofence:{$geofenceId}:state");
            Cache::forget("bus:{$bus->id}:geofence:{$geofenceId}:entered_at");
            Cache::forget("bus:{$bus->id}:geofence:{$geofenceId}:exit_pending_at");
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildRouteCSequence(Route $route): array
    {
        $stops = $route->stops->values();
        $polyline = array_values($route->polyline_coordinates ?? []);

        $start = [(float) $stops[0]->lat, (float) $stops[0]->lng];
        $shaw = [(float) $stops[1]->lat, (float) $stops[1]->lng];
        $final = [(float) $stops[2]->lat, (float) $stops[2]->lng];
        $preFinal = $polyline[count($polyline) - 2];

        $steps = [];
        $this->appendPoint($steps, 'A', 'Start terminal - SPED Terminal', $start, 0.2, null, false, 'native', 10.0);

        $this->appendSampledPath(
            $steps,
            'B',
            'Gradual Route C movement from SPED Terminal toward Shaw',
            [$polyline[0], $polyline[1], $polyline[2], $polyline[3]],
            90.0,
            5.0,
            12.0
        );

        $this->appendDwell($steps, 'C', 'Fresh fixes inside Shaw Blvd. Crossing STOP geofence', $shaw, 15, 0.25, 300.0, false, 'native', 10.0);
        $this->appendPoint($steps, 'C-heartbeat', 'Cached heartbeat inside Shaw STOP geofence', $shaw, 0.25, 300.0, true, 'cached', 10.0);

        $this->appendSampledPath(
            $steps,
            'D-depart',
            'Gradual departure from Shaw along Route C corridor',
            [$polyline[3], $polyline[4]],
            90.0,
            5.0,
            12.0
        );

        $offRoute = [(float) $preFinal[0], round((float) $preFinal[1] + 0.0048, 7)];
        $this->appendSampledPath(
            $steps,
            'D-off',
            'Controlled gradual off-route deviation from Route C corridor',
            [$preFinal, $offRoute],
            60.0,
            4.0,
            12.0
        );

        $this->appendSampledPath(
            $steps,
            'E-return',
            'Controlled gradual return to Route C corridor',
            [$offRoute, $preFinal],
            60.0,
            4.0,
            12.0
        );

        $this->appendSampledPath(
            $steps,
            'F-approach',
            'Multiple points approaching One San Miguel Ave final STOP',
            [$polyline[4], $polyline[5]],
            80.0,
            4.5,
            12.0
        );
        $this->appendDwell($steps, 'F', 'Fresh fixes inside One San Miguel Ave final STOP geofence', $final, 18, 0.2, null, false, 'native', 10.0);

        return array_values($steps);
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     * @param array<int, array{0: float|int, 1: float|int}> $path
     */
    private function appendSampledPath(array &$steps, string $prefix, string $label, array $path, float $spacingMeters, float $speedMps, float $accuracy): void
    {
        $stepNumber = 1;
        for ($segment = 0; $segment < count($path) - 1; $segment++) {
            $from = [(float) $path[$segment][0], (float) $path[$segment][1]];
            $to = [(float) $path[$segment + 1][0], (float) $path[$segment + 1][1]];
            $distance = $this->distanceMeters($from, $to);
            $samples = max(2, (int) ceil($distance / $spacingMeters));
            $heading = $this->bearingDegrees($from, $to);

            for ($i = 1; $i <= $samples; $i++) {
                $ratio = $i / $samples;
                $point = [
                    $from[0] + (($to[0] - $from[0]) * $ratio),
                    $from[1] + (($to[1] - $from[1]) * $ratio),
                ];

                $this->appendPoint(
                    $steps,
                    $prefix.'-'.$stepNumber,
                    $label,
                    $point,
                    $speedMps,
                    $heading,
                    false,
                    'native',
                    $accuracy
                );
                $stepNumber++;
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     * @param array{0: float|int, 1: float|int} $point
     */
    private function appendDwell(array &$steps, string $prefix, string $label, array $point, int $count, float $speedMps, ?float $heading, bool $cached, string $speedSource, float $accuracy): void
    {
        for ($i = 1; $i <= $count; $i++) {
            $this->appendPoint($steps, $prefix.'-'.$i, $label, $point, $speedMps, $heading, $cached, $speedSource, $accuracy);
        }
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     * @param array{0: float|int, 1: float|int} $point
     */
    private function appendPoint(array &$steps, string $key, string $label, array $point, float $speedMps, ?float $heading, bool $isCachedFix, string $speedSource, float $accuracy): void
    {
        $steps[] = [
            'key' => $key,
            'label' => $label,
            'lat' => round((float) $point[0], 7),
            'lng' => round((float) $point[1], 7),
            'speed' => $speedMps,
            'heading' => $heading,
            'accuracy' => $accuracy,
            'is_cached_fix' => $isCachedFix,
            'speed_source' => $speedSource,
        ];
    }

    /**
     * @param array{0: float|int, 1: float|int} $from
     * @param array{0: float|int, 1: float|int} $to
     */
    private function distanceMeters(array $from, array $to): float
    {
        $earthRadius = 6371000.0;
        $lat1 = deg2rad((float) $from[0]);
        $lat2 = deg2rad((float) $to[0]);
        $deltaLat = deg2rad((float) $to[0] - (float) $from[0]);
        $deltaLng = deg2rad((float) $to[1] - (float) $from[1]);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * @param array{0: float|int, 1: float|int} $from
     * @param array{0: float|int, 1: float|int} $to
     */
    private function bearingDegrees(array $from, array $to): float
    {
        $lat1 = deg2rad((float) $from[0]);
        $lat2 = deg2rad((float) $to[0]);
        $deltaLng = deg2rad((float) $to[1] - (float) $from[1]);

        $y = sin($deltaLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($deltaLng);
        $bearing = rad2deg(atan2($y, $x));

        return round(fmod($bearing + 360.0, 360.0), 1);
    }
    /**
     * @return array<string, mixed>
     */
    private function payloadForStep(array $step, Carbon $stepTime, int $index): array
    {
        $fixTime = $stepTime->copy()->subMilliseconds($step['is_cached_fix'] ? 1500 : 250);

        return [
            'lat' => $step['lat'],
            'lng' => $step['lng'],
            'speed' => $step['speed'],
            'heading' => $step['heading'],
            'accuracy' => $step['accuracy'],
            'is_simulated' => false,
            'gps_fix_timestamp' => $fixTime->toIso8601String(),
            'gps_fix_age_ms' => $step['is_cached_fix'] ? 1500 : 250,
            'is_cached_fix' => $step['is_cached_fix'],
            'speed_source' => $step['speed_source'],
            '_uat_step_index' => $index,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postJsonAs(User $user, string $uri, array $payload): array
    {
        return $this->kernelRequest('POST', $uri, $user, $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonAs(User $user, string $uri): array
    {
        return $this->kernelRequest('GET', $uri, $user);
    }

    /**
     * @return array<string, mixed>
     */
    private function kernelRequest(string $method, string $uri, User $user, array $payload = []): array
    {
        $session = Session::driver();
        $session->start();
        $session->regenerateToken();

        Auth::guard()->login($user);

        $server = [
            'HTTP_ACCEPT' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        $content = null;
        if ($method !== 'GET') {
            $server['CONTENT_TYPE'] = 'application/json';
            $server['HTTP_X_CSRF_TOKEN'] = $session->token();
            $content = json_encode($payload);
        }

        $request = \Illuminate\Http\Request::create($uri, $method, [], [], [], $server, $content);
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $user);

        app()->instance('request', $request);

        /** @var Kernel $kernel */
        $kernel = app(Kernel::class);
        /** @var Response $response */
        $response = $kernel->handle($request);
        $raw = $response->getContent();
        $decoded = json_decode($raw, true);
        $kernel->terminate($request, $response);

        return [
            'status' => $response->getStatusCode(),
            'ok' => $response->getStatusCode() >= 200 && $response->getStatusCode() < 300,
            'content_type' => $response->headers->get('content-type'),
            'json' => is_array($decoded) ? $decoded : null,
            'raw' => is_string($raw) ? mb_substr($raw, 0, 500) : null,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function snapshot(array $context, array $step, array $payload, array $post): array
    {
        $trip = $context['trip']->fresh();
        $bus = $context['bus']->fresh();
        $log = GPSLog::where('trip_id', $trip->id)->latest('id')->first();
        $position = VehiclePosition::where('bus_id', $bus->id)->first();
        $progress = TripProgress::where('trip_id', $trip->id)->first();
        $transition = GeofenceTransition::where('bus_id', $bus->id)->where('trip_id', $trip->id)->latest('id')->first();
        $arrival = StopArrival::where('trip_id', $trip->id)->latest('id')->first();
        $deviation = RouteDeviation::where('trip_id', $trip->id)->latest('id')->first();

        $fleet = $this->getJsonAs($context['dispatcherUser'], '/fleet/api/bus-gps-positions');
        $admin = $this->getJsonAs($context['adminUser'], '/admin/api/fleet-data');

        $fleetBus = collect($fleet['json']['buses'] ?? [])->firstWhere('id', $bus->id);
        $adminBus = collect($admin['json']['buses'] ?? [])->firstWhere('id', $bus->id);
        $mismatches = $this->fleetAdminMismatches($fleetBus, $adminBus);

        return [
            'step' => $step['key'],
            'label' => $step['label'],
            'coordinate_sent' => [
                'lat' => $payload['lat'],
                'lng' => $payload['lng'],
                'is_cached_fix' => $payload['is_cached_fix'],
                'speed_source' => $payload['speed_source'],
                'speed_mps' => $payload['speed'],
                'heading' => $payload['heading'],
                'accuracy' => $payload['accuracy'],
            ],
            'http' => [
                'status' => $post['status'],
                'ok' => $post['ok'],
                'message' => $post['json']['message'] ?? null,
                'log_id' => $post['json']['log_id'] ?? null,
            ],
            'gps_log' => $log ? [
                'id' => $log->id,
                'processing_status' => $log->processing_status,
                'filtered_lat' => $log->filtered_lat,
                'filtered_lng' => $log->filtered_lng,
                'is_cached_fix' => (bool) $log->is_cached_fix,
                'speed_source' => $log->speed_source,
            ] : null,
            'vehicle_position' => $position ? [
                'lat' => $position->lat,
                'lng' => $position->lng,
                'corridor_distance' => $position->corridor_distance,
                'movement_state' => $position->movement_state,
                'gps_quality_state' => $position->gps_quality_state,
            ] : null,
            'trip_progress' => $progress ? [
                'current_stop_id' => $progress->current_stop_id,
                'next_stop_id' => $progress->next_stop_id,
                'completed_stops_count' => $progress->completed_stops_count,
                'trip_percentage' => $progress->trip_percentage,
                'route_adherence' => $progress->route_adherence,
            ] : null,
            'geofence' => $transition ? [
                'id' => $transition->id,
                'geofence_id' => $transition->geofence_id,
                'entered_at' => $transition->entered_at?->toIso8601String(),
                'exited_at' => $transition->exited_at?->toIso8601String(),
            ] : null,
            'stop_arrival' => $arrival ? [
                'id' => $arrival->id,
                'stop_id' => $arrival->stop_id,
                'arrival_time' => $arrival->arrival_time?->toIso8601String(),
                'departure_time' => $arrival->departure_time?->toIso8601String(),
            ] : null,
            'route_deviation' => $deviation ? [
                'id' => $deviation->id,
                'distance_meters' => $deviation->distance_meters,
                'severity' => $deviation->severity,
                'resolved_at' => $deviation->resolved_at?->toIso8601String(),
            ] : null,
            'fleet_api' => $fleetBus ? [
                'next_stop' => $fleetBus['next_stop'] ?? null,
                'upcoming_stop' => $fleetBus['upcoming_stop'] ?? null,
                'eta' => $fleetBus['eta'] ?? null,
                'corridor_distance' => $fleetBus['corridor_distance'] ?? null,
                'route_adherence' => $fleetBus['route_adherence'] ?? null,
                'has_live_telemetry' => $fleetBus['has_live_telemetry'] ?? null,
            ] : null,
            'admin_api' => $adminBus ? [
                'next_stop' => $adminBus['next_stop'] ?? null,
                'eta' => $adminBus['eta'] ?? null,
                'corridor_distance' => $adminBus['corridor_distance'] ?? null,
                'route_adherence' => $adminBus['route_adherence'] ?? null,
                'has_live_telemetry' => $adminBus['has_live_telemetry'] ?? null,
            ] : null,
            'fleet_admin_mismatches' => $mismatches,
        ];
    }

    /**
     * @param array<string, mixed>|null $fleetBus
     * @param array<string, mixed>|null $adminBus
     * @return array<string, mixed>
     */
    private function fleetAdminMismatches(?array $fleetBus, ?array $adminBus): array
    {
        if (! $fleetBus || ! $adminBus) {
            return ['presence' => 'Bus missing from one API'];
        }

        $checks = [
            'lat' => [$fleetBus['lat'] ?? null, $adminBus['lat'] ?? null],
            'lng' => [$fleetBus['lng'] ?? null, $adminBus['lng'] ?? null],
            'next_stop' => [$fleetBus['next_stop'] ?? null, $adminBus['next_stop'] ?? null],
            'eta' => [$fleetBus['eta'] ?? null, $adminBus['eta'] ?? null],
            'movement_state' => [$fleetBus['movement_state'] ?? null, $adminBus['movement_state'] ?? null],
            'operational_status' => [$fleetBus['operational_status'] ?? null, $adminBus['operational_status'] ?? null],
        ];

        $mismatches = [];
        foreach ($checks as $field => [$fleetValue, $adminValue]) {
            if ($fleetValue != $adminValue) {
                $mismatches[$field] = ['fleet' => $fleetValue, 'admin' => $adminValue];
            }
        }

        if (array_key_exists('corridor_distance', $fleetBus) && ! array_key_exists('corridor_distance', $adminBus)) {
            $mismatches['corridor_distance'] = ['fleet' => $fleetBus['corridor_distance'], 'admin' => 'not_exposed'];
        }

        return $mismatches;
    }

    /**
     * Lightweight invariant checks used by feature tests.
     *
     * @param array<string, mixed> $run
     */
    public function assertRunProcessed(array $run): void
    {
        PHPUnit::assertNotEmpty($run['results']);
        foreach ($run['results'] as $result) {
            PHPUnit::assertTrue($result['http']['ok'], 'Telemetry POST failed at step '.$result['step']);
            PHPUnit::assertSame('processed', $result['gps_log']['processing_status'] ?? null);
            PHPUnit::assertSame(true, $result['fleet_api']['has_live_telemetry'] ?? null);
            PHPUnit::assertSame(true, $result['admin_api']['has_live_telemetry'] ?? null);
        }
    }
}






