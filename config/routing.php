<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Routing Provider Engine
    |--------------------------------------------------------------------------
    | Supported: "manual", "google", "osrm"
    */
    'default' => env('ROUTING_PROVIDER_DEFAULT', 'manual'),

    /*
    |--------------------------------------------------------------------------
    | Route Generation Maintenance Mode
    |--------------------------------------------------------------------------
    | Hidden by default. Enable only when admins intentionally need backend
    | route/variant geometry generation and provider telemetry tooling.
    */
    'route_generation_maintenance_enabled' => env('ROUTE_GENERATION_MAINTENANCE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Geometry Cache TTL
    |--------------------------------------------------------------------------
    | Duration in seconds for polyline and metrics cache entries.
    | Default: 86400 (24 hours).
    | Set to 0 to disable caching (not recommended in production).
    */
    'geometry_cache_ttl'         => env('GEOMETRY_CACHE_TTL', 86400),
    'geometry_metrics_cache_ttl' => env('GEOMETRY_METRICS_CACHE_TTL', 86400),

    /*
    |--------------------------------------------------------------------------
    | Douglas-Peucker Simplification Tolerance
    |--------------------------------------------------------------------------
    | In degrees (~0.00005° ≈ 5m). Overridable via SystemSetting.
    */
    'simplification_tolerance' => env('GEOMETRY_SIMPLIFICATION_TOLERANCE', 0.00005),

    /*
    |--------------------------------------------------------------------------
    | Routing Providers Configuration & Priorities
    |--------------------------------------------------------------------------
    */
    'providers' => [
        'google' => [
            'priority' => 1,
            'key' => env('GOOGLE_MAPS_API_KEY'),
            'timeout' => 5, // seconds
            'retries' => 3,
            'daily_limit' => 1000,
            'price_per_request' => 0.005, // USD
        ],
        'osrm' => [
            'priority' => 2,
            'base_url' => env('OSRM_BASE_URL', 'https://router.project-osrm.org'),
            'timeout' => 3,
            'retries' => 2,
            'daily_limit' => 5000,
            'price_per_request' => 0.0, // Free / self-hosted
        ],
        'manual' => [
            'priority' => 3,
            'daily_limit' => -1,
            'price_per_request' => 0.0,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Circuit Breaker State Configuration
    |--------------------------------------------------------------------------
    */
    'circuit_breaker' => [
        'failure_rate_threshold' => env('ROUTING_CIRCUIT_FAILURE_RATE', 50.0), // % of failed requests in window
        'cooldown_seconds' => env('ROUTING_CIRCUIT_COOLDOWN', 300),          // 5 minutes
        'sliding_window_size' => env('ROUTING_CIRCUIT_WINDOW_SIZE', 10),      // last 10 requests evaluated
        'min_requests_to_trip' => env('ROUTING_CIRCUIT_MIN_REQUESTS', 5),     // minimum requests before tripping
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Quality Validation Thresholds
    |--------------------------------------------------------------------------
    */
    'quality_thresholds' => [
        'max_spacing_deviation_meters' => env('ROUTING_QUALITY_MAX_SPACING', 200.0),
        'min_overlap_percentage' => env('ROUTING_QUALITY_MIN_OVERLAP', 70.0),
    ],

];
