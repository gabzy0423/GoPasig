<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get existing settings to avoid duplicates
        $existing = \App\Models\SystemSetting::pluck('key')->toArray();

        $settings = [
            // Analytics Settings
            [
                'key' => 'analytics_default_date_range',
                'value' => 'today',
                'description' => 'Default date range for analytics (today, yesterday, week, month)',
            ],
            [
                'key' => 'analytics_default_start_time',
                'value' => '06:00',
                'description' => 'Default start time for analytics queries',
            ],
            [
                'key' => 'analytics_default_end_time',
                'value' => '23:00',
                'description' => 'Default end time for analytics queries',
            ],
            
            // Bus Settings
            [
                'key' => 'bus_capacity_default',
                'value' => '45',
                'description' => 'Default bus capacity when creating new bus',
            ],
            [
                'key' => 'bus_capacity_min',
                'value' => '10',
                'description' => 'Minimum bus capacity allowed',
            ],
            [
                'key' => 'bus_capacity_max',
                'value' => '150',
                'description' => 'Maximum bus capacity allowed',
            ],
            [
                'key' => 'bus_default_driver_name',
                'value' => 'Unassigned',
                'description' => 'Default driver_name value when a bus has no assigned driver',
            ],
            [
                'key' => 'bus_default_next_stop',
                'value' => 'None',
                'description' => 'Default next_stop value when a bus has no upcoming stop',
            ],
            [
                'key' => 'bus_initial_speed',
                'value' => '0',
                'description' => 'Initial speed value for newly registered buses',
            ],
            [
                'key' => 'bus_initial_passengers',
                'value' => '0',
                'description' => 'Initial passenger count for newly registered buses',
            ],
            [
                'key' => 'bus_initial_eta',
                'value' => '0',
                'description' => 'Initial ETA value for newly registered buses',
            ],
            
            // Schedule Settings
            [
                'key' => 'schedule_default_travel_time_minutes',
                'value' => '30',
                'description' => 'Default travel time in minutes for route',
            ],
            [
                'key' => 'driver_schedule_buffer_minutes',
                'value' => '15',
                'description' => 'Buffer time in minutes between driver schedules',
            ],
            [
                'key' => 'bus_schedule_buffer_minutes',
                'value' => '15',
                'description' => 'Buffer time in minutes between bus schedules',
            ],
            
            // Time Slot Settings
            [
                'key' => 'default_time_slot',
                'value' => '06:00-08:00',
                'description' => 'Default time slot when no configuration exists',
            ],
            [
                'key' => 'time_slot_start_hour',
                'value' => '06',
                'description' => 'Start hour for time slot calculations',
            ],
            [
                'key' => 'time_slot_end_hour',
                'value' => '23',
                'description' => 'End hour for time slot calculations',
            ],
            
            // Map/Location Settings
            [
                'key' => 'map_default_latitude',
                'value' => '14.5690',
                'description' => 'Default map center latitude (Pasig, Philippines)',
            ],
            [
                'key' => 'map_default_longitude',
                'value' => '121.0680',
                'description' => 'Default map center longitude (Pasig, Philippines)',
            ],
            [
                'key' => 'map_default_zoom',
                'value' => '13',
                'description' => 'Default map zoom level',
            ],
            [
                'key' => 'map_gps_polling_interval_ms',
                'value' => '10000',
                'description' => 'GPS polling interval in milliseconds for real-time tracking',
            ],
            [
                'key' => 'overview_default_route_name',
                'value' => 'No route configured',
                'description' => 'Dashboard map chip label when no route exists',
            ],
            [
                'key' => 'default_route_avg_pax',
                'value' => '0',
                'description' => 'Default average route passenger count when a route has no completed trips',
            ],
            
            // Geofencing Settings
            [
                'key' => 'geofence_default_radius_meters',
                'value' => '100',
                'description' => 'Default geofence radius for stops in meters',
            ],
            [
                'key' => 'coordinates_bounds_north_latitude',
                'value' => '14.85',
                'description' => 'Northern boundary for valid coordinates (Philippines)',
            ],
            [
                'key' => 'coordinates_bounds_south_latitude',
                'value' => '14.30',
                'description' => 'Southern boundary for valid coordinates (Philippines)',
            ],
            [
                'key' => 'coordinates_bounds_east_longitude',
                'value' => '121.20',
                'description' => 'Eastern boundary for valid coordinates (Philippines)',
            ],
            [
                'key' => 'coordinates_bounds_west_longitude',
                'value' => '120.95',
                'description' => 'Western boundary for valid coordinates (Philippines)',
            ],
            
            // Driver Settings
            [
                'key' => 'license_expiry_warning_threshold_days',
                'value' => '30',
                'description' => 'Days before license expiry to show warning',
            ],
            [
                'key' => 'license_expiry_warn_critical_days',
                'value' => '7',
                'description' => 'Days before license expiry to classify as critical',
            ],
            [
                'key' => 'driver_initial_performance_score',
                'value' => '80',
                'description' => 'Initial performance score assigned to newly registered drivers',
            ],
            [
                'key' => 'incident_score_penalty_per_event',
                'value' => '10',
                'description' => 'Performance score points deducted per driver incident',
            ],
            [
                'key' => 'driver_performance_rolling_days',
                'value' => '30',
                'description' => 'Rolling window in days for driver performance calculations',
            ],
            [
                'key' => 'driver_passenger_rating_default',
                'value' => '80',
                'description' => 'Default passenger rating score until passenger feedback data exists',
            ],
        ];

        foreach ($settings as $setting) {
            if (!in_array($setting['key'], $existing)) {
                \App\Models\SystemSetting::create($setting);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Don't delete settings on rollback to preserve production data
    }
};
