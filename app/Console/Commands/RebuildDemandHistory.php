<?php

namespace App\Console\Commands;

use App\Services\DemandHistoryRebuildService;
use App\Services\DemandHistoryTimeSlotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class RebuildDemandHistory extends Command
{
    protected $signature = 'demand-history:rebuild
        {--from= : First local service date, defaults to yesterday}
        {--to= : Last local service date, defaults to today}
        {--through= : Finalization cutoff in Asia/Manila, defaults to now}
        {--only-unfinalized : Skip finalized buckets unless trusted promotion is requested}
        {--training-eligible : Mark rebuilt buckets as trusted forecast input}';

    protected $description = 'Rebuild direction-aware demand history from actual commuter journeys and dispatch trips.';

    public function handle(DemandHistoryRebuildService $rebuild): int
    {
        $now = CarbonImmutable::now(DemandHistoryTimeSlotService::TIMEZONE);
        $from = $this->option('from') ?: $now->subDay()->toDateString();
        $to = $this->option('to') ?: $now->toDateString();
        $through = $this->option('through') ?: $now;

        try {
            $summary = $rebuild->rebuild(
                $from,
                $to,
                $through,
                (bool) $this->option('only-unfinalized'),
                (bool) $this->option('training-eligible')
            );
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Direction-aware demand history rebuild complete.');
        $this->table(
            ['Finalized', 'Open skipped', 'Existing skipped', 'Failed'],
            [[
                $summary['finalized'],
                $summary['skipped_open'],
                $summary['skipped_existing'],
                $summary['failed'],
            ]]
        );

        return $summary['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
