<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DispatchLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'trip_id',
        'dispatched_by',
        'dispatched_at',
        'notes',
    ];

    protected $casts = [
        'dispatched_at' => 'datetime',
    ];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function dispatcher()
    {
        return $this->belongsTo(User::class, 'dispatched_by');
    }
}
