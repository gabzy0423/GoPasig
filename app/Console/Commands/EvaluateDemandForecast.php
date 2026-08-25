<?php

namespace App\Console\Commands;

use App\Services\DemandForecastShadowService;
use App\Services\DemandHistoryTimeSlotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class EvaluateDemandForecast extends Command
{
    protected $signature = 'demand-forecast:evaluate
        {date? : Snapshot target date, defaults to yesterday in Asia/Manila}';

    protected $description = 'Compare saved direction-aware forecasts with finalized actual demand.';

    public function handle(DemandForecastShadowService $shadow): int
    {
        $targetDate = $this->argument('date')
            ?: CarbonImmutable::now(DemandHistoryTimeSlotService::TIMEZONE)->subDay()->toDateString();

        try {
            $summary = $shadow->evaluate($targetDate);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Direction-aware forecast shadow evaluation complete.');
        $this->table(
            ['Target date', 'Snapshots', 'Evaluated', 'Actual/no forecast', 'Pending actual', 'Existing skipped'],
            [[
                $summary['target_date'],
                $summary['snapshots'],
                $summary['evaluated'],
                $summary['actual_without_forecast'],
                $summary['pending_actual'],
                $summary['existing_skipped'],
            ]]
        );
        $this->line('Advisory only: no Trip or dispatch action was created.');

        return self::SUCCESS;
    }
}
