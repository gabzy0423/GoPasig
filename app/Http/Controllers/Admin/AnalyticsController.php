<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Trip;
use App\Models\Route;
use App\Models\Stop;
use App\Models\CommuterTrip;
use App\Models\SystemSetting;
use App\Models\DemandHistory;
use App\Models\MaintenanceRecord;
use App\Models\TimeSlotConfiguration;
use App\Models\TripLog;
use App\Models\TripPassengerEvent;
use App\Services\DriverPerformanceService;
use App\Services\DirectionAwareDemandForecastService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class AnalyticsController extends Controller
{
    private const ANALYTICS_TIMEZONE = 'Asia/Manila';

    /**
     * Fetch all dashboard analytics data dynamically.
     */
    public function index(Request $request)
    {
        $nowManila = Carbon::now(self::ANALYTICS_TIMEZONE);
        $startDate = $request->query('start')
            ? Carbon::parse((string) $request->query('start'), self::ANALYTICS_TIMEZONE)->startOfDay()
            : $nowManila->copy()->startOfDay();
        $endDate = $request->query('end')
            ? Carbon::parse((string) $request->query('end'), self::ANALYTICS_TIMEZONE)->startOfDay()
            : $nowManila->copy()->startOfDay();

        // If no explicit date range, default based on system setting
        if (!$request->has('start') && !$request->has('end')) {
            $dateRange = SystemSetting::get('analytics_default_date_range', 'today');

            switch ($dateRange) {
                case 'yesterday':
                    $rangeStart = $nowManila->copy()->subDay()->startOfDay();
                    $rangeEnd = $nowManila->copy()->subDay()->startOfDay();
                    break;
                case 'week':
                    $rangeStart = $nowManila->copy()->startOfWeek();
                    $rangeEnd = $nowManila->copy()->endOfWeek();
                    break;
                case 'month':
                    $rangeStart = $nowManila->copy()->startOfMonth();
                    $rangeEnd = $nowManila->copy()->endOfMonth();
                    break;
                case 'today':
                default:
                    $rangeStart = $nowManila->copy()->startOfDay();
                    $rangeEnd = $nowManila->copy()->startOfDay();
                    break;
            }
        } else {
            // Custom range
            $rangeStart = $startDate;
            $rangeEnd = $endDate;
        }

        // Fetch bus capacity limit from SystemSetting (not hardcoded)
        $busCapacityLimit = (int) SystemSetting::get('default_bus_capacity', 45);
        $officialRouteFilter = static fn ($routeQuery) => $routeQuery->publicCommuterActiveService();

        // 1. KPI Metrics
        $totalBuses = Bus::count();
        $periodStart = $rangeStart->copy()->startOfDay()->utc();
        $periodEnd = $rangeEnd->copy()->endOfDay()->utc();
        $operationalTripsForFleet = Trip::query()
            ->whereNotNull('bus_id')
            ->whereHas('route', $officialRouteFilter)
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->where(function ($completed) use ($periodStart, $periodEnd) {
                    $completed->where('status', 'completed')
                        ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($ongoing) use ($periodStart, $periodEnd) {
                    $ongoing->where('status', 'ongoing')
                        ->whereBetween('started_at', [$periodStart, $periodEnd])
                        ->whereHas('bus', fn ($busQuery) => $busQuery->whereIn('status', [
                            // A live trip transitions its bus from ready to operating.
                            // Keep active for legacy rows that already have an ongoing Trip.
                            Bus::STATUS_ACTIVE,
                            'operating',
                        ]));
                });
            });
        $busesInServiceCount = $operationalTripsForFleet
            ->distinct()
            ->count('bus_id');
        $fleetUtil = $totalBuses > 0 ? round(($busesInServiceCount / $totalBuses) * 100) : 0;

        $tripsCompleted = Trip::where('status', 'completed')
            ->whereHas('route', $officialRouteFilter)
            ->whereBetween('ended_at', [$periodStart, $periodEnd])
            ->count();

        $passengersHandled = TripPassengerEvent::where('event_type', TripPassengerEvent::TYPE_BOARDED)
            ->whereHas('route', $officialRouteFilter)
            ->whereBetween('recorded_at', [$periodStart, $periodEnd])
            ->sum('passenger_delta');
        $passengersInSelectedPeriod = TripPassengerEvent::where('event_type', TripPassengerEvent::TYPE_BOARDED)
            ->whereBetween('recorded_at', [$periodStart, $periodEnd])
            ->whereHas('route', $officialRouteFilter)
            ->sum('passenger_delta');
        $avgPassengerLoad = Trip::whereNotNull('peak_passengers')
            ->whereHas('route', $officialRouteFilter)
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query
                    ->where(function ($q) use ($periodStart, $periodEnd) {
                        $q->where('status', 'completed')
                            ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                    })
                    ->orWhere(function ($q) use ($periodStart, $periodEnd) {
                        $q->where('status', 'ongoing')
                            ->whereBetween('started_at', [$periodStart, $periodEnd]);
                    });
            })
            ->avg('peak_passengers');

        $kpis = [
            'total_pax_today' => (int) $passengersHandled,
            'pax_change_yesterday' => 'Recorded boarded events in selected period',
            // Keep the existing response key for frontend compatibility; its value follows the selected report period.
            'pax_this_week' => (int) $passengersInSelectedPeriod,
            'pax_change_last_week' => 'Recorded boarded events in selected period',
            'avg_pax_trip' => $avgPassengerLoad === null ? 0 : round((float) $avgPassengerLoad, 1),
            'avg_pax_trip_change' => 'Average peak load per actual trip in selected period',
            'trips_completed' => $tripsCompleted,
            'trips_scheduled' => null,
            'fleet_util' => $fleetUtil,
            'active_buses' => $busesInServiceCount,
            'buses_in_service' => $busesInServiceCount,
            'total_buses' => $totalBuses,
        ];

        // 2. Trips started by configured time slots
        $hourlyRidership = [];
        $routes = Route::publicCommuterActiveService()->get();
        $routeIds = $routes->pluck('id');
        $timeSlotConfigs = TimeSlotConfiguration::where('is_active', true)->orderBy('order')->get();

        if ($timeSlotConfigs->isEmpty()) {
            \Illuminate\Support\Facades\Log::error('TimeSlotConfiguration table is empty. Admin hourly ridership charts will not be rendered. Run time slot configuration seeder.');
            $timeSlotConfigs = collect();
        }

        $startedTripsInPeriod = Trip::whereIn('route_id', $routes->pluck('id'))
            ->whereIn('status', ['ongoing', 'completed'])
            ->whereBetween('started_at', [$periodStart, $periodEnd])
            ->get();

        foreach ($timeSlotConfigs as $slotConfig) {
            $hourlyData = [
                'hour' => $slotConfig->time_slot_display,
            ];

            foreach ($routes as $route) {
                $tripCount = $startedTripsInPeriod
                    ->where('route_id', $route->id)
                    ->filter(function (Trip $trip) use ($slotConfig) {
                        $startedTime = $trip->started_at
                            ?->copy()
                            ->setTimezone(self::ANALYTICS_TIMEZONE)
                            ->format('H:i:s');

                        return $startedTime !== null
                            && $startedTime >= $slotConfig->start_time
                            && $startedTime < $slotConfig->end_time;
                    })
                    ->count();

                $hourlyData[$route->name] = (int) $tripCount;
            }
            $hourlyRidership[] = $hourlyData;
        }

        // 3. Actual trips by route (Doughnut Chart & Comparison Table)
        $routeComparison = [];
        $completedTripsInPeriod = Trip::whereIn('route_id', $routes->pluck('id'))
            ->where('status', 'completed')
            ->whereBetween('ended_at', [$periodStart, $periodEnd])
            ->get()
            ->groupBy('route_id');
        $ongoingTripsInPeriod = Trip::whereIn('route_id', $routes->pluck('id'))
            ->where('status', 'ongoing')
            ->whereBetween('started_at', [$periodStart, $periodEnd])
            ->get()
            ->groupBy('route_id');
        $dispatchedTripsInPeriod = Trip::whereIn('route_id', $routes->pluck('id'))
            ->where('status', 'dispatched')
            ->whereBetween('dispatched_at', [$periodStart, $periodEnd])
            ->get()
            ->groupBy('route_id');
        $cancelledTripsInPeriod = Trip::whereIn('route_id', $routes->pluck('id'))
            ->where('status', 'cancelled')
            ->whereBetween('ended_at', [$periodStart, $periodEnd])
            ->get()
            ->groupBy('route_id');
        $totalTripsRun = $completedTripsInPeriod->flatten(1)->count() + $ongoingTripsInPeriod->flatten(1)->count();
        foreach ($routes as $route) {
            $completedTrips = $completedTripsInPeriod->get($route->id, collect())->count();
            $ongoingTrips = $ongoingTripsInPeriod->get($route->id, collect())->count();
            $dispatchedTrips = $dispatchedTripsInPeriod->get($route->id, collect())->count();
            $cancelledTrips = $cancelledTripsInPeriod->get($route->id, collect())->count();
            $tripsRun = $completedTrips + $ongoingTrips;
            $completionDenominator = $completedTrips + $cancelledTrips;
            $completionRate = $completionDenominator > 0
                ? round(($completedTrips / $completionDenominator) * 100)
                : 'No data';

            $routeComparison[] = [
                'route' => $route->name,
                'color' => $route->color,
                'trips' => $tripsRun,
                'tripsRun' => $tripsRun,
                'completedTrips' => $completedTrips,
                'ongoingTrips' => $ongoingTrips,
                'dispatchedTrips' => $dispatchedTrips,
                'cancelledTrips' => $cancelledTrips,
                'completionRate' => $completionRate,
                'pax' => 0,
                'avgPax' => 'No data',
                'peakHour' => 'No data',
                'busiestStop' => 'No data',
                'percentage' => $totalTripsRun > 0 ? round(($tripsRun / $totalTripsRun) * 100) : 0,
            ];
        }

        // 4. Heatmap Patterns (last 7 days average matrix)
        $heatmap = [];
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $timeSlotConfigs = TimeSlotConfiguration::where('is_active', true)->orderBy('order')->get();
        if ($timeSlotConfigs->isEmpty()) {
            \Illuminate\Support\Facades\Log::error('TimeSlotConfiguration table is empty. Admin heatmap patterns will not be rendered. Run time slot configuration seeder.');
            $timeSlotConfigs = collect();
        }
        $heatmapAverages = DemandHistory::forecastEligible()
            ->whereIn('route_id', $routeIds)
            ->groupBy('day_of_week', 'time_slot')
            ->select('day_of_week', 'time_slot', DB::raw('AVG(total_commuters) as avg_commuters'))
            ->get()
            ->groupBy(['day_of_week', 'time_slot']);

        foreach ($daysOfWeek as $dayName) {
            $dayRow = [];
            $dayAverages = $heatmapAverages->get($dayName);
            foreach ($timeSlotConfigs as $slotConfig) {
                $dbTimeSlot = $slotConfig->time_slot_display;

                $avgRecord = $dayAverages ? $dayAverages->get($dbTimeSlot) : null;
                $avg = $avgRecord ? $avgRecord->first()->avg_commuters : null;

                if ($avg !== null) {
                    $paxValue = round($avg);
                } else {
                    $paxValue = 0;
                }

                $dayRow[] = $paxValue;
            }
            $heatmap[$dayName] = $dayRow;
        }

        $topStopsLimit = (int) SystemSetting::get('analytics_top_stops_limit', (int) SystemSetting::get('analytics_top_stops_count', 10));

        // 5. Stop Boarding Horizontal Bars (Top Stops Flow)
        $stopBoarding = [];
        $allStops = Stop::whereHas('route', $officialRouteFilter)->get();

        $boardingCounts = CommuterTrip::where('is_simulated', false)
            ->whereHas('route', $officialRouteFilter)
            ->groupBy('origin_stop_id')
            ->select('origin_stop_id', DB::raw('count(*) as count'))
            ->pluck('count', 'origin_stop_id');

        $alightingCounts = CommuterTrip::where('is_simulated', false)
            ->whereHas('route', $officialRouteFilter)
            ->groupBy('destination_stop_id')
            ->select('destination_stop_id', DB::raw('count(*) as count'))
            ->pluck('count', 'destination_stop_id');

        foreach ($allStops as $stop) {
            $boarding = $boardingCounts->get($stop->id) ?? 0;
            $alighting = $alightingCounts->get($stop->id) ?? 0;

            $stopBoarding[] = [
                'name' => $stop->name,
                'boarding' => $boarding,
                'alighting' => $alighting,
                'net' => $boarding - $alighting,
            ];
        }

        // Sort descending by boarding
        usort($stopBoarding, fn($a, $b) => $b['boarding'] <=> $a['boarding']);
        $stopBoarding = array_slice($stopBoarding, 0, $topStopsLimit);

        // 6. Trip load table details (actual Trip records in selected period)
        $tripPaxTable = [];
        $tripLoadRecords = Trip::with(['bus', 'driver', 'route'])
            ->whereHas('route', $officialRouteFilter)
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query
                    ->where(function ($q) use ($periodStart, $periodEnd) {
                        $q->where('status', 'completed')
                            ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                    })
                    ->orWhere(function ($q) use ($periodStart, $periodEnd) {
                        $q->where('status', 'cancelled')
                            ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                    })
                    ->orWhere(function ($q) use ($periodStart, $periodEnd) {
                        $q->where('status', 'ongoing')
                            ->whereBetween('started_at', [$periodStart, $periodEnd]);
                    })
                    ->orWhere(function ($q) use ($periodStart, $periodEnd) {
                        $q->where('status', 'dispatched')
                            ->whereBetween('dispatched_at', [$periodStart, $periodEnd]);
                    });
            })
            ->get()
            ->sortBy(function (Trip $trip) {
                return match ($trip->status) {
                    'completed', 'cancelled' => $trip->ended_at,
                    'ongoing' => $trip->started_at,
                    'dispatched' => $trip->dispatched_at,
                    default => $trip->created_at,
                };
            })
            ->values();

        $tripIds = $tripLoadRecords->pluck('id');
        $tripLogsByTripId = TripLog::whereIn('trip_id', $tripIds)
            ->get()
            ->keyBy('trip_id');
        $passengerEventSums = TripPassengerEvent::whereIn('trip_id', $tripIds)
            ->select('trip_id', 'event_type', DB::raw('SUM(passenger_delta) as total'))
            ->groupBy('trip_id', 'event_type')
            ->get()
            ->groupBy('trip_id');

        foreach ($tripLoadRecords as $trip) {
            $eventSums = $passengerEventSums->get($trip->id, collect());
            $eventBoarded = (int) ($eventSums->firstWhere('event_type', TripPassengerEvent::TYPE_BOARDED)->total ?? 0);
            $eventAlighted = (int) ($eventSums->firstWhere('event_type', TripPassengerEvent::TYPE_ALIGHTED)->total ?? 0);
            $tripLog = $tripLogsByTripId->get($trip->id);
            $isFinalizedTrip = in_array($trip->status, ['completed', 'cancelled'], true);

            $tripPaxTable[] = [
                'tripNo' => 'TRIP-' . str_pad($trip->id, 3, '0', STR_PAD_LEFT),
                'plate' => $trip->bus ? $trip->bus->plate_number : 'Unassigned',
                'driver' => $trip->driver ? ($trip->driver->first_name . ' ' . $trip->driver->last_name) : 'Unassigned',
                'route' => $trip->route ? $trip->route->name : 'N/A',
                'status' => ucfirst((string) $trip->status),
                'startedAt' => $trip->started_at
                    ? $trip->started_at->copy()->setTimezone(self::ANALYTICS_TIMEZONE)->format('g:i A')
                    : 'Not started',
                'endedAt' => $trip->ended_at
                    ? $trip->ended_at->copy()->setTimezone(self::ANALYTICS_TIMEZONE)->format('g:i A')
                    : 'Not ended',
                'recordedBoarded' => $isFinalizedTrip && $tripLog ? (int) $tripLog->passengers : $eventBoarded,
                'recordedAlighted' => $isFinalizedTrip && $tripLog ? (int) $tripLog->alighted_passengers : $eventAlighted,
                'peakLoad' => (int) ($trip->peak_passengers ?? 0),
            ];
        }

        // The load timeline represents actual operations only. Keep the full
        // all-status history in tripPaxTable for the Trip Load Records table.
        $peakLoadTimeline = array_values(array_filter(
            $tripPaxTable,
            static fn (array $trip) => in_array(strtolower((string) ($trip['status'] ?? '')), ['completed', 'ongoing'], true)
        ));

        $commuterTripsInPeriod = CommuterTrip::whereBetween('created_at', [$periodStart, $periodEnd])
            ->where('is_simulated', false)
            ->get();

        // 7. Bus Ridership Summary Cards
        $busSummaryCards = [];
        $buses = Bus::all();
        $busTripsInPeriod = $tripLoadRecords->groupBy('bus_id');
        $busPassengerHandled = TripPassengerEvent::whereIn('bus_id', $buses->pluck('id'))
            ->where('event_type', TripPassengerEvent::TYPE_BOARDED)
            ->whereHas('route', $officialRouteFilter)
            ->whereBetween('recorded_at', [$periodStart, $periodEnd])
            ->select('bus_id', DB::raw('SUM(passenger_delta) as total'))
            ->groupBy('bus_id')
            ->pluck('total', 'bus_id');

        foreach ($buses as $bus) {
            $busTrips = $busTripsInPeriod->get($bus->id, collect());
            $operationalBusTrips = $busTrips->whereIn('status', ['completed', 'ongoing']);
            $tripsCount = $operationalBusTrips->count();
            $busPeak = (int) ($operationalBusTrips->max('peak_passengers') ?? 0);
            $busPeakLoads = $operationalBusTrips
                ->pluck('peak_passengers')
                ->filter(fn ($peakLoad) => $peakLoad !== null);
            $avgPassengerLoad = $busPeakLoads->isEmpty()
                ? 0
                : round((float) $busPeakLoads->avg(), 1);

            $avgCapPct = $bus->capacity > 0 ? round(($busPeak / $bus->capacity) * 100) : 0;

            $busSummaryCards[] = [
                'plate' => $bus->plate_number,
                'status' => ucfirst($bus->status),
                'trips' => $tripsCount,
                'totalPax' => (int) ($busPassengerHandled->get($bus->id) ?? 0),
                'avgPax' => $avgPassengerLoad,
                'peakLoad' => $busPeak,
                'avgCapacity' => $avgCapPct,
                'driver' => $bus->driver_name ?: 'Unassigned',
                'capacity' => $bus->capacity,
            ];
        }

        // 8. Tomorrow's direction-aware demand forecast remains advisory-only.
        $demandForecast = app(DirectionAwareDemandForecastService::class)
            ->forecastForDate(Carbon::now('Asia/Manila')->addDay()->startOfDay());
        $forecastTable = $demandForecast['rows'];

        // 9. Driver Performance Table
        $driverPerformance = [];
        $driversLimit = (int) SystemSetting::get('analytics_top_drivers_limit', 5);
        $drivers = Driver::orderBy('last_name')->orderBy('first_name')->get();
        $driverIds = $drivers->pluck('id');
        $driverTripsInPeriod = Trip::whereIn('driver_id', $driverIds)
            ->whereHas('route', $officialRouteFilter)
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->where(function ($completed) use ($periodStart, $periodEnd) {
                    $completed->where('status', 'completed')
                        ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($ongoing) use ($periodStart, $periodEnd) {
                    $ongoing->where('status', 'ongoing')
                        ->whereBetween('started_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($dispatched) use ($periodStart, $periodEnd) {
                    $dispatched->where('status', 'dispatched')
                        ->whereBetween('dispatched_at', [$periodStart, $periodEnd]);
                })->orWhere(function ($cancelled) use ($periodStart, $periodEnd) {
                    $cancelled->where('status', 'cancelled')
                        ->whereBetween('ended_at', [$periodStart, $periodEnd]);
                });
            })
            ->get()
            ->groupBy('driver_id');

        $incidentsByDriver = Incident::whereIn('driver_id', $driverIds)
            ->whereHas('trip.route', $officialRouteFilter)
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('reported_at', [$periodStart, $periodEnd])
                    ->orWhere(function ($fallback) use ($periodStart, $periodEnd) {
                        $fallback->whereNull('reported_at')
                            ->whereBetween('created_at', [$periodStart, $periodEnd]);
                    });
            })
            ->get()
            ->groupBy('driver_id');

        $allBusesCollection = Bus::all();

        $driverPerformance = $drivers->map(function (Driver $driver) use ($driverTripsInPeriod, $incidentsByDriver, $allBusesCollection, $routes) {
            $driverTrips = $driverTripsInPeriod->get($driver->id, collect());
            $completedTrips = $driverTrips->where('status', 'completed')->count();
            $ongoingTrips = $driverTrips->where('status', 'ongoing')->count();
            $dispatchedTrips = $driverTrips->where('status', 'dispatched')->count();
            $cancelledTrips = $driverTrips->where('status', 'cancelled')->count();
            $tripsRun = $completedTrips + $ongoingTrips;
            $driverIncidents = $incidentsByDriver->get($driver->id, collect());
            $qualifyingIncidents = $driverIncidents->filter(function (Incident $incident) {
                return Incident::isBreakdown($incident->type) || Incident::isAccident($incident->type);
            })->count();
            $operationalScore = DriverPerformanceService::calculateOperationalScore($tripsRun, $qualifyingIncidents);
            $peakLoad = (int) ($driverTrips
                ->whereIn('status', ['completed', 'ongoing'])
                ->max('peak_passengers') ?? 0);
            $driverBus = $driver->assigned_bus ? $allBusesCollection->firstWhere('plate_number', $driver->assigned_bus) : null;
            $driverBusCapacity = $driverBus ? $driverBus->capacity : Bus::getDefaultCapacity();

            return [
                'name' => "{$driver->first_name} {$driver->last_name}",
                'bus' => $driver->assigned_bus ?: 'PAS-000',
                'route' => $driver->assigned_route ? ($routes->firstWhere('id', (int) $driver->assigned_route)?->name ?? 'N/A') : 'N/A',
                'trips' => $tripsRun,
                'tripsRun' => $tripsRun,
                'completedTrips' => $completedTrips,
                'ongoingTrips' => $ongoingTrips,
                'dispatchedTrips' => $dispatchedTrips,
                'cancelledTrips' => $cancelledTrips,
                'pax' => 0,
                'avgPax' => 'Deferred',
                'peakLoad' => $peakLoad,
                'operationalScore' => $operationalScore,
                'qualifyingIncidents' => $qualifyingIncidents,
                'incidents' => $driverIncidents->count(),
                'capacity' => $driverBusCapacity,
            ];
        })
            ->sort(function ($a, $b) {
                return [
                    $b['operationalScore'] ?? -1,
                    $b['tripsRun'],
                    $b['completedTrips'],
                    $b['ongoingTrips'],
                    $b['peakLoad'],
                    $a['name'],
                ] <=> [
                    $a['operationalScore'] ?? -1,
                    $a['tripsRun'],
                    $a['completedTrips'],
                    $a['ongoingTrips'],
                    $a['peakLoad'],
                    $b['name'],
                ];
            })
            ->take($driversLimit)
            ->values()
            ->map(function ($row, $index) {
                $row['rank'] = '#' . ($index + 1);

                return $row;
            })
            ->toArray();

        // 10. Historical demand uses finalized actual observations, not the
        // stricter forecast-training trust gate or the selected report period.
        $historicalDemand = $this->buildHistoricalDemandPayload($timeSlotConfigs);
        $historicalTrend = $this->buildHistoricalTrendCompatibility($historicalDemand);

        // 11. Maintenance Log report records use the maintenance module source of truth.
        $maintenanceRecords = MaintenanceRecord::with('bus')
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query
                    ->whereBetween('scheduled_at', [$periodStart, $periodEnd])
                    ->orWhere(function ($completed) use ($periodStart, $periodEnd) {
                        $completed->whereNotNull('completed_at')
                            ->whereBetween('completed_at', [$periodStart, $periodEnd]);
                    });
            })
            ->orderByDesc('scheduled_at')
            ->get();

        $maintenanceBusById = Bus::whereIn('id', $maintenanceRecords->map(
            fn (MaintenanceRecord $record) => $record->getRawOriginal('bus_id')
        )->filter()->unique()->values())->get()->keyBy('id');

        $maintenanceLogRecords = $maintenanceRecords->map(function (MaintenanceRecord $record) use ($maintenanceBusById) {
            $formatMaintenanceDate = fn (?string $value) => $value
                ? Carbon::parse($value, 'UTC')->setTimezone(self::ANALYTICS_TIMEZONE)->format('M d, Y g:i A')
                : null;
            $scheduledAt = $formatMaintenanceDate($record->getRawOriginal('scheduled_at'));
            $completedAt = $formatMaintenanceDate($record->getRawOriginal('completed_at'));
            $bus = $maintenanceBusById->get((int) $record->getRawOriginal('bus_id'));

            return [
                'ticket' => $record->ticket_number ?: 'MT-' . str_pad((string) $record->id, 6, '0', STR_PAD_LEFT),
                'bus' => $bus ? $bus->plate_number : 'Unassigned',
                'type' => $record->type,
                'status' => $record->status,
                'scheduledAt' => $scheduledAt ?: 'Not scheduled',
                'completedAt' => $completedAt ?: 'Not completed',
                'technician' => $record->technician_name ?: 'Unassigned',
                'inspector' => $record->inspector_name ?: ($record->inspected_by ?: 'Unassigned'),
                'result' => $record->maintenance_result ?: 'No result',
                'roadworthy' => $record->roadworthy === null ? 'No data' : ($record->roadworthy ? 'Yes' : 'No'),
                'totalCost' => round((float) ($record->cost_php ?? 0), 2),
            ];
        })->values()->all();

        $maintenanceSummary = [
            'total' => $maintenanceRecords->count(),
            'completed' => $maintenanceRecords->where('status', 'completed')->count(),
            'active' => $maintenanceRecords->whereIn('status', ['scheduled', 'in_progress'])->count(),
        ];

        // Return combined JSON response
        return response()->json([
            'success' => true,
            'kpis' => $kpis,
            'hourlyRidership' => $hourlyRidership,
            'routeComparison' => $routeComparison,
            'heatmap' => $heatmap,
            'stopBoarding' => $stopBoarding,
            'tripPaxTable' => $tripPaxTable,
            'peakLoadTimeline' => $peakLoadTimeline,
            'busSummaryCards' => $busSummaryCards,
            'forecastTable' => $forecastTable,
            'demandForecast' => $demandForecast,
            'driverPerformance' => $driverPerformance,
            'historicalDemand' => $historicalDemand,
            'historicalTrend' => $historicalTrend,
            'maintenanceLogRecords' => $maintenanceLogRecords,
            'maintenanceSummary' => $maintenanceSummary,
            'busCapacityLimit' => $busCapacityLimit,
        ]);
    }

    private function buildHistoricalDemandPayload(Collection $timeSlotConfigs): array
    {
        $timezone = 'Asia/Manila';
        $now = Carbon::now($timezone);
        $rangeEnd = $now->copy()->startOfDay();
        $rangeStart = $rangeEnd->copy()->subDays(29);
        $dates = collect(range(0, 29))->map(function (int $offset) use ($rangeStart, $rangeEnd) {
            $date = $rangeStart->copy()->addDays($offset);

            return [
                'date' => $date->toDateString(),
                'label' => $date->format('M d'),
                'is_today' => $date->isSameDay($rangeEnd),
            ];
        });

        $routes = Route::publicCommuterActiveService()
            ->with(['variants' => function ($query) {
                $query->whereIn('direction', ['outbound', 'inbound'])
                    ->orderByRaw("CASE direction WHEN 'outbound' THEN 0 WHEN 'inbound' THEN 1 ELSE 2 END")
                    ->orderBy('id');
            }])
            ->get();
        $variants = $routes->flatMap->variants;
        $variantIds = $variants->pluck('id');

        $histories = $variantIds->isEmpty()
            ? collect()
            : DemandHistory::finalizedActual()
                ->whereIn('route_id', $routes->pluck('id'))
                ->whereIn('route_variant_id', $variantIds)
                ->whereBetween('date', [$rangeStart->toDateString(), $rangeEnd->toDateString()])
                ->get();
        $historiesByDirectionDate = $histories->groupBy(function (DemandHistory $history) {
            return $history->route_variant_id.'|'.$history->date->toDateString();
        });

        $expectedSlotsByDate = $dates->mapWithKeys(function (array $date) use ($timeSlotConfigs, $now, $timezone) {
            $closedSlots = $timeSlotConfigs->filter(function (TimeSlotConfiguration $slot) use ($date, $now, $timezone) {
                $slotStart = Carbon::parse($date['date'].' '.substr((string) $slot->start_time, 0, 8), $timezone);
                $slotEnd = Carbon::parse($date['date'].' '.substr((string) $slot->end_time, 0, 8), $timezone);

                if ($slotEnd->lessThanOrEqualTo($slotStart)) {
                    $slotEnd->addDay();
                }

                return $slotEnd->lessThanOrEqualTo($now);
            })->count();

            return [$date['date'] => $closedSlots];
        });

        $coverageCounts = [
            'finalized' => 0,
            'partial' => 0,
            'unavailable' => 0,
        ];
        $series = [];

        foreach ($routes as $route) {
            foreach ($route->variants as $variant) {
                $points = $dates->map(function (array $date) use (
                    $variant,
                    $historiesByDirectionDate,
                    $expectedSlotsByDate,
                    &$coverageCounts
                ) {
                    $records = $historiesByDirectionDate->get($variant->id.'|'.$date['date'], collect());
                    $finalizedSlots = $records->unique('time_slot')->count();
                    $expectedSlots = (int) $expectedSlotsByDate->get($date['date'], 0);

                    if ($finalizedSlots === 0) {
                        $coverage = 'unavailable';
                        $value = null;
                    } else {
                        $coverage = $expectedSlots > 0 && $finalizedSlots >= $expectedSlots
                            ? 'finalized'
                            : 'partial';
                        $value = (int) $records->sum('total_commuters');
                    }

                    $coverageCounts[$coverage]++;

                    return [
                        'date' => $date['date'],
                        'value' => $value,
                        'coverage' => $coverage,
                        'finalized_slots' => $finalizedSlots,
                        'expected_slots' => $expectedSlots,
                    ];
                })->values()->all();
                $direction = strtolower((string) $variant->direction);
                $directionCode = $direction === 'outbound' ? 'OUT' : 'IN';

                $series[] = [
                    'route_id' => (int) $route->id,
                    'route_name' => $route->name,
                    'route_variant_id' => (int) $variant->id,
                    'direction' => $direction,
                    'direction_label' => ucfirst($direction),
                    'label' => $route->name.' '.$directionCode,
                    'color' => $route->color,
                    'points' => $points,
                ];
            }
        }

        $totalPoints = array_sum($coverageCounts);
        $coverageLabel = $totalPoints === 0
            ? 'No official route directions configured.'
            : sprintf(
                '%d finalized, %d partial, %d unavailable direction-days',
                $coverageCounts['finalized'],
                $coverageCounts['partial'],
                $coverageCounts['unavailable']
            );

        return [
            'range' => [
                'start' => $rangeStart->toDateString(),
                'end' => $rangeEnd->toDateString(),
                'days' => 30,
                'timezone' => $timezone,
            ],
            'basis' => 'Finalized actual commuter check-ins',
            'dates' => $dates->values()->all(),
            'series' => $series,
            'coverage' => array_merge($coverageCounts, [
                'total' => $totalPoints,
                'label' => $coverageLabel,
            ]),
        ];
    }

    private function buildHistoricalTrendCompatibility(array $historicalDemand): array
    {
        $series = collect($historicalDemand['series']);

        return collect($historicalDemand['dates'])
            ->map(function (array $date, int $index) use ($series) {
                $row = [
                    'date' => $date['date'],
                    'label' => $date['label'],
                ];
                $allValues = collect();

                foreach ($series->groupBy('route_name') as $routeName => $routeSeries) {
                    $values = $routeSeries
                        ->map(fn (array $item) => $item['points'][$index]['value'])
                        ->filter(fn ($value) => $value !== null);
                    $row[$routeName] = $values->isEmpty() ? null : (int) $values->sum();
                    $allValues = $allValues->concat($values);
                }

                $row['total'] = $allValues->isEmpty() ? null : (int) $allValues->sum();

                return $row;
            })
            ->filter(fn (array $row) => $row['total'] !== null)
            ->values()
            ->all();
    }
}
