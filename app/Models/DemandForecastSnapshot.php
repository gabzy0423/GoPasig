<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DemandForecastSnapshot extends Model
{
    use HasFactory;

    public const STATUS_READY = 'ready';

    public const STATUS_INSUFFICIENT_HISTORY = 'insufficient_history';

    protected $fillable = [
        'route_id',
        'route_variant_id',
        'target_date',
        'day_of_week',
        'time_slot',
        'direction',
        'direction_label',
        'expected_commuters',
        'sample_count',
        'minimum_samples',
        'confidence',
        'minimum_buses',
        'reference_bus_capacity',
        'forecast_status',
        'forecast_version',
        'advisory_only',
        'captured_at',
        'actual_commuters',
        'actual_source',
        'actual_finalized_at',
        'error_delta',
        'absolute_error',
        'percentage_error',
        'evaluated_at',
    ];

    protected $casts = [
        'target_date' => 'date:Y-m-d',
        'expected_commuters' => 'float',
        'sample_count' => 'integer',
        'minimum_samples' => 'integer',
        'minimum_buses' => 'integer',
        'reference_bus_capacity' => 'integer',
        'advisory_only' => 'boolean',
        'captured_at' => 'datetime',
        'actual_commuters' => 'integer',
        'actual_finalized_at' => 'datetime',
        'error_delta' => 'float',
        'absolute_error' => 'float',
        'percentage_error' => 'float',
        'evaluated_at' => 'datetime',
    ];

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function routeVariant()
    {
        return $this->belongsTo(RouteVariant::class);
    }
}
