<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusStatusAuditLog extends Model
{
    use HasFactory;

    protected $table = 'bus_status_audit_log';

    protected $fillable = [
        'bus_id',
        'old_status',
        'new_status',
        'changed_by',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function bus(): BelongsTo
    {
        return $this->belongsTo(Bus::class);
    }

    public function changedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /**
     * Log a bus status change
     */
    public static function logStatusChange(int $busId, string $newStatus, ?string $oldStatus = null, ?int $userId = null, ?string $reason = null, ?array $metadata = null): self
    {
        return self::create([
            'bus_id' => $busId,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'changed_by' => $userId,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Get audit history for a bus
     */
    public static function getHistoryForBus(int $busId, int $limit = 50)
    {
        return self::where('bus_id', $busId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get status changes within a date range
     */
    public static function getStatusChanges($startDate, $endDate, ?string $newStatus = null)
    {
        $query = self::whereBetween('created_at', [$startDate, $endDate]);
        
        if ($newStatus) {
            $query->where('new_status', $newStatus);
        }
        
        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * Calculate average time a bus stays in each status
     */
    public static function getAverageStatusDuration(int $busId)
    {
        $records = self::where('bus_id', $busId)
            ->orderBy('created_at', 'asc')
            ->get();

        $durations = [];
        
        for ($i = 0; $i < count($records) - 1; $i++) {
            $status = $records[$i]->new_status;
            $start = $records[$i]->created_at;
            $end = $records[$i + 1]->created_at;
            $minutes = $start->diffInMinutes($end);

            if (!isset($durations[$status])) {
                $durations[$status] = [];
            }

            $durations[$status][] = $minutes;
        }

        $averages = [];
        foreach ($durations as $status => $times) {
            $averages[$status] = [
                'count' => count($times),
                'average_minutes' => round(array_sum($times) / count($times), 2),
                'min_minutes' => min($times),
                'max_minutes' => max($times),
            ];
        }

        return $averages;
    }
}
