<?php

return [
    'gps' => [
        'retention_days' => env('FLEET_GPS_RETENTION_DAYS', 30),
        'max_accuracy_meters' => env('FLEET_GPS_MAX_ACCURACY', 50.0),
        'offline_timeout_seconds' => env('FLEET_VEHICLE_OFFLINE_TIMEOUT', 300),
    ],
    'gps_quality' => [
        'good_accuracy_meters' => env('FLEET_GPS_QUALITY_GOOD_ACCURACY_METERS', 20.0),
        'degraded_accuracy_meters' => env('FLEET_GPS_QUALITY_DEGRADED_ACCURACY_METERS', env('FLEET_GPS_MAX_ACCURACY', 50.0)),
        'degraded_fix_age_seconds' => env('FLEET_GPS_QUALITY_DEGRADED_FIX_AGE_SECONDS', 30),
        'stale_fix_age_seconds' => env('FLEET_GPS_QUALITY_STALE_FIX_AGE_SECONDS', 300),
    ],
    'heading' => [
        'derive_min_displacement_meters' => env('FLEET_HEADING_DERIVE_MIN_DISPLACEMENT_METERS', 5.0),
        'derive_accuracy_noise_multiplier' => env('FLEET_HEADING_DERIVE_ACCURACY_NOISE_MULTIPLIER', 0.35),
        'max_reliable_accuracy_meters' => env('FLEET_HEADING_MAX_RELIABLE_ACCURACY_METERS', env('FLEET_GPS_MAX_ACCURACY', 50.0)),
    ],    'movement' => [
        'moving_speed_threshold_mps' => env('FLEET_MOVEMENT_SPEED_THRESHOLD_MPS', 0.5),
        'sustained_speed_threshold_mps' => env('FLEET_MOVEMENT_SUSTAINED_SPEED_THRESHOLD_MPS', 1.0),
        'speed_evidence_min_displacement_meters' => env('FLEET_MOVEMENT_SPEED_EVIDENCE_MIN_DISPLACEMENT_METERS', 2.0),
        'stationary_speed_threshold_mps' => env('FLEET_STATIONARY_SPEED_THRESHOLD_MPS', 0.3),
        'min_displacement_meters' => env('FLEET_MOVEMENT_MIN_DISPLACEMENT_METERS', 8.0),
        'accuracy_noise_multiplier' => env('FLEET_MOVEMENT_ACCURACY_NOISE_MULTIPLIER', 0.5),
        'max_reliable_accuracy_meters' => env('FLEET_MOVEMENT_MAX_RELIABLE_ACCURACY_METERS', 50.0),
        'moving_confirm_samples' => env('FLEET_MOVEMENT_CONFIRM_SAMPLES', 2),
        'stationary_confirm_samples' => env('FLEET_STATIONARY_CONFIRM_SAMPLES', 3),
        'idle_threshold_seconds' => env('FLEET_MOVEMENT_IDLE_THRESHOLD_SECONDS', 180),
    ],
    'stops' => [
        'entry_radius_meters' => env('FLEET_STOP_ENTRY_RADIUS', 30.0),
        'exit_radius_meters' => env('FLEET_STOP_EXIT_RADIUS', 45.0), // Hysteresis threshold
    ],
    'deviation' => [
        'minor_meters' => env('FLEET_DEVIATION_MINOR', 50.0),
        'major_meters' => env('FLEET_DEVIATION_MAJOR', 150.0),
        'critical_meters' => env('FLEET_DEVIATION_CRITICAL', 300.0),
    ],
    'spatial' => [
        'cache_ttl' => 86400,
        'corridor_default' => 20.0,
        'polygon_precision' => 6,
        'stop_radius' => 30.0,
        'stop_exit_radius' => 45.0,
        'terminal_radius' => 50.0,
        'depot_radius' => 60.0,
        'indexing_margin' => 0.015,
        'hysteresis_time_threshold_seconds' => 15,
    ],
];






