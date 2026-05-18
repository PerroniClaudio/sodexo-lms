<?php

namespace App\Services;

use App\Models\DocumentConversionJob;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class CloudRunJobClient
{
    public function __construct(
        private readonly GoogleServiceAccountTokenProvider $tokenProvider
    ) {}

    /**
     * @return array{
     *     operation_name: string,
     *     payload: array<string, mixed>
     * }
     */
    public function runDocumentConversionJob(DocumentConversionJob $documentConversionJob): array
    {
        $payload = [
            'overrides' => [
                'containerOverrides' => [
                    [
                        'env' => [
                            [
                                'name' => 'DOCUMENT_CONVERSION_JOB_ID',
                                'value' => (string) $documentConversionJob->getKey(),
                            ],
                            [
                                'name' => 'DOCUMENT_CONVERSION_INPUT_DISK',
                                'value' => $documentConversionJob->input_disk,
                            ],
                            [
                                'name' => 'DOCUMENT_CONVERSION_INPUT_PATH',
                                'value' => $documentConversionJob->input_path,
                            ],
                            [
                                'name' => 'DOCUMENT_CONVERSION_OUTPUT_DISK',
                                'value' => (string) $documentConversionJob->output_disk,
                            ],
                            [
                                'name' => 'DOCUMENT_CONVERSION_OUTPUT_PATH',
                                'value' => (string) $documentConversionJob->output_path,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $responsePayload = $this->request()
                ->post($this->jobRunPath(), $payload)
                ->throw()
                ->json();
        } catch (Throwable $exception) {
            Log::error('Cloud Run document conversion job start failed.', [
                'document_conversion_job_id' => $documentConversionJob->getKey(),
                'input_disk' => $documentConversionJob->input_disk,
                'input_path' => $documentConversionJob->input_path,
                'output_disk' => $documentConversionJob->output_disk,
                'output_path' => $documentConversionJob->output_path,
                'cloud_run_project' => config('services.cloud_run.project_id'),
                'cloud_run_region' => config('services.cloud_run.region'),
                'cloud_run_job' => config('services.cloud_run.job'),
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if (! is_array($responsePayload)) {
            throw new RuntimeException('Cloud Run job response is invalid.');
        }

        $operationName = (string) ($responsePayload['name'] ?? '');

        if ($operationName === '') {
            throw new RuntimeException('Cloud Run job response is missing operation name.');
        }

        Log::info('Cloud Run document conversion job started.', [
            'document_conversion_job_id' => $documentConversionJob->getKey(),
            'operation_name' => $operationName,
            'cloud_run_project' => config('services.cloud_run.project_id'),
            'cloud_run_region' => config('services.cloud_run.region'),
            'cloud_run_job' => config('services.cloud_run.job'),
        ]);

        return [
            'operation_name' => $operationName,
            'payload' => $responsePayload,
        ];
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl('https://run.googleapis.com/v2/')
            ->acceptJson()
            ->asJson()
            ->withToken($this->tokenProvider->cloudPlatformAccessToken())
            ->connectTimeout((int) config('services.cloud_run.timeout.connect', 5))
            ->timeout((int) config('services.cloud_run.timeout.request', 30))
            ->retry([200, 500, 1000], throw: true);
    }

    private function jobRunPath(): string
    {
        return sprintf(
            'projects/%s/locations/%s/jobs/%s:run',
            $this->requireValue(config('services.cloud_run.project_id'), 'services.cloud_run.project_id'),
            $this->requireValue(config('services.cloud_run.region'), 'services.cloud_run.region'),
            $this->requireValue(config('services.cloud_run.job'), 'services.cloud_run.job'),
        );
    }

    private function requireValue(mixed $value, string $configKey): string
    {
        if (! is_string($value) || $value === '') {
            throw new RuntimeException(sprintf('Missing configuration value [%s].', $configKey));
        }

        return $value;
    }
}
