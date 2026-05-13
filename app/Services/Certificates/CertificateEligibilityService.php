<?php

namespace App\Services\Certificates;

use App\Models\CourseEnrollment;
use App\Models\CustomCertificate;
use App\Models\Module;
use App\Models\ModuleProgress;

class CertificateEligibilityService
{
    public function isEligible(CourseEnrollment $enrollment, string $type): bool
    {
        $enrollment->loadMissing('course.modules', 'moduleProgresses');

        if ($enrollment->status !== CourseEnrollment::STATUS_COMPLETED) {
            return false;
        }

        $satisfactionModule = $enrollment->course->modules
            ->first(fn (Module $module): bool => $module->type === 'satisfaction_quiz');

        if ($satisfactionModule === null) {
            return false;
        }

        $satisfactionProgress = $enrollment->moduleProgresses
            ->first(fn (ModuleProgress $progress): bool => (int) $progress->module_id === (int) $satisfactionModule->getKey());

        if ($satisfactionProgress?->status !== ModuleProgress::STATUS_COMPLETED) {
            return false;
        }

        if ($type === CustomCertificate::TYPE_PARTICIPATION) {
            return true;
        }

        $learningQuizIds = $enrollment->course->modules
            ->filter(fn (Module $module): bool => $module->type === 'learning_quiz')
            ->pluck('id');

        if ($learningQuizIds->isEmpty()) {
            return true;
        }

        $learningQuizProgresses = $enrollment->moduleProgresses
            ->whereIn('module_id', $learningQuizIds)
            ->values();

        if ($learningQuizProgresses->count() !== $learningQuizIds->count()) {
            return false;
        }

        return $learningQuizProgresses
            ->every(fn (ModuleProgress $progress): bool => $progress->status === ModuleProgress::STATUS_COMPLETED
                && $progress->passed_at !== null);
    }
}
