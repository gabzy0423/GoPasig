<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Bus;
use App\Models\BusStatusAuditLog;
use App\Models\Driver;
use App\Models\Incident;
use App\Models\Route;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class IncidentWorkflowService
{
    private const ALLOWED_STATUSES = ['reported', 'under_review', 'resolved'];

    public function eligibleOngoingTripsQuery(): Builder
    {
        return Trip::query()
            ->where('status', 'ongoing')
            ->whereNotNull('bus_id')
            ->whereNotNull('driver_id')
            ->whereHas('route', fn (Builder $query) => $query->publicCommuterActiveService());
    }

    public function reportForTrip(
        int $tripId,
        string $type,
        string $description,
        string $source,
        ?int $actorUserId = null,
        ?int $expectedDriverId = null,
        ?int $expectedBusId = null
    ): Incident {
        $type = trim($type);
        $description = trim($description);

        if (! in_array($type, Incident::getTypes(), true)) {
            throw new \DomainException('Select a valid incident type.');
        }

        if (mb_strlen($description) < 5 || mb_strlen($description) > 2000) {
            throw new \DomainException('Incident description must be between 5 and 2000 characters.');
        }

        $snapshot = Trip::query()->find($tripId);
        if (! $snapshot || ! $snapshot->bus_id || ! $snapshot->driver_id) {
            throw new \DomainException('The selected trip is not eligible for incident reporting.');
        }

        return DB::transaction(function () use (
            $snapshot,
            $tripId,
            $type,
            $description,
            $source,
            $actorUserId,
            $expectedDriverId,
            $expectedBusId
        ) {
            // Keep this lock order aligned with passenger and bus-state mutations.
            $bus = Bus::query()->whereKey($snapshot->bus_id)->lockForUpdate()->first();
            $driver = Driver::query()->whereKey($snapshot->driver_id)->lockForUpdate()->first();
            $trip = Trip::query()->whereKey($tripId)->lockForUpdate()->first();

            if (! $trip || ! $bus || ! $driver) {
                throw new \DomainException('The trip, bus, or driver is no longer available.');
            }

            if ($trip->status !== 'ongoing'
                || (int) $trip->bus_id !== (int) $bus->id
                || (int) $trip->driver_id !== (int) $driver->id) {
                throw new \DomainException('Incidents can only be reported for the current ongoing trip.');
            }

            if (($expectedDriverId !== null && (int) $driver->id !== $expectedDriverId)
                || ($expectedBusId !== null && (int) $bus->id !== $expectedBusId)) {
                throw new \DomainException('The selected trip does not match the assigned driver and bus.');
            }

            $officialRouteExists = Route::query()
                ->publicCommuterActiveService()
                ->whereKey($trip->route_id)
                ->exists();

            if (! $officialRouteExists) {
                throw new \DomainException('Incidents can only be reported for an official active route.');
            }

            $incident = Incident::query()->create([
                'trip_id' => $trip->id,
                'driver_id' => $driver->id,
                'type' => $type,
                'description' => $description,
                'status' => 'reported',
                'reported_at' => now(),
            ]);

            $reason = 'Incident Report: '.$type;
            if (Incident::isBreakdown($type) || Incident::isAccident($type)) {
                BusStateService::transition($bus, Bus::STATUS_BREAKDOWN, $reason);
            } else {
                BusStatusAuditLog::logStatusChange(
                    busId: $bus->id,
                    newStatus: $bus->status,
                    oldStatus: $bus->status,
                    userId: $actorUserId,
                    reason: $reason,
                    metadata: ['incident_id' => $incident->id, 'source' => $source]
                );
            }

            ActivityLog::query()->create([
                'type' => 'Incident',
                'description' => sprintf('Reported via %s: %s - %s', $source, $bus->plate_number, $type),
                'user_id' => $actorUserId,
            ]);

            return $incident->fresh(['trip.bus', 'trip.route', 'driver']);
        }, 3);
    }

    public function updateStatus(
        int $incidentId,
        string $status,
        string $source,
        ?int $actorUserId = null
    ): Incident {
        if (! in_array($status, self::ALLOWED_STATUSES, true)) {
            throw new \DomainException('Select a valid incident status.');
        }

        return DB::transaction(function () use ($incidentId, $status, $source, $actorUserId) {
            $incident = Incident::query()->whereKey($incidentId)->lockForUpdate()->first();
            if (! $incident) {
                throw new \DomainException('Incident record not found.');
            }

            $oldStatus = $incident->status;
            if ($oldStatus !== $status) {
                $incident->update(['status' => $status]);

                ActivityLog::query()->create([
                    'type' => 'Incident',
                    'description' => sprintf(
                        'Incident %s changed from %s to %s via %s',
                        $incident->incident_id,
                        $oldStatus,
                        $status,
                        $source
                    ),
                    'user_id' => $actorUserId,
                ]);
            }

            // Resolution closes the record only. Maintenance owns breakdown recovery.
            return $incident->fresh(['trip.bus', 'trip.route', 'driver']);
        }, 3);
    }
}
