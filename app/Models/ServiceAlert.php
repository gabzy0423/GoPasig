<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceAlert extends Model
{
    use HasFactory;

    protected $table = 'service_alerts';

    protected $fillable = [
        'route_id',
        'title',
        'message',
        'severity',
        'status',
        'type',
        'affected_routes',
        'estimated_resumption',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function reads()
    {
        return $this->hasMany(ServiceAlertRead::class, 'service_alert_id');
    }
}
