<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the staff profile associated with the user (for admin and fleet_manager roles).
     */
    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    /**
     * Get the driver profile associated with the user (for driver role).
     */
    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    /**
     * Determine if the user is a staff member (admin or fleet_manager).
     */
    public function isStaff(): bool
    {
        return in_array($this->role, ['admin', 'fleet_manager'], true);
    }

    /**
     * Canonical source of truth for visible role display labels.
     */
    public function displayRole(): string
    {
        return match ($this->role) {
            'admin' => 'Administrator / Dispatcher',
            'fleet_manager' => 'Fleet Operations Manager',
            'driver' => 'Driver',
            default => 'User',
        };
    }

    /**
     * Accessor for role_display attribute.
     */
    public function getRoleDisplayAttribute(): string
    {
        return $this->displayRole();
    }
}
