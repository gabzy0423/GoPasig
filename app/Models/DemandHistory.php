<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandHistory extends Model
{
    use HasFactory;

    protected $table = 'demand_history';

    protected $fillable = [
        'route_id',
        'date',
        'time_slot',
        'day_of_week',
        'total_commuters',
        'buses_dispatched',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }
}
