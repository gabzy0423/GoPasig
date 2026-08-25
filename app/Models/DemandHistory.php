<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class DemandHistory extends Model
{
    use HasFactory;

    public const SOURCE_LEGACY_UNKNOWN = 'legacy_unknown';
    public const SOURCE_ACTUAL_RUNTIME = 'actual_runtime';
    public const SOURCE_ACTUAL_REBUILD = 'actual_rebuild';

    protected $table = 'demand_history';

    protected $attributes = [
        'source' => self::SOURCE_LEGACY_UNKNOWN,
        'is_training_eligible' => false,
    ];

    protected $fillable = [
        'route_id',
        'route_variant_id',
        'date',
        'time_slot',
        'day_of_week',
        'total_commuters',
        'buses_dispatched',
        'source',
        'is_training_eligible',
        'finalized_at',
    ];

    protected $casts = [
        'date' => 'date',
        'total_commuters' => 'integer',
        'buses_dispatched' => 'integer',
        'is_training_eligible' => 'boolean',
        'finalized_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (DemandHistory $history): void {
            if (! $history->route_variant_id) {
                $history->source = self::SOURCE_LEGACY_UNKNOWN;
                $history->is_training_eligible = false;
                $history->finalized_at = null;

                return;
            }

            $variant = $history->routeVariant ?: RouteVariant::find($history->route_variant_id);

            if (! $variant || (int) $variant->route_id !== (int) $history->route_id) {
                throw new InvalidArgumentException(
                    'Demand history RouteVariant must belong to the selected Route.'
                );
            }

            if ($history->source === self::SOURCE_LEGACY_UNKNOWN) {
                $history->is_training_eligible = false;
            }

            if ($history->is_training_eligible && ! $history->finalized_at) {
                throw new InvalidArgumentException(
                    'Training-eligible demand history must be finalized.'
                );
            }
        });
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function routeVariant()
    {
        return $this->belongsTo(RouteVariant::class);
    }

    public function scopeDirectionAware($query)
    {
        return $query->whereNotNull('route_variant_id');
    }

    public function scopeForecastEligible($query)
    {
        return $query
            ->directionAware()
            ->where('is_training_eligible', true)
            ->whereNotNull('finalized_at');
    }

    public function scopeFinalizedActual($query)
    {
        return $query
            ->directionAware()
            ->where('source', self::SOURCE_ACTUAL_REBUILD)
            ->whereNotNull('finalized_at');
    }
}
