<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceAlert extends Model
{
    use HasFactory, SoftDeletes;

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


    public function scopePublicCommuterVisible($query)
    {
        $canonicalRouteIds = Route::publicCommuterVisible()->pluck('id')->all();
        $canonicalRouteNames = Route::canonicalProductionNames();

        return $query->where(function ($visibility) use ($canonicalRouteIds, $canonicalRouteNames) {
            $visibility->whereIn('route_id', $canonicalRouteIds)
                ->orWhere(function ($globalOrNamed) use ($canonicalRouteNames) {
                    $globalOrNamed->whereNull('route_id')
                        ->where(function ($routeText) use ($canonicalRouteNames) {
                            $routeText->whereNull('affected_routes')
                                ->orWhere('affected_routes', '')
                                ->orWhere('affected_routes', 'All Routes')
                                ->orWhere('affected_routes', 'All routes')
                                ->orWhere('affected_routes', 'All official routes');

                            foreach ($canonicalRouteNames as $routeName) {
                                $routeText->orWhere('affected_routes', 'like', '%' . $routeName . '%');
                            }
                        });
                });
        });
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

