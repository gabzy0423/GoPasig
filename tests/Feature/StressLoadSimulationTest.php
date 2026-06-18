<?php

namespace Tests\Feature;

use App\Livewire\Commuter\GeofenceDetector;
use App\Models\Bus;
use App\Models\CommuterSession;
use App\Models\CommuterTrip;
use App\Models\Driver;
use App\Models\Route;
use App\Models\Stop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StressLoadSimulationTest extends TestCase
{
    use RefreshDatabase;

    private Route $route;
    private Stop $origin;
    private Stop $destination;

    public function test_gopasig_local_kernel_stress_load_scenarios(): void
    {
        $this->seedLoadFixture(24);

        $this->line("\nGoPasig local kernel stress/load simulation");
        $this->line("Environment: PHPUnit + Laravel HTTP kernel + in-memory SQLite + array cache");

        foreach ([50, 100, 200] as $commuters) {
            $this->benchmark("Scenario 1A: {$commuters} commuters open /commuter/dashboard", $commuters, function ($i) {
                $response = $this
                    ->withCookie('commuter_session_token', "missing-load-token-{$i}")
                    ->get('/commuter/dashboard');

                if (!$response->isOk()) {
                    throw new \RuntimeException("HTTP {$response->getStatusCode()}");
                }
            }, [
                'sessions' => fn () => CommuterSession::count(),
            ]);

            $this->benchmark("Scenario 1B: {$commuters} commuter geofence updates", $commuters, function ($i) {
                $token = "geo-load-token-{$i}";
                CommuterSession::firstOrCreate(
                    ['session_token' => $token],
                    ['ip_address' => '127.0.0.1', 'expires_at' => now()->addHour()]
                );
                CommuterTrip::firstOrCreate(
                    ['session_token' => $token],
                    [
                        'route_id' => $this->route->id,
                        'origin_stop_id' => $this->origin->id,
                        'destination_stop_id' => $this->destination->id,
                        'status' => 'WAITING',
                    ]
                );

                request()->cookies->set('commuter_session_token', $token);
                $component = app(GeofenceDetector::class);
                $component->updateLocation($this->origin->lat, $this->origin->lng);
            }, [
                'waitingTrips' => fn () => CommuterTrip::where('status', 'WAITING')->count(),
                'boardedTrips' => fn () => CommuterTrip::where('status', 'ON_BUS')->count(),
            ]);
        }

        foreach ([5, 10, 20] as $buses) {
            $drivers = $this->driverUsers($buses);
            $cycles = 3;

            $this->benchmark("Scenario 2: {$buses} buses GPS polling x {$cycles} cycles", $buses * $cycles, function ($i) use ($drivers, $buses) {
                $driverUser = $drivers[$i % $buses];

                $response = $this->actingAs($driverUser)->postJson('/driver/trip/gps', [
                    'lat' => 14.5600 + (($i % $buses) * 0.0001),
                    'lng' => 121.0800 + (($i % $buses) * 0.0001),
                    'speed' => 24 + ($i % 8),
                    'is_simulated' => true,
                ]);

                if (!$response->isOk()) {
                    throw new \RuntimeException("HTTP {$response->getStatusCode()}");
                }
            }, [
                'gpsLogs' => fn () => DB::table('gps_logs')->count(),
                'kalmanStates' => fn () => $this->countKalmanStates($buses),
            ]);
        }

        foreach ([5, 10] as $dispatchers) {
            $users = User::factory()->count($dispatchers)->create(['role' => 'dispatcher']);

            $this->benchmark("Scenario 3: {$dispatchers} dispatchers poll overview + dispatch data", $dispatchers * 2, function ($i) use ($users) {
                $endpoint = $i % 2 === 0 ? '/fleet/api/overview-data' : '/fleet/api/dispatch-data';
                $response = $this->actingAs($users[$i % $users->count()])->getJson($endpoint);

                if (!$response->isOk()) {
                    throw new \RuntimeException("{$endpoint} HTTP {$response->getStatusCode()}");
                }
            });
        }

        $dispatcherUsers = User::factory()->count(5)->create(['role' => 'dispatcher']);
        $adminUsers = User::factory()->count(3)->create(['role' => 'admin']);
        $driverUsers = $this->driverUsers(10);

        $this->benchmark("Scenario 4: peak hour mixed load", 118, function ($i) use ($dispatcherUsers, $adminUsers, $driverUsers) {
            if ($i < 100) {
                $response = $this
                    ->withCookie('commuter_session_token', "peak-commuter-{$i}")
                    ->get('/commuter/dashboard');
            } elseif ($i < 110) {
                $driverUser = $driverUsers[$i - 100];
                $response = $this->actingAs($driverUser)->postJson('/driver/trip/gps', [
                    'lat' => 14.5610 + (($i - 100) * 0.0001),
                    'lng' => 121.0810 + (($i - 100) * 0.0001),
                    'speed' => 28,
                    'is_simulated' => true,
                ]);
            } elseif ($i < 115) {
                $response = $this->actingAs($dispatcherUsers[$i - 110])->getJson('/fleet/api/overview-data');
            } else {
                $response = $this->actingAs($adminUsers[$i - 115])->getJson('/admin/api/analytics');
            }

            if (!$response->isOk()) {
                throw new \RuntimeException("HTTP {$response->getStatusCode()}");
            }
        }, [
            'sessions' => fn () => CommuterSession::count(),
            'gpsLogs' => fn () => DB::table('gps_logs')->count(),
        ]);

        $this->benchmark('Scenario 5A: 50 simultaneous-style GPS log inserts', 50, function ($i) {
            DB::table('gps_logs')->insert([
                'trip_id' => DB::table('trips')->where('status', 'ongoing')->value('id'),
                'lat' => 14.5700 + ($i * 0.00001),
                'lng' => 121.0900 + ($i * 0.00001),
                'speed' => 25,
                'timestamp' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, [
            'gpsLogs' => fn () => DB::table('gps_logs')->count(),
        ]);

        $this->benchmark('Scenario 5B: 50 simultaneous-style commuter trip inserts', 50, function ($i) {
            $token = "write-trip-token-{$i}";
            CommuterSession::create([
                'session_token' => $token,
                'ip_address' => '127.0.0.1',
                'expires_at' => now()->addHour(),
            ]);

            CommuterTrip::create([
                'session_token' => $token,
                'route_id' => $this->route->id,
                'origin_stop_id' => $this->origin->id,
                'destination_stop_id' => $this->destination->id,
                'status' => 'WAITING',
            ]);
        }, [
            'commuterTrips' => fn () => CommuterTrip::count(),
        ]);

        $rateLimitStatuses = [];
        $rateLimitDriver = $this->driverUsers(24)[23];
        $this->benchmark('Scenario 2B: rate limit probe, one driver sends 16 GPS updates inside 1 minute', 16, function ($i) use ($rateLimitDriver, &$rateLimitStatuses) {
            $response = $this->actingAs($rateLimitDriver)->postJson('/driver/trip/gps', [
                'lat' => 14.5800 + ($i * 0.00001),
                'lng' => 121.1000 + ($i * 0.00001),
                'speed' => 25,
                'is_simulated' => true,
            ]);

            $rateLimitStatuses[$response->getStatusCode()] = ($rateLimitStatuses[$response->getStatusCode()] ?? 0) + 1;

            if (!in_array($response->getStatusCode(), [200, 429], true)) {
                throw new \RuntimeException("HTTP {$response->getStatusCode()}");
            }
        }, [
            'http200' => function () use (&$rateLimitStatuses) {
                return $rateLimitStatuses[200] ?? 0;
            },
            'http429' => function () use (&$rateLimitStatuses) {
                return $rateLimitStatuses[429] ?? 0;
            },
        ]);

        $this->assertTrue(true);
    }

    public function test_insert_only_paths_are_lightweight(): void
    {
        $this->seedLoadFixture(1);

        $this->line("\nGoPasig insert-only simulation");

        $this->benchmark('Insert-only: 50 GPS log inserts', 50, function ($i) {
            DB::table('gps_logs')->insert([
                'trip_id' => DB::table('trips')->where('status', 'ongoing')->value('id'),
                'lat' => 14.5700 + ($i * 0.00001),
                'lng' => 121.0900 + ($i * 0.00001),
                'speed' => 25,
                'timestamp' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, [
            'gpsLogs' => fn () => DB::table('gps_logs')->count(),
        ]);

        $this->benchmark('Insert-only: 50 commuter session + trip inserts', 50, function ($i) {
            $token = "insert-only-trip-token-{$i}";
            CommuterSession::create([
                'session_token' => $token,
                'ip_address' => '127.0.0.1',
                'expires_at' => now()->addHour(),
            ]);

            CommuterTrip::create([
                'session_token' => $token,
                'route_id' => $this->route->id,
                'origin_stop_id' => $this->origin->id,
                'destination_stop_id' => $this->destination->id,
                'status' => 'WAITING',
            ]);
        }, [
            'commuterTrips' => fn () => CommuterTrip::count(),
        ]);

        $this->assertTrue(true);
    }

    private function seedLoadFixture(int $busCount): void
    {
        $this->route = Route::create([
            'name' => 'Load Test Route',
            'description' => 'Stress simulation corridor',
            'polyline_coordinates' => [[14.5600, 121.0800], [14.5700, 121.0900]],
            'status' => 'Active',
            'travel_time_minutes' => 25,
        ]);

        $this->origin = Stop::create([
            'route_id' => $this->route->id,
            'name' => 'Load Origin',
            'lat' => 14.5600,
            'lng' => 121.0800,
            'radius_meters' => 100,
            'sequence' => 1,
        ]);

        $this->destination = Stop::create([
            'route_id' => $this->route->id,
            'name' => 'Load Destination',
            'lat' => 14.5700,
            'lng' => 121.0900,
            'radius_meters' => 100,
            'sequence' => 2,
        ]);

        User::factory()->create(['role' => 'dispatcher', 'name' => 'Load Dispatcher']);
        User::factory()->create(['role' => 'admin', 'name' => 'Load Admin']);

        for ($i = 0; $i < $busCount; $i++) {
            $user = User::factory()->create(['role' => 'driver']);
            $plate = sprintf('LOAD-%03d', $i + 1);
            $bus = Bus::create([
                'plate_number' => $plate,
                'route_id' => $this->route->id,
                'status' => 'active',
                'capacity' => 60,
                'lat' => 14.5600 + ($i * 0.0001),
                'lng' => 121.0800 + ($i * 0.0001),
                'speed' => 25,
                'passengers' => 0,
                'next_stop' => $this->origin->name,
                'eta' => 5,
            ]);

            $driver = Driver::create([
                'user_id' => $user->id,
                'emp_id' => sprintf('LOAD-EMP-%03d', $i + 1),
                'first_name' => 'Load',
                'last_name' => "Driver {$i}",
                'license_number' => sprintf('LOAD-LIC-%03d', $i + 1),
                'license_expiry' => now()->addYear(),
                'status' => 'active',
                'assigned_bus' => $plate,
                'assigned_route' => (string) $this->route->id,
                'performance_score' => 90,
            ]);

            DB::table('trips')->insert([
                'bus_id' => $bus->id,
                'driver_id' => $driver->id,
                'route_id' => $this->route->id,
                'status' => 'ongoing',
                'started_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Cache::forget('routes_all');
        Cache::forget('stops_all');
    }

    private function driverUsers(int $count)
    {
        return User::where('role', 'driver')->orderBy('id')->take($count)->get()->values();
    }

    private function benchmark(string $label, int $iterations, \Closure $callback, array $metrics = []): void
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $errors = [];
        $startMemory = memory_get_usage(true);
        $start = hrtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            try {
                $callback($i);
            } catch (\Throwable $exception) {
                $errors[] = $exception::class . ': ' . $exception->getMessage();
            }
        }

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $extra = [];
        foreach ($metrics as $name => $metric) {
            $extra[] = "{$name}=" . $metric();
        }

        $this->line(sprintf(
            '%s | n=%d | total=%0.2fms | avg=%0.2fms | q=%d | q/iter=%0.1f | mem_delta=%0.2fMB | peak=%0.2fMB | errors=%d%s',
            $label,
            $iterations,
            $elapsedMs,
            $elapsedMs / max(1, $iterations),
            $queries,
            $queries / max(1, $iterations),
            (memory_get_usage(true) - $startMemory) / 1048576,
            memory_get_peak_usage(true) / 1048576,
            count($errors),
            $extra ? ' | ' . implode(' | ', $extra) : ''
        ));

        if ($errors) {
            $this->line('  First error: ' . $errors[0]);
        }
    }

    private function line(string $message): void
    {
        fwrite(STDERR, $message . PHP_EOL);
    }

    private function countKalmanStates(int $buses): int
    {
        $count = 0;
        for ($i = 1; $i <= $buses; $i++) {
            $bus = Bus::orderBy('id')->skip($i - 1)->first();
            if ($bus && Cache::has("bus_kalman_state_{$bus->id}")) {
                $count++;
            }
        }

        return $count;
    }
}
