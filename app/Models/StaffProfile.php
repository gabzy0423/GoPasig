<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasFactory;

    protected $table = 'staff_profiles';

    protected $fillable = [
        'user_id',
        'contact_number',
        'address',
        'emergency_contact',
        'profile_photo_path',
    ];

    /**
     * Get the user that owns the staff profile.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
