<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DefaultRouteSetting extends Model
{
    use HasFactory;

    protected $table = 'default_route_settings';

    protected $fillable = [
        'default_latitude',
        'default_longitude',
        'default_origin_label',
        'default_destination_label',
    ];
}
