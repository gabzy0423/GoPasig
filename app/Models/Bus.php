<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

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
        return (int) SystemSetting::get('gps_sync_interval_ms', 6000);
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
        'is_simulated'
    ];

    /**
     * Lock bus to maintenance status, preserving the prior status for restoration.
     * Idempotent — safe to call multiple times; previous_status is only overwritten
     * when the bus is not already in maintenance, preventing self-referential state.
     */
    public function lockToMaintenance(): void
    {
        $this->update([
            'previous_status' => $this->status === 'maintenance'
                ? $this->previous_status
                : $this->status,
            'status' => 'maintenance',
        ]);
    }

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
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
}
