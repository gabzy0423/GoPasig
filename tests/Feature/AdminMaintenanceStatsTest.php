<?php

namespace Tests\Feature;

use App\Models\Bus;
use App\Models\User;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceInspection;
use App\Services\MaintenanceStatisticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class AdminMaintenanceStatsTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    public function test_statistics_service_calculates_correct_aggregates(): void
    {
        // 1. Create buses
        $bus1 = Bus::create([
            'plate_number' => 'PAS-1001',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
            'has_observation' => true, // 1 observation
        ]);

        $bus2 = Bus::create([
            'plate_number' => 'PAS-1002',
            'status' => 'inactive',
            'capacity' => 45,
            'lat' => 14.5593,
            'lng' => 121.0805,
            'speed' => 0,
            'passengers' => 0,
            'has_observation' => false,
        ]);

        // 2. Create maintenance records with different statuses
        // Scheduled (not overdue)
        MaintenanceRecord::create([
            'bus_id' => $bus1->id,
            'ticket_number' => 'MT-2026-000001',
            'type' => 'Preventive Maintenance',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now()->addDays(1),
            'expected_duration_minutes' => 120,
        ]);

        // Scheduled (overdue: scheduled_at in the past)
        MaintenanceRecord::create([
            'bus_id' => $bus2->id,
            'ticket_number' => 'MT-2026-000002',
            'type' => 'Corrective Maintenance',
            'status' => 'scheduled',
            'scheduled_at' => Carbon::now()->subDays(1),
            'expected_duration_minutes' => 120,
        ]);

        // In Progress (no failed inspection)
        $inProgressRecord1 = MaintenanceRecord::create([
            'bus_id' => $bus1->id,
            'ticket_number' => 'MT-2026-000003',
            'type' => 'Preventive Maintenance',
            'status' => 'in_progress',
            'scheduled_at' => Carbon::now(),
            'expected_duration_minutes' => 120,
        ]);

        // In Progress (with failed inspection -> requiring repair)
        $inProgressRecord2 = MaintenanceRecord::create([
            'bus_id' => $bus2->id,
            'ticket_number' => 'MT-2026-000004',
            'type' => 'Corrective Maintenance',
            'status' => 'in_progress',
            'scheduled_at' => Carbon::now(),
            'expected_duration_minutes' => 120,
            'inspection_passed' => false, // derived from failed inspection
        ]);

        // Completed (duration 180 mins)
        MaintenanceRecord::create([
            'bus_id' => $bus1->id,
            'ticket_number' => 'MT-2026-000005',
            'type' => 'Preventive Maintenance',
            'status' => 'completed',
            'scheduled_at' => Carbon::now()->subDays(2),
            'expected_duration_minutes' => 120,
            'actual_duration_minutes' => 180,
        ]);

        // Completed (duration 120 mins)
        MaintenanceRecord::create([
            'bus_id' => $bus2->id,
            'ticket_number' => 'MT-2026-000006',
            'type' => 'Corrective Maintenance',
            'status' => 'completed',
            'scheduled_at' => Carbon::now()->subDays(2),
            'expected_duration_minutes' => 120,
            'actual_duration_minutes' => 120,
        ]);

        // Cancelled
        MaintenanceRecord::create([
            'bus_id' => $bus1->id,
            'ticket_number' => 'MT-2026-000007',
            'type' => 'Preventive Maintenance',
            'status' => 'cancelled',
            'scheduled_at' => Carbon::now()->subDays(3),
            'expected_duration_minutes' => 120,
        ]);

        // Run stats service
        $service = new MaintenanceStatisticsService();
        $summary = $service->getSummary();

        // Assertions
        $this->assertEquals(7, $summary['totalRecords']);
        $this->assertEquals(2, $summary['scheduledCount']);
        $this->assertEquals(2, $summary['inProgressCount']);
        $this->assertEquals(2, $summary['completedCount']);
        $this->assertEquals(1, $summary['cancelledCount']);
        $this->assertEquals(1, $summary['observationCount']); // Bus 1 has_observation is true
        $this->assertEquals(1, $summary['overdueCount']); // MT-2026-000002 scheduled_at is past
        $this->assertEquals(1, $summary['requiringRepairCount']); // MT-2026-000004 inspection_passed is false
        
        // Avg duration: (180 + 120) / 2 = 150 mins = 2 hrs 30 mins
        $this->assertEquals('2 hrs 30 mins', $summary['averageDuration']);
    }

    public function test_admin_stats_endpoint_returns_json(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/admin/api/maintenance/stats');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'totalRecords',
            'scheduledCount',
            'inProgressCount',
            'completedCount',
            'cancelledCount',
            'observationCount',
            'overdueCount',
            'requiringRepairCount',
            'averageDuration',
        ]);
    }
}
