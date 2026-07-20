<?php

use App\Models\Driver;
use App\Models\Bus;
use App\Models\Trip;
use App\Models\User;

$bus = Bus::first();
$driver = Driver::first();
$user = User::find($driver->user_id);

$driver->update(['assigned_bus' => $bus->plate_number, 'operational_status' => 'on_duty']);

Trip::where('driver_id', $driver->id)->where('status', 'ongoing')->update([
    'status' => 'completed', 'gps_session' => 'CLOSED', 'ended_at' => now(),
]);

$trip = Trip::create([
    'bus_id' => $bus->id,
    'driver_id' => $driver->id,
    'route_id' => 1,
    'status' => 'ongoing',
    'gps_session' => 'ACTIVE',
    'started_at' => now(),
    'gps_session_started_at' => now(),
]);

$fresh = $driver->fresh();
echo "Driver assigned_bus: {$fresh->assigned_bus}\n";
echo "Trip ID: {$trip->id} | status: {$trip->status} | gps_session: {$trip->gps_session}\n";
echo "User email: {$user->email} | role: {$user->role}\n";
