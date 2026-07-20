<?php

namespace App\Services;

use App\Services\ValueObjects\Coordinate;
use App\Services\ValueObjects\Polyline;

class PolylineEncoder
{
    /**
     * Encode a Polyline object into an encoded polyline string.
     */
    public static function encode(Polyline $polyline): string
    {
        $points = $polyline->getCoordinates();
        $encoded = '';
        $lastLat = 0;
        $lastLng = 0;

        foreach ($points as $point) {
            $lat = (int) round($point->getLatitude() * 1e5);
            $lng = (int) round($point->getLongitude() * 1e5);

            $dLat = $lat - $lastLat;
            $dLng = $lng - $lastLng;

            $lastLat = $lat;
            $lastLng = $lng;

            $encoded .= self::encodeValue($dLat) . self::encodeValue($dLng);
        }

        return $encoded;
    }

    /**
     * Decode an encoded polyline string into a Polyline object.
     */
    public static function decode(string $encoded): Polyline
    {
        $len = strlen($encoded);
        $index = 0;
        $lat = 0;
        $lng = 0;
        $coordinates = [];

        while ($index < $len) {
            // Decode latitude
            $shift = 0;
            $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            // Decode longitude
            $shift = 0;
            $result = 0;
            do {
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);
            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $coordinates[] = new Coordinate($lat / 1e5, $lng / 1e5);
        }

        return new Polyline($coordinates);
    }

    private static function encodeValue(int $val): string
    {
        $val = $val < 0 ? ~($val << 1) : ($val << 1);
        $out = '';
        while ($val >= 0x20) {
            $out .= chr((0x20 | ($val & 0x1f)) + 63);
            $val >>= 5;
        }
        $out .= chr($val + 63);
        return $out;
    }
}
