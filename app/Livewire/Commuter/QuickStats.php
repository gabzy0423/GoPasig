<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Services\CommuterDashboardCacheService;

class QuickStats extends Component
{
    public function render()
    {
        $stats = app(CommuterDashboardCacheService::class)->dashboardData()['quickStats'];

        return view('livewire.commuter.quick-stats', [
            'activeBuses' => $stats['active_buses'],
            'delayedBuses' => $stats['delayed_buses'],
            'passengersToday' => $stats['passengers_today'],
            'openAlerts' => $stats['open_alerts'],
        ]);
    }
}
