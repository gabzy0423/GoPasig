<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_BREAKDOWN = 'breakdown';
    public const STATUS_MAINTENANCE = 'maintenance';

    /**
     * Normal commuter-visible runtime states. Breakdown and maintenance are
     * incident states, not available-service candidates.
     */
    public static function commuterServiceStatuses(): array
    {
        return [self::STATUS_ACTIVE, 'operating'];
    }

    // -------------------------------------------------------------------------
    // All operational thresholds are stored in system_settings and accessed via
    // the static getter methods below (e.g. Bus::getDelayThreshold()).
    // PHP class constants have been intentionally removed to ensure a single
    // source of truth — the database — for every configurable value.
    // -------------------------------------------------------------------------

    /** Sentinel value stored in driver_name when no driver is assigned. */
    public const DEFAULT_DRIVER_NAME = 'Unassigned';

    /** Sentinel value stored in next_stop when the bus has no upcoming stop. */
    public const DEFAULT_NEXT_STOP = 'None';

    /**
     * Return the bus capacity, falling back to the system_settings value
     * (key: default_bus_capacity, default: 45) when the column is null.
     */
    public function getCapacityAttribute($value)
    {
        return $value ?: self::getDefaultCapacity();
    }

    /**
     * System-wide default bus capacity (seats).
     * Reads from system_settings key `default_bus_capacity` (default: 45).
     * Use this instead of raw SystemSetting::get() calls so the fallback
     * value is defined in exactly one place.
     */
    public static function getDefaultCapacity(): int
    {
        return (int) SystemSetting::get('default_bus_capacity', 45);
    }

    public static function getDefaultDriverName(): string
    {
        return (string) SystemSetting::get('bus_default_driver_name', self::DEFAULT_DRIVER_NAME);
    }

    public static function getDefaultNextStop(): string
    {
        return (string) SystemSetting::get('bus_default_next_stop', self::DEFAULT_NEXT_STOP);
    }

    public static function getInitialSpeed(): int
    {
        return (int) SystemSetting::get('bus_initial_speed', 0);
    }

    public static function getInitialPassengers(): int
    {
        return (int) SystemSetting::get('bus_initial_passengers', 0);
    }

    public static function getInitialEta(): int
    {
        return (int) SystemSetting::get('bus_initial_eta', 0);
    }

    public function getRouteDelayThreshold()
    {
        if ($this->route && isset($this->route->delay_threshold_minutes)) {
            return (int) $this->route->delay_threshold_minutes;
        }
        return self::getDelayThreshold();
    }

    public function getMinSpeed()
    {
        if ($this->route && isset($this->route->min_speed)) {
            return (int) $this->route->min_speed;
        }
        return self::getSimSpeedMin();
    }

    public function getMaxSpeed()
    {
        if ($this->route && isset($this->route->max_speed)) {
            return (int) $this->route->max_speed;
        }
        return self::getSimSpeedMax();
    }

    public static function getDelayThreshold()
    {
        return (int) SystemSetting::get('delay_threshold', 10);
    }

    public static function getOccupancyWarningThreshold()
    {
        return (int) SystemSetting::get('occupancy_warning_threshold', 50);
    }

    public static function getOccupancyCriticalThreshold()
    {
        return (int) SystemSetting::get('occupancy_critical_threshold', 85);
    }

    public static function getGpsSyncIntervalMs()
    {
        return (int) SystemSetting::get('gps_sync_interval_ms', 5000);
    }

    public static function getSpeedSimulationIntervalMs()
    {
        return (int) SystemSetting::get('speed_simulation_interval_ms', 1500);
    }

    public static function getSimSpeedMin()
    {
        return (int) SystemSetting::get('sim_speed_min', 18);
    }

    public static function getSimSpeedMax()
    {
        return (int) SystemSetting::get('sim_speed_max', 43);
    }

    public static function getSpeedFastThreshold()
    {
        return (int) SystemSetting::get('speed_fast_threshold', 30);
    }

    protected $fillable = [
        'plate_number',
        'fleet_number',
        'vin',
        'manufacturer',
        'model',
        'year_model',
        'battery_capacity_kwh',
        'charging_port_type',
        'max_charging_power_kw',
        'purchase_date',
        'supplier',
        'warranty_expiry',
        'serial_number',
        'acquisition_cost',
        'route_id',
        'driver_name',
        'capacity',
        'speed',
        'passengers',
        'next_stop',
        'eta',
        'lat',
        'lng',
        'status',
        'previous_status',
        'is_simulated',
        'has_observation'
    ];

    /**
     * Lock bus to maintenance status, preserving the prior status for restoration.
     * Idempotent — safe to call multiple times; previous_status is only overwritten
     * when the bus is not already in maintenance, preventing self-referential state.
     */
    public function lockToMaintenance(): void
    {
        \App\Services\BusStateService::transition($this, self::STATUS_MAINTENANCE, 'Lock to maintenance');
    }

    public function syncObservationStatus(): void
    {
        $latestCompleted = $this->maintenanceRecords()
            ->where('status', 'completed')
            ->orderBy('completed_at', 'desc')
            ->first();

        $hasObs = $latestCompleted && $latestCompleted->maintenance_result === 'Passed with Observation';

        if ($this->has_observation !== $hasObs) {
            $this->update(['has_observation' => $hasObs]);
        }
    }

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
        'speed' => 'float',
        'has_observation' => 'boolean',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Get the maintenance records for this bus.
     */
    public function maintenanceRecords()
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /**
     * Get the trips for this bus.
     */
    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * Get the live vehicle position record for this bus.
     */
    public function vehiclePosition()
    {
        return $this->hasOne(VehiclePosition::class);
    }
}


