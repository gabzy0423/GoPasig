<?php

// A script to parse the restored gopasig_audit_report.md and check the status of each issue in the codebase

$report = file_get_contents('gopasig_audit_report.md');
preg_match_all('/#### ISSUE-(\d+)\s*\|\s*Field\s*\|\s*Detail\s*\|.*?\n(.*?)\n\n---/s', $report, $matches, PREG_SET_ORDER);

echo "Found " . count($matches) . " issues in report.\n";

$issues = [];
foreach ($matches as $match) {
    $id = 'ISSUE-' . $match[1];
    $details = $match[2];
    
    // Parse table details
    // Match something like [filename](file:///path/to/file#L123-L125) or [filename](file:///path/to/file#L123)
    preg_match('/\|\s*\*\*File & Line\*\*\s*\|\s*\[`([^`]+)`\]\(file:\/\/([^\)]+)\)/', $details, $fileMatch);
    preg_match('/\|\s*\*\*Severity\*\*\s*\|\s*([^\s\|]+)/', $details, $sevMatch);
    preg_match('/\|\s*\*\*Description\*\*\s*\|\s*(.*?)\s*\|/', $details, $descMatch);
    preg_match('/\|\s*\*\*Category\*\*\s*\|\s*(.*?)\s*\|/', $details, $catMatch);
    
    $file = $fileMatch[1] ?? 'unknown';
    $fullPathAndHash = $fileMatch[2] ?? '';
    
    // Remove query / hash from path
    $parts = explode('#', $fullPathAndHash);
    $fullPath = $parts[0];
    
    $severity = $sevMatch[1] ?? 'unknown';
    $description = $descMatch[1] ?? '';
    $category = $catMatch[1] ?? '';
    
    $issues[$id] = [
        'id' => $id,
        'file' => $file,
        'full_path' => $fullPath,
        'severity' => $severity,
        'description' => $description,
        'category' => $category,
    ];
}

// Print results
echo "Checking issues against codebase...\n";
$notFixed = [];
$fixed = [];

foreach ($issues as $id => $issue) {
    $filePath = str_replace('/c:/xampp/htdocs/GoPasig/', '', $issue['full_path']);
    $filePath = str_replace('c:/xampp/htdocs/GoPasig/', '', $filePath);
    $filePath = str_replace('c:\\xampp\\htdocs\\GoPasig\\', '', $filePath);
    $filePath = trim($filePath);
    
    if (!file_exists($filePath)) {
        echo "File does not exist: '$filePath' for $id\n";
        $notFixed[] = $id;
        continue;
    }
    
    $content = file_get_contents($filePath);
    
    // Let's check some specific indicators for each issue to determine if it is fixed or not.
    $isFixed = false;
    
    switch ($id) {
        case 'ISSUE-001':
            $isFixed = (strpos($content, 'driver_passenger_rating_default') !== false);
            break;
        case 'ISSUE-002':
            $isFixed = (strpos($content, 'debug_backtrace') === false);
            break;
        case 'ISSUE-003':
            $isFixed = (strpos($content, 'driver_performance_default_on_time_score') !== false);
            break;
        case 'ISSUE-004':
            $isFixed = (strpos($content, 'driver_score_incident_penalty') !== false);
            break;
        case 'ISSUE-005':
            $isFixed = (strpos($content, 'travel_time_minutes') !== false && strpos($content, '1.5') === false);
            break;
        case 'ISSUE-006':
            $isFixed = (strpos($content, 'report_score_weight_performance') !== false);
            break;
        case 'ISSUE-007':
            $isFixed = (strpos($content, 'default_delayed_variance_minutes') !== false);
            break;
        case 'ISSUE-008':
            $isFixed = (strpos($content, 'stop_default_variance_minutes') !== false);
            break;
        case 'ISSUE-009':
            $isFixed = (strpos($content, 'default_headway_target') !== false);
            break;
        case 'ISSUE-010':
            $isFixed = (strpos($content, 'default_delayed_variance_minutes') !== false && strpos($content, '0.1') === false);
            break;
        case 'ISSUE-011':
            $isFixed = (strpos($content, 'actual_departure_time') !== false && strpos($content, 'Early') !== false);
            break;
        case 'ISSUE-012':
            $isFixed = (strpos($content, 'is_simulated') !== false);
            break;
        case 'ISSUE-013':
            $isFixed = (strpos($content, 'map_default_latitude') !== false);
            break;
        case 'ISSUE-014':
            // DriverPerformanceController - is messaging removed or stubbed or implemented?
            $isFixed = (strpos($content, 'messageDriver') === false || strpos($content, '404') !== false || strpos($content, '501') !== false || strpos($content, 'Route::') === false);
            break;
        case 'ISSUE-015':
            $isFixed = (strpos($content, 'CommuterTrip::where') !== false);
            break;
        case 'ISSUE-016':
            $isFixed = (strpos($content, 'NotificationMail') !== false || strpos($content, 'sendEmailNotification') === false);
            break;
        case 'ISSUE-017':
            $isFixed = (strpos($content, 'coordinates_bounds_south_latitude') !== false);
            break;
        case 'ISSUE-018':
            $isFixed = (strpos($content, 'max_polyline_jump_km') !== false);
            break;
        case 'ISSUE-019':
            $isFixed = (strpos($content, 'min_trip_duration_minutes') !== false);
            break;
        case 'ISSUE-020':
            $isFixed = (strpos($content, 'route_min_bus_capacity') !== false);
            break;
        case 'ISSUE-021':
            $isFixed = (strpos($content, 'departure_time') !== false && strpos($content, 'whereDate') !== false);
            break;
        case 'ISSUE-022':
            $isFixed = (strpos($content, 'isTimeOnly') !== false && strpos($content, 'parseScheduleDateTime') !== false);
            break;
        case 'ISSUE-023':
            $isFixed = (strpos($content, 'driver_id') !== false && strpos($content, 'LIKE') === false);
            break;
        case 'ISSUE-024':
            $isFixed = (strpos($content, 'ActivityLog::create') !== false || strpos($content, 'activity_logs') !== false || strpos($content, 'ActivityLog') !== false);
            break;
        case 'ISSUE-025':
            $isFixed = (strpos($content, 'bus_gps_offline_threshold_minutes') !== false);
            break;
        case 'ISSUE-026':
            $isFixed = (strpos($content, 'analytics_fallback_peak_hour') !== false || strpos($content, 'No data') !== false);
            break;
        case 'ISSUE-027':
            $isFixed = (strpos($content, 'analytics_top_stops_limit') !== false || strpos($content, 'analytics_top_stops_count') !== false);
            break;
        case 'ISSUE-028':
            $isFixed = (strpos($content, 'maintenance_default_duration_minutes') !== false);
            break;
        case 'ISSUE-029':
            $isFixed = (strpos($content, 'maintenance_types') !== false);
            break;
        case 'ISSUE-031':
            $isFixed = (strpos($content, 'routeColors[route.id') !== false || strpos($content, 'route.color') !== false || strpos($content, 'routeColors[') !== false);
            break;
        case 'ISSUE-032':
            $isFixed = (strpos($content, 'window.GoPasigConfig') !== false || strpos($content, 'mapCenterLat') !== false);
            break;
        case 'ISSUE-033':
            $isFixed = (strpos($content, 'stopBoardingData[0]') !== false || strpos($content, 'boarding_count') !== false);
            break;
        case 'ISSUE-034':
            $isFixed = (strpos($content, 'ml_accuracy') === false || strpos($content, 'Simulated') !== false || strpos($content, 'Math.random()') === false || strpos($content, '96.4') === false);
            break;
        case 'ISSUE-035':
            $isFixed = (strpos($content, 'routeColors[') !== false && strpos($content, 'log.route_id') !== false);
            break;
        case 'ISSUE-036':
            $isFixed = (strpos($content, 'boarding_geofence_radius_meters') !== false);
            break;
        case 'ISSUE-037':
            $isFixed = (strpos($content, 'route_color_default') !== false);
            break;
        case 'ISSUE-038':
            $isFixed = (strpos($content, 'next_stop') !== false);
            break;
        case 'ISSUE-039':
            $isFixed = (strpos($content, 'setAlert') !== false && strpos($content, 'origin_stop_id') !== false);
            break;
        case 'ISSUE-040':
            $isFixed = (strpos($content, 'active') !== false && strpos($content, 'maintenance') === false);
            break;
        case 'ISSUE-041':
            $isFixed = (strpos($content, 'whereDate') !== false && strpos($content, 'service_date') !== false);
            break;
        case 'ISSUE-042':
            $isFixed = (strpos($content, 'Maintenance') !== false && strpos($content, 'Breakdown') === false);
            break;
        case 'ISSUE-043':
            $isFixed = (strpos($content, 'kalman_process_variance') !== false);
            break;
        case 'ISSUE-044':
            $isFixed = (strpos($content, 'ROLE_ADMIN') !== false || strpos($content, 'ROLE_DISPATCHER') !== false);
            break;
        case 'ISSUE-045':
            $isFixed = (strpos($content, 'Route A') === false && strpos($content, 'Route B') === false);
            break;
        case 'ISSUE-046':
            $isFixed = (strpos($content, 'Empty State') !== false || strpos($content, 'No Data') !== false || strpos($content, 'EmptyState') !== false || strpos($content, 'no-data-overlay') !== false);
            break;
        case 'ISSUE-047':
            $isFixed = (strpos($content, 'prediction') !== false && strpos($content, 'Route A') === false);
            break;
        case 'ISSUE-048':
            $isFixed = (strpos($content, 'default_route_name') !== false);
            break;
        case 'ISSUE-049':
            $isFixed = (strpos($content, 'driver_email_domain') !== false);
            break;
        case 'ISSUE-050':
            $isFixed = (strpos($content, 'resolve') !== false && strpos($content, 'NotificationService') !== false);
            break;
        case 'ISSUE-051':
            $isFixed = (strpos($content, 'environment') !== false || strpos($content, 'autologin-dispatcher') === false);
            break;
        case 'ISSUE-052':
            $isFixed = (strpos($content, 'calculateDistance') !== false);
            break;
    }
    
    if ($isFixed) {
        $fixed[] = $id;
    } else {
        $notFixed[] = $id;
    }
}

echo "\nFIXED ISSUES (" . count($fixed) . "):\n";
echo implode(", ", $fixed) . "\n";

echo "\nNOT YET FIXED ISSUES (" . count($notFixed) . "):\n";
echo implode(", ", $notFixed) . "\n";

foreach ($notFixed as $id) {
    $issue = $issues[$id];
    echo "$id: {$issue['file']} | {$issue['severity']} | {$issue['category']}\n";
}

