<?php

namespace App\Services\Certificates;

use App\Models\CourseEnrollment;
use App\Models\CustomCertificate;
use App\Support\CloudStorage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserCourseCertificateLocator
{
    /**
     * @return array{disk: Filesystem, download_name: string, path: string}|null
     */
    public function locate(CourseEnrollment $courseEnrollment, ?string $type = null): ?array
    {
        if ($type !== null) {
            return $this->locateByType($courseEnrollment, $type);
        }

        foreach ([CustomCertificate::TYPE_COMPLETION, CustomCertificate::TYPE_PARTICIPATION] as $fallbackType) {
            $certificate = $this->locateByType($courseEnrollment, $fallbackType);

            if ($certificate !== null) {
                return $certificate;
            }
        }

        return null;
    }

    /**
     * @return array<string, array{disk: Filesystem, download_name: string, label: string, path: string, type: string}>
     */
    public function locateAll(CourseEnrollment $courseEnrollment): array
    {
        $certificates = [];

        foreach (CustomCertificate::availableTypes() as $type) {
            $certificate = $this->locateByType($courseEnrollment, $type);

            if ($certificate === null) {
                continue;
            }

            $certificates[$type] = $certificate;
        }

        return $certificates;
    }

    /**
     * @return array{disk: Filesystem, download_name: string, label: string, path: string, type: string}|null
     */
    private function locateByType(CourseEnrollment $courseEnrollment, string $type): ?array
    {
        if (! in_array($type, CustomCertificate::availableTypes(), true)) {
            return null;
        }

        $courseEnrollment->loadMissing('course', 'user');

        if ($courseEnrollment->completed_at === null) {
            return null;
        }

        $userFiscalCode = Str::upper(
            Str::of($courseEnrollment->user->fiscal_code ?? 'unknown')
                ->replaceMatches('/[^A-Za-z0-9]/', '')
                ->value()
        );

        $path = sprintf(
            'certificates/word/%s_%s_%s_%s.pdf',
            $courseEnrollment->course->getKey(),
            $userFiscalCode,
            $courseEnrollment->completed_at->format('Ymd'),
            $type
        );

        $disk = Storage::disk(CloudStorage::disk());

        if (! $disk->exists($path)) {
            return null;
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'type' => $type,
            'label' => CustomCertificate::availableTypeLabels()[$type] ?? $type,
            'download_name' => sprintf(
                'attestato-%s-corso-%s-%s.pdf',
                $type === CustomCertificate::TYPE_COMPLETION ? 'superamento' : 'partecipazione',
                $courseEnrollment->course->getKey(),
                $courseEnrollment->completed_at->format('Ymd')
            ),
        ];
    }
}
