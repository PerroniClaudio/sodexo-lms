<?php

namespace App\Observers;

use App\Jobs\GenerateCourseCertificate;
use App\Models\CourseEnrollment;

class CourseEnrollmentObserver
{
    public function created(CourseEnrollment $courseEnrollment): void
    {
        $this->dispatchCertificateGenerationIfCompleted($courseEnrollment);
    }

    public function updated(CourseEnrollment $courseEnrollment): void
    {
        if (! $courseEnrollment->wasChanged('status')) {
            return;
        }

        $this->dispatchCertificateGenerationIfCompleted($courseEnrollment);
    }

    private function dispatchCertificateGenerationIfCompleted(CourseEnrollment $courseEnrollment): void
    {
        if ($courseEnrollment->status !== CourseEnrollment::STATUS_COMPLETED) {
            return;
        }

        GenerateCourseCertificate::dispatch($courseEnrollment)->afterCommit();
    }
}
