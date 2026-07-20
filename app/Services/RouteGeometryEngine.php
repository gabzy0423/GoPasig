<?php

namespace App\Services;

use App\Services\Contracts\RouteGeometryEngineInterface;
use App\Repositories\Contracts\RouteGeometryRepositoryInterface;
use App\Services\Contracts\GeometryValidatorInterface;
use App\Services\ValueObjects\Polyline;
use App\Services\ValueObjects\Coordinate;
use App\Exceptions\GeometryConflictException;
use App\Events\RouteGeometryCreated;
use App\Events\RouteGeometryUpdated;
use App\Events\RouteGeometryDeleted;
use App\Enums\GeometryStatus;
use App\Models\Route;
use Illuminate\Support\Facades\DB;

class RouteGeometryEngine implements RouteGeometryEngineInterface
{
    private RouteGeometryRepositoryInterface $repository;
    private GeometryValidatorInterface $validator;
    private GeometryVersioningService $versioning;
    private GeometrySimplifier $simplifier;

    public function __construct(
        RouteGeometryRepositoryInterface $repository,
        GeometryValidatorInterface       $validator,
        GeometryVersioningService       $versioning,
        GeometrySimplifier              $simplifier
    ) {
        $this->repository = $repository;
        $this->validator = $validator;
        $this->versioning = $versioning;
        $this->simplifier = $simplifier;
    }

    public function updateGeometry(int $routeId, Polyline $polyline, int $clientVersion, ?int $restoredFromVersion = null): Polyline
    {
        $newGeometry = null;
        $metrics     = null;
        $previous    = null;

        DB::transaction(function () use (
            $routeId,
            $polyline,
            $clientVersion,
            $restoredFromVersion,
            &$newGeometry,
            &$metrics,
            &$previous
        ) {
            // 1. Row-level lock inside transaction
            $route = Route::lockForUpdate()->findOrFail($routeId);

            // 2. Optimistic locking check
            if ((int) $route->geometry_version !== (int) $clientVersion) {
                throw new GeometryConflictException(
                    "Route geometry was modified by another user. " .
                    "Current version: {$route->geometry_version}, " .
                    "your version: {$clientVersion}."
                );
            }

            // 3. Validate
            $polyValidation = $this->validator->validatePolyline($polyline);
            if (!$polyValidation->isValid()) {
                throw new \InvalidArgumentException($polyValidation['error'] ?? 'Invalid polyline coordinates.');
            }

            $segValidation = $this->validator->validateSegments($polyline);
            if (!$segValidation->isValid()) {
                throw new \InvalidArgumentException($segValidation['error'] ?? 'Invalid segment rules.');
            }

            // 4. Snapshot previous geometry
            $previous = $this->repository->getRoutePolyline($routeId);
            $this->versioning->snapshot($routeId, $previous, 'Auto Snapshot', $restoredFromVersion);

            // 5. Persist
            $this->repository->persistPolyline($routeId, $polyline);

            // 6. Refresh route to get new updated_at and version (since persist increments version)
            $route->refresh();

            // Compute and cache metrics
            $metrics = $this->computeMetrics($routeId, $polyline);
            $this->repository->clearAll($routeId);
            $this->repository->storeMetrics($routeId, $metrics);

            $newGeometry = $polyline;

            // 7. Register post-commit event
            DB::afterCommit(function () use ($routeId, $previous, $newGeometry, $metrics) {
                if ($previous->isEmpty() && !$newGeometry->isEmpty()) {
                    event(new RouteGeometryCreated($routeId, $newGeometry, $metrics));
                } elseif (!$previous->isEmpty() && $newGeometry->isEmpty()) {
                    event(new RouteGeometryDeleted($routeId, $previous));
                } else {
                    event(new RouteGeometryUpdated($routeId, $previous, $newGeometry, $metrics));
                }
            });
        });

        return $newGeometry;
    }

    public function getGeometry(int $routeId): Polyline
    {
        return $this->repository->getRoutePolyline($routeId);
    }

    public function computeMetrics(int $routeId, Polyline $polyline): array
    {
        $route = Route::find($routeId);
        $tolerance = (float) config('routing.simplification_tolerance', 0.00005);
        $simplified = $this->simplifier->simplify($polyline, $tolerance);

        $polyValidation = $this->validator->validatePolyline($polyline);
        $segValidation = $this->validator->validateSegments($polyline);

        // Derive overall geometry status
        $status = GeometryStatus::VALID;
        if (!$polyValidation->isValid() || !$segValidation->isValid()) {
            $status = GeometryStatus::INVALID;
        } elseif ($segValidation->hasWarnings()) {
            $status = GeometryStatus::WARNING;
        }

        // Count intersections
        $intersections = 0;
        $coords = $polyline->getCoordinates();
        $n = count($coords);
        $tooManyIntersections = false;

        for ($i = 0; $i < $n - 2; $i++) {
            for ($j = $i + 2; $j < $n - 1; $j++) {
                if ($this->segmentsIntersect($coords[$i], $coords[$i + 1], $coords[$j], $coords[$j + 1])) {
                    $intersections++;
                    if ($intersections > 500) {
                        $tooManyIntersections = true;
                        break 2;
                    }
                }
            }
        }

        return [
            'length_km' => round($polyline->getLengthKm(), 4),
            'vertex_count' => $polyline->count(),
            'avg_segment_m' => round($polyline->getAverageSegmentLengthM(), 2),
            'longest_segment_m' => round($polyline->getMaxSegmentLengthM(), 2),
            'shortest_segment_m' => round($polyline->getMinSegmentLengthM(), 2),
            'max_vertex_spacing_m' => round($polyline->getMaxVertexSpacingM(), 2),
            'bounds' => $polyline->getBounds(),
            'center_point' => $polyline->getCenter(),
            'closed_loop' => $polyline->isClosedLoop(50.0),
            'self_intersections' => $tooManyIntersections ? null : $intersections,
            'duplicate_vertices' => $polyline->getDuplicateVerticesCount(),
            'simplified_vertices' => $simplified->count(),
            'geometry_status' => $status->value,
            'geometry_version' => $route ? $route->geometry_version : 0,
            'last_updated_at' => $route && $route->updated_at ? $route->updated_at->toIso8601String() : now()->toIso8601String(),
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
