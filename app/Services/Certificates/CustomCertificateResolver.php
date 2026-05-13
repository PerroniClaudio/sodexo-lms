<?php

namespace App\Services\Certificates;

use App\Models\Course;
use App\Models\CustomCertificate;

class CustomCertificateResolver
{
    public function resolve(string $type, Course $course): ?CustomCertificate
    {
        $certificate = CustomCertificate::query()
            ->active()
            ->ofType($type)
            ->get()
            ->first(fn (CustomCertificate $customCertificate): bool => ! $customCertificate->isGeneric()
                && $customCertificate->supportsCourse((int) $course->getKey()));

        if ($certificate !== null) {
            return $certificate;
        }

        return CustomCertificate::query()
            ->active()
            ->ofType($type)
            ->get()
            ->first(fn (CustomCertificate $customCertificate): bool => $customCertificate->isGeneric());
    }
}
