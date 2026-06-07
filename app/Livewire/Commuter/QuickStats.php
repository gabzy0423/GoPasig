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
        $activeBuses = Bus::where('status', 'active')->count();
        $delayedBuses = Bus::where('status', 'active')->where('eta', '>=', Bus::getDelayThreshold())->count();
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
