<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TimeSlotConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'time_slot_display',
        'order',
        'is_active',
    ];

    public static function getTimeSlotByHour($hour = null)
    {
        $hour = $hour ?? now()->hour;

        return self::where('is_active', true)
            ->where('start_time', '<=', sprintf('%02d:00:00', $hour))
            ->where('end_time', '>', sprintf('%02d:00:00', $hour))
            ->orderBy('order')
            ->first();
    }

}
