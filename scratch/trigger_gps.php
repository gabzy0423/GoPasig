<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

// Find the driver user
$user = User::where('role', 'driver')->first();
Auth::login($user);

// Build a synthetic request
$request = Request::create('/driver/trip/gps', 'POST', [
    'lat'          => 14.5700,
    'lng'          => 121.0680,
    'speed'        => 25.5,
    'is_simulated' => false,
]);
$request->setLaravelSession(app('session')->driver());

// Bind to the service container so middleware picks it up
app()->instance('request', $request);
app(\Illuminate\Contracts\Http\Kernel::class);

// Call the controller directly (bypassing middleware/CSRF)
$controller = app(\App\Http\Controllers\Driver\DriverController::class);
$response   = $controller->updateGPS($request);

echo "HTTP Status: " . $response->getStatusCode() . "\n";
echo "Response: " . $response->getContent() . "\n";
