<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Trip;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Route;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class TripSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('dispatch_logs')->delete();
        Trip::query()->delete();

        // Find active buses from BusSeeder
        $bus1 = Bus::where('plate_number', 'PAS-001')->first();
        $bus2 = Bus::where('plate_number', 'PAS-002')->first();
        $bus3 = Bus::where('plate_number', 'PAS-003')->first();
        $bus4 = Bus::where('plate_number', 'PAS-004')->first();
        $bus5 = Bus::where('plate_number', 'PAS-005')->first(); // inactive

        $driver1 = Driver::where('emp_id', 'EMP-0021')->first();
        $driver2 = Driver::where('emp_id', 'EMP-0022')->first();
        $driver3 = Driver::where('emp_id', 'EMP-0023')->first();
        $driver4 = Driver::where('emp_id', 'EMP-0024')->first();
        $driver5 = Driver::where('emp_id', 'EMP-0025')->first();

        // No active ongoing trips seeded to ensure a clean operational slate.

        // Create 2 completed trips
        if ($bus4 && $driver4) {
            $t4 = Trip::create([
                'bus_id' => $bus4->id,
                'driver_id' => $driver4->id,
                'route_id' => 4,
                'status' => 'completed',
                'peak_passengers' => 40,
                'started_at' => Carbon::now()->subHours(2),
                'ended_at' => Carbon::now()->subHours(1)->subMinutes(20),
                'created_at' => Carbon::now()->subHours(2),
            ]);
            DB::table('dispatch_logs')->insert([
                'trip_id' => $t4->id,
                'dispatched_by' => 1,
                'dispatched_at' => Carbon::now()->subHours(2),
                'notes' => 'Completed morning trip for Route 4.',
                'created_at' => Carbon::now()->subHours(2),
                'updated_at' => Carbon::now()->subHours(2)
            ]);
        }

        if ($bus5 && $driver5) {
            $t5 = Trip::create([
                'bus_id' => $bus5->id,
                'driver_id' => $driver5->id,
                'route_id' => 1,
                'status' => 'completed',
                'peak_passengers' => 50,
                'started_at' => Carbon::now()->subHours(4),
                'ended_at' => Carbon::now()->subHours(3)->subMinutes(30),
                'created_at' => Carbon::now()->subHours(4),
            ]);
            DB::table('dispatch_logs')->insert([
                'trip_id' => $t5->id,
                'dispatched_by' => 1,
                'dispatched_at' => Carbon::now()->subHours(4),
                'notes' => 'Completed early morning trip for Route 1.',
                'created_at' => Carbon::now()->subHours(4),
                'updated_at' => Carbon::now()->subHours(4)
            ]);
        }
    }
}
