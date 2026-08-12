<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('routes') || ! Schema::hasTable('route_variants') || ! Schema::hasTable('route_variant_stops')) {
            return;
        }

        DB::transaction(function (): void {
            $route = DB::table('routes')
                ->where('name', 'Route 1')
                ->where('description', 'like', '%Temporary Pasig City Hall%')
                ->orderBy('id')
                ->first();

            if (! $route) {
                $this->clearRuntimeCaches();
                return;
            }

            $variantIds = DB::table('route_variants')
                ->where('route_id', $route->id)
                ->pluck('id')
                ->all();

            $this->abortIfUnexpectedReferences((int) $route->id, $variantIds);

            if (Schema::hasTable('route_variant_corridors')) {
                DB::table('route_variant_corridors')->whereIn('route_variant_id', $variantIds)->delete();
            }

            DB::table('route_variant_stops')->whereIn('route_variant_id', $variantIds)->delete();
            DB::table('route_variants')->where('route_id', $route->id)->delete();
            DB::table('routes')->where('id', $route->id)->delete();

            $this->clearRuntimeCaches();
        });
    }

    public function down(): void
    {
        // Intentionally irreversible. Bridgetown is no longer part of the official operational runtime.
    }

    private function abortIfUnexpectedReferences(int $routeId, array $variantIds): void
    {
        $routeReferenceTables = [
            'buses',
            'commuter_trips',
            'demand_history',
            'demand_thresholds',
            'dispatch_logs',
            'gps_logs',
            'incidents',
            'route_service_schedules',
            'schedules',
            'service_alerts',
            'trips',
        ];

        foreach ($routeReferenceTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'route_id')) {
                $count = DB::table($table)->where('route_id', $routeId)->count();
                if ($count > 0) {
                    throw new RuntimeException("Refusing to remove Bridgetown route: {$table} has {$count} route_id references.");
                }
            }
        }

        if ($variantIds === []) {
            return;
        }

        $variantReferenceTables = [
            'dispatch_logs',
            'gps_logs',
            'route_service_schedules',
            'schedules',
            'trips',
        ];

        foreach ($variantReferenceTables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'route_variant_id')) {
                $count = DB::table($table)->whereIn('route_variant_id', $variantIds)->count();
                if ($count > 0) {
                    throw new RuntimeException("Refusing to remove Bridgetown route: {$table} has {$count} route_variant_id references.");
                }
            }
        }
    }

    private function clearRuntimeCaches(): void
    {
        Cache::forget('routes_all');
        Cache::forget('stops_all');
        Cache::forget('commuter_dashboard_aggregate');
        Cache::forget('commuter_route_stops_aggregate');
    }
};
