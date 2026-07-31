<?php

namespace Database\Factories;

use App\Models\Route;
use App\Models\RouteServiceSchedule;
use App\Models\RouteVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

class RouteServiceScheduleFactory extends Factory
{
    protected $model = RouteServiceSchedule::class;

    public function definition(): array
    {
        $route = Route::factory()->create();
        $variant = RouteVariant::create([
            'route_id' => $route->id,
            'direction' => 'outbound',
            'origin_name' => 'Origin',
            'destination_name' => 'Destination',
            'polyline_coordinates' => [[14.5593, 121.0805], [14.5603, 121.0815]],
            'geometry_version' => 1,
            'geometry_status' => 'valid',
            'is_default' => true,
        ]);

        return [
            'route_id' => $route->id,
            'route_variant_id' => $variant->id,
            'first_trip_time' => '05:30',
            'last_trip_time' => '17:00',
            'service_configuration' => RouteServiceSchedule::CONFIG_CONTINUOUS,
            'service_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
            'is_active' => true,
            'source' => RouteServiceSchedule::SOURCE_BENEFICIARY_OFFICIAL,
            'effective_from' => now('Asia/Manila')->toDateString(),
            'effective_until' => null,
        ];
    }
}
