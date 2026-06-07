<?php

namespace App\Services;

use App\Models\Route;
use App\Models\Schedule;
use Carbon\Carbon;

class SchedulePeekService
{
    /**
     * Get schedule peek metrics for routes.
     *
     * @return \Illuminate\Support\Collection
     */
    public function getSchedulePeek(): \Illuminate\Support\Collection
    {
        return Route::all()->map(function ($route) {
            $schedules = Schedule::where('route_id', $route->id)->orderBy('departure_time')->get();
            
            $firstTrip = $schedules->first() ? Carbon::parse($schedules->first()->departure_time)->format('g:i A') : 'No schedules';
            $lastTrip = $schedules->last() ? Carbon::parse($schedules->last()->departure_time)->format('g:i A') : 'No schedules';

            $serviceStatus = 'In service';
            $minsUntilStart = 0;

            if ($route->status === 'Suspended') {
                $serviceStatus = 'Suspended';
            } elseif ($schedules->isNotEmpty()) {
                $firstTripTime = Carbon::parse($schedules->first()->departure_time);
                $lastTripTime = Carbon::parse($schedules->last()->departure_time);
                $currentTime = Carbon::now();

                if ($currentTime->lessThan($firstTripTime)) {
                    $minsUntilStart = max(1, $currentTime->diffInMinutes($firstTripTime));
                    $serviceStatus = "Starts in {$minsUntilStart} min";
                } elseif ($currentTime->greaterThan($lastTripTime)) {
                    $serviceStatus = 'Service ended';
                }
            } else {
                $serviceStatus = 'No service';
            }

            return (object) [
                'route_name' => $route->name,
                'route_color' => $route->color ?: '#003F87',
                'first_trip' => $firstTrip,
                'last_trip' => $lastTrip,
                'service_status' => $serviceStatus,
                'mins_until_start' => $minsUntilStart,
            ];
        });
    }
}
