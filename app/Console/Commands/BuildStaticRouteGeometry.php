<?php

namespace App\Console\Commands;

use App\Models\RouteVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BuildStaticRouteGeometry extends Command
{
    protected $signature = 'gopasig:build-static-route-geometry
        {--dry-run : Validate and report official production variants without writing}
        {--apply : Write the static schematic geometry to official production variants}
        {--force : Allow replacing existing active variant geometry when used with --apply}';

    protected $description = 'Build schematic directional geometry from official RouteVariantStops.';
    private const DIRECTIONS = ['outbound', 'inbound'];

    public function handle(): int
    {
        if ($this->option('dry-run') === $this->option('apply')) {
            $this->error('Choose exactly one of --dry-run or --apply.');
            return self::FAILURE;
        }

        try {
            $plans = $this->buildPlans();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }

        $this->table(
            ['Route', 'Direction', 'Stops', 'First stop', 'Final stop', 'Coordinates', 'Existing', 'Change'],
            array_map(fn (array $plan) => [
                $plan['route'],
                strtoupper($plan['direction']),
                count($plan['stops']),
                $plan['stops'][0]['name'],
                $plan['stops'][count($plan['stops']) - 1]['name'],
                count($plan['geometry']),
                $plan['existing_count'],
                $plan['changed'] ? 'yes' : 'unchanged',
            ], $plans)
        );

        $active = array_values(array_filter($plans, fn (array $plan) => $plan['active']));
        if ($active !== []) {
            foreach ($active as $plan) {
                $this->warn(sprintf(
                    '%s %s has active geometry (%d points, status %s).',
                    $plan['route'], strtoupper($plan['direction']), $plan['existing_count'], $plan['status'] ?: 'unset'
                ));
            }
            if ($this->option('apply') && !$this->option('force')) {
                $this->error('Apply blocked: use --force to explicitly replace active geometry.');
                return self::FAILURE;
            }
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run complete. No database writes were made.');
            return self::SUCCESS;
        }

        $updated = 0;
        DB::transaction(function () use ($plans, &$updated): void {
            foreach ($plans as $plan) {
                if (!$plan['changed']) {
                    continue;
                }
                $plan['variant']->update([
                    'polyline_coordinates' => $plan['geometry'],
                    'geometry_version' => ((int) $plan['variant']->geometry_version) + 1,
                    'geometry_status' => 'schematic',
                ]);
                $updated++;
            }
        });

        $this->info(sprintf('Static schematic geometry applied. %d variant(s) updated; %d unchanged.', $updated, count($plans) - $updated));
        return self::SUCCESS;
    }

    private function buildPlans(): array
    {
        $plans = [];
        foreach (\App\Models\Route::canonicalProductionNames() as $routeName) {
            foreach (self::DIRECTIONS as $direction) {
                $variant = RouteVariant::query()
                    ->where('direction', $direction)
                    ->whereHas('route', fn ($query) => $query->where('name', $routeName))
                    ->with(['route', 'stops'])
                    ->first();

                if (!$variant) {
                    throw new RuntimeException("Missing canonical variant: {$routeName} {$direction}.");
                }

                $stops = $variant->stops->sortBy('sequence')->values();
                if ($stops->count() < 2) {
                    throw new RuntimeException("{$routeName} {$direction} must have at least two ordered stops.");
                }

                $geometry = [];
                foreach ($stops as $index => $stop) {
                    if ((int) $stop->sequence !== $index + 1) {
                        throw new RuntimeException("{$routeName} {$direction} has an invalid stop sequence at {$stop->sequence}.");
                    }
                    if (!is_numeric($stop->lat) || !is_numeric($stop->lng)) {
                        throw new RuntimeException("{$routeName} {$direction} has a missing coordinate at sequence {$stop->sequence}.");
                    }
                    $lat = (float) $stop->lat;
                    $lng = (float) $stop->lng;
                    if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
                        throw new RuntimeException("{$routeName} {$direction} has an invalid coordinate at sequence {$stop->sequence}.");
                    }
                    $geometry[] = [round($lat, 7), round($lng, 7)];
                }

                $existing = array_map(
                    fn ($point) => [round((float) ($point[0] ?? 0), 7), round((float) ($point[1] ?? 0), 7)],
                    is_array($variant->polyline_coordinates) ? $variant->polyline_coordinates : []
                );
                $status = (string) ($variant->geometry_status ?? '');
                $active = $existing !== [] && in_array($status, ['authoritative', 'active', 'approved'], true);

                $plans[] = [
                    'route' => $routeName,
                    'direction' => $direction,
                    'variant' => $variant,
                    'stops' => $stops->map(fn ($stop) => ['name' => $stop->name])->all(),
                    'geometry' => $geometry,
                    'existing_count' => count($existing),
                    'status' => $status,
                    'active' => $active,
                    'changed' => $existing !== $geometry,
                ];
            }
        }
        return $plans;
    }
}
