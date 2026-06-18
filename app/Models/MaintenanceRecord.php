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
        'workflow_status',
        'expected_duration_minutes',
        'technician_name',
        'cost_php',
        'completed_at',
        'technician_notes',
        'actual_duration_minutes',
        'inspection_passed',
        'inspection_notes',
        'inspected_by',
        'inspected_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'inspected_at' => 'datetime',
        'cost_php' => 'float',
        'expected_duration_minutes' => 'integer',
        'actual_duration_minutes' => 'integer',
        'inspection_passed' => 'boolean',
    ];

    /**
     * Get the associated bus.
     */
    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    /**
     * Get all inspections for this maintenance record
     */
    public function inspections()
    {
        return $this->hasMany(MaintenanceInspection::class);
    }

    /**
     * Accessor to return the bus plate number when bus_id is accessed.
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
        if ($this->relationLoaded('bus') && $this->bus) {
            $route = $this->bus->route;
            return $route ? $route->name : null;
        }
        
        $bus = Bus::with('route')->find($this->getRawOriginal('bus_id'));
        return $bus && $bus->route ? $bus->route->name : null;
    }

    /**
     * Get the latest inspection
     */
    public function getLatestInspection()
    {
        return $this->inspections()->latest('performed_at')->first();
    }

    /**
     * Check if maintenance can be completed
     * SIMPLE APPROACH: Just check inspection_passed flag
     */
    public function canBeCompleted(): bool
    {
        return $this->inspection_passed === true;
    }

    /**
     * Check if inspection has been performed
     */
    public function hasBeenInspected(): bool
    {
        return $this->inspected_at !== null;
    }

    /**
     * Check if inspection passed
     */
    public function inspectionPassed(): bool
    {
        return $this->inspection_passed === true;
    }

    /**
     * Check if inspection failed
     */
    public function inspectionFailed(): bool
    {
        return $this->inspection_passed === false;
    }

    /**
     * Get inspection status label for display
     */
    public function getInspectionStatus(): string
    {
        if (!$this->hasBeenInspected()) {
            return 'Not Yet Inspected';
        }
        return $this->inspectionPassed() ? 'Passed ✅' : 'Failed ❌';
    }

    /**
     * Get workflow progress as percentage
     */
    public function getProgressPercentage(): int
    {
        if ($this->status === 'cancelled') {
            return 0;
        }

        if ($this->status === 'completed') {
            return 100;
        }

        if ($this->status === 'scheduled') {
            return 20;
        }

        if ($this->status === 'in_progress') {
            if ($this->hasBeenInspected()) {
                return $this->inspectionPassed() ? 80 : 50;
            }
            return 40;
        }

        return 0;
    }

    /**
     * Scope: Get only pending maintenance
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', ['scheduled', 'in_progress']);
    }

    /**
     * Scope: Get only completed maintenance
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Get only maintenance awaiting inspection
     */
    public function scopeAwaitingInspection($query)
    {
        return $query->where('status', 'in_progress')
            ->where('inspection_passed', false)
            ->orWhere('inspected_at', null);
    }
}
