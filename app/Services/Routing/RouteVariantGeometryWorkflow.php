<?php

namespace App\Services\Routing;

use App\Data\RoutePreviewResult;
use App\Exceptions\GeometryConflictException;
use App\Exceptions\RoutingProviderException;
use App\Models\RouteGenerationSession;
use App\Models\RouteVariant;
use App\Models\RouteVariantGeometryVersion;
use App\Services\RoutingProviderFactory;
use App\Services\Contracts\GeometryValidatorInterface;
use App\Services\ValueObjects\Polyline;
use Illuminate\Support\Facades\DB;

class RouteVariantGeometryWorkflow
{
    public function __construct(
        private RouteGenerationSessionService $sessions,
        private RouteComparisonService $comparer,
        private GeometryValidatorInterface $validator,
        private RouteQualityService $quality
    ) {}

    public function generatePreview(RouteVariant $variant, string $providerName, ?int $userId = null): RoutePreviewResult
    {
        if ($providerName !== 'google') {
            throw new RoutingProviderException('Official RouteVariant geometry generation requires the explicit Google provider.');
        }

        $stops = $variant->stops()->orderBy('sequence')->get();
        $blockingStops = $stops->filter(fn ($stop) =>
            !is_numeric($stop->lat) || !is_numeric($stop->lng) || $stop->coordinate_status !== 'verified'
        );
        if ($stops->count() < 2 || $blockingStops->isNotEmpty()) {
            $details = $blockingStops->map(fn ($stop) => sprintf(
                '%s. %s [%s]',
                $stop->sequence,
                $stop->name,
                $stop->coordinate_status ?: 'pending'
            ))->values()->implode(', ');
            throw new RoutingProviderException(
                'Geometry generation is blocked until every ordered directional stop has verified coordinates (valid lat/lng and coordinate_status=verified).'
                . ($details ? ' Blocking stops: ' . $details : '')
            );
        }

        $existing = $this->sessions->findActiveSession($variant->route_id, $providerName, $variant->id);
        if ($existing) {
            return new RoutePreviewResult(
                $existing->id,
                $existing->route_id,
                $existing->provider,
                $existing->generated_geometry,
                $existing->comparison_metrics,
                $existing->comparison_metrics['quality'] ?? [],
                $existing->expires_at->toIso8601String(),
                $existing->route_variant_id
            );
        }

        $waypoints = $stops->map(fn ($stop) => [
            'latitude' => (float) $stop->lat,
            'longitude' => (float) $stop->lng,
        ])->all();
        $polyline = RoutingProviderFactory::make('google')->getRouteGeometry($waypoints);
        $validation = $this->validator->validatePolyline($polyline);
        $segments = $this->validator->validateSegments($polyline);
        if (!$validation->isValid() || !$segments->isValid()) {
            throw new RoutingProviderException('Generated variant geometry failed validation.');
        }

        $baseline = Polyline::fromArray($variant->polyline_coordinates ?: []);
        $comparisonResult = $this->comparer->compareFast($baseline, $polyline);
        $comparison = $comparisonResult->toArray();
        $quality = $this->quality->analyze($comparisonResult, [
            'length_km' => $polyline->getLengthKm(),
            'vertex_count' => $polyline->count(),
        ])->toArray();
        $comparison['quality'] = $quality;
        $session = $this->sessions->createSession(
            $variant->route_id,
            'google',
            $polyline,
            $comparison,
            $userId,
            30,
            $variant->id
        );

        return new RoutePreviewResult(
            $session->id,
            $session->route_id,
            'google',
            $session->generated_geometry,
            $comparison,
            $quality,
            $session->expires_at->toIso8601String(),
            $session->route_variant_id
        );
    }

    public function acceptPreview(string $sessionId, RouteVariant $variant, int $clientVersion, ?int $userId = null): RouteVariant
    {
        $session = RouteGenerationSession::where('id', $sessionId)
            ->where('route_variant_id', $variant->id)
            ->where('status', 'pending')
            ->firstOrFail();
        if ($session->isExpired()) {
            throw new \InvalidArgumentException('RouteVariant geometry preview session has expired.');
        }

        return DB::transaction(function () use ($session, $variant, $clientVersion, $userId) {
            $locked = RouteVariant::lockForUpdate()->findOrFail($variant->id);
            if ((int) $locked->geometry_version !== $clientVersion) {
                throw new GeometryConflictException("RouteVariant geometry was modified by another user. Current version: {$locked->geometry_version}.");
            }
            $previous = $locked->polyline_coordinates ?: [];
            $previousPolyline = Polyline::fromArray($previous);
            RouteVariantGeometryVersion::create([
                'route_variant_id' => $locked->id,
                'polyline_coordinates' => $previous,
                'vertex_count' => $previousPolyline->count(),
                'length_km' => $previousPolyline->getLengthKm(),
                'label' => 'Auto Snapshot',
                'created_by_user_id' => $userId,
            ]);
            $locked->update([
                'polyline_coordinates' => $session->generated_geometry,
                'geometry_version' => ((int) $locked->geometry_version) + 1,
                'geometry_status' => 'authoritative',
            ]);
            $session->update(['status' => 'accepted']);
            $session->delete();
            return $locked->fresh();
        });
    }

    public function rejectPreview(string $sessionId, int $variantId): void
    {
        RouteGenerationSession::where('id', $sessionId)
            ->where('route_variant_id', $variantId)
            ->where('status', 'pending')
            ->update(['status' => 'rejected']);
    }
}
