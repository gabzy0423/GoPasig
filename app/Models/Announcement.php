<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $table = 'announcements';

    protected $fillable = [
        'headline',
        'body',
        'priority',
        'audience',
        'affected_route',
        'posted_by',
        'is_draft',
        'is_scheduled',
        'scheduled_at',
        'expires_at',
    ];

    protected $casts = [
        'is_draft' => 'boolean',
        'is_scheduled' => 'boolean',
        'scheduled_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the status of the announcement dynamically.
     */
    public function getStatusAttribute()
    {
        if ($this->is_draft) {
            return 'Draft';
        }

        if ($this->is_scheduled && $this->scheduled_at && $this->scheduled_at->isFuture()) {
            return 'Scheduled';
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return 'Expired';
        }

        return 'Active';
    }
}
