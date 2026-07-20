<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SystemSetting;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            ['key' => 'default_bus_capacity', 'value' => '45', 'description' => 'Default passenger seating capacity for buses when not specified (default: 45)'],
            ['key' => 'bus_capacity_min', 'value' => '10', 'description' => 'Minimum allowed passenger seating capacity for buses (default: 10)'],
            ['key' => 'bus_capacity_max', 'value' => '150', 'description' => 'Maximum allowed passenger seating capacity for buses (default: 150)'],
            ['key' => 'bus_default_driver_name', 'value' => 'Unassigned', 'description' => 'Default driver_name value when a bus has no assigned driver'],
            ['key' => 'bus_default_next_stop', 'value' => 'None', 'description' => 'Default next_stop value when a bus has no upcoming stop'],
            ['key' => 'bus_initial_speed', 'value' => '0', 'description' => 'Initial speed value for newly registered buses'],
            ['key' => 'bus_initial_passengers', 'value' => '0', 'description' => 'Initial passenger count for newly registered buses'],
            ['key' => 'bus_initial_eta', 'value' => '0', 'description' => 'Initial ETA value for newly registered buses'],
            ['key' => 'captcha_attempt_threshold', 'value' => '3', 'description' => 'Number of failed login attempts before displaying captcha (default: 3)'],
            ['key' => 'delay_threshold', 'value' => '10', 'description' => 'Threshold in minutes to classify a bus trip as delayed (default: 10)'],
            ['key' => 'occupancy_warning_threshold', 'value' => '50', 'description' => 'Bus passenger occupancy percentage threshold to trigger a warning status (default: 50)'],
            ['key' => 'occupancy_critical_threshold', 'value' => '85', 'description' => 'Bus passenger occupancy percentage threshold to trigger a critical status (default: 85)'],
            ['key' => 'gps_sync_interval_ms', 'value' => '5000', 'description' => 'Interval in milliseconds for GPS telemetry synchronization (default: 5000)'],
            ['key' => 'speed_simulation_interval_ms', 'value' => '1500', 'description' => 'Telemetry simulation speed tick interval in milliseconds (default: 1500)'],
            ['key' => 'sim_speed_min', 'value' => '18', 'description' => 'Minimum bus simulation speed in km/h (default: 18)'],
            ['key' => 'sim_speed_max', 'value' => '43', 'description' => 'Maximum bus simulation speed in km/h (default: 43)'],
            ['key' => 'speed_fast_threshold', 'value' => '30', 'description' => 'Threshold in km/h above which a bus is considered traveling fast (default: 30)'],
            ['key' => 'default_route_color', 'value' => '#003F87', 'description' => 'Default hex color used for route displays (default: #003F87)'],
            ['key' => 'default_terminal_name', 'value' => 'SPED Terminal', 'description' => 'Default fallback terminal name (default: SPED Terminal)'],
            ['key' => 'default_route_start_lat', 'value' => '14.5593', 'description' => 'Fallback map center latitude coordinates (default: 14.5593)'],
            ['key' => 'default_route_start_lng', 'value' => '121.0805', 'description' => 'Fallback map center longitude coordinates (default: 121.0805)'],
            ['key' => 'stop_auto_advance_distance', 'value' => '100', 'description' => 'Radius in meters around a bus stop to auto-advance the next stop (default: 100)'],
            ['key' => 'label_bus_status_full', 'value' => 'Full', 'description' => 'UI status label for full bus capacity (default: Full)'],
            ['key' => 'label_bus_status_delayed', 'value' => 'Delayed', 'description' => 'UI status label for delayed bus trips (default: Delayed)'],
            ['key' => 'label_bus_status_on_time', 'value' => 'On Time', 'description' => 'UI status label for on-time bus trips (default: On Time)'],
            ['key' => 'label_bus_status_ontime', 'value' => 'On-time', 'description' => 'UI status label variant for on-time (default: On-time)'],
            ['key' => 'label_bus_status_idle', 'value' => 'Idle', 'description' => 'UI status label for idle buses (default: Idle)'],
            ['key' => 'label_bus_status_breakdown', 'value' => 'Breakdown', 'description' => 'UI status label for broken down buses (default: Breakdown)'],
            ['key' => 'db_status_ontime_label', 'value' => 'On time', 'description' => 'Database status label for on-time schedules (default: On time)'],
            ['key' => 'db_status_delayed_label', 'value' => 'Delayed', 'description' => 'Database status label for delayed schedules (default: Delayed)'],
            ['key' => 'db_status_cancelled_label', 'value' => 'Cancelled', 'description' => 'Database status label for cancelled schedules (default: Cancelled)'],
            ['key' => 'service_start_time',   'value' => '05:00', 'description' => 'Start of daily bus operations (HH:MM, 24-hour)'],
            ['key' => 'service_end_time',     'value' => '21:00', 'description' => 'End of daily bus operations (HH:MM, 24-hour)'],
            ['key' => 'stop_grouping_radius', 'value' => '50',   'description' => 'Max distance (meters) to consider two stop records the same physical location'],
            [
                'key'         => 'incident_severity_map',
                'value'       => '{"Low":"Route Issue","Medium":"Delay","High":"Breakdown"}',
                'description' => 'JSON map of incident severity level to incident type label',
            ],
            [
                'key'         => 'incident_breakdown_type',
                'value'       => 'Breakdown',
                'description' => 'Incident type label that triggers a bus status change to maintenance',
            ],
            [
                'key'         => 'driver_score_incident_penalty',
                'value'       => '10',
                'description' => 'Points deducted from a driver performance score per logged incident (default: 10)',
            ],
            [
                'key'         => 'incident_score_penalty_per_event',
                'value'       => '10',
                'description' => 'Performance score points deducted per driver incident (default: 10)',
            ],
            [
                'key'         => 'driver_score_delay_penalty',
                'value'       => '5',
                'description' => 'Points deducted from a driver performance score per delayed schedule (default: 5)',
            ],
            [
                'key'         => 'driver_performance_rolling_days',
                'value'       => '30',
                'description' => 'Rolling window in days for driver performance calculations (default: 30)',
            ],
            [
                'key'         => 'driver_passenger_rating_default',
                'value'       => '80',
                'description' => 'Default passenger rating score until passenger feedback data exists (default: 80)',
            ],
            [
                'key'         => 'license_expiry_warning_threshold_days',
                'value'       => '30',
                'description' => 'Number of days in advance to show license expiry warning (default: 30)',
            ],
            [
                'key'         => 'license_expiry_warn_critical_days',
                'value'       => '7',
                'description' => 'Number of days in advance to classify license expiry as critical (default: 7)',
            ],
            [
                'key'         => 'driver_initial_performance_score',
                'value'       => '80',
                'description' => 'Initial performance score assigned to newly registered drivers (default: 80)',
            ],
            [
                'key'         => 'maintenance_due_warning_days',
                'value'       => '7',
                'description' => 'Number of days in advance to highlight a maintenance record as due (default: 7)',
            ],
            [
                'key'         => 'maintenance_schedule_window_days',
                'value'       => '30',
                'description' => 'Days forward lookahead for upcoming maintenance schedule view (default: 30)',
            ],
            [
                'key'         => 'default_on_time_target',
                'value'       => '85',
                'description' => 'Default route performance SLA on-time rate percentage target (default: 85)',
            ],
            [
                'key'         => 'default_headway_target',
                'value'       => '15',
                'description' => 'Default route performance headway frequency target in minutes (default: 15)',
            ],
            [
                'key'         => 'default_dispatch_eta_minutes',
                'value'       => '5',
                'description' => 'Default initial ETA in minutes for a bus when it is dispatched (default: 5)',
            ],
            [
                'key'         => 'default_travel_time_minutes',
                'value'       => '30',
                'description' => 'Default route travel time in minutes when not specified (default: 30)',
            ],
            [
                'key'         => 'driver_schedule_buffer_minutes',
                'value'       => '15',
                'description' => 'Driver fatigue protection buffer in minutes between schedules to prevent back-to-back conflicts (default: 15)',
            ],
            [
                'key'         => 'bus_schedule_buffer_minutes',
                'value'       => '15',
                'description' => 'Bus turnaround buffer in minutes between schedules to prevent immediate reuse (default: 15)',
            ],
            [
                'key'         => 'sim_rush_spurt_min',
                'value'       => '2',
                'description' => 'Minimum number of commuters added in simulated rush surge spurt (default: 2)',
            ],
            [
                'key'         => 'sim_rush_spurt_max',
                'value'       => '5',
                'description' => 'Maximum number of commuters added in simulated rush surge spurt (default: 5)',
            ],
            [
                'key'         => 'threshold_min_value',
                'value'       => '5',
                'description' => 'Minimum allowed value for route passenger demand thresholds (default: 5)',
            ],
            [
                'key'         => 'threshold_max_value',
                'value'       => '100',
                'description' => 'Maximum allowed value for route passenger demand thresholds (default: 100)',
            ],
            [
                'key'         => 'alert_template_predictive_title_fil',
                'value'       => '⚡ Pre-dispatch Recommended',
                'description' => 'Predictive dispatch alert title template in Filipino',
            ],
            [
                'key'         => 'alert_template_predictive_message_fil',
                'value'       => 'Tuwing {day} {slot_start} = laging maraming commuters sa {route_name} (Avg: {historical_avg} pax expected). Dispatch a bus now to pre-empt overflow.',
                'description' => 'Predictive dispatch alert message template in Filipino',
            ],
            [
                'key'         => 'alert_template_predictive_title_en',
                'value'       => '⚡ Pre-dispatch Recommended',
                'description' => 'Predictive dispatch alert title template in English',
            ],
            [
                'key'         => 'alert_template_predictive_message_en',
                'value'       => 'Every {day} at {slot_start} = passenger demand is consistently high on {route_name} (Avg: {historical_avg} pax expected). Dispatch a bus now to pre-empt overflow.',
                'description' => 'Predictive dispatch alert message template in English',
            ],
            [
                'key'         => 'alert_template_reactive_title_fil',
                'value'       => 'Alarm ng Threshold Overflow',
                'description' => 'Reactive threshold overflow alert title in Filipino',
            ],
            [
                'key'         => 'alert_template_reactive_message_fil',
                'value'       => '{total} commuters ang naghihintay sa {route_name}. Lumampas sa limitasyon na {threshold}! Inirerekomenda ang pag-dispatch.',
                'description' => 'Reactive threshold overflow alert message in Filipino',
            ],
            [
                'key'         => 'alert_template_reactive_title_en',
                'value'       => 'Threshold Overflow Alarm',
                'description' => 'Reactive threshold overflow alert title in English',
            ],
            [
                'key'         => 'alert_template_reactive_message_en',
                'value'       => '{total} commuters waiting on {route_name}. Limit of {threshold} exceeded! Dispatch recommended.',
                'description' => 'Reactive threshold overflow alert message in English',
            ],
            [
                'key'         => 'alert_template_reactive_live_title_fil',
                'value'       => 'Alarm ng Threshold Overflow (Live)',
                'description' => 'Reactive live counter threshold alert title in Filipino',
            ],
            [
                'key'         => 'alert_template_reactive_live_message_fil',
                'value'       => '{total} commuters ang naghihintay sa {route_name} (Live counter). Ang threshold ay {threshold}! Mag-dispatch kaagad.',
                'description' => 'Reactive live counter threshold alert message in Filipino',
            ],
            [
                'key'         => 'alert_template_reactive_live_title_en',
                'value'       => 'Threshold Overflow Alarm (Live)',
                'description' => 'Reactive live counter threshold alert title in English',
            ],
            [
                'key'         => 'alert_template_reactive_live_message_en',
                'value'       => '{total} commuters waiting on {route_name} (Live counter). Threshold is {threshold}! Dispatch immediately.',
                'description' => 'Reactive live counter threshold alert message in English',
            ],
            [
                'key'         => 'default_maintenance_duration_minutes',
                'value'       => '120',
                'description' => 'Default expected duration in minutes for maintenance records when not specified (default: 120)',
            ],
            [
                'key'         => 'default_map_center_lat',
                'value'       => '14.5690',
                'description' => 'Default map center latitude coordinate for dashboard overview (default: 14.5690)',
            ],
            [
                'key'         => 'default_map_center_lng',
                'value'       => '121.0680',
                'description' => 'Default map center longitude coordinate for dashboard overview (default: 121.0680)',
            ],
            [
                'key'         => 'default_map_zoom',
                'value'       => '13.5',
                'description' => 'Default map zoom level for dashboard overview (default: 13.5)',
            ],
            [
                'key'         => 'map_telemetry_polling_interval_ms',
                'value'       => '10000',
                'description' => 'Interval in milliseconds for map telemetry updates polling (default: 10000)',
            ],
            [
                'key'         => 'overview_default_route_name',
                'value'       => 'No route configured',
                'description' => 'Dashboard map chip label when no route exists',
            ],
            [
                'key'         => 'default_route_avg_pax',
                'value'       => '0',
                'description' => 'Default average route passenger count when a route has no completed trips',
            ],
            [
                'key'         => 'default_route_on_time_target',
                'value'       => '85',
                'description' => 'Default target on-time rate percentage for new routes (default: 85)',
            ],
            [
                'key'         => 'default_route_headway_minutes',
                'value'       => '15',
                'description' => 'Default target headway in minutes for new routes (default: 15)',
            ],
            [
                'key'         => 'stop_default_radius_meters',
                'value'       => '50',
                'description' => 'Default geofence boarding/alighting detection radius in meters for new stops (default: 50)',
            ],
            [
                'key'         => 'route_min_buses_required',
                'value'       => '2',
                'description' => 'Default minimum number of active buses required per route (default: 2)',
            ],
            [
                'key'         => 'bus_max_speed_kmh',
                'value'       => '80',
                'description' => 'Maximum allowed speed for buses in km/h to prevent GPS teleportation spoofing',
            ],
        ];

        foreach ($settings as $setting) {
            $data = ['value' => $setting['value']];
            if (isset($setting['description'])) {
                $data['description'] = $setting['description'];
            }
            SystemSetting::updateOrCreate(
                ['key' => $setting['key']],
                $data
            );
        }
    }
}
