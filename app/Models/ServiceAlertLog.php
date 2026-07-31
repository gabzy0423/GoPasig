<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceAlertLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'service_alert_id',
        'title',
        'message',
        'type',
        'severity',
        'affected_routes',
        'status',
        'suspend_route',
        'alert_created_at',
        'resolved_at',
        'archived_at',
    ];

    protected $casts = [
        'suspend_route' => 'boolean',
        'alert_created_at' => 'datetime',
        'resolved_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function serviceAlert()
    {
        return $this->belongsTo(ServiceAlert::class)->withTrashed();
    }
}
