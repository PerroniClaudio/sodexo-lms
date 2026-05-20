<?php

namespace App\Services\Certificates;

use App\Models\CourseEnrollment;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UserCourseCertificateLocator
{
    private const STORAGE_DISK = 's3';

    /**
     * @return array{disk: Filesystem, download_name: string, path: string}|null
     */
    public function locate(CourseEnrollment $courseEnrollment): ?array
    {
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
            'certificates/word/%s_%s_%s.pdf',
            $courseEnrollment->course->getKey(),
            $userFiscalCode,
            $courseEnrollment->completed_at->format('Ymd')
        );

        $disk = Storage::disk(self::STORAGE_DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        return [
            'disk' => $disk,
            'path' => $path,
            'download_name' => sprintf(
                'attestato-corso-%s-%s.pdf',
                $courseEnrollment->course->getKey(),
                $courseEnrollment->completed_at->format('Ymd')
            ),
        ];
    }
}
