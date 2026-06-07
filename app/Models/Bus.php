<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bus extends Model
{
    use HasFactory;

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
        'status'
    ];

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
