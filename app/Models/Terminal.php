<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Schema;

class Terminal extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'lat',
        'lng',
        'description',
        'is_default',
    ];

    protected $casts = [
        'lat'        => 'float',
        'lng'        => 'float',
        'is_default' => 'boolean',
    ];

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Return the terminal flagged as default, or the first terminal in the
     * table, or null if the table is empty / doesn't exist yet.
     */
    public static function getDefault(): ?self
    {
        if (!Schema::hasTable((new self)->getTable())) {
            return null;
        }

        return static::where('is_default', true)->first()
            ?? static::first();
    }

    /**
     * Return the name of the default terminal.
     *
     * @param  string  $fallback  Returned when no terminal row exists yet.
     */
    public static function getDefaultName(string $fallback = ''): string
    {
        return static::getDefault()?->name ?? $fallback;
    }

    /**
     * Look up a terminal by an exact or partial name match and return its name,
     * falling back to $fallback when no row is found.
     *
     * @param  string  $name      Exact name or partial substring to match.
     * @param  string  $fallback  Returned when the lookup finds no record.
     */
    public static function findByName(string $name, string $fallback = ''): string
    {
        if (!Schema::hasTable((new self)->getTable())) {
            return $fallback;
        }

        $terminal = static::where('name', $name)->first()
            ?? static::where('name', 'like', "%{$name}%")->first();

        return $terminal?->name ?? $fallback;
    }
}
