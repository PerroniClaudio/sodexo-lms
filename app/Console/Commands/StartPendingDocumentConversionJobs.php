<?php

namespace App\Console\Commands;

use App\Enums\DocumentConversionJobStatus;
use App\Models\DocumentConversionJob;
use App\Services\CloudRunJobClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('app:start-pending-document-conversion-jobs')]
#[Description('Start pending document conversion jobs on Google Cloud Run')]
class StartPendingDocumentConversionJobs extends Command
{
    public function handle(CloudRunJobClient $cloudRunJobClient): int
    {
        $startedJobs = 0;

        DocumentConversionJob::query()
            ->where('status', DocumentConversionJobStatus::PENDING)
            ->orderBy('id')
            ->get()
            ->each(function (DocumentConversionJob $documentConversionJob) use ($cloudRunJobClient, &$startedJobs): void {
                try {
                    $cloudRunJobClient->runDocumentConversionJob($documentConversionJob);
                    $startedJobs++;
                } catch (Throwable $throwable) {
                    $documentConversionJob->forceFill([
                        'status' => DocumentConversionJobStatus::FAILED,
                        'failed_at' => now(),
                        'error_message' => $throwable->getMessage(),
                    ])->save();

                    report($throwable);
                }
            });

        $this->info(sprintf('Started %d pending document conversion job(s).', $startedJobs));

        return self::SUCCESS;
    }
}
