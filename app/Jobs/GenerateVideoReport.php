<?php

namespace App\Jobs;

use App\Models\VideoReportRequest;
use App\Services\LiveAuditTrailExporter;
use App\Services\VideoReportExporter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\WithoutRelations;
use Illuminate\Queue\SerializesModels;
use RuntimeException;
use Throwable;

class GenerateVideoReport implements ShouldQueue
{
    use Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    #[WithoutRelations]
    public VideoReportRequest $videoReportRequest;

    public function __construct(VideoReportRequest $videoReportRequest)
    {
        $this->videoReportRequest = $videoReportRequest;
    }

    public function handle(
        VideoReportExporter $videoReportExporter,
        LiveAuditTrailExporter $liveAuditTrailExporter,
    ): void {
        $videoReportRequest = $this->videoReportRequest->fresh(['course']);

        if ($videoReportRequest === null || $videoReportRequest->status === VideoReportRequest::STATUS_COMPLETED) {
            return;
        }

        $videoReportRequest->forceFill([
            'status' => VideoReportRequest::STATUS_PROCESSING,
            'error_message' => null,
            'started_at' => now(),
            'completed_at' => null,
        ])->save();

        try {
            $outputPath = match ($videoReportRequest->report_type) {
                VideoReportRequest::REPORT_TYPE_VIDEO => $videoReportExporter->store($videoReportRequest),
                VideoReportRequest::REPORT_TYPE_LIVE => $liveAuditTrailExporter->store($videoReportRequest),
                default => throw new RuntimeException('Unsupported audit trail report type.'),
            };

            $videoReportRequest->forceFill([
                'status' => VideoReportRequest::STATUS_COMPLETED,
                'output_path' => $outputPath,
                'completed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $videoReportRequest->forceFill([
                'status' => VideoReportRequest::STATUS_FAILED,
                'error_message' => $exception->getMessage(),
                'completed_at' => now(),
            ])->save();

            throw $exception instanceof RuntimeException
                ? $exception
                : new RuntimeException($exception->getMessage(), previous: $exception);
        }
    }
}
