<?php

namespace App\Console\Commands;

use App\Services\RouteVariantSelectionService;
use Database\Seeders\UATSuspendRouteFixtureSeeder;
use Illuminate\Console\Command;

class UATSuspendRouteFixtureCommand extends Command
{
    protected $signature = 'uat:suspend-route-fixture {--cleanup : Remove the isolated suspend-route UAT fixture} {--force : Force cleanup even when UAT trips are ongoing}';

    protected $description = 'Seed or cleanup the isolated bidirectional Suspend Route UAT fixture';

    public function handle(): int
    {
        if ($this->option('cleanup')) {
            $this->info('Cleaning up UAT Suspend Route fixture data...');

            try {
                $summary = UATSuspendRouteFixtureSeeder::cleanup(force: (bool) $this->option('force'));
            } catch (\RuntimeException $e) {
                $this->error($e->getMessage());
                $this->warn('Use php artisan uat:suspend-route-fixture --cleanup --force only when you intentionally want to remove ongoing UAT trips.');
                return Command::FAILURE;
            }

            $this->info('Cleanup complete. Official Routes 1-3 remain untouched.');
            foreach ($summary as $label => $count) {
                $this->line("- {$label}: {$count}");
            }

            return Command::SUCCESS;
        }

        $this->info('Cleaning up old defective UAT fixture before seeding...');
        try {
            UATSuspendRouteFixtureSeeder::cleanup(force: (bool) $this->option('force'), includeLegacy: true);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            return Command::FAILURE;
        }

        $this->info('Seeding UAT Suspend Route fixture...');
        $result = (new UATSuspendRouteFixtureSeeder())->run();
        $selection = app(RouteVariantSelectionService::class);

        $this->info('UAT Suspend Route fixture ready.');
        $this->line("UAT Route ID: {$result['route']->id}");
        $this->line("Outbound Variant ID: {$result['outboundVariant']->id} usable=".($selection->isUsableForLiveDispatch($result['outboundVariant']->fresh('stops')) ? 'true' : 'false'));
        $this->line("Inbound Variant ID: {$result['inboundVariant']->id} usable=".($selection->isUsableForLiveDispatch($result['inboundVariant']->fresh('stops')) ? 'true' : 'false'));
        $this->line("Outbound Driver ID: {$result['outboundDriver']->id}");
        $this->line("Inbound Driver ID: {$result['inboundDriver']->id}");
        $this->line("Outbound Bus ID: {$result['outboundBus']->id}");
        $this->line("Inbound Bus ID: {$result['inboundBus']->id}");
        $this->line("Outbound Schedule ID: {$result['outboundSchedule']->id}");
        $this->line("Inbound Schedule ID: {$result['inboundSchedule']->id}");
        $this->newLine();
        $this->info('Driver login credentials:');
        $this->line(UATSuspendRouteFixtureSeeder::OUTBOUND_DRIVER_EMAIL.' / '.UATSuspendRouteFixtureSeeder::DRIVER_PASSWORD);
        $this->line(UATSuspendRouteFixtureSeeder::INBOUND_DRIVER_EMAIL.' / '.UATSuspendRouteFixtureSeeder::DRIVER_PASSWORD);
        $this->newLine();
        $this->warn('Cleanup: php artisan uat:suspend-route-fixture --cleanup');
        $this->warn('Force cleanup ongoing UAT trips: php artisan uat:suspend-route-fixture --cleanup --force');

        return Command::SUCCESS;
    }
}