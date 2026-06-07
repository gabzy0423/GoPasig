<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Incident extends Model
{
    use HasFactory;

    protected $table = 'incidents';

    const TYPES = [
        'Breakdown',
        'Accident',
        'Heavy Traffic Delay',
        'Passenger Concern'
    ];

    const TYPES_METADATA = [
        'Breakdown' => [
            'icon' => 'ti-tool',
            'active_class' => 'bg-rose-50/55 border-rose-500 text-rose-700',
            'icon_active' => 'text-rose-500',
        ],
        'Accident' => [
            'icon' => 'ti-alert-triangle',
            'active_class' => 'bg-amber-50/55 border-amber-500 text-amber-700',
            'icon_active' => 'text-amber-500',
        ],
        'Heavy Traffic Delay' => [
            'icon' => 'ti-hourglass-high',
            'active_class' => 'bg-orange-50/55 border-orange-500 text-orange-700',
            'icon_active' => 'text-orange-500',
        ],
        'Passenger Concern' => [
            'icon' => 'ti-help-circle',
            'active_class' => 'bg-blue-50/55 border-blue-500 text-blue-700',
            'icon_active' => 'text-blue-500',
        ]
    ];

    protected $fillable = [
        'trip_id',
        'driver_id',
        'type',
        'description',
        'status',
        'reported_at',
    ];

    protected $casts = [
        'reported_at' => 'datetime',
    ];

    /**
     * Get the trip associated with the incident.
     */
    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * Get the driver associated with the incident.
     */
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Accessor for dynamic title (Bus plate + Type + Route)
     */
    public function getTitleAttribute()
    {
        $busPlate = $this->trip && $this->trip->bus ? $this->trip->bus->plate_number : 'Unknown Bus';
        $routeName = $this->trip && $this->trip->route ? $this->trip->route->name : 'Unknown Route';
        return "Bus {$busPlate} " . strtolower($this->type) . " — {$routeName}";
    }

    /**
     * Accessor for formatted incident ID code (e.g. INC-0001)
     */
    public function getIncidentIdAttribute()
    {
        return 'INC-' . str_pad($this->id, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Accessor for bus plate number
     */
    public function getBusPlateAttribute()
    {
        return $this->trip && $this->trip->bus ? $this->trip->bus->plate_number : 'N/A';
    }

    /**
     * Accessor for driver's full name
     */
    public function getDriverNameAttribute()
    {
        return $this->driver ? "{$this->driver->first_name} {$this->driver->last_name}" : 'Unknown Driver';
    }

    /**
     * Accessor for route name
     */
    public function getRouteNameAttribute()
    {
        return $this->trip && $this->trip->route ? $this->trip->route->name : 'N/A';
    }
}
