<?php

namespace Tests\Feature;

use Tests\TestCase;
use Livewire\Livewire;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CommuterTrackerTest extends TestCase
{
    use RefreshDatabase;

    public function test_commuter_tracker_shows_correct_driver_name_or_fallback(): void
    {
        // 1. Create a Route
        $route = Route::create([
            'id' => 1,
            'name' => 'Route A',
            'status' => 'Active'
        ]);

        // 2. Create two active buses
        $busWithDriver = Bus::create([
            'plate_number' => 'PAS-101',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 10,
            'passengers' => 12,
            'route_id' => $route->id,
        ]);

        $busWithoutDriver = Bus::create([
            'plate_number' => 'PAS-102',
            'status' => 'active',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 10,
            'passengers' => 15,
            'route_id' => $route->id,
        ]);

        // 3. Create a driver assigned to the first bus
        Driver::create([
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'emp_id' => 'EMP-001',
            'license_number' => 'LIC-001',
            'license_expiry' => now()->addYear(),
            'assigned_bus' => 'PAS-101',
            'status' => 'active'
        ]);

        // 4. Test the Livewire component view data
        Livewire::test(\App\Livewire\Commuter\Tracker::class)
            ->assertViewHas('activeBuses', function ($buses) {
                $busesArray = collect($buses)->keyBy('plate_number');

                // Bus 1 should have the correct driver name (Juan Dela Cruz)
                if ($busesArray->get('PAS-101')->driver_name !== 'Juan Dela Cruz') {
                    return false;
                }

                // Bus 2 should have the fallback "No Driver Assigned"
                if ($busesArray->get('PAS-102')->driver_name !== 'No Driver Assigned') {
                    return false;
                }

                return true;
            });
    }
}
