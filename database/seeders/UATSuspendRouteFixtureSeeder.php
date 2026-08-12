<?php

namespace Database\Seeders;

use App\Models\Bus;
use App\Models\DispatchLog;
use App\Models\Driver;
use App\Models\GPSLog;
use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use App\Models\RouteVariantStop;
use App\Models\Schedule;
use App\Models\ServiceAlert;
use App\Models\Stop;
use App\Models\Trip;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class UATSuspendRouteFixtureSeeder extends Seeder
{
    public const ROUTE_NAME = 'UAT Suspend Route - Bidirectional';
    public const LEGACY_ROUTE_NAME = 'Route UAT (Bidirectional Test Route)';
    public const OUTBOUND_BUS_PLATE = 'UAT-BUS-OUT';
    public const INBOUND_BUS_PLATE = 'UAT-BUS-IN';
    public const OUTBOUND_DRIVER_EMP_ID = 'UAT-SUSPEND-OUT-DRIVER';
    public const INBOUND_DRIVER_EMP_ID = 'UAT-SUSPEND-IN-DRIVER';
    public const OUTBOUND_DRIVER_LICENSE = 'UAT-SUSPEND-OUT-LICENSE';
    public const INBOUND_DRIVER_LICENSE = 'UAT-SUSPEND-IN-LICENSE';
    public const OUTBOUND_DRIVER_EMAIL = 'uat.driver.outbound@gopasig.test';
    public const INBOUND_DRIVER_EMAIL = 'uat.driver.inbound@gopasig.test';
    public const DRIVER_PASSWORD = 'password123';
    public const ALERT_TITLE = 'UAT Route Suspension Test';

    public function run(): array
    {
        self::cleanup(force: true, includeLegacy: true);

        return DB::transaction(function () {
            $now = now('Asia/Manila');
            $today = $now->toDateString();
            $departureBase = $now->copy()->addMinutes(15)->second(0);

            $route = Route::create([
                'name' => self::ROUTE_NAME,
                'color' => '#7C3AED',
                'description' => 'Temporary UAT-only bidirectional route for Suspend Route manual validation. Safe to remove with uat:suspend-route-fixture --cleanup.',
                'polyline_coordinates' => [],
                'geometry_version' => 1,
                'travel_time_minutes' => 20,
                'delay_threshold_minutes' => 5,
                'min_speed' => 0,
                'max_speed' => 40,
                'target_on_time_rate' => 95,
                'target_headway_minutes' => 10,
                'status' => 'Active',
            ]);

            $outboundStops = [
                ['name' => 'UAT Terminal A', 'lat' => 14.5609000, 'lng' => 121.0789000, 'sequence' => 1],
                ['name' => 'UAT Midpoint', 'lat' => 14.5588000, 'lng' => 121.0819000, 'sequence' => 2],
                ['name' => 'UAT Terminal B', 'lat' => 14.5565000, 'lng' => 121.0852000, 'sequence' => 3],
            ];

            $inboundStops = array_values(array_map(
                fn (array $stop, int $index) => array_merge($stop, ['sequence' => $index + 1]),
                array_reverse($outboundStops),
                array_keys($outboundStops)
            ));

            $legacyStops = [];
            foreach ($outboundStops as $stopDefinition) {
                $legacyStops[] = Stop::create([
                    'route_id' => $route->id,
                    'name' => $stopDefinition['name'],
                    'lat' => $stopDefinition['lat'],
                    'lng' => $stopDefinition['lng'],
                    'radius_meters' => 100,
                    'sequence' => $stopDefinition['sequence'],
                ]);
            }

            $outboundVariant = $this->createVariant($route, 'outbound', 'UAT Terminal A', 'UAT Terminal B', true, $outboundStops, $legacyStops);
            $inboundVariant = $this->createVariant($route, 'inbound', 'UAT Terminal B', 'UAT Terminal A', false, $inboundStops, array_reverse($legacyStops));

            $outboundUser = User::create([
                'name' => 'UAT Driver Outbound',
                'email' => self::OUTBOUND_DRIVER_EMAIL,
                'password' => Hash::make(self::DRIVER_PASSWORD),
                'role' => 'driver',
                'email_verified_at' => $now,
            ]);

            $inboundUser = User::create([
                'name' => 'UAT Driver Inbound',
                'email' => self::INBOUND_DRIVER_EMAIL,
                'password' => Hash::make(self::DRIVER_PASSWORD),
                'role' => 'driver',
                'email_verified_at' => $now,
            ]);

            $outboundDriver = Driver::create([
                'user_id' => $outboundUser->id,
                'emp_id' => self::OUTBOUND_DRIVER_EMP_ID,
                'first_name' => 'UAT Driver',
                'last_name' => 'Outbound',
                'license_number' => self::OUTBOUND_DRIVER_LICENSE,
                'license_expiry' => $now->copy()->addYears(2)->toDateString(),
                'status' => 'active',
                'operational_status' => 'available',
                'assigned_bus' => null,
                'assigned_route' => null,
                'address' => 'UAT Fixture Only',
                'contact_number' => '09000000001',
                'emergency_contact' => 'UAT Dispatch - 09000000000',
                'trips_today' => 0,
                'pax_today' => 0,
                'performance_score' => 100,
                'incidents_30' => 0,
            ]);

            $inboundDriver = Driver::create([
                'user_id' => $inboundUser->id,
                'emp_id' => self::INBOUND_DRIVER_EMP_ID,
                'first_name' => 'UAT Driver',
                'last_name' => 'Inbound',
                'license_number' => self::INBOUND_DRIVER_LICENSE,
                'license_expiry' => $now->copy()->addYears(2)->toDateString(),
                'status' => 'active',
                'operational_status' => 'available',
                'assigned_bus' => null,
                'assigned_route' => null,
                'address' => 'UAT Fixture Only',
                'contact_number' => '09000000002',
                'emergency_contact' => 'UAT Dispatch - 09000000000',
                'trips_today' => 0,
                'pax_today' => 0,
                'performance_score' => 100,
                'incidents_30' => 0,
            ]);

            $outboundBus = Bus::create([
                'plate_number' => self::OUTBOUND_BUS_PLATE,
                'fleet_number' => 'UAT-SR-OUT-FLEET',
                'vin' => 'UATSROUT000000001',
                'manufacturer' => 'UAT',
                'model' => 'Suspend Route Fixture',
                'year_model' => 2026,
                'route_id' => null,
                'driver_name' => Bus::DEFAULT_DRIVER_NAME,
                'capacity' => 45,
                'speed' => 0,
                'passengers' => 0,
                'next_stop' => null,
                'eta' => null,
                'lat' => $outboundStops[0]['lat'],
                'lng' => $outboundStops[0]['lng'],
                'status' => Bus::STATUS_INACTIVE,
                'is_simulated' => true,
            ]);

            $inboundBus = Bus::create([
                'plate_number' => self::INBOUND_BUS_PLATE,
                'fleet_number' => 'UAT-SR-IN-FLEET',
                'vin' => 'UATSRIN0000000001',
                'manufacturer' => 'UAT',
                'model' => 'Suspend Route Fixture',
                'year_model' => 2026,
                'route_id' => null,
                'driver_name' => Bus::DEFAULT_DRIVER_NAME,
                'capacity' => 45,
                'speed' => 0,
                'passengers' => 0,
                'next_stop' => null,
                'eta' => null,
                'lat' => $inboundStops[0]['lat'],
                'lng' => $inboundStops[0]['lng'],
                'status' => Bus::STATUS_INACTIVE,
                'is_simulated' => true,
            ]);

            $outboundSchedule = Schedule::create([
                'route_id' => $route->id,
                'route_variant_id' => $outboundVariant->id,
                'service_date' => $today,
                'bus_id' => $outboundBus->id,
                'driver_id' => $outboundDriver->id,
                'departure_time' => $departureBase->format('H:i:s'),
                'arrival_time' => $departureBase->copy()->addMinutes(20)->format('H:i:s'),
                'passengers' => 0,
                'status' => Schedule::STATUS_ON_TIME,
            ]);

            $inboundSchedule = Schedule::create([
                'route_id' => $route->id,
                'route_variant_id' => $inboundVariant->id,
                'service_date' => $today,
                'bus_id' => $inboundBus->id,
                'driver_id' => $inboundDriver->id,
                'departure_time' => $departureBase->copy()->addMinutes(10)->format('H:i:s'),
                'arrival_time' => $departureBase->copy()->addMinutes(30)->format('H:i:s'),
                'passengers' => 0,
                'status' => Schedule::STATUS_ON_TIME,
            ]);

            Cache::forget('routes_all');
            Cache::forget('stops_all');

            return [
                'route' => $route,
                'outboundVariant' => $outboundVariant,
                'inboundVariant' => $inboundVariant,
                'outboundDriver' => $outboundDriver,
                'inboundDriver' => $inboundDriver,
                'outboundUser' => $outboundUser,
                'inboundUser' => $inboundUser,
                'outboundBus' => $outboundBus,
                'inboundBus' => $inboundBus,
                'outboundSchedule' => $outboundSchedule,
                'inboundSchedule' => $inboundSchedule,
                'coordinates' => [
                    'outbound' => $outboundStops,
                    'inbound' => $inboundStops,
                ],
            ];
        });
    }

    public static function cleanup(bool $force = false, bool $includeLegacy = true): array
    {
        return DB::transaction(function () use ($force, $includeLegacy) {
            $routeNames = [self::ROUTE_NAME];
            if ($includeLegacy && class_exists(UATBidirectionalRouteSeeder::class)) {
                UATBidirectionalRouteSeeder::cleanup();
            }
            if ($includeLegacy) {
                $routeNames[] = self::LEGACY_ROUTE_NAME;
            }

            $routes = Route::withTrashed()->whereIn('name', $routeNames)->get();
            $buses = Bus::whereIn('plate_number', [self::OUTBOUND_BUS_PLATE, self::INBOUND_BUS_PLATE, 'PAS-UAT1', 'PAS-UAT2'])->get();
            $users = User::whereIn('email', [self::OUTBOUND_DRIVER_EMAIL, self::INBOUND_DRIVER_EMAIL])->get();
            $drivers = Driver::whereIn('emp_id', [self::OUTBOUND_DRIVER_EMP_ID, self::INBOUND_DRIVER_EMP_ID])
                ->orWhereIn('license_number', [self::OUTBOUND_DRIVER_LICENSE, self::INBOUND_DRIVER_LICENSE])
                ->orWhereIn('user_id', $users->pluck('id'))
                ->get();

            $tripQuery = Trip::query()->where(function ($query) use ($routes, $buses, $drivers) {
                $query->whereIn('route_id', $routes->pluck('id'))
                    ->orWhereIn('bus_id', $buses->pluck('id'))
                    ->orWhereIn('driver_id', $drivers->pluck('id'));
            });

            $ongoingTrips = (clone $tripQuery)->where('status', 'ongoing')->get(['id']);
            if ($ongoingTrips->isNotEmpty() && ! $force) {
                throw new \RuntimeException('Cleanup refused: UAT fixture has ongoing trip IDs '.$ongoingTrips->pluck('id')->join(', ').'. Re-run with --force to delete operational UAT trips.');
            }

            $tripIds = (clone $tripQuery)->pluck('id');
            $routeIds = $routes->pluck('id');
            $busIds = $buses->pluck('id');
            $driverIds = $drivers->pluck('id');
            $userIds = $users->pluck('id');
            $variantIds = RouteVariant::whereIn('route_id', $routeIds)->pluck('id');

            $fixtureBusPlates = [
                self::OUTBOUND_BUS_PLATE,
                self::INBOUND_BUS_PLATE,
                'PAS-UAT1',
                'PAS-UAT2',
            ];
            $fixtureRouteAssignments = $routeIds
                ->map(fn ($routeId) => (string) $routeId)
                ->merge($routeNames)
                ->unique()
                ->values();

            Driver::query()
                ->where(function ($query) use ($fixtureBusPlates, $fixtureRouteAssignments) {
                    $query->whereIn('assigned_bus', $fixtureBusPlates)
                        ->orWhereIn('assigned_route', $fixtureRouteAssignments);
                })
                ->get()
                ->each(function (Driver $driver) {
                    $driver->update([
                        'assigned_bus' => null,
                        'assigned_route' => null,
                        'operational_status' => $driver->status === 'active' ? 'available' : 'unavailable',
                    ]);
                });

            if ($tripIds->isNotEmpty()) {
                foreach (['trip_progresses', 'trip_progress', 'stop_arrivals', 'route_deviations', 'vehicle_positions'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->whereIn('trip_id', $tripIds)->delete();
                    }
                }

                if (class_exists(DispatchLog::class)) {
                    DispatchLog::whereIn('trip_id', $tripIds)->delete();
                }
                if (class_exists(GPSLog::class)) {
                    GPSLog::whereIn('trip_id', $tripIds)->delete();
                }
                if (Schema::hasTable('incidents')) {
                    DB::table('incidents')->whereIn('trip_id', $tripIds)->delete();
                }
                Trip::whereIn('id', $tripIds)->delete();
            }

            if ($routeIds->isNotEmpty()) {
                ServiceAlert::where(function ($query) use ($routeIds, $routeNames) {
                    $query->whereIn('route_id', $routeIds);
                    foreach ($routeNames as $routeName) {
                        $query->orWhere('affected_routes', $routeName)
                            ->orWhere('affected_routes', 'like', $routeName.',%')
                            ->orWhere('affected_routes', 'like', '%,'.$routeName)
                            ->orWhere('affected_routes', 'like', '%,'.$routeName.',%');
                    }
                })->delete();

                Schedule::whereIn('route_id', $routeIds)->delete();
                if (class_exists(RouteServiceSchedule::class)) {
                    RouteServiceSchedule::whereIn('route_id', $routeIds)->delete();
                }
                RouteVariantStop::whereIn('route_variant_id', $variantIds)->delete();
                if (Schema::hasTable('route_variant_geometry_versions')) {
                    DB::table('route_variant_geometry_versions')->whereIn('route_variant_id', $variantIds)->delete();
                }
                RouteVariant::whereIn('id', $variantIds)->delete();
                Stop::whereIn('route_id', $routeIds)->delete();
                foreach ($routes as $route) {
                    $route->forceDelete();
                }
            }

            if ($busIds->isNotEmpty()) {
                foreach (['vehicle_positions', 'geofence_transitions', 'bus_status_audit_log', 'maintenance_records'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->whereIn('bus_id', $busIds)->delete();
                    }
                }
                Bus::whereIn('id', $busIds)->delete();
            }

            if ($driverIds->isNotEmpty()) {
                foreach (['driver_messages', 'driver_route_certifications', 'trip_logs'] as $table) {
                    if (Schema::hasTable($table)) {
                        DB::table($table)->whereIn('driver_id', $driverIds)->delete();
                    }
                }
                Driver::whereIn('id', $driverIds)->delete();
            }

            if ($userIds->isNotEmpty()) {
                User::whereIn('id', $userIds)->delete();
            }

            Cache::forget('routes_all');
            Cache::forget('stops_all');

            return [
                'routes' => $routes->count(),
                'variants' => $variantIds->count(),
                'trips' => $tripIds->count(),
                'buses' => $busIds->count(),
                'drivers' => $driverIds->count(),
                'users' => $userIds->count(),
            ];
        });
    }

    private function createVariant(Route $route, string $direction, string $origin, string $destination, bool $isDefault, array $stops, array $legacyStops): RouteVariant
    {
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => $direction,
            'origin_name' => $origin,
            'destination_name' => $destination,
            'polyline_coordinates' => array_map(
                fn (array $stop) => [(float) $stop['lat'], (float) $stop['lng']],
                $stops
            ),
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => $isDefault,
        ]);

        foreach ($stops as $index => $stopDefinition) {
            RouteVariantStop::create([
                'route_variant_id' => $variant->id,
                'canonical_stop_id' => $legacyStops[$index]->id ?? null,
                'name' => $stopDefinition['name'],
                'lat' => $stopDefinition['lat'],
                'lng' => $stopDefinition['lng'],
                'radius_meters' => 100,
                'sequence' => $stopDefinition['sequence'],
                'stop_type' => $index === 0 ? 'pickup_point' : 'designated_stop',
                'coordinate_status' => 'verified',
                'coordinate_source' => 'uat_fixture',
                'coordinates_verified_at' => now(),
                'coordinate_notes' => 'Temporary UAT-only coordinate for suspend route validation.',
            ]);
        }

        return $variant;
    }
}
