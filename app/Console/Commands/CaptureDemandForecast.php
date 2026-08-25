<?php

namespace App\Console\Commands;

use App\Services\DemandForecastShadowService;
use App\Services\DemandHistoryTimeSlotService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class CaptureDemandForecast extends Command
{
    protected $signature = 'demand-forecast:capture
        {date? : Future target date in Asia/Manila, defaults to tomorrow}';

    protected $description = 'Capture an immutable direction-aware advisory forecast for shadow validation.';

    public function handle(DemandForecastShadowService $shadow): int
    {
        $targetDate = $this->argument('date')
            ?: CarbonImmutable::now(DemandHistoryTimeSlotService::TIMEZONE)->addDay()->toDateString();

        try {
            $summary = $shadow->capture($targetDate);
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Direction-aware forecast shadow snapshot captured.');
        $this->table(
            ['Target date', 'Captured', 'Existing skipped', 'Ready', 'Insufficient'],
            [[
                $summary['target_date'],
                $summary['captured'],
                $summary['existing_skipped'],
                $summary['ready'],
                $summary['insufficient_history'],
            ]]
        );
        $this->line('Advisory only: no Trip or dispatch action was created.');

        return self::SUCCESS;
    }
}
