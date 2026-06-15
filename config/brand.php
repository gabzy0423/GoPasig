<?php

return [

    /*
    |--------------------------------------------------------------------------
    | GoPasig Brand Color Defaults
    |--------------------------------------------------------------------------
    |
    | These values are used as fallback colors throughout the application when
    | a route, bus, or entity does not have an explicitly assigned color.
    |
    | route_color_default   – primary brand blue; used when a route row has no
    |                         color set in the database.
    | route_color_unassigned – neutral grey; used for buses or routes that have
    |                          not yet been assigned to any route.
    |
    | To change the brand color system-wide, edit only these two values.
    |
    */

    'route_color_default'    => env('BRAND_ROUTE_COLOR_DEFAULT',    '#003F87'),
    'route_color_unassigned' => env('BRAND_ROUTE_COLOR_UNASSIGNED', '#888780'),

];
