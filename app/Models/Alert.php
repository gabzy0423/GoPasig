<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Alert extends Model
{
    use HasFactory;

    protected $fillable = [
        'stop_id',
        'minutes_before',
        'status',
    ];

    public function stop()
    {
        return $this->belongsTo(Stop::class);
    }
}
