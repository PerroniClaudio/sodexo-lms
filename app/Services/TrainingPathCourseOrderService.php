<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;

class TrainingPathCourseOrderService
{
    /**
     * @return array<int, array{message: string, current_course_id: int|null}>
     */
    public function locksByCourseId(User $user): array
    {
        /** @var Collection<int, TrainingPathEnrollment> $enrollments */
        $enrollments = TrainingPathEnrollment::query()
            ->where('user_id', $user->getKey())
            ->whereNull('deleted_at')
            ->with([
                'trainingPath:id,title,enforce_course_order,status',
                'trainingPath.courses:id,status,title',
            ])
            ->get();

        $directOriginCourseIds = CourseEnrollment::query()
            ->where('user_id', $user->getKey())
            ->where('direct_origin', true)
            ->whereNull('deleted_at')
            ->pluck('course_id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->flip();

        $locks = [];

        $enrollments->each(function (TrainingPathEnrollment $enrollment) use (&$locks, $directOriginCourseIds): void {
            $trainingPath = $enrollment->trainingPath;

            if ($trainingPath === null || ! $trainingPath->enforce_course_order || $trainingPath->status !== 'published') {
                return;
            }

            $orderedCourses = $trainingPath->courses
                ->where('status', 'published')
                ->values();
            $currentCourseId = $enrollment->current_course_id;

            if ($currentCourseId === null) {
                return;
            }

            $currentIndex = $orderedCourses->search(fn (Course $course): bool => (int) $course->getKey() === (int) $currentCourseId);

            if ($currentIndex === false) {
                return;
            }

            $currentCourse = $orderedCourses->get((int) $currentIndex);

            if (! $currentCourse instanceof Course) {
                return;
            }

            $orderedCourses
                ->slice(((int) $currentIndex) + 1)
                ->each(function (Course $course) use (&$locks, $currentCourse, $directOriginCourseIds): void {
                    $courseId = (int) $course->getKey();

                    $directEnrollmentExists = $directOriginCourseIds->has($courseId);

                    if ($directEnrollmentExists) {
                        return;
                    }

                    if (array_key_exists($courseId, $locks)) {
                        return;
                    }

                    $locks[$courseId] = [
                        'message' => __('Per proseguire in questo percorso devi seguire l\'ordine dei corsi. Completa prima ":course".', [
                            'course' => $currentCourse->title,
                        ]),
                        'current_course_id' => (int) $currentCourse->getKey(),
                    ];
                });
        });

        return $locks;
    }

    /**
     * @return array{message: string, current_course_id: int|null}|null
     */
    public function lockForCourse(User $user, Course $course): ?array
    {
        return $this->locksByCourseId($user)[(int) $course->getKey()] ?? null;
    }
}
