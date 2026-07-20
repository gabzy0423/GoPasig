<?php

namespace App\Services\Providers;

use App\Services\Contracts\RoutingProviderInterface;
use App\Services\ValueObjects\Polyline;
use App\Services\ValueObjects\Coordinate;
use App\Services\PolylineEncoder;
use App\Exceptions\RoutingProviderException;
use Illuminate\Support\Facades\Http;

class OsrmRoutingProvider implements RoutingProviderInterface
{
    public function getRouteGeometry(array $waypoints): Polyline
    {
        // Normalize waypoints to Coordinates
        $coords = array_map(function ($coord) {
            return $coord instanceof Coordinate ? $coord : Coordinate::fromArray((array) $coord);
        }, $waypoints);

        $count = count($coords);
        if ($count < 2) {
            throw new RoutingProviderException("At least origin and destination waypoints are required.");
        }

        $waypointStrings = array_map(function (Coordinate $coord) {
            return $coord->getLongitude() . ',' . $coord->getLatitude();
        }, $coords);

        $path = implode(';', $waypointStrings);
        $baseUrl = rtrim(config('routing.providers.osrm.base_url', 'https://router.project-osrm.org'), '/');
        $url = "{$baseUrl}/route/v1/driving/{$path}";

        $timeout = (int) config('routing.providers.osrm.timeout', 3);
        $retries = (int) config('routing.providers.osrm.retries', 2);

        try {
            $response = Http::timeout($timeout)
                ->retry($retries, 100)
                ->get($url, [
                    'overview' => 'full',
                    'geometries' => 'polyline',
                ]);

            if ($response->failed()) {
                throw new RoutingProviderException("OSRM API HTTP request failed with status: " . $response->status());
            }

            $data = $response->json();

            if (strtolower($data['code'] ?? '') !== 'ok') {
                throw new RoutingProviderException("OSRM API returned error code: " . ($data['code'] ?? 'UNKNOWN') . ". Message: " . ($data['message'] ?? 'None'));
            }

            $geometry = $data['routes'][0]['geometry'] ?? null;
            if (!$geometry) {
                throw new RoutingProviderException("OSRM API response did not contain route geometry.");
            }

            return PolylineEncoder::decode($geometry);
        } catch (\Exception $e) {
            if ($e instanceof RoutingProviderException) {
                throw $e;
            }
            throw new RoutingProviderException("Failed to generate route geometry via OSRM: " . $e->getMessage(), 0, $e);
        }
    }

    public function getIdentifier(): string
    {
        return 'osrm';
    }
}
