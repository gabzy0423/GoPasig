<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceRecord extends Model
{
    use HasFactory;

    protected $table = 'maintenance_records';

    protected $fillable = [
        'bus_id',
        'type',
        'description',
        'scheduled_at',
        'status',
        'technician_name',
        'cost_php',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'cost_php' => 'float',
    ];

    /**
     * Get the associated bus.
     */
    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    /**
     * Accessor to return the bus plate number when bus_id is accessed.
     * This ensures compatibility with existing mock view template.
     */
    public function getBusIdAttribute($value)
    {
        if ($this->relationLoaded('bus') && $this->bus) {
            return $this->bus->plate_number;
        }
        $bus = Bus::find($value);
        return $bus ? $bus->plate_number : $value;
    }

    /**
     * Accessor for maintenance date (maps to scheduled_at).
     */
    public function getMaintenanceDateAttribute()
    {
        return $this->scheduled_at;
    }

    /**
     * Accessor for assigned route.
     */
    public function getAssignedRouteAttribute()
    {
        // Join/relationship lookup to get the route name
        if ($this->relationLoaded('bus') && $this->bus) {
            $route = $this->bus->route;
            return $route ? $route->name : null;
        }
        
        // Find bus and get route name
        $bus = Bus::with('route')->find($this->getRawOriginal('bus_id'));
        return $bus && $bus->route ? $bus->route->name : null;
    }
}
