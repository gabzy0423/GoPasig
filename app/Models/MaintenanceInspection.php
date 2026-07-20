<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Represents a single inspection attempt within a maintenance ticket.
 * A maintenance ticket may have multiple inspection attempts before passing.
 * Each attempt is recorded here with its own result, cost, checklist, and notes.
 *
 * Business Rules:
 *  - attempt_no is sequential per maintenance_record_id (1, 2, 3, ...)
 *  - roadworthy and inspection_passed are derived from maintenance_result (never client-supplied)
 *  - Only Passed Inspection / Passed with Observation transitions the ticket to completed
 *  - Failed Inspection keeps the ticket in_progress for re-inspection
 */
class MaintenanceInspection extends Model
{
    use HasFactory;

    protected $table = 'maintenance_inspections';

    protected $fillable = [
        'maintenance_record_id',
        'attempt_no',
        'inspector_name',
        'bus_condition',
        'maintenance_result',
        'roadworthy',
        'inspection_passed',
        'inspection_checklist',
        'parts_replaced',
        'labor_cost',
        'parts_cost',
        'other_cost',
        'cost_php',
        'technician_notes',
        'recommendation',
        'inspected_at',
    ];

    protected $casts = [
        'roadworthy'           => 'boolean',
        'inspection_passed'    => 'boolean',
        'inspection_checklist' => 'array',
        'labor_cost'           => 'float',
        'parts_cost'           => 'float',
        'other_cost'           => 'float',
        'cost_php'             => 'float',
        'inspected_at'         => 'datetime',
    ];

    /**
     * Get the parent maintenance record.
     */
    public function maintenanceRecord()
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }

    /**
     * Check if this inspection passed.
     */
    public function passed(): bool
    {
        return $this->inspection_passed === true;
    }

    /**
     * Check if this inspection failed.
     */
    public function failed(): bool
    {
        return $this->inspection_passed === false;
    }

    /**
     * Get a display label for the result.
     */
    public function getResultBadgeClass(): string
    {
        return match($this->maintenance_result) {
            'Passed Inspection'       => 'bg-emerald-50 text-emerald-700 border-emerald-100',
            'Passed with Observation' => 'bg-amber-50 text-amber-700 border-amber-100',
            'Failed Inspection'       => 'bg-red-50 text-red-700 border-red-100',
            default                   => 'bg-slate-100 text-slate-600 border-slate-200',
        };
    }

    /**
     * Scope: only passed inspections.
     */
    public function scopePassed($query)
    {
        return $query->where('inspection_passed', true);
    }

    /**
     * Scope: only failed inspections.
     */
    public function scopeFailed($query)
    {
        return $query->where('inspection_passed', false);
    }
}
