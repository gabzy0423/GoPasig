<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PassengerRating extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'schedule_id',
        'rating',
        'comments',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }
}
