<?php

namespace App\Jobs;

use App\Models\UserAccessExport;
use App\Services\UserAccessExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class GenerateUserAccessExport implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    #[WithoutRelations]
    public UserAccessExport $userAccessExport;

    public function __construct(UserAccessExport $userAccessExport)
    {
        $this->userAccessExport = $userAccessExport;
    }

    public function handle(UserAccessExporter $exporter): void
    {
        $userAccessExport = $this->userAccessExport->fresh();

        if ($userAccessExport === null || $userAccessExport->status === UserAccessExport::STATUS_COMPLETED) {
            return;
        }

        $userAccessExport->forceFill([
            'status' => UserAccessExport::STATUS_PROCESSING,
            'error_message' => null,
            'started_at' => now(),
            'completed_at' => null,
        ])->save();

        try {
            $outputPath = $exporter->store($userAccessExport);

            $userAccessExport->forceFill([
                'status' => UserAccessExport::STATUS_COMPLETED,
                'output_path' => $outputPath,
                'completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $userAccessExport->forceFill([
                'status' => UserAccessExport::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException($exception->getMessage(), previous: $exception);
        }
    }
}
