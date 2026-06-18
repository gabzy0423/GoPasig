<?php

namespace App\Livewire\Commuter;

use Livewire\Component;
use App\Services\CommuterDashboardCacheService;

class NearestBuses extends Component
{
    public function render()
    {
        $buses = app(CommuterDashboardCacheService::class)->dashboardData()['nearestBuses'];

        return view('livewire.commuter.nearest-buses', [
            'nearestBuses' => $buses,
        ]);
    }
}
