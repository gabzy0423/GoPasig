<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AnalyticsSetting extends Model
{
    use HasFactory;

    protected $table = 'analytics_settings';

    protected $fillable = [
        'key',
        'value',
        'description',
    ];
}
