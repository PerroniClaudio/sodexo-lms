<?php

namespace App\Services\Certificates;

use App\Models\Course;
use App\Models\CustomCertificate;
use App\Models\User;

class CustomCertificateResolver
{
    public function resolve(string $type, Course $course, ?User $user = null): ?CustomCertificate
    {
        $certificate = CustomCertificate::query()
            ->active()
            ->ofType($type)
            ->orderByDesc('id')
            ->get()
            ->first(fn (CustomCertificate $customCertificate): bool => ! $customCertificate->isGeneric()
                && $customCertificate->supportsCourse((int) $course->getKey()));

        if ($certificate !== null) {
            return $certificate;
        }

        if ($user?->job_sector_id !== null) {
            $certificate = CustomCertificate::query()
                ->active()
                ->ofType($type)
                ->where('job_sector_id', $user->job_sector_id)
                ->orderByDesc('id')
                ->first();

            if ($certificate !== null) {
                return $certificate;
            }
        }

        return CustomCertificate::query()
            ->active()
            ->ofType($type)
            ->orderByDesc('id')
            ->get()
            ->first(fn (CustomCertificate $customCertificate): bool => $customCertificate->isGeneric());
    }
}
