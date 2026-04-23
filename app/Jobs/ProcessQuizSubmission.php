<?php

namespace App\Jobs;

use App\Models\ModuleQuizSubmission;
use App\Services\GoogleDocumentAiQuizService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class ProcessQuizSubmission implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    #[WithoutRelations]
    public ModuleQuizSubmission $submission;

    public function __construct(ModuleQuizSubmission $submission)
    {
        $this->submission = $submission;
    }

    public function handle(GoogleDocumentAiQuizService $googleDocumentAiQuizService): void
    {
        $submission = $this->submission->fresh(['module.quizQuestions.answers']);

        if ($submission === null || $submission->status === ModuleQuizSubmission::STATUS_FINALIZED) {
            return;
        }

        $submission->forceFill([
            'status' => ModuleQuizSubmission::STATUS_PROCESSING,
            'error_message' => null,
        ])->save();

        try {
            $googleDocumentAiQuizService->processSubmission($submission);
        } catch (Throwable $exception) {
            $submission->forceFill([
                'status' => ModuleQuizSubmission::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ])->save();

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException($exception->getMessage(), previous: $exception);
        }
    }
}
