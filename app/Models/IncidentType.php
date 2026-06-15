<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class IncidentType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'severity_level',
        'triggers_maintenance',
        'is_active',
    ];

    /**
     * Get a map of incident type names to severity levels.
     */
    public static function getSeverityMap(): array
    {
        return self::where('is_active', true)
            ->pluck('severity_level', 'name')
            ->toArray();
    }
}
