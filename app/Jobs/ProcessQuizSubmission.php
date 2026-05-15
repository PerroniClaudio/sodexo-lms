<?php

namespace App\Jobs;

use App\Models\ModuleQuizDocumentUpload;
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
    public ModuleQuizDocumentUpload $documentUpload;

    public function __construct(ModuleQuizDocumentUpload $documentUpload)
    {
        $this->documentUpload = $documentUpload;
    }

    public function handle(GoogleDocumentAiQuizService $googleDocumentAiQuizService): void
    {
        $documentUpload = $this->documentUpload->fresh(['module.quizQuestions.answers']);

        if ($documentUpload === null || $documentUpload->status === ModuleQuizDocumentUpload::STATUS_PROCESSED) {
            return;
        }

        $documentUpload->forceFill([
            'status' => ModuleQuizDocumentUpload::STATUS_PROCESSING,
            'error_message' => null,
        ])->save();

        try {
            $googleDocumentAiQuizService->processDocumentUpload($documentUpload);
        } catch (Throwable $exception) {
            $documentUpload->forceFill([
                'status' => ModuleQuizDocumentUpload::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'processed_at' => now(),
            ])->save();

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException($exception->getMessage(), previous: $exception);
        }
    }
}
