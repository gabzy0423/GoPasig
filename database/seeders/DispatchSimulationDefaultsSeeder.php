<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DispatchSimulationDefault;
use App\Models\Route;

class DispatchSimulationDefaultsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            [
                'key' => 'default_demand_threshold',
                'value' => '20',
                'description' => 'Default demand count threshold to trigger a dispatch alert (default: 20)'
            ],
            [
                'key' => 'phase_default',
                'value' => '1',
                'description' => 'Default simulated algorithm phase (1 = Reactive, 2 = Predictive, 3 = Self-Improving)'
            ],
            [
                'key' => 'default_simulated_day',
                'value' => 'Monday',
                'description' => 'Default simulated operational weekday'
            ],
            [
                'key' => 'route_default',
                'value' => (string) (Route::first()?->id ?? 1),
                'description' => 'Default initial simulated route selection ID'
            ],
            [
                'key' => 'default_terminal',
                'value' => 'SPED Terminal',
                'description' => 'Default starting terminal station name for dispatched vehicles'
            ],
            [
                'key' => 'default_time_slot',
                'value' => '06:00-08:00',
                'description' => 'Default simulated time slot block'
            ],
            [
                'key' => 'sim_rush_spurt_min',
                'value' => '2',
                'description' => 'Minimum number of commuters added in simulated rush surge spurt (default: 2)'
            ],
            [
                'key' => 'sim_rush_spurt_max',
                'value' => '5',
                'description' => 'Maximum number of commuters added in simulated rush surge spurt (default: 5)'
            ],
        ];

        foreach ($defaults as $default) {
            DispatchSimulationDefault::updateOrCreate(
                ['key' => $default['key']],
                [
                    'value' => $default['value'],
                    'description' => $default['description']
                ]
            );
        }
    }
}
