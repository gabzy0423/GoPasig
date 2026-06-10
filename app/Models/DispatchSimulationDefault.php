<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;

class DispatchSimulationDefault extends Model
{
    use HasFactory;

    protected $table = 'dispatch_simulation_defaults';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];

    public static function getValue(string $key, $default = null)
    {
        if (!Schema::hasTable((new self)->getTable())) {
            return $default;
        }

        $record = static::where('key', $key)->first();
        return $record ? $record->value : $default;
    }
}
