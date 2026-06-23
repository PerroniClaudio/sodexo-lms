<?php

namespace App\Observers;

use App\Models\CourseEnrollment;
use App\Services\Certificates\CourseCertificateGenerator;
use App\Services\CourseRiskRequirementService;
use App\Services\TrainingPathEnrollmentSyncService;
use App\Support\LanguageVerificationGate;
use Illuminate\Support\Facades\DB;

class CourseEnrollmentObserver
{
    public function created(CourseEnrollment $courseEnrollment): void
    {
        $this->dispatchTrainingPathSync($courseEnrollment);
        $this->dispatchCertificateGenerationIfCompleted($courseEnrollment);
    }

    public function updated(CourseEnrollment $courseEnrollment): void
    {
        if ($this->shouldSyncTrainingPaths($courseEnrollment)) {
            $this->dispatchTrainingPathSync($courseEnrollment);
        }

        if (! $this->wasCompletedNow($courseEnrollment)) {
            return;
        }

        $this->dispatchCertificateGenerationIfCompleted($courseEnrollment);
    }

    public function deleted(CourseEnrollment $courseEnrollment): void
    {
        $this->dispatchTrainingPathSync($courseEnrollment);
    }

    public function restored(CourseEnrollment $courseEnrollment): void
    {
        $this->dispatchTrainingPathSync($courseEnrollment);
    }

    private function dispatchCertificateGenerationIfCompleted(CourseEnrollment $courseEnrollment): void
    {
        if (! $this->isCompleted($courseEnrollment)) {
            return;
        }

        $courseEnrollmentId = (int) $courseEnrollment->getKey();

        DB::afterCommit(function () use ($courseEnrollmentId): void {
            $enrollment = CourseEnrollment::query()->find($courseEnrollmentId);

            if ($enrollment === null) {
                return;
            }

            app(LanguageVerificationGate::class)->syncVerifiedLanguageLevelFromEnrollment($enrollment);
            app(CourseCertificateGenerator::class)->generateForEnrollment($enrollment);
            app(CourseRiskRequirementService::class)->syncCertificatesForEnrollment($enrollment);
        });
    }

    private function wasCompletedNow(CourseEnrollment $courseEnrollment): bool
    {
        if (! $this->isCompleted($courseEnrollment)) {
            return false;
        }

        return ($courseEnrollment->wasChanged('status')
                && $courseEnrollment->status === CourseEnrollment::STATUS_COMPLETED)
            || ($courseEnrollment->wasChanged('completed_at')
                && $courseEnrollment->completed_at !== null);
    }

    private function isCompleted(CourseEnrollment $courseEnrollment): bool
    {
        return $courseEnrollment->status === CourseEnrollment::STATUS_COMPLETED
            || $courseEnrollment->completed_at !== null;
    }

    private function dispatchTrainingPathSync(CourseEnrollment $courseEnrollment): void
    {
        $userId = (int) $courseEnrollment->user_id;

        DB::afterCommit(function () use ($userId): void {
            app(TrainingPathEnrollmentSyncService::class)->syncAllEnrollmentsForUser($userId);
        });
    }

    private function shouldSyncTrainingPaths(CourseEnrollment $courseEnrollment): bool
    {
        return $this->wasCompletedNow($courseEnrollment)
            || $courseEnrollment->wasChanged('direct_origin')
            || $courseEnrollment->wasChanged('pathway_origin');
    }
}
