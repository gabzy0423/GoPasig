<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ColorPalette extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hex_color',
        'order',
        'description',
        'usage',
    ];

    /**
     * Get ordered array ng hex colors para sa charts/analytics
     */
    public static function getColors($usage = null)
    {
        $query = self::orderBy('order', 'asc');

        if ($usage) {
            $query->where('usage', $usage);
        }

        $colors = $query->pluck('hex_color')->toArray();
        if (empty($colors)) {
            return ['#003F87', '#3B6D11', '#854F0B', '#6B21A8', '#0F6E56', '#DC2626', '#0891B2', '#D97706'];
        }
        return $colors;
    }

    /**
     * Get full color palette array with names
     */
    public static function getPaletteArray($usage = null)
    {
        $query = self::orderBy('order', 'asc');

        if ($usage) {
            $query->where('usage', $usage);
        }

        return $query->get()->map(fn($color) => [
            'color' => $color->hex_color,
            'name' => $color->name,
            'description' => $color->description,
        ])->toArray();
    }

    /**
     * Get single color by name
     */
    public static function getByName($name)
    {
        return self::where('name', $name)->first()?->hex_color;
    }
}

