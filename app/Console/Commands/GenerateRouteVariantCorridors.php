<?php

namespace App\Console\Commands;

use App\Services\RouteVariantCorridorGenerator;
use Illuminate\Console\Command;
use RuntimeException;

class GenerateRouteVariantCorridors extends Command
{
    protected $signature = 'gopasig:generate-route-variant-corridors
        {--dry-run : Validate and report official production RouteVariant corridors without writing}
        {--apply : Persist or refresh official production RouteVariant corridors}';

    protected $description = 'Generate preparation-phase RouteVariant-owned corridors from stored official variant polylines.';

    public function handle(RouteVariantCorridorGenerator $generator): int
    {
        if ($this->option('dry-run') === $this->option('apply')) {
            $this->error('Choose exactly one of --dry-run or --apply.');
            return self::FAILURE;
        }

        try {
            $plans = $this->option('apply') ? $generator->apply() : $generator->buildPlans();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $this->table(
            ['Route', 'Direction', 'Variant', 'Coordinates', 'First', 'Last', 'Hash', 'Change'],
            array_map(fn (array $plan) => [
                $plan['route_name'],
                strtoupper($plan['direction']),
                $plan['route_variant_id'],
                $plan['coordinate_count'],
                implode(',', $plan['first_coordinate']),
                implode(',', $plan['last_coordinate']),
                substr($plan['geometry_hash'], 0, 12),
                $plan['changed'] ? 'yes' : 'unchanged',
            ], $plans)
        );

        $this->info($this->option('apply')
            ? 'Official RouteVariant corridor generation complete.'
            : 'Dry run complete. No database writes were made.');

        return self::SUCCESS;
    }
}
