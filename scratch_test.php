<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

use Illuminate\Contracts\Console\Kernel;
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::where('role', 'dispatcher')->first();
if (!$user) {
    echo "No dispatcher user found!\n";
    exit(1);
}

Auth::login($user);

$request = Illuminate\Http\Request::create('fleet/dashboard', 'GET');
$response = Route::dispatch($request);
echo "Status Code: " . $response->getStatusCode() . "\n";
$content = $response->getContent();
file_put_contents('dashboard_output.html', $content);
echo "Output saved to dashboard_output.html. Length: " . strlen($content) . " bytes\n";

