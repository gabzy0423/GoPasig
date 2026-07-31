<?php

namespace App\Console\Commands;

use Database\Seeders\UATBidirectionalRouteSeeder;
use Illuminate\Console\Command;

class SeedUatBidirectionalRoute extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uat:bidirectional-route {--cleanup : Clean up all seeded UAT bidirectional route data}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed or cleanup the temporary bidirectional route UAT fixture for Suspend Route Phase 4 testing';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('cleanup')) {
            $this->info('Cleaning up UAT Bidirectional Route fixture data...');
            UATBidirectionalRouteSeeder::cleanup();
            $this->info('Cleanup complete! Production and official route data remain 100% untouched.');
            return Command::SUCCESS;
        }

        $this->info('Seeding UAT Bidirectional Route fixture...');
        $seeder = new UATBidirectionalRouteSeeder();
        $result = $seeder->run();

        $this->info("✓ Route Created: {$result['route']->name} (ID: {$result['route']->id})");
        $this->info("  - Outbound Variant: {$result['outboundVariant']->origin_name} -> {$result['outboundVariant']->destination_name}");
        $this->info("  - Inbound Variant:  {$result['inboundVariant']->origin_name} -> {$result['inboundVariant']->destination_name}");
        $this->info("  - Outbound Schedule: 08:00 (Bus: PAS-UAT1, Driver: UAT Driver 1)");
        $this->info("  - Inbound Schedule:  08:30 (Bus: PAS-UAT2, Driver: UAT Driver 2)");
        $this->info("✓ UAT Fixture successfully seeded!");

        return Command::SUCCESS;
    }
}
