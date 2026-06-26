<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingPathEnrollmentSyncService
{
    private const ORIGIN_PATHWAY = 'pathway';

    private const ORIGIN_DIRECT = 'direct';

    public function __construct(
        private readonly SyncCourseModuleProgresses $syncCourseModuleProgresses,
    ) {}

    public function syncAllEnrollmentsForPath(TrainingPath $trainingPath): void
    {
        $trainingPath->loadMissing('courses:id,status');

        TrainingPathEnrollment::query()
            ->whereBelongsTo($trainingPath, 'trainingPath')
            ->get()
            ->each(fn (TrainingPathEnrollment $enrollment): bool => $this->syncEnrollment($enrollment));
    }

    public function syncAllEnrollmentsForUser(int $userId): void
    {
        TrainingPathEnrollment::query()
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->get()
            ->each(fn (TrainingPathEnrollment $enrollment): bool => $this->syncEnrollment($enrollment));
    }

    public function syncEnrollment(TrainingPathEnrollment $enrollment): bool
    {
        return DB::transaction(function () use ($enrollment): bool {
            $enrollment->loadMissing([
                'user:id',
                'trainingPath:id,enforce_course_order,status',
                'trainingPath.courses:id,title,status,visible_to_all',
            ]);

            $trainingPath = $enrollment->trainingPath;
            $user = $enrollment->user;

            if ($trainingPath === null || $user === null) {
                return false;
            }

            $orderedCourses = $trainingPath->courses
                ->where('status', 'published')
                ->values();

            $this->ensureCourseEnrollments($user->getKey(), $orderedCourses, self::ORIGIN_PATHWAY);

            $currentCourseId = $trainingPath->enforce_course_order
                ? $this->resolveCurrentCourseId($user->getKey(), $orderedCourses)
                : null;

            $enrollment->forceFill([
                'current_course_id' => $currentCourseId,
            ])->save();

            return true;
        });
    }

    /**
     * @param  Collection<int, int>  $courseIds
     */
    public function countActiveCourseEnrollmentsForPathUsers(TrainingPath $trainingPath, Collection $courseIds): int
    {
        return (int) $this->deletableCourseEnrollmentCountsForPathUsers($trainingPath, $courseIds)->sum();
    }

    /**
     * @param  Collection<int, int>  $courseIds
     * @return Collection<int, int>
     */
    public function activeCourseEnrollmentCountsForPathUsers(TrainingPath $trainingPath, Collection $courseIds): Collection
    {
        return $this->deletableCourseEnrollmentCountsForPathUsers($trainingPath, $courseIds);
    }

    /**
     * @param  Collection<int, int>  $courseIds
     * @return Collection<int, int>
     */
    public function deletableCourseEnrollmentCountsForPathUsers(TrainingPath $trainingPath, Collection $courseIds): Collection
    {
        $normalizedCourseIds = $courseIds
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->unique()
            ->values();

        if ($normalizedCourseIds->isEmpty()) {
            return collect();
        }

        $pathUserIds = $this->activeTrainingPathUserIds($trainingPath);

        if ($pathUserIds->isEmpty()) {
            return collect();
        }

        return CourseEnrollment::query()
            ->whereIn('course_id', $normalizedCourseIds->all())
            ->whereIn('user_id', $pathUserIds->all())
            ->whereNull('deleted_at')
            ->where('pathway_origin', true)
            ->where('direct_origin', false)
            ->where('status', '!=', CourseEnrollment::STATUS_COMPLETED)
            ->selectRaw('course_id, COUNT(*) as aggregate')
            ->groupBy('course_id')
            ->pluck('aggregate', 'course_id')
            ->mapWithKeys(fn (mixed $count, mixed $courseId): array => [(int) $courseId => (int) $count]);
    }

    /**
     * @param  Collection<int, int>  $courseIds
     */
    public function softDeleteCourseEnrollmentsForPathUsers(TrainingPath $trainingPath, Collection $courseIds): int
    {
        return $this->unsetPathwayOriginAndDeleteIfNeededForPathUsers($trainingPath, $courseIds);
    }

    /**
     * @param  Collection<int, int>  $courseIds
     */
    public function unsetPathwayOriginAndDeleteIfNeededForPathUsers(TrainingPath $trainingPath, Collection $courseIds): int
    {
        $normalizedCourseIds = $courseIds
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->unique()
            ->values();

        if ($normalizedCourseIds->isEmpty()) {
            return 0;
        }

        $pathUserIds = $this->activeTrainingPathUserIds($trainingPath);

        if ($pathUserIds->isEmpty()) {
            return 0;
        }

        $enrollments = CourseEnrollment::query()
            ->whereIn('course_id', $normalizedCourseIds->all())
            ->whereIn('user_id', $pathUserIds->all())
            ->whereNull('deleted_at')
            ->get();

        $deletedCount = 0;

        foreach ($enrollments as $enrollment) {
            $enrollment->forceFill([
                'pathway_origin' => false,
            ])->save();

            if (! $enrollment->direct_origin && $enrollment->status !== CourseEnrollment::STATUS_COMPLETED) {
                $enrollment->delete();
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * @param  Collection<int, int>  $courseIds
     */
    public function unsetPathwayOriginAndDeleteIfNeededForUser(TrainingPath $trainingPath, int $userId, Collection $courseIds): int
    {
        $normalizedCourseIds = $courseIds
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->unique()
            ->values();

        if ($normalizedCourseIds->isEmpty()) {
            return 0;
        }

        $otherActivePathwayCountsByCourseId = DB::table('training_path_user')
            ->join('training_path_course', 'training_path_course.training_path_id', '=', 'training_path_user.training_path_id')
            ->where('training_path_user.user_id', $userId)
            ->whereNull('training_path_user.deleted_at')
            ->where('training_path_user.training_path_id', '!=', (int) $trainingPath->getKey())
            ->whereIn('training_path_course.course_id', $normalizedCourseIds->all())
            ->selectRaw('training_path_course.course_id, COUNT(*) as aggregate')
            ->groupBy('training_path_course.course_id')
            ->pluck('aggregate', 'training_path_course.course_id')
            ->mapWithKeys(fn (mixed $count, mixed $courseId): array => [(int) $courseId => (int) $count]);

        $enrollments = CourseEnrollment::query()
            ->where('user_id', $userId)
            ->whereIn('course_id', $normalizedCourseIds->all())
            ->whereNull('deleted_at')
            ->get();

        $deletedCount = 0;

        foreach ($enrollments as $enrollment) {
            $hasOtherPathwayOrigins = ((int) $otherActivePathwayCountsByCourseId->get((int) $enrollment->course_id, 0)) > 0;

            $enrollment->forceFill([
                'pathway_origin' => $hasOtherPathwayOrigins,
            ])->save();

            if (! $enrollment->direct_origin && ! $hasOtherPathwayOrigins && $enrollment->status !== CourseEnrollment::STATUS_COMPLETED) {
                $enrollment->delete();
                $deletedCount++;
            }
        }

        return $deletedCount;
    }

    /**
     * @return Collection<int, int>
     */
    private function activeTrainingPathUserIds(TrainingPath $trainingPath): Collection
    {
        return TrainingPathEnrollment::query()
            ->whereBelongsTo($trainingPath, 'trainingPath')
            ->whereNull('deleted_at')
            ->pluck('user_id')
            ->map(fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, Course>  $orderedCourses
     */
    private function ensureCourseEnrollments(int $userId, Collection $orderedCourses, string $origin): void
    {
        $user = User::query()->findOrFail($userId);

        $orderedCourses->each(function (Course $course) use ($origin, $user): void {
            if ($course->enrollmentVisibilityMessageFor($user) !== null) {
                return;
            }

            $activeEnrollment = CourseEnrollment::query()
                ->where('user_id', $user->getKey())
                ->where('course_id', $course->getKey())
                ->first();

            if ($activeEnrollment !== null) {
                $activeEnrollment->mergeOrigins(
                    $origin === self::ORIGIN_DIRECT,
                    $origin === self::ORIGIN_PATHWAY,
                );

                return;
            }

            $trashedEnrollment = CourseEnrollment::withTrashed()
                ->where('user_id', $user->getKey())
                ->where('course_id', $course->getKey())
                ->whereNotNull('deleted_at')
                ->orderByDesc('id')
                ->first();

            if ($trashedEnrollment !== null) {
                $trashedEnrollment->restore();
                $trashedEnrollment->mergeOrigins(
                    $origin === self::ORIGIN_DIRECT,
                    $origin === self::ORIGIN_PATHWAY,
                );
            } else {
                CourseEnrollment::enroll(
                    $user,
                    $course,
                    directOrigin: $origin === self::ORIGIN_DIRECT,
                    pathwayOrigin: $origin === self::ORIGIN_PATHWAY,
                );
            }

            $this->syncCourseModuleProgresses->handle($course);
        });
    }

    /**
     * @param  Collection<int, Course>  $orderedCourses
     */
    private function resolveCurrentCourseId(int $userId, Collection $orderedCourses): ?int
    {
        if ($orderedCourses->isEmpty()) {
            return null;
        }

        $enrollmentsByCourseId = CourseEnrollment::query()
            ->where('user_id', $userId)
            ->whereIn('course_id', $orderedCourses->pluck('id')->all())
            ->get(['course_id', 'status'])
            ->keyBy(fn (CourseEnrollment $enrollment): int => (int) $enrollment->course_id);

        $firstIncompleteCourse = $orderedCourses->first(function (Course $course) use ($enrollmentsByCourseId): bool {
            $courseEnrollment = $enrollmentsByCourseId->get((int) $course->getKey());

            if (! $courseEnrollment instanceof CourseEnrollment) {
                return true;
            }

            return $courseEnrollment->status !== CourseEnrollment::STATUS_COMPLETED;
        });

        return $firstIncompleteCourse?->getKey();
    }
}
