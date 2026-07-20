<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'emp_id',
        'license_number',
        'license_expiry',
        'status',
        'operational_status',
        'previous_status',
        'assigned_bus',
        'assigned_route',
        'trips_today',
        'pax_today',
        'address',
        'contact_number',
        'emergency_contact',
        'performance_score',
        'incidents_30',
    ];

    protected $casts = [
        'license_expiry' => 'date',
        'performance_score' => 'integer',
        'incidents_30' => 'integer',
        'trips_today' => 'integer',
        'pax_today' => 'integer',
    ];

    protected $appends = ['initials', 'name'];

    public function getInitialsAttribute()
    {
        return ($this->first_name ? strtoupper(substr($this->first_name, 0, 1)) : '') . 
               ($this->last_name ? strtoupper(substr($this->last_name, 0, 1)) : '');
    }

    public function getNameAttribute()
    {
        return trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the incidents logged against this driver.
     */
    public function incidents()
    {
        return $this->hasMany(Incident::class);
    }

    /**
     * Get the trip logs for this driver.
     */
    public function tripLogs()
    {
        return $this->hasMany(TripLog::class);
    }

    /**
     * Get the route certifications for this driver
     */
    public function routeCertifications()
    {
        return $this->hasMany(DriverRouteCertification::class);
    }

    /**
     * Get active route certifications
     */
    public function activeRouteCertifications()
    {
        return $this->routeCertifications()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }
}
