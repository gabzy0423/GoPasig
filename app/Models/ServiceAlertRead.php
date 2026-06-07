<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceAlertRead extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_alert_id',
        'user_id',
        'session_id',
        'read_at'
    ];

    protected $casts = [
        'read_at' => 'datetime'
    ];

    public function serviceAlert()
    {
        return $this->belongsTo(ServiceAlert::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}