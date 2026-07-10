<?php

namespace App\Console\Commands;

use App\Models\RequirementCalculationRun;
use App\Models\User;
use App\Services\JobBasedRequirementEngineService;
use Illuminate\Console\Command;

class RefreshJobBasedRequirements extends Command
{
    protected $signature = 'requirements:refresh-job-based
        {--mode=full : full|promote}
        {--user= : Recalculate one user id only}';

    protected $description = 'Refresh job-based requirement cache for users.';

    public function handle(JobBasedRequirementEngineService $jobBasedRequirementEngineService): int
    {
        $userId = (int) $this->option('user');
        $mode = (string) $this->option('mode');

        if ($userId > 0) {
            $user = User::query()->findOrFail($userId);
            $jobBasedRequirementEngineService->recalculateUser($user);
            $this->info("User {$userId} recalculated.");

            return self::SUCCESS;
        }

        if ($mode === 'promote') {
            $updatedRows = $jobBasedRequirementEngineService->promoteDueRequirements();
            $this->info("Promoted {$updatedRows} pending requirements.");

            return self::SUCCESS;
        }

        $run = RequirementCalculationRun::query()->create([
            'scope' => 'global',
            'status' => 'running',
            'started_at' => now(),
            'meta' => ['source' => 'command'],
        ]);

        try {
            $processedUsers = $jobBasedRequirementEngineService->recalculateAll();

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'meta' => [
                    'source' => 'command',
                    'processed_users' => $processedUsers,
                ],
            ]);

            $this->info("Recalculated {$processedUsers} users.");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'meta' => [
                    'source' => 'command',
                    'error' => $exception->getMessage(),
                ],
            ]);

            throw $exception;
        }
    }
}
