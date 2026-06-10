<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\Schedule;
use App\Models\Route;
use App\Models\Stop;
use App\Models\Alert;
use App\Models\SystemSetting;
use Carbon\Carbon;

class CommuterSchedule extends Component
{
    public $routes = [];
    public $activeRouteFilter = null;
    public $selectedTripId = null;
    public $selectedTrip = null;
    public $setAlerts = []; // to track alert registration state locally

    public function mount()
    {
        $this->loadRoutes();
    }

    public function loadRoutes()
    {
        $this->routes = Route::all()->toArray();
    }

    public function filterByRoute($routeId)
    {
        $this->activeRouteFilter = $routeId ? (int)$routeId : null;
        $this->selectedTripId = null;
        $this->selectedTrip = null;
    }

    public function selectTrip($tripId)
    {
        $this->selectedTripId = $tripId ? (int)$tripId : null;
        
        if (!$this->selectedTripId) {
            $this->selectedTrip = null;
            return;
        }

        $schedule = Schedule::with(['route', 'route.stops', 'bus'])->find($this->selectedTripId);
        
        if (!$schedule) {
            $this->selectedTrip = null;
            return;
        }

        // Compute estimated duration in minutes
        $duration = Carbon::parse($schedule->arrival_time)->diffInMinutes(Carbon::parse($schedule->departure_time));
        
        // Status mapping: 'On time' => 'on_time', 'Delayed' => 'delayed', 'Cancelled' => 'cancelled'
        $statusMap = [
            'On time' => 'on_time',
            'Delayed' => 'delayed',
            'Cancelled' => 'cancelled',
        ];
        $status = $statusMap[$schedule->status] ?? 'on_time';
        $delayMinutes = $status === 'delayed' ? ($schedule->delay_minutes ?: 0) : 0;

        // Group & sequence timeline stops (maximum 6 stops)
        $stops = [];
        if ($schedule->route && $schedule->route->stops) {
            $routeStops = $schedule->route->stops->take(6);
            $totalStopsCount = $routeStops->count();
            
            $routeTravelTime = $duration;
            $averageInterval = $totalStopsCount > 1 ? ($routeTravelTime / ($totalStopsCount - 1)) : 8;
            
            foreach ($routeStops as $index => $stop) {
                // Determine stop_status: departed, current, upcoming
                $stopStatus = 'upcoming';
                if ($status === 'cancelled') {
                    $stopStatus = 'upcoming';
                } else {
                    if ($index === 0) {
                        $stopStatus = 'departed';
                    } elseif ($index === 1 && $totalStopsCount > 1) {
                        $stopStatus = 'current';
                    } else {
                        $stopStatus = 'upcoming';
                    }
                }

                // Estimate arrival time at this stop based on departure + (index * averageInterval)
                $estimatedTime = Carbon::parse($schedule->departure_time)->addMinutes(round($index * $averageInterval))->format('g:i A');

                $stops[] = [
                    'stop_name' => $stop->name,
                    'estimated_time' => $estimatedTime,
                    'stop_status' => $stopStatus,
                ];
            }
        }

        $this->selectedTrip = [
            'trip_id' => $schedule->id,
            'route_name' => $schedule->route ? $schedule->route->name : 'Route',
            'route_color' => $schedule->route?->color ?: '#003F87',
            'departure_time' => Carbon::parse($schedule->departure_time)->format('g:i A'),
            'estimated_arrival_time' => Carbon::parse($schedule->arrival_time)->format('g:i A'),
            'estimated_duration_minutes' => $duration,
            'status' => $status,
            'delay_minutes' => $delayMinutes,
            'bus_plate' => $schedule->bus ? $schedule->bus->plate_number : null,
            'stops' => $stops,
        ];
    }

    public function setAlert($tripId)
    {
        $schedule = Schedule::with(['route', 'route.stops'])->find($tripId);
        if ($schedule && $schedule->route) {
            $firstStop = $schedule->route->stops->first();
            if ($firstStop) {
                // Create the alert in alerts table
                $minutesBefore = (int) SystemSetting::get('default_alert_warning_minutes', 5);
                Alert::create([
                    'stop_id' => $firstStop->id,
                    'minutes_before' => $minutesBefore,
                    'status' => 'active',
                ]);
            }
        }

        // Register the alert in our session state
        $this->setAlerts[] = $tripId;

        // Dispatch browser alert feedback
        $this->dispatch('alert-created', [
            'stop_name' => ($schedule && $schedule->route && $schedule->route->stops->first()) ? $schedule->route->stops->first()->name : 'Terminal',
            'minutes' => $minutesBefore,
        ]);
    }

    public function render()
    {
        // Query schedules
        $query = Schedule::with(['route', 'route.stops']);

        if ($this->activeRouteFilter) {
            $query->where('route_id', $this->activeRouteFilter);
        }

        $schedules = $query->get()->sortBy(function($schedule) {
            return $schedule->departure_time;
        });

        // Map schedules to collection format
        $scheduleList = $schedules->map(function ($schedule) {
            $duration = Carbon::parse($schedule->arrival_time)->diffInMinutes(Carbon::parse($schedule->departure_time));
            
            $statusMap = [
                'On time' => 'on_time',
                'Delayed' => 'delayed',
                'Cancelled' => 'cancelled',
            ];
            $status = $statusMap[$schedule->status] ?? 'on_time';
            $delayMinutes = $status === 'delayed' ? ($schedule->delay_minutes ?: 0) : 0;

            return [
                'trip_id' => $schedule->id,
                'route_id' => $schedule->route_id,
                'route_name' => $schedule->route ? ('Route ' . $schedule->route_id . ' — ' . $schedule->route->description) : 'Route',
                'route_color' => $schedule->route?->color ?: '#003F87',
                'departure_time' => Carbon::parse($schedule->departure_time)->format('g:i A'),
                'estimated_arrival_time' => Carbon::parse($schedule->arrival_time)->format('g:i A'),
                'stop_count' => $schedule->route ? $schedule->route->stops->count() : 0,
                'estimated_duration_minutes' => $duration,
                'status' => $status,
                'delay_minutes' => $delayMinutes,
            ];
        })->toArray();

        // Group by Morning, Afternoon, Evening
        $groupedSchedules = [
            'Morning  5:00 AM – 11:59 AM' => [],
            'Afternoon  12:00 PM – 5:59 PM' => [],
            'Evening  6:00 PM – 9:00 PM' => [],
        ];

        foreach ($scheduleList as $trip) {
            $time = Carbon::parse($trip['departure_time']);
            $hour = $time->hour;

            if ($hour >= 5 && $hour < 12) {
                $groupedSchedules['Morning  5:00 AM – 11:59 AM'][] = $trip;
            } elseif ($hour >= 12 && $hour < 18) {
                $groupedSchedules['Afternoon  12:00 PM – 5:59 PM'][] = $trip;
            } else {
                $groupedSchedules['Evening  6:00 PM – 9:00 PM'][] = $trip;
            }
        }

        // Remove empty bands
        $groupedSchedules = array_filter($groupedSchedules, function($list) {
            return count($list) > 0;
        });

        // Keep selected trip details synced upon polling
        if ($this->selectedTripId) {
            $this->selectTrip($this->selectedTripId);
        }

        return view('livewire.commuter.commuter-schedule', [
            'groupedSchedules' => $groupedSchedules,
        ]);
    }
}
