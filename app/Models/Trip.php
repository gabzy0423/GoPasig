<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Trip extends Model
{
    use HasFactory;

    protected $table = 'trips';

    protected $fillable = [
        'bus_id',
        'driver_id',
        'route_id',
        'status',
        'started_at',
        'ended_at',
    ];

    /**
     * Get the bus assigned to this trip.
     */
    public function bus()
    {
        return $this->belongsTo(Bus::class);
    }

    /**
     * Get the driver assigned to this trip.
     */
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Get the route assigned to this trip.
     */
    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Get the incidents associated with this trip.
     */
    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }
}
