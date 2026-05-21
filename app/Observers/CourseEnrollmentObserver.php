<?php

namespace App\Observers;

use App\Models\CourseEnrollment;
use App\Services\Certificates\CourseCertificateGenerator;
use Illuminate\Support\Facades\DB;

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

        $courseEnrollmentId = (int) $courseEnrollment->getKey();

        DB::afterCommit(function () use ($courseEnrollmentId): void {
            $enrollment = CourseEnrollment::query()->find($courseEnrollmentId);

            if ($enrollment === null) {
                return;
            }

            app(CourseCertificateGenerator::class)->generateForEnrollment($enrollment);
        });
    }
}
