<?php

namespace Tests\Feature;

use App\Livewire\Commuter\GeofenceDetector;
use App\Models\Route;
use App\Models\Stop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class DeveloperLocationSimulatorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    public function test_developer_location_panel_is_visible_only_in_local_environment_with_database_presets(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $this->seedPresetStops();

        Livewire::test(GeofenceDetector::class)
            ->assertSee('Developer GPS')
            ->assertSee('Quick Presets')
            ->assertSee('Pasig Rotonda')
            ->assertSee('Ligaya')
            ->assertSee('Rosario')
            ->assertSee('San Joaquin')
            ->assertViewHas('developerLocationPresets', function (array $presets) {
                $preset = collect($presets)->firstWhere('label', 'Pasig Rotonda');

                return $preset
                    && $preset['available'] === true
                    && $preset['lat'] === 14.5585
                    && $preset['lng'] === 121.0842;
            });
    }

    public function test_developer_location_panel_and_presets_are_disabled_in_production(): void
    {
        $this->app->detectEnvironment(fn () => 'production');
        $this->seedPresetStops();

        Livewire::test(GeofenceDetector::class)
            ->assertDontSee('Quick Presets')
            ->assertDontSee('Use Browser GPS')
            ->assertSee('developerSimulatorEnabled: false')
            ->assertSee('developerPresets: []')
            ->assertViewHas('developerLocationPresets', []);
    }

    public function test_developer_coordinates_flow_through_existing_stop_geofence_pipeline(): void
    {
        $this->app->detectEnvironment(fn () => 'local');
        $stops = $this->seedPresetStops();

        Livewire::test(GeofenceDetector::class)
            ->call('updateLocation', $stops['pasig']->lat, $stops['pasig']->lng, 5)
            ->assertSet('activeStop.id', $stops['pasig']->id)
            ->call('updateLocation', 14.1, 121.5, 5)
            ->assertSet('activeStop', null);
    }

    private function seedPresetStops(): array
    {
        $route = Route::create([
            'name' => 'Route 1',
            'description' => 'Canonical route',
            'status' => 'Active',
            'color' => '#003F87',
        ]);

        return [
            'pasig' => Stop::create([
                'route_id' => $route->id,
                'name' => 'Pasig Rotonda Terminal',
                'lat' => 14.5585,
                'lng' => 121.0842,
                'sequence' => 1,
                'radius_meters' => 100,
            ]),
            'ligaya' => Stop::create([
                'route_id' => $route->id,
                'name' => 'Ligaya Stop',
                'lat' => 14.6201,
                'lng' => 121.0992,
                'sequence' => 2,
                'radius_meters' => 100,
            ]),
            'rosario' => Stop::create([
                'route_id' => $route->id,
                'name' => 'Rosario Stop',
                'lat' => 14.5873,
                'lng' => 121.0848,
                'sequence' => 3,
                'radius_meters' => 100,
            ]),
            'san_joaquin' => Stop::create([
                'route_id' => $route->id,
                'name' => 'San Joaquin Stop',
                'lat' => 14.5516,
                'lng' => 121.0799,
                'sequence' => 4,
                'radius_meters' => 100,
            ]),
        ];
    }
}