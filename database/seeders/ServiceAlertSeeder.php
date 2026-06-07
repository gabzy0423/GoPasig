<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceAlert;
use Carbon\Carbon;

class ServiceAlertSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        ServiceAlert::query()->delete();

        // 1. Seed Active Service Alert
        ServiceAlert::create([
            'route_id' => 2,
            'title' => 'Route 2 Traffic Delay',
            'message' => 'Expect minor delays along Route 2 due to ongoing road repairs near Pasig City General Hospital. Please adjust your travel plans accordingly.',
            'severity' => 'warning',
            'type' => 'delay',
            'affected_routes' => 'Route 2',
            'status' => 'active',
            'estimated_resumption' => '3:30 PM',
            'created_at' => Carbon::now()->subMinutes(30),
            'updated_at' => Carbon::now()->subMinutes(30),
        ]);

        // 2. Seed Resolved Service Alerts
        ServiceAlert::create([
            'route_id' => 2,
            'title' => 'Route 2 Traffic Bottleneck Cleared',
            'message' => 'The heavy congestion along C. Raymundo Ave due to an earlier minor accident has cleared. Route 2 buses have resumed their standard timetables.',
            'severity' => 'info',
            'type' => 'delay',
            'affected_routes' => 'Route 2',
            'status' => 'resolved',
            'created_at' => Carbon::now()->subHours(5),
            'updated_at' => Carbon::now()->subHours(3), // resolved 3 hours ago
        ]);

        ServiceAlert::create([
            'route_id' => 1,
            'title' => 'Route 1 Unit Inspection Completed',
            'message' => 'Routine mechanical checks on the primary units assigned to Route 1 have been successfully completed. Full schedule operations are back on track.',
            'severity' => 'info',
            'type' => 'maintenance',
            'affected_routes' => 'Route 1',
            'status' => 'resolved',
            'created_at' => Carbon::now()->subHours(6),
            'updated_at' => Carbon::now()->subHours(4), // resolved 4 hours ago
        ]);
    }
}
