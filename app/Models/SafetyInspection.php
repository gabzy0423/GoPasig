<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SafetyInspection extends Model
{
    use HasFactory;

    protected $fillable = [
        'bus_id',
        'driver_id',
        'oil_ok',
        'brakes_ok',
        'ac_ok',
        'lights_ok',
        'tires_ok',
        'status',
        'notes',
    ];

    protected $casts = [
        'oil_ok' => 'boolean',
        'brakes_ok' => 'boolean',
        'ac_ok' => 'boolean',
        'lights_ok' => 'boolean',
        'tires_ok' => 'boolean',
    ];

    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }
}
