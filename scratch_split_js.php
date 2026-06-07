<?php
$content = file_get_contents('public/js/admin-dashboard.js');

if (!is_dir('public/js/admin')) {
    mkdir('public/js/admin', 0777, true);
}

// Map Module
preg_match('/^\s*\/\/\s*==================== LIVE FLEET MAP MODULE ====================.*?(^\s*\/\/\s*==================== END LIVE FLEET MAP MODULE ====================.*?)/ms', $content, $mapMatch);
if ($mapMatch) {
    $mapJs = $mapMatch[0];
    file_put_contents('public/js/admin/map.js', $mapJs);
    $content = str_replace($mapJs, "", $content);
} else {
    echo "Map module not found\n";
}

// Analytics Module
preg_match('/^\s*\/\/\s*=========================================================================\s*\n\s*\/\/\s*==================== REPORTS & ANALYTICS MODULE ========================\s*\n\s*\/\/\s*=========================================================================.*/ms', $content, $analyticsMatch);
if ($analyticsMatch) {
    $analyticsJs = $analyticsMatch[0];
    file_put_contents('public/js/admin/analytics.js', $analyticsJs);
    $content = str_replace($analyticsJs, "", $content);
} else {
    echo "Analytics module not found\n";
}

// The rest is core / shared data (fleetData, switchScreen, etc.)
file_put_contents('public/js/admin/core.js', trim($content));

echo "Splitting done.";
