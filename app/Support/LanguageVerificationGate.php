<?php

namespace App\Support;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LanguageLevel;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LanguageVerificationGate
{
    /**
     * @return array{
     *     required_level: LanguageLevel,
     *     verification_course: Course,
     *     verification_enrollment: CourseEnrollment
     * }|null
     */
    public function resolveBlockedEnrollment(CourseEnrollment $enrollment): ?array
    {
        $enrollment->loadMissing([
            'user.verifiedLanguageLevel',
            'course.requiredLanguageLevel',
        ]);

        $course = $enrollment->course;
        $user = $enrollment->user;

        if (! $course instanceof Course || ! $user instanceof User) {
            return null;
        }

        if (! $user->needs_language_level_verification || $course->isLanguageVerificationCourse()) {
            return null;
        }

        $requiredLevel = $course->requiredLanguageLevel;

        if (! $requiredLevel instanceof LanguageLevel || $user->hasVerifiedLanguageLevelFor($requiredLevel)) {
            return null;
        }

        $verificationCourse = Course::query()
            ->where('is_language_verification_course', true)
            ->where('grants_language_level_id', $requiredLevel->getKey())
            ->where('status', 'published')
            ->orderBy('id')
            ->first();

        if (! $verificationCourse instanceof Course) {
            return null;
        }

        $verificationEnrollment = $this->ensureVerificationEnrollment(
            user: $user,
            verificationCourse: $verificationCourse,
            originCourse: $course,
        );

        return [
            'required_level' => $requiredLevel,
            'verification_course' => $verificationCourse,
            'verification_enrollment' => $verificationEnrollment,
        ];
    }

    public function syncVerifiedLanguageLevelFromEnrollment(CourseEnrollment $enrollment): void
    {
        $enrollment->loadMissing([
            'course.grantsLanguageLevel',
            'user.verifiedLanguageLevel',
        ]);

        $course = $enrollment->course;
        $user = $enrollment->user;

        if (! $course instanceof Course || ! $user instanceof User || ! $course->isLanguageVerificationCourse()) {
            return;
        }

        $grantedLevel = $course->grantsLanguageLevel;

        if (! $grantedLevel instanceof LanguageLevel) {
            return;
        }

        $currentVerifiedLevel = $user->verifiedLanguageLevel;

        if ($currentVerifiedLevel instanceof LanguageLevel && $currentVerifiedLevel->sort_order >= $grantedLevel->sort_order) {
            return;
        }

        $user->forceFill([
            'verified_language_level_id' => $grantedLevel->getKey(),
        ])->save();
    }

    private function ensureVerificationEnrollment(User $user, Course $verificationCourse, Course $originCourse): CourseEnrollment
    {
        return DB::transaction(function () use ($user, $verificationCourse, $originCourse): CourseEnrollment {
            $activeEnrollment = CourseEnrollment::query()
                ->where('user_id', $user->getKey())
                ->where('course_id', $verificationCourse->getKey())
                ->first();

            if ($activeEnrollment === null) {
                $activeEnrollment = CourseEnrollment::enroll($user, $verificationCourse);
            }

            $activeEnrollment->forceFill([
                'origin_course_id' => $originCourse->getKey(),
            ])->save();

            return $activeEnrollment->fresh(['course', 'user']);
        });
    }
}
