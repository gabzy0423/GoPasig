<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Models\Bus;
use App\Models\Schedule;
use App\Models\ServiceAlert;

class QuickStats extends Component
{
    public function render()
    {
        $activeBusesCollection = Bus::with('route')->where('status', 'active')->get();
        $activeBuses = $activeBusesCollection->count();
        $delayedBuses = $activeBusesCollection->filter(function ($bus) {
            return $bus->eta >= $bus->getRouteDelayThreshold();
        })->count();
        $passengersToday = Schedule::sum('passengers');
        $openAlerts = ServiceAlert::where('status', 'active')->count();

        return view('livewire.commuter.quick-stats', [
            'activeBuses' => $activeBuses,
            'delayedBuses' => $delayedBuses,
            'passengersToday' => $passengersToday,
            'openAlerts' => $openAlerts,
        ]);
    }
}
