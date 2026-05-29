<?php

namespace App\Jobs;

use App\Services\CourseRiskRequirementService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncCompletedCourseEnrollmentRiskRequirementCertificates implements ShouldQueue
{
    use Queueable;

    public function handle(CourseRiskRequirementService $courseRiskRequirementService): void
    {
        $courseRiskRequirementService->syncCertificatesForCompletedEnrollments();
    }
}
