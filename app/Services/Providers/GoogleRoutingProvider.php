<?php

namespace App\Services\Providers;

use App\Services\Contracts\RoutingProviderInterface;
use App\Services\ValueObjects\Polyline;
use App\Services\ValueObjects\Coordinate;
use App\Services\PolylineEncoder;
use App\Exceptions\RoutingProviderException;
use Illuminate\Support\Facades\Http;

class GoogleRoutingProvider implements RoutingProviderInterface
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

        $origin = $coords[0];
        $destination = $coords[$count - 1];

        $originStr = $origin->getLatitude() . ',' . $origin->getLongitude();
        $destinationStr = $destination->getLatitude() . ',' . $destination->getLongitude();

        $params = [
            'origin' => $originStr,
            'destination' => $destinationStr,
            'mode' => 'driving',
            'key' => config('routing.providers.google.key') ?: env('GOOGLE_MAPS_API_KEY'),
        ];

        if ($count > 2) {
            $intermediates = [];
            for ($i = 1; $i < $count - 1; $i++) {
                $intermediates[] = 'via:' . $coords[$i]->getLatitude() . ',' . $coords[$i]->getLongitude();
            }
            $params['waypoints'] = implode('|', $intermediates);
        }

        $timeout = (int) config('routing.providers.google.timeout', 5);
        $retries = (int) config('routing.providers.google.retries', 3);

        try {
            $response = Http::timeout($timeout)
                ->retry($retries, 100)
                ->get('https://maps.googleapis.com/maps/api/directions/json', $params);

            if ($response->failed()) {
                throw new RoutingProviderException("Google Directions API HTTP request failed with status: " . $response->status());
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'OK') {
                throw new RoutingProviderException("Google Directions API returned status error: " . ($data['status'] ?? 'UNKNOWN') . ". Error details: " . ($data['error_message'] ?? 'None'));
            }

            $points = $data['routes'][0]['overview_polyline']['points'] ?? null;
            if (!$points) {
                throw new RoutingProviderException("Google Directions API response did not contain overview polyline points.");
            }

            return PolylineEncoder::decode($points);
        } catch (\Exception $e) {
            if ($e instanceof RoutingProviderException) {
                throw $e;
            }
            throw new RoutingProviderException("Failed to generate route geometry via Google: " . $e->getMessage(), 0, $e);
        }
    }

    public function getIdentifier(): string
    {
        return 'google';
    }
}
