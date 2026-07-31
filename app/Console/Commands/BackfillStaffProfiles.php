<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\StaffProfile;

class BackfillStaffProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backfill-staff-profiles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backfill missing staff_profiles records for Admin and Dispatcher users.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting staff profile backfill...');

        $staffUsers = User::whereIn('role', ['admin', 'fleet_manager'])->get();
        $createdCount = 0;
        $skippedCount = 0;

        foreach ($staffUsers as $user) {
            $existing = StaffProfile::where('user_id', $user->id)->first();
            if ($existing) {
                $skippedCount++;
            } else {
                StaffProfile::create([
                    'user_id' => $user->id,
                ]);
                $createdCount++;
            }
        }

        $this->info("Staff profiles backfill complete!");
        $this->info("Created: {$createdCount} | Skipped (already existed): {$skippedCount}");

        return 0;
    }
}
