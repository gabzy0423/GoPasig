<?php

namespace App\Services\Routing;

use App\Models\RouteGenerationSession;
use App\Services\ValueObjects\Polyline;

class RouteGenerationSessionService
{
    /**
     * Find an active (non-expired, pending) session for the given route and provider.
     */
    public function findActiveSession(int $routeId, string $provider, ?int $routeVariantId = null): ?RouteGenerationSession
    {
        return RouteGenerationSession::where('route_id', $routeId)
            ->where('provider', $provider)
            ->where('status', 'pending')
            ->when($routeVariantId !== null, fn ($query) => $query->where('route_variant_id', $routeVariantId), fn ($query) => $query->whereNull('route_variant_id'))
            ->where('expires_at', '>', now())
            ->first();
    }

    /**
     * Create a new generation session.
     */
    public function createSession(
        int $routeId,
        string $provider,
        Polyline $polyline,
        array $metrics,
        ?int $userId = null,
        int $ttlMinutes = 30,
        ?int $routeVariantId = null
    ): RouteGenerationSession {
        // Expire any existing pending sessions for this route and provider first to avoid leftovers
        RouteGenerationSession::where('route_id', $routeId)
            ->where('provider', $provider)
            ->where('status', 'pending')
            ->when($routeVariantId !== null, fn ($query) => $query->where('route_variant_id', $routeVariantId), fn ($query) => $query->whereNull('route_variant_id'))
            ->update(['status' => 'rejected']);

        return RouteGenerationSession::create([
            'route_id' => $routeId,
            'route_variant_id' => $routeVariantId,
            'provider' => $provider,
            'generated_geometry' => $polyline->toArray(),
            'comparison_metrics' => $metrics,
            'status' => 'pending',
            'expires_at' => now()->addMinutes($ttlMinutes),
            'created_by_user_id' => $userId,
        ]);
    }

    /**
     * Retrieve a session by its UUID.
     */
    public function getSession(string $sessionId): ?RouteGenerationSession
    {
        return RouteGenerationSession::find($sessionId);
    }

    /**
     * Delete/remove a session.
     */
    public function deleteSession(string $sessionId): void
    {
        RouteGenerationSession::where('id', $sessionId)->delete();
    }

    /**
     * Prune expired sessions.
     */
    public function pruneExpired(): void
    {
        RouteGenerationSession::where('expires_at', '<=', now())->delete();
    }
}
