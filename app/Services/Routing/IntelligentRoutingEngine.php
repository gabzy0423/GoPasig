<?php

namespace App\Services\Routing;

use App\Models\Route;
use App\Models\RouteGenerationSession;
use App\Services\RoutingProviderFactory;
use App\Services\Contracts\RouteGeometryEngineInterface;
use App\Services\Contracts\GeometryValidatorInterface;
use App\Services\Contracts\RouteQualityInterface;
use App\Services\ValueObjects\Polyline;
use App\Services\ValueObjects\Coordinate;
use App\Data\RoutePreviewResult;
use App\Exceptions\RoutingProviderException;
use App\Events\RouteQualityCalculated;
use InvalidArgumentException;

class IntelligentRoutingEngine
{
    private RouteGenerationSessionService $sessions;
    private RouteComparisonService $comparer;
    private RouteGeometryEngineInterface $geometryEngine;
    private GeometryValidatorInterface $validator;
    private RouteQualityInterface $qualityEngine;

    public function __construct(
        RouteGenerationSessionService $sessions,
        RouteComparisonService $comparer,
        RouteGeometryEngineInterface $geometryEngine,
        GeometryValidatorInterface $validator,
        RouteQualityInterface $qualityEngine
    ) {
        $this->sessions = $sessions;
        $this->comparer = $comparer;
        $this->geometryEngine = $geometryEngine;
        $this->validator = $validator;
        $this->qualityEngine = $qualityEngine;
    }

    /**
     * Generate preview for a route using a given provider.
     */
    public function generatePreview(Route $route, string $providerName, ?int $userId = null): RoutePreviewResult
    {
        // 1. Check for existing active session
        $existing = $this->sessions->findActiveSession($route->id, $providerName);
        if ($existing) {
            $metrics = $existing->comparison_metrics;
            $quality = $metrics['quality'] ?? [
                'score' => 100,
                'grade' => 'Excellent',
                'warnings' => [],
                'recommendations' => ['Ready for deployment.']
            ];

            $comparisonOnly = $metrics;
            unset($comparisonOnly['quality']);

            return new RoutePreviewResult(
                $existing->id,
                $existing->route_id,
                $existing->provider,
                $existing->generated_geometry,
                $comparisonOnly,
                $quality,
                $existing->expires_at->toIso8601String()
            );
        }

        // 2. Fetch waypoints from route stops
        $stops = $route->stops()->orderBy('sequence')->get();
        if ($stops->count() < 2) {
            throw new RoutingProviderException("At least origin and destination stops are required to generate route geometry.");
        }

        $waypoints = $stops->map(fn($stop) => [
            'latitude' => $stop->lat,
            'longitude' => $stop->lng
        ])->toArray();

        // 3. Resolve starting provider using circuit breaker checks
        $circuitBreaker = app(\App\Services\Contracts\ProviderCircuitBreakerInterface::class);
        $healthSvc = app(\App\Services\Routing\ProviderHealthService::class);
        $quotaSvc = app(\App\Services\Routing\ProviderQuotaService::class);

        $resolvedProvider = $providerName;
        if (!$circuitBreaker->canRequest($resolvedProvider)) {
            $fallback = $this->resolveFallbackProvider($resolvedProvider);
            if ($fallback === null) {
                throw new RoutingProviderException("All available routing providers are currently unavailable/tripped.");
            }
            $resolvedProvider = $fallback;
        }

        // 4. Request geometry, record health and quota statistics
        $generatedPolyline = null;
        $startTime = microtime(true);
        try {
            $provider = RoutingProviderFactory::make($resolvedProvider);
            $generatedPolyline = $provider->getRouteGeometry($waypoints);
            $latencyMs = (microtime(true) - $startTime) * 1000.0;

            // Log successful attempt
            $healthSvc->recordRequest($resolvedProvider, $latencyMs, true);
            $quotaSvc->recordRequest($resolvedProvider);

            $snapshot = $healthSvc->getSnapshot($resolvedProvider);
            $circuitBreaker->evaluate($snapshot);

            $providerName = $resolvedProvider;
        } catch (\Exception $e) {
            $latencyMs = (microtime(true) - $startTime) * 1000.0;

            // Log failed attempt
            $healthSvc->recordRequest($resolvedProvider, $latencyMs, false);
            $quotaSvc->recordRequest($resolvedProvider);

            $snapshot = $healthSvc->getSnapshot($resolvedProvider);
            $circuitBreaker->evaluate($snapshot);

            // Failover
            $fallback = $this->resolveFallbackProvider($resolvedProvider);
            if ($fallback !== null) {
                return $this->generatePreview($route, $fallback, $userId);
            }

            throw new RoutingProviderException("Provider {$resolvedProvider} failed and no fallbacks available: " . $e->getMessage(), 0, $e);
        }

        // 5. Validate generated geometry
        $validation = $this->validator->validatePolyline($generatedPolyline);
        if (!$validation->isValid()) {
            $issues = $validation->getIssues();
            $firstIssue = reset($issues) ?: 'Unknown validation error';
            throw new RoutingProviderException("Generated geometry failed validation constraints: " . $firstIssue);
        }

        // 6. Run comparison & quality scoring
        $originalPolyline = Polyline::fromArray($route->polyline_coordinates ?: []);
        $comparison = $this->comparer->compareFast($originalPolyline, $generatedPolyline);

        $geometryMetrics = $this->computeGeometryMetrics($generatedPolyline);
        $quality = $this->qualityEngine->analyze($comparison, $geometryMetrics);

        // Dispatch quality event
        event(new RouteQualityCalculated($route->id, $quality));

        // Store comparison + quality nested array in DB session
        $storedMetrics = $comparison->toArray();
        $storedMetrics['quality'] = $quality->toArray();

        // 7. Create session
        $session = $this->sessions->createSession(
            $route->id,
            $providerName,
            $generatedPolyline,
            $storedMetrics,
            $userId
        );

        return new RoutePreviewResult(
            $session->id,
            $session->route_id,
            $session->provider,
            $session->generated_geometry,
            $comparison->toArray(),
            $quality->toArray(),
            $session->expires_at->toIso8601String()
        );
    }

    /**
     * Perform optional advanced Fréchet comparison on a preview session.
     */
    public function runAdvancedAnalysis(Route $route, string $sessionId): array
    {
        $session = $this->sessions->getSession($sessionId);
        if (!$session) {
            throw new InvalidArgumentException("Route generation session not found or expired.");
        }

        $originalPolyline = Polyline::fromArray($route->polyline_coordinates ?: []);
        $generatedPolyline = Polyline::fromArray($session->generated_geometry);

        $comparison = $this->comparer->compareAdvanced($originalPolyline, $generatedPolyline);
        $geometryMetrics = $this->computeGeometryMetrics($generatedPolyline);
        $quality = $this->qualityEngine->analyze($comparison, $geometryMetrics);

        // Update database session metrics
        $storedMetrics = $comparison->toArray();
        $storedMetrics['quality'] = $quality->toArray();

        $session->update([
            'comparison_metrics' => $storedMetrics
        ]);

        // Dispatch events
        event(new RouteQualityCalculated($route->id, $quality));
        event(new \App\Events\AdvancedComparisonCompleted($route->id, $sessionId, $comparison->frechetSimilarityPercent));

        return [
            'comparison' => $comparison->toArray(),
            'quality' => $quality->toArray()
        ];
    }

    /**
     * Accept a preview session and apply geometry to production route.
     */
    public function acceptPreview(string $sessionId, int $clientVersion): void
    {
        $session = $this->sessions->getSession($sessionId);
        if (!$session) {
            throw new InvalidArgumentException("Route generation session not found or expired.");
        }

        if ($session->isExpired()) {
            $this->sessions->deleteSession($sessionId);
            throw new InvalidArgumentException("Route generation session has expired.");
        }

        $polyline = Polyline::fromArray($session->generated_geometry);
        $this->geometryEngine->updateGeometry($session->route_id, $polyline, $clientVersion);

        $session->update(['status' => 'accepted']);
        $this->sessions->deleteSession($sessionId);
    }

    /**
     * Reject a preview session.
     */
    public function rejectPreview(string $sessionId): void
    {
        $session = $this->sessions->getSession($sessionId);
        if ($session) {
            $session->update(['status' => 'rejected']);
            $this->sessions->deleteSession($sessionId);
        }
    }

    private function resolveFallbackProvider(string $failedProvider): ?string
    {
        $circuitBreaker = app(\App\Services\Contracts\ProviderCircuitBreakerInterface::class);
        $providers = config('routing.providers', []);

        uasort($providers, fn($a, $b) => ($a['priority'] ?? 99) <=> ($b['priority'] ?? 99));

        foreach ($providers as $name => $conf) {
            if ($name === $failedProvider) {
                continue;
            }
            if ($circuitBreaker->canRequest($name)) {
                return $name;
            }
        }

        return null;
    }

    private function computeGeometryMetrics(Polyline $polyline): array
    {
        $coords = $polyline->getCoordinates();
        $n = count($coords);
        $maxSpacing = 0.0;
        $duplicateCount = 0;
        $hasSelfIntersections = false;

        $geospatial = app(\App\Services\Contracts\GeospatialServiceInterface::class);

        for ($i = 0; $i < $n - 1; $i++) {
            $dist = $geospatial->calculateDistanceKm($coords[$i], $coords[$i + 1]) * 1000.0;
            if ($dist > $maxSpacing) {
                $maxSpacing = $dist;
            }

            if ($coords[$i]->equals($coords[$i + 1])) {
                $duplicateCount++;
            }
        }

        for ($i = 0; $i < $n - 2; $i++) {
            for ($j = $i + 2; $j < $n - 1; $j++) {
                if ($this->segmentsIntersect($coords[$i], $coords[$i + 1], $coords[$j], $coords[$j + 1])) {
                    $hasSelfIntersections = true;
                    break 2;
                }
            }
        }

        return [
            'max_spacing_meters' => $maxSpacing,
            'duplicate_vertices_count' => $duplicateCount,
            'has_self_intersections' => $hasSelfIntersections,
        ];
    }

    private function ccw(Coordinate $a, Coordinate $b, Coordinate $c): bool
    {
        return ($c->getLatitude() - $a->getLatitude()) * ($b->getLongitude() - $a->getLongitude()) >
               ($b->getLatitude() - $a->getLatitude()) * ($c->getLongitude() - $a->getLongitude());
    }

    private function segmentsIntersect(Coordinate $a, Coordinate $b, Coordinate $c, Coordinate $d): bool
    {
        if ($a->equals($c) || $a->equals($d) || $b->equals($c) || $b->equals($d)) {
            return false;
        }

        return ($this->ccw($a, $c, $d) != $this->ccw($b, $c, $d)) &&
               ($this->ccw($a, $b, $c) != $this->ccw($a, $b, $d));
    }
}
