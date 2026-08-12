<?php

namespace App\Services;

use App\Models\CommuterTrip;
use App\Models\Route;
use App\Models\RouteVariant;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

class ReactiveDispatchDecisionService
{
    public function __construct(
        private readonly RouteServiceScheduleEvaluator $scheduleEvaluator
    ) {}

    /**
     * Evaluate the production-safe prerequisites for a reactive dispatch.
     *
     * This intentionally does not board commuters or start the driver trip.
     * Those transitions remain owned by the commuter and driver workflows.
     */
    public function evaluate(
        Route $route,
        RouteVariant $variant,
        ?CarbonInterface $at = null
    ): array {
        $at = $this->manila($at ?? Carbon::now('Asia/Manila'));

        if ((int) $variant->route_id !== (int) $route->id) {
            return $this->denied('The selected direction does not belong to this route.', $at);
        }

        if (! Route::publicCommuterActiveService()->whereKey($route->id)->exists()) {
            return $this->denied('Only active official production routes can receive reactive dispatch.', $at);
        }

        $window = $this->scheduleEvaluator->currentWindowForVariant($variant, $at);

        return [
            'allowed' => $window !== null,
            'reason' => $window
                ? 'Official operating window is active.'
                : $this->closedWindowReason($variant, $at),
            'route_id' => (int) $route->id,
            'route_variant_id' => (int) $variant->id,
            'direction' => $variant->direction,
            'waiting_count' => $this->realWaitingCount($route, $variant, $at),
            'current_window' => $window,
            'evaluated_at' => $at,
        ];
    }

    public function assertCanDispatch(
        Route $route,
        RouteVariant $variant,
        ?CarbonInterface $at = null
    ): array {
        $decision = $this->evaluate($route, $variant, $at);

        if (! $decision['allowed']) {
            throw ValidationException::withMessages([
                'route_variant_id' => $decision['reason'],
            ]);
        }

        return $decision;
    }

    private function realWaitingCount(Route $route, RouteVariant $variant, CarbonInterface $at): int
    {
        $sessionCutoff = Carbon::instance($at)->copy()->utc();

        return CommuterTrip::query()
            ->where('route_id', $route->id)
            ->where('route_variant_id', $variant->id)
            ->where('status', 'WAITING')
            ->where('is_simulated', false)
            ->whereHas('session', fn ($query) => $query->where('expires_at', '>', $sessionCutoff))
            ->count();
    }

    private function closedWindowReason(RouteVariant $variant, CarbonInterface $at): string
    {
        $nextWindow = $this->scheduleEvaluator->nextWindowForVariant($variant, $at);

        if ($nextWindow) {
            return sprintf(
                'The %s direction is outside its official operating window. Next window starts at %s.',
                $variant->direction,
                substr((string) $nextWindow->first_trip_time, 0, 5)
            );
        }

        return sprintf(
            'The %s direction is outside its official operating window.',
            $variant->direction
        );
    }

    private function denied(string $reason, CarbonInterface $at): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'route_id' => null,
            'route_variant_id' => null,
            'direction' => null,
            'waiting_count' => 0,
            'current_window' => null,
            'evaluated_at' => $at,
        ];
    }

    private function manila(CarbonInterface $at): Carbon
    {
        return Carbon::instance($at)->copy()->timezone('Asia/Manila');
    }
}
