<?php

namespace App\Services\Certificates;

use App\Enums\DocumentConversionJobStatus;
use App\Models\CourseEnrollment;
use App\Models\CustomCertificate;
use App\Models\DocumentConversionJob;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class CourseCertificateGenerator
{
    private const STORAGE_DISK = 's3';

    public function __construct(
        private readonly CertificateEligibilityService $certificateEligibilityService,
        private readonly CertificateVariableResolver $certificateVariableResolver,
        private readonly CustomCertificateResolver $customCertificateResolver,
        private readonly DocxTemplateRenderer $docxTemplateRenderer,
    ) {}

    public function generateForEnrollment(CourseEnrollment $enrollment): ?DocumentConversionJob
    {
        $enrollment = $enrollment->fresh(['course.modules', 'moduleProgresses', 'user']);

        if ($enrollment === null || $enrollment->status !== CourseEnrollment::STATUS_COMPLETED) {
            return null;
        }

        $certificateType = $this->resolveCertificateType($enrollment);

        if ($certificateType === null) {
            return null;
        }

        $certificate = $this->customCertificateResolver->resolve($certificateType, $enrollment->course);

        if ($certificate === null) {
            return null;
        }

        $temporaryPath = $this->docxTemplateRenderer->renderToTemporaryPath(
            $certificate,
            $this->certificateVariableResolver->resolve($enrollment->course, $enrollment->user, $enrollment)
        );

        try {
            $inputPath = Storage::disk(self::STORAGE_DISK)->putFileAs(
                'certificates/word',
                new File($temporaryPath),
                $this->outputFileName($enrollment)
            );

            if ($inputPath === false) {
                throw new RuntimeException('Unable to store the generated certificate on S3.');
            }

            $conversionJob = DocumentConversionJob::query()->create([
                'status' => DocumentConversionJobStatus::PENDING,
                'input_disk' => self::STORAGE_DISK,
                'input_path' => $inputPath,
                'output_disk' => self::STORAGE_DISK,
                'output_path' => (string) str($inputPath)->replaceEnd('.docx', '.pdf'),
            ]);

            return $conversionJob;
        } finally {
            @unlink($temporaryPath);
        }
    }

    private function resolveCertificateType(CourseEnrollment $enrollment): ?string
    {
        foreach ([CustomCertificate::TYPE_COMPLETION, CustomCertificate::TYPE_PARTICIPATION] as $type) {
            if (! $this->certificateEligibilityService->isEligible($enrollment, $type)) {
                continue;
            }

            if ($this->customCertificateResolver->resolve($type, $enrollment->course) !== null) {
                return $type;
            }
        }

        return null;
    }

    private function outputFileName(CourseEnrollment $enrollment): string
    {
        $userFiscalCode = Str::upper(
            Str::of($enrollment->user->fiscal_code ?? 'unknown')
                ->replaceMatches('/[^A-Za-z0-9]/', '')
                ->value()
        );

        return sprintf(
            '%s_%s_%s.docx',
            $enrollment->course->getKey(),
            $userFiscalCode,
            $enrollment->completed_at?->format('Ymd') ?? now()->format('Ymd')
        );
    }
}
