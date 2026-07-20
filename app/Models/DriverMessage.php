<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'sender_id',
        'message',
        'is_read',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
