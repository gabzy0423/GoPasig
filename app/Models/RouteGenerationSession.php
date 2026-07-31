<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class RouteGenerationSession extends Model
{
    protected $table = 'route_generation_sessions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'route_id',
        'route_variant_id',
        'provider',
        'generated_geometry',
        'comparison_metrics',
        'status',
        'expires_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'generated_geometry' => 'array',
        'comparison_metrics' => 'array',
        'expires_at' => 'datetime',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function routeVariant(): BelongsTo
    {
        return $this->belongsTo(RouteVariant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
