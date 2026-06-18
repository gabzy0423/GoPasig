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
        'suspend_route',
    ];

    /**
     * Scope to return active and currently visible alerts (excluding future scheduled alerts)
     */
    public function scopeActiveAlerts($query)
    {
        return $query->where('status', 'active')->where('created_at', '<=', now());
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function reads()
    {
        return $this->hasMany(ServiceAlertRead::class, 'service_alert_id');
    }
}
