<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DispatchAlertSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'yellow_percentage',
        'red_percentage',
        'description',
    ];

    /**
     * Return percentages for use by controllers. If no active setting, return null.
     */
    public static function getPercentages()
    {
        $rec = self::latest()->first();
        if (!$rec) return null;
        return [
            'yellow' => (int) $rec->yellow_percentage,
            'red' => (int) $rec->red_percentage,
        ];
    }
}
