<?php

namespace App\Console\Commands;

use App\Services\OfficialRouteCoordinateImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportOfficialRouteStops extends Command
{
    protected $signature = 'gopasig:import-official-route-stops
        {--file= : Path to the official XLSX workbook}
        {--dry-run : Validate and report without changing the database}
        {--apply : Explicitly apply the validated import}
        {--force : Skip the interactive apply confirmation}';

    protected $description = 'Validate or import official production RouteVariantStop coordinates';

    public function handle(OfficialRouteCoordinateImporter $importer): int
    {
        $path = $this->option('file');
        if (!$path) {
            $this->error('The --file option is required.');
            return self::FAILURE;
        }
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Use either --dry-run or --apply, not both.');
            return self::FAILURE;
        }

        try {
            $plan = $importer->buildPlan($path);
            $this->renderPlan($plan);
            if (!$plan['ready']) return self::FAILURE;

            if (!$this->option('apply')) {
                $this->info('Dry run only. No database changes were made. Use --apply to import explicitly.');
                return self::SUCCESS;
            }
            if (!$this->option('force') && !$this->confirm('Apply the official production variant replacements transactionally?')) {
                $this->info('Import cancelled.');
                return self::SUCCESS;
            }

            $applied = $importer->apply($plan);
            $this->info('Import applied successfully.');
            $this->renderPlan($applied);
            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }
    }

    private function renderPlan(array $plan): void
    {
        $this->line('Source: ' . $plan['source']);
        foreach ($plan['sheets'] as $sheet) {
            $this->line(sprintf(
                '%s - %s %s | route_id=%s variant_id=%s | rows=%d creates=%d updates=%d unchanged=%d removals=%d | %s',
                $sheet['canonical_route'], $sheet['direction'], $sheet['worksheet'],
                $sheet['route_id'] ?? 'missing', $sheet['variant_id'] ?? 'missing', count($sheet['rows']),
                $sheet['planned_creates'], $sheet['planned_updates'], $sheet['planned_unchanged'],
                $sheet['planned_removals'], $sheet['status']
            ));
            foreach ($sheet['rows'] as $row) {
                $suffix = $row['duplicate_coordinate'] ? ' [EXACT COORDINATE DUPLICATE - PRESERVED]' : '';
                $invalid = $row['invalid'] ? ' [INVALID: ' . $row['invalid_reason'] . ']' : '';
                $this->line(sprintf('  %d. %s => %s (%s, %s)%s%s', $row['sequence'], $row['raw_name'], $row['normalized_name'], $row['lat'] ?? 'null', $row['lng'] ?? 'null', $suffix, $invalid));
            }
        }
        foreach ($plan['warnings'] as $warning) $this->warn($warning);
        foreach ($plan['errors'] as $error) $this->error($error);
    }
}
