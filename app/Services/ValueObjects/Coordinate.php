<?php

namespace App\Services\ValueObjects;

class Coordinate
{
    private float $latitude;
    private float $longitude;
    private ?float $bearing;
    private ?float $accuracy;
    private ?float $speed;
    private ?string $timestamp;

    public function __construct(
        float $latitude,
        float $longitude,
        ?float $bearing = null,
        ?float $accuracy = null,
        ?float $speed = null,
        ?string $timestamp = null
    ) {
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->bearing = $bearing;
        $this->accuracy = $accuracy;
        $this->speed = $speed;
        $this->timestamp = $timestamp;
    }

    public function getLatitude(): float
    {
        return $this->latitude;
    }

    public function getLongitude(): float
    {
        return $this->longitude;
    }

    public function getBearing(): ?float
    {
        return $this->bearing;
    }

    public function getAccuracy(): ?float
    {
        return $this->accuracy;
    }

    public function getSpeed(): ?float
    {
        return $this->speed;
    }

    public function getTimestamp(): ?string
    {
        return $this->timestamp;
    }

    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'bearing' => $this->bearing,
            'accuracy' => $this->accuracy,
            'speed' => $this->speed,
            'timestamp' => $this->timestamp,
        ];
    }

    public function toLatLngArray(): array
    {
        return [$this->latitude, $this->longitude];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            (float) ($data['latitude'] ?? $data['lat'] ?? 0.0),
            (float) ($data['longitude'] ?? $data['lng'] ?? 0.0),
            isset($data['bearing']) ? (float)$data['bearing'] : null,
            isset($data['accuracy']) ? (float)$data['accuracy'] : null,
            isset($data['speed']) ? (float)$data['speed'] : null,
            $data['timestamp'] ?? null
        );
    }

    public static function fromLatLngArray(array $coords): self
    {
        return new self(
            (float) ($coords[0] ?? 0.0),
            (float) ($coords[1] ?? 0.0)
        );
    }

    public function equals(Coordinate $other): bool
    {
        return abs($this->latitude - $other->getLatitude()) < 0.0000001 &&
               abs($this->longitude - $other->getLongitude()) < 0.0000001;
    }
}
