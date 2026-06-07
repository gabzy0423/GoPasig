<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DispatchSimulatorCount extends Model
{
    use HasFactory;

    protected $fillable = [
        'route_id',
        'manual_count',
        'day_of_week',
        'time_slot',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }
}
