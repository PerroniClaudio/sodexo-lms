<?php

namespace App\Services\Certificates;

use App\Enums\DocumentConversionJobStatus;
use App\Models\CourseEnrollment;
use App\Models\CustomCertificate;
use App\Models\DocumentConversionJob;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CourseCertificateGenerator
{
    public function __construct(
        private readonly CertificateEligibilityService $certificateEligibilityService,
        private readonly CertificateVariableResolver $certificateVariableResolver,
        private readonly CustomCertificateResolver $customCertificateResolver,
        private readonly DocxTemplateRenderer $docxTemplateRenderer,
    ) {}

    /**
     * @return Collection<int, DocumentConversionJob>
     */
    public function generateForEnrollment(CourseEnrollment $enrollment): Collection
    {
        $enrollment = $enrollment->fresh(['course.modules', 'moduleProgresses', 'user']);

        if ($enrollment === null || $enrollment->status !== CourseEnrollment::STATUS_COMPLETED) {
            return collect();
        }

        $variables = $this->certificateVariableResolver->resolve($enrollment->course, $enrollment->user, $enrollment);

        return collect(CustomCertificate::availableTypes())
            ->map(function (string $type) use ($enrollment, $variables): ?DocumentConversionJob {
                if (! $this->certificateEligibilityService->isEligible($enrollment, $type)) {
                    return null;
                }

                $certificate = $this->customCertificateResolver->resolve($type, $enrollment->course);

                if ($certificate === null) {
                    return null;
                }

                return $this->createConversionJob($enrollment, $certificate, $variables);
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, string>  $variables
     */
    private function createConversionJob(
        CourseEnrollment $enrollment,
        CustomCertificate $certificate,
        array $variables
    ): DocumentConversionJob {
        $temporaryPath = $this->docxTemplateRenderer->renderToTemporaryPath($certificate, $variables);

        try {
            $inputPath = Storage::putFileAs(
                'certificates/word',
                new File($temporaryPath),
                $this->outputFileName($enrollment, $certificate->type)
            );

            if ($inputPath === false) {
                throw new RuntimeException('Unable to store the generated certificate on S3.');
            }

            return DocumentConversionJob::query()->create([
                'status' => DocumentConversionJobStatus::PENDING,
                'input_disk' => Storage::getDefaultDriver(),
                'input_path' => $inputPath,
                'output_disk' => Storage::getDefaultDriver(),
                'output_path' => (string) str($inputPath)->replaceEnd('.docx', '.pdf'),
            ]);
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function outputFileName(CourseEnrollment $enrollment, string $type): string
    {
        $userFiscalCode = Str::upper(
            Str::of($enrollment->user->fiscal_code ?? 'unknown')
                ->replaceMatches('/[^A-Za-z0-9]/', '')
                ->value()
        );

        return sprintf(
            '%s_%s_%s_%s.docx',
            $enrollment->course->getKey(),
            $userFiscalCode,
            $enrollment->completed_at?->format('Ymd') ?? now()->format('Ymd'),
            $type
        );
    }
}
