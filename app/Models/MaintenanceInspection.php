<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MaintenanceInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'maintenance_record_id',
        'inspector_id',
        'engine_ok',
        'brakes_ok',
        'steering_ok',
        'suspension_ok',
        'electrical_ok',
        'interior_ok',
        'lights_ok',
        'tires_ok',
        'overall_status',
        'notes',
        'photos_path',
        'performed_at',
    ];

    protected $casts = [
        'engine_ok' => 'boolean',
        'brakes_ok' => 'boolean',
        'steering_ok' => 'boolean',
        'suspension_ok' => 'boolean',
        'electrical_ok' => 'boolean',
        'interior_ok' => 'boolean',
        'lights_ok' => 'boolean',
        'tires_ok' => 'boolean',
        'performed_at' => 'datetime',
    ];

    /**
     * Get the maintenance record
     */
    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    /**
     * Get the inspector (user who performed the inspection)
     */
    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    /**
     * Check if all critical systems passed
     * Critical: Engine, Brakes, Steering
     */
    public function allCriticalSystemsPassed(): bool
    {
        return $this->engine_ok && $this->brakes_ok && $this->steering_ok;
    }

    /**
     * Get count of systems that failed
     */
    public function failedSystemsCount(): int
    {
        $count = 0;
        $systems = [
            'engine_ok', 'brakes_ok', 'steering_ok', 'suspension_ok',
            'electrical_ok', 'interior_ok', 'lights_ok', 'tires_ok'
        ];

        foreach ($systems as $system) {
            if (!$this->$system) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get list of failed systems
     */
    public function getFailedSystems(): array
    {
        $systems = [];
        $systemMap = [
            'engine_ok' => 'Engine',
            'brakes_ok' => 'Brakes',
            'steering_ok' => 'Steering',
            'suspension_ok' => 'Suspension',
            'electrical_ok' => 'Electrical',
            'interior_ok' => 'Interior',
            'lights_ok' => 'Lights',
            'tires_ok' => 'Tires',
        ];

        foreach ($systemMap as $field => $label) {
            if (!$this->$field) {
                $systems[] = $label;
            }
        }

        return $systems;
    }

    /**
     * Scope: Get only passed inspections
     */
    public function scopePassed($query)
    {
        return $query->where('overall_status', 'passed');
    }

    /**
     * Scope: Get only failed inspections
     */
    public function scopeFailed($query)
    {
        return $query->where('overall_status', 'failed');
    }
}
