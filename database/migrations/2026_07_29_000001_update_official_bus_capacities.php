<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Apply verified seating capacities for the official Pasig Libreng Sakay fleet.
     */
    public function up(): void
    {
        $capacities = [
            'PAS-001' => 26,
            'PAS-002' => 26,
            'PAS-003' => 26,
            'PAS-004' => 26,
            'PAS-005' => 26,
            'PAS-006' => 42,
        ];

        foreach ($capacities as $plateNumber => $capacity) {
            DB::table('buses')
                ->where('plate_number', $plateNumber)
                ->where('is_simulated', false)
                ->update(['capacity' => $capacity]);
        }
    }

    /**
     * This is an authoritative data correction; do not revert live fleet capacity data automatically.
     */
    public function down(): void
    {
        // Intentionally left blank to avoid restoring incorrect generic capacities.
    }
};
