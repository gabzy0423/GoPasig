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
use App\Models\TimeSlotConfiguration;
use App\Models\TripLog;
use App\Models\TripPassengerEvent;
use App\Services\DriverPerformanceService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsController extends Controller
{
    /**
     * Fetch all dashboard analytics data dynamically.
     */
    public function index(Request $request)
    {
        // Parse date range parameters
        $startDate = $request->query('start') ? Carbon::parse($request->query('start')) : Carbon::today();
        $endDate = $request->query('end') ? Carbon::parse($request->query('end')) : Carbon::today();

        // If no explicit date range, default based on system setting
        if (!$request->has('start') && !$request->has('end')) {
            $dateRange = SystemSetting::get('analytics_default_date_range', 'today');

            switch ($dateRange) {
                case 'yesterday':
                    $rangeStart = Carbon::yesterday();
                    $rangeEnd = Carbon::yesterday();
                    break;
                case 'week':
                    $rangeStart = Carbon::now()->startOfWeek();
                    $rangeEnd = Carbon::now()->endOfWeek();
                    break;
                case 'month':
                    $rangeStart = Carbon::now()->startOfMonth();
                    $rangeEnd = Carbon::now()->endOfMonth();
                    break;
                case 'today':
                default:
                    $rangeStart = Carbon::today();
                    $rangeEnd = Carbon::today();
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
        $periodStart = $rangeStart->copy()->startOfDay();
        $periodEnd = $rangeEnd->copy()->endOfDay();
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
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $periodStart = $rangeStart->copy()->startOfDay();
                $periodEnd = $rangeEnd->copy()->endOfDay();

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
            'on_time_rate' => 'Deferred',
            'delayed_trips' => null,
            'insufficient_data' => true,
        ];

        // 2. Trips started by configured time slots
        $hourlyRidership = [];
        $routes = Route::publicCommuterActiveService()->get();
        $timeSlotConfigs = TimeSlotConfiguration::where('is_active', true)->orderBy('order')->get();

        if ($timeSlotConfigs->isEmpty()) {
            \Illuminate\Support\Facades\Log::error('TimeSlotConfiguration table is empty. Admin hourly ridership charts will not be rendered. Run time slot configuration seeder.');
            $timeSlotConfigs = collect();
        }

        $startedTripsInPeriod = Trip::whereIn('route_id', $routes->pluck('id'))
            ->whereIn('status', ['ongoing', 'completed'])
            ->whereBetween('started_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
            ->get();

        foreach ($timeSlotConfigs as $slotConfig) {
            $hourlyData = [
                'hour' => $slotConfig->time_slot_display,
            ];

            foreach ($routes as $route) {
                $tripCount = $startedTripsInPeriod
                    ->where('route_id', $route->id)
                    ->filter(function (Trip $trip) use ($slotConfig) {
                        $startedTime = $trip->started_at?->format('H:i:s');

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
            ->whereBetween('ended_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
            ->get()
            ->groupBy('route_id');
        $ongoingTripsInPeriod = Trip::whereIn('route_id', $routes->pluck('id'))
            ->where('status', 'ongoing')
            ->whereBetween('started_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
            ->get()
            ->groupBy('route_id');
        $dispatchedTripsInPeriod = Trip::whereIn('route_id', $routes->pluck('id'))
            ->where('status', 'dispatched')
            ->whereBetween('dispatched_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
            ->get()
            ->groupBy('route_id');
        $cancelledTripsInPeriod = Trip::whereIn('route_id', $routes->pluck('id'))
            ->where('status', 'cancelled')
            ->whereBetween('ended_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
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
        $heatmapAverages = DemandHistory::groupBy('day_of_week', 'time_slot')
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
            ->where(function ($query) use ($rangeStart, $rangeEnd) {
                $periodStart = $rangeStart->copy()->startOfDay();
                $periodEnd = $rangeEnd->copy()->endOfDay();

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
                'startedAt' => $trip->started_at ? $trip->started_at->format('g:i A') : 'Not started',
                'endedAt' => $trip->ended_at ? $trip->ended_at->format('g:i A') : 'Not ended',
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

        $commuterTripsInPeriod = CommuterTrip::whereBetween('created_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
            ->where('is_simulated', false)
            ->get();

        // 7. Bus Ridership Summary Cards
        $busSummaryCards = [];
        $buses = Bus::all();
        $busTripsInPeriod = $tripLoadRecords->groupBy('bus_id');
        $busPassengerHandled = TripPassengerEvent::whereIn('bus_id', $buses->pluck('id'))
            ->where('event_type', TripPassengerEvent::TYPE_BOARDED)
            ->whereHas('route', $officialRouteFilter)
            ->whereBetween('recorded_at', [$rangeStart->copy()->startOfDay(), $rangeEnd->copy()->endOfDay()])
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

        // 8. Dispatch forecast recommendations are deferred until demand and TripLog sources are reliable.
        $forecastTable = [];

        // 9. Driver Performance Table
        $driverPerformance = [];
        $driversLimit = (int) SystemSetting::get('analytics_top_drivers_limit', 5);
        $drivers = Driver::orderBy('last_name')->orderBy('first_name')->get();
        $driverIds = $drivers->pluck('id');
        $periodStart = $rangeStart->copy()->startOfDay();
        $periodEnd = $rangeEnd->copy()->endOfDay();

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

        // 10. Historical Ridership (Last 30 Days or dynamic range)
        $historicalTrend = [];
        $trendStart = $rangeStart;
        $trendEnd = $rangeEnd;
        if ($trendStart->toDateString() === $trendEnd->toDateString()) {
            $trendLimit = (int) SystemSetting::get('analytics_historical_trend_limit', 30);
            $trendStart = $trendEnd->copy()->subDays($trendLimit - 1);
        }

        $trendData = DemandHistory::select('date')
            ->whereBetween('date', [$trendStart->toDateString(), $trendEnd->toDateString()])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        if ($trendData->isEmpty()) {
            $historicalTrend = [];
        } else {
            $historiesInPeriod = DemandHistory::whereBetween('date', [$trendStart->toDateString(), $trendEnd->toDateString()])->get();
            $historiesByDate = $historiesInPeriod->groupBy(function ($item) {
                return Carbon::parse($item->date)->toDateString();
            });

            foreach ($trendData as $trend) {
                $dateStr = $trend->date->toDateString();
                $dateObj = Carbon::parse($trend->date);

                $total = 0;
                $dataRow = [
                    'label' => $dateObj->format('M d'),
                ];

                $dateHistories = $historiesByDate->get($dateStr, collect());

                foreach ($routes as $route) {
                    $pax = (int) $dateHistories->where('route_id', $route->id)->sum('total_commuters');
                    $dataRow[$route->name] = $pax;
                    $total += $pax;
                }

                $dataRow['total'] = $total;
                $historicalTrend[] = $dataRow;
            }
        }

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
            'driverPerformance' => $driverPerformance,
            'historicalTrend' => $historicalTrend,
            'busCapacityLimit' => $busCapacityLimit,
        ]);
    }
}
