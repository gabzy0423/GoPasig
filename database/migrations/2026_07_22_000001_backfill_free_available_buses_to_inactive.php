<?php

use App\Models\Bus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('buses')
            ->where('status', 'available')
            ->whereNull('route_id')
            ->where(function ($query) {
                $query->whereNull('driver_name')
                    ->orWhere('driver_name', '')
                    ->orWhere('driver_name', Bus::DEFAULT_DRIVER_NAME)
                    ->orWhere('driver_name', Bus::getDefaultDriverName());
            })
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('trips')
                    ->whereColumn('trips.bus_id', 'buses.id')
                    ->whereIn('trips.status', ['dispatched', 'ongoing']);
            })
            ->update([
                'status' => Bus::STATUS_INACTIVE,
                'previous_status' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Intentionally irreversible: only free legacy rows are normalized forward.
    }
};