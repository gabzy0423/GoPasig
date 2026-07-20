<?php

use App\Models\GPSLog;
use App\Models\VehiclePosition;
use App\Models\Bus;
use App\Models\Trip;

$trip = Trip::find(11);
$bus  = Bus::find(1);

// ---- Layer 5: GPS Log processing_status ----
$log = GPSLog::where('trip_id', $trip->id)->latest()->first();
echo "=== Layer 5: GPS Log ===\n";
if ($log) {
    echo "  Log ID: {$log->id}\n";
    echo "  processing_status: {$log->processing_status}\n";
    echo "  filtered_lat: " . ($log->filtered_lat ?? 'NULL') . "\n";
    echo "  filtered_lng: " . ($log->filtered_lng ?? 'NULL') . "\n";
} else {
    echo "  No GPS log found for trip {$trip->id}\n";
}

// ---- Layer 6: Vehicle Position ----
$vp = VehiclePosition::where('bus_id', $bus->id)->latest()->first();
echo "\n=== Layer 6: Vehicle Position ===\n";
if ($vp) {
    echo "  ID: {$vp->id}\n";
    echo "  lat: {$vp->latitude}, lng: {$vp->longitude}\n";
    echo "  recorded_at: {$vp->recorded_at}\n";
} else {
    echo "  No vehicle_position record found for bus {$bus->id}\n";
}

// ---- Layer 7: Bus sync ----
$bus->refresh();
echo "\n=== Layer 7: Bus Status ===\n";
echo "  plate: {$bus->plate_number}\n";
echo "  lat: {$bus->lat}, lng: {$bus->lng}\n";
echo "  speed: {$bus->speed}\n";
echo "  status: {$bus->status}\n";
echo "  updated_at: {$bus->updated_at}\n";

// ---- Jobs table (Layer 3) ----
$jobCount = DB::table('jobs')->count();
echo "\n=== Layer 3: Jobs Table ===\n";
echo "  Pending jobs remaining: {$jobCount}\n";

// ---- Failed jobs ----
$failCount = DB::table('failed_jobs')->count();
echo "\n=== Failed Jobs ===\n";
echo "  Failed jobs count: {$failCount}\n";
