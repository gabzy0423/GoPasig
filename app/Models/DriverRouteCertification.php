<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DriverRouteCertification extends Model
{
    use HasFactory;

    protected $fillable = [
        'driver_id',
        'route_id',
        'certified_at',
        'expires_at',
        'status',
        'notes',
    ];

    protected $casts = [
        'certified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the driver
     */
    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    /**
     * Get the route
     */
    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    /**
     * Check if certification is currently valid
     */
    public function isValid(): bool
    {
        if ($this->status !== 'active') {
            return false;
        }

        if ($this->expires_at && now()->isAfter($this->expires_at)) {
            return false;
        }

        return true;
    }

    /**
     * Check if certification is expiring soon (within 30 days)
     */
    public function isExpiringSoon(int $daysThreshold = 30): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        $expiryDate = $this->expires_at;
        $daysUntilExpiry = now()->diffInDays($expiryDate);

        return $daysUntilExpiry >= 0 && $daysUntilExpiry <= $daysThreshold;
    }

    /**
     * Renew certification for another period
     */
    public function renew(int $monthsDuration = 12): self
    {
        $this->update([
            'expires_at' => now()->addMonths($monthsDuration),
            'status' => 'active',
        ]);

        return $this->refresh();
    }

    /**
     * Revoke certification
     */
    public function revoke(): self
    {
        $this->update(['status' => 'inactive']);
        return $this->refresh();
    }
}
