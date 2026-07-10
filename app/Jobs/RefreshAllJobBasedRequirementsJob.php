<?php

namespace App\Jobs;

use App\Models\RequirementCalculationRun;
use App\Services\JobBasedRequirementEngineService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

class RefreshAllJobBasedRequirementsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    public function __construct(
        public readonly ?int $triggeredByUserId = null,
    ) {}

    public function handle(JobBasedRequirementEngineService $jobBasedRequirementEngineService): void
    {
        $run = RequirementCalculationRun::query()->create([
            'scope' => 'global',
            'status' => 'running',
            'triggered_by_user_id' => $this->triggeredByUserId,
            'started_at' => now(),
            'meta' => ['source' => 'job'],
        ]);

        try {
            $processedUsers = $jobBasedRequirementEngineService->recalculateAll();

            $run->update([
                'status' => 'completed',
                'finished_at' => now(),
                'meta' => [
                    'source' => 'job',
                    'processed_users' => $processedUsers,
                ],
            ]);
        } catch (\Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'finished_at' => now(),
                'meta' => [
                    'source' => 'job',
                    'error' => $exception->getMessage(),
                ],
            ]);

            throw $exception;
        }
    }

    public function uniqueId(): string
    {
        return 'refresh-all-job-based-requirements';
    }
}
