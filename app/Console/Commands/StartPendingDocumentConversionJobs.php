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
        $documentConversionJob = DocumentConversionJob::query()
            ->where('status', DocumentConversionJobStatus::PENDING)
            ->orderBy('id')
            ->first();

        if ($documentConversionJob === null) {
            $this->info('No pending document conversion jobs found.');

            return self::SUCCESS;
        }

        try {
            $cloudRunJobClient->runDocumentConversionJob($documentConversionJob);
        } catch (Throwable $throwable) {
            report($throwable);

            $this->error($throwable->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Started Cloud Run document conversion worker for pending job queue from job #%d.',
            $documentConversionJob->getKey()
        ));

        return self::SUCCESS;
    }
}
