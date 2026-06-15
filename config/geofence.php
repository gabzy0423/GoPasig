<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Geofence Audio Chime Fallback Configurations
    |--------------------------------------------------------------------------
    |
    | These values represent the default frequencies and delay parameters for
    | the geofence entry notification sound, used as a fallback if not set
    | in SystemSetting.
    |
    */

    'chime_freq_1' => env('GEOFENCE_CHIME_FREQ_1', 1318.51),
    'chime_freq_2' => env('GEOFENCE_CHIME_FREQ_2', 1760.00),
    'chime_delay'  => env('GEOFENCE_CHIME_DELAY', 0.12),

];
