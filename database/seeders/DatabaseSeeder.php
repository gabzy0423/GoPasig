<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SystemSettingSeeder::class,
            UserSeeder::class,
            BusSeeder::class,
            RouteSeeder::class,
            OfficialPasigRouteSeeder::class,
            TerminalSeeder::class,
            DriverSeeder::class,
            ScheduleSeeder::class,
            ServiceAlertSeeder::class,
            TripSeeder::class,
            DemandIntelligenceSeeder::class,
            ColorPaletteSeeder::class,
            TimeSlotConfigurationSeeder::class,
            DispatchSimulationDefaultsSeeder::class,
            DriverUserSeeder::class,
        ]);
    }
}

