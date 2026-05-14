<?php

namespace App\Actions;

use App\Models\ModuleProgress;
use App\Models\ModuleQuizSubmission;
use Illuminate\Support\Facades\DB;

class AbandonLearningQuizAttempt
{
    public function __invoke(ModuleQuizSubmission $submission, ModuleProgress $progress, string $reason): void
    {
        DB::transaction(function () use ($submission, $progress, $reason): void {
            $submission->update([
                'status' => ModuleQuizSubmission::STATUS_FAILED,
                'submitted_at' => now(),
                'error_message' => $reason,
            ]);

            $progress->forceFill([
                'status' => ModuleProgress::STATUS_FAILED,
                'quiz_attempts' => $progress->quiz_attempts + 1,
                'last_accessed_at' => now(),
            ])->save();

            $progress->courseEnrollment()->firstOrFail()->syncProgressState();
        });
    }
}
