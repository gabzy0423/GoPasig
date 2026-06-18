<?php

namespace Tests\Feature;

use App\Models\Driver;
use App\Models\Incident;
use App\Models\Route;
use App\Models\Schedule;
use App\Models\SystemSetting;
use App\Models\Trip;
use App\Models\User;
use App\Services\DriverPerformanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AdminDriverManagementAuditFixTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        return $user;
    }

    #[Test]
    public function new_driver_initial_performance_score_uses_system_setting(): void
    {
        $this->actingAsAdmin();
        SystemSetting::updateOrCreate(['key' => 'driver_initial_performance_score'], ['value' => '72']);
        Cache::forget('system_setting_driver_initial_performance_score');

        $response = $this->postJson(route('admin.api.drivers.store'), [
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'emp_id' => 'EMP-M3-001',
            'license_number' => 'LIC-M3-001',
            'license_expiry' => now('Asia/Manila')->addYear()->toDateString(),
            'status' => 'inactive',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('drivers', [
            'emp_id' => 'EMP-M3-001',
            'performance_score' => 72,
        ]);
    }

    #[Test]
    public function expired_license_driver_cannot_be_set_active_on_update(): void
    {
        $this->actingAsAdmin();
        $driver = Driver::factory()->create(['status' => 'inactive']);

        $response = $this->putJson(route('admin.api.drivers.update', $driver), [
            'first_name' => $driver->first_name,
            'last_name' => $driver->last_name,
            'license_number' => $driver->license_number,
            'license_expiry' => now('Asia/Manila')->subDay()->toDateString(),
            'status' => 'active',
        ]);

        $response->assertStatus(422);
        $this->assertSame('inactive', $driver->fresh()->status);
    }

    #[Test]
    public function unsuspending_driver_restores_previous_status(): void
    {
        $this->actingAsAdmin();
        $driver = Driver::factory()->create(['status' => 'active']);

        $this->postJson(route('admin.api.drivers.suspend', $driver))->assertOk();
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'status' => 'suspended',
            'previous_status' => 'active',
        ]);

        $this->postJson(route('admin.api.drivers.suspend', $driver->fresh()))->assertOk();
        $this->assertDatabaseHas('drivers', [
            'id' => $driver->id,
            'status' => 'active',
            'previous_status' => null,
        ]);
    }

    #[Test]
    public function performance_score_uses_configured_incident_penalty_and_passenger_rating(): void
    {
        SystemSetting::updateOrCreate(['key' => 'incident_score_penalty_per_event'], ['value' => '25']);
        SystemSetting::updateOrCreate(['key' => 'driver_score_incident_penalty'], ['value' => '25']);
        SystemSetting::updateOrCreate(['key' => 'driver_passenger_rating_default'], ['value' => '100']);
        Cache::flush();

        $driver = Driver::factory()->create(['performance_score' => 100, 'incidents_30' => 0]);
        $trip = Trip::factory()->create(['driver_id' => $driver->id]);
        Incident::factory()->create([
            'driver_id' => $driver->id,
            'trip_id' => $trip->id,
            'created_at' => now('Asia/Manila'),
        ]);

        DriverPerformanceService::recalculate($driver->id);

        $driver->refresh();
        $this->assertSame(75, $driver->performance_score);
        $this->assertSame(1, $driver->incidents_30);
    }

    #[Test]
    public function performance_recalculation_reads_incidents_from_database_not_cached_driver_field(): void
    {
        SystemSetting::updateOrCreate(['key' => 'driver_passenger_rating_default'], ['value' => '100']);
        Cache::flush();

        $driver = Driver::factory()->create(['performance_score' => 50, 'incidents_30' => 3]);

        DriverPerformanceService::recalculate($driver->id);

        $driver->refresh();
        $this->assertSame(100, $driver->performance_score);
        $this->assertSame(0, $driver->incidents_30);
    }

    #[Test]
    public function performance_schedule_window_filters_by_service_date(): void
    {
        SystemSetting::updateOrCreate(['key' => 'driver_passenger_rating_default'], ['value' => '100']);
        Cache::flush();

        $driver = Driver::factory()->create(['performance_score' => 100]);
        $route = Route::factory()->create();

        Schedule::factory()->create([
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'service_date' => now('Asia/Manila')->toDateString(),
            'status' => Schedule::STATUS_ON_TIME,
            'created_at' => now('Asia/Manila')->subDays(90),
        ]);

        Schedule::factory()->create([
            'driver_id' => $driver->id,
            'route_id' => $route->id,
            'service_date' => now('Asia/Manila')->subDays(90)->toDateString(),
            'status' => 'delayed',
            'created_at' => now('Asia/Manila'),
        ]);

        DriverPerformanceService::recalculate($driver->id);

        $this->assertSame(100, $driver->fresh()->performance_score);
    }

    #[Test]
    public function license_status_thresholds_use_system_settings(): void
    {
        SystemSetting::updateOrCreate(['key' => 'license_expiry_warn_critical_days'], ['value' => '3']);
        SystemSetting::updateOrCreate(['key' => 'license_expiry_warning_threshold_days'], ['value' => '20']);
        Cache::flush();

        $this->assertSame('expiring_soon', DriverPerformanceService::getLicenseStatus(now('Asia/Manila')->addDays(3)->toDateString()));
        $this->assertSame('expiring_soon_warning', DriverPerformanceService::getLicenseStatus(now('Asia/Manila')->addDays(10)->toDateString()));
        $this->assertSame('valid', DriverPerformanceService::getLicenseStatus(now('Asia/Manila')->addDays(25)->toDateString()));
    }
}
