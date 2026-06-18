<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrphanedRecordsLog extends Model
{
    use HasFactory;

    protected $table = 'orphaned_records_log';

    protected $fillable = [
        'table_name',
        'record_id',
        'foreign_key_name',
        'missing_foreign_id',
        'resolution_status',
        'resolution_notes',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Log an orphaned record
     */
    public static function logOrphanedRecord(string $tableName, int $recordId, string $fkName, int $missingFkId, ?string $notes = null): self
    {
        return self::create([
            'table_name' => $tableName,
            'record_id' => $recordId,
            'foreign_key_name' => $fkName,
            'missing_foreign_id' => $missingFkId,
            'resolution_status' => 'pending',
            'resolution_notes' => $notes,
        ]);
    }

    /**
     * Get all pending orphaned records
     */
    public static function getPending()
    {
        return self::where('resolution_status', 'pending')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get orphaned records for a specific table
     */
    public static function forTable(string $tableName)
    {
        return self::where('table_name', $tableName)
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Mark as resolved
     */
    public function markResolved(string $status, string $notes)
    {
        $this->update([
            'resolution_status' => $status,
            'resolution_notes' => $notes,
        ]);

        return $this;
    }

    /**
     * Get summary statistics
     */
    public static function getSummary()
    {
        return [
            'total_orphaned' => self::count(),
            'pending' => self::where('resolution_status', 'pending')->count(),
            'resolved' => self::where('resolution_status', 'resolved')->count(),
            'deleted' => self::where('resolution_status', 'deleted')->count(),
            'by_table' => self::selectRaw('table_name, COUNT(*) as count')
                ->groupBy('table_name')
                ->get()
                ->pluck('count', 'table_name'),
        ];
    }
}
