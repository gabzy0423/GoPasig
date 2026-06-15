<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CommuterSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_token',
        'ip_address',
        'expires_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function trips()
    {
        return $this->hasMany(CommuterTrip::class, 'session_token', 'session_token');
    }
}
