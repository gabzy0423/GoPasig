<?php

namespace App\Data;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

class RoutePreviewResult implements Arrayable, JsonSerializable
{
    public string $sessionId;
    public int $routeId;
    public ?int $routeVariantId;
    public string $provider;
    public array $generatedGeometry;
    public array $comparisonMetrics;
    public array $qualityResult;
    public string $expiresAt;

    public function __construct(
        string $sessionId,
        int $routeId,
        string $provider,
        array $generatedGeometry,
        array $comparisonMetrics,
        array $qualityResult,
        string $expiresAt,
        ?int $routeVariantId = null
    ) {
        $this->sessionId = $sessionId;
        $this->routeId = $routeId;
        $this->routeVariantId = $routeVariantId;
        $this->provider = $provider;
        $this->generatedGeometry = $generatedGeometry;
        $this->comparisonMetrics = $comparisonMetrics;
        $this->qualityResult = $qualityResult;
        $this->expiresAt = $expiresAt;
    }

    public function toArray(): array
    {
        return [
            'session_id' => $this->sessionId,
            'route_id' => $this->routeId,
            'route_variant_id' => $this->routeVariantId,
            'provider' => $this->provider,
            'generated_geometry' => $this->generatedGeometry,
            'comparison' => $this->comparisonMetrics,
            'quality' => $this->qualityResult,
            'expires_at' => $this->expiresAt,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
