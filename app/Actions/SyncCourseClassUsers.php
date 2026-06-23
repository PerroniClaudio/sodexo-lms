<?php

namespace App\Actions;

use App\Models\CourseClass;
use App\Models\CourseClassUser;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncCourseClassUsers
{
    /**
     * @param  array<int, int>  $userIds
     */
    public function handle(CourseClass $courseClass, array $userIds): void
    {
        DB::transaction(function () use ($courseClass, $userIds): void {
            $courseClass->loadMissing('module.course');

            User::query()
                ->whereKey($userIds)
                ->get()
                ->each(function (User $user) use ($courseClass): void {
                    $this->ensureCourseEnrollment($courseClass, $user);
                    $this->ensureClassAssignment($courseClass, $user);
                });
        });
    }

    private function ensureCourseEnrollment(CourseClass $courseClass, User $user): void
    {
        $course = $courseClass->module?->course;

        if ($course === null) {
            return;
        }

        $existingEnrollment = CourseEnrollment::withTrashed()
            ->where('course_id', $course->getKey())
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->first();

        if ($existingEnrollment === null) {
            CourseEnrollment::enroll($user, $course, directOrigin: true, pathwayOrigin: false);

            return;
        }

        if ($existingEnrollment->trashed()) {
            $existingEnrollment->restore();
            $existingEnrollment->mergeOrigins(true, (bool) $existingEnrollment->pathway_origin);
        } else {
            $existingEnrollment->mergeOrigins(true, (bool) $existingEnrollment->pathway_origin);
        }
    }

    private function ensureClassAssignment(CourseClass $courseClass, User $user): void
    {
        $assignment = CourseClassUser::withTrashed()
            ->where('course_class_id', $courseClass->getKey())
            ->where('user_id', $user->getKey())
            ->first();

        if ($assignment === null) {
            CourseClassUser::query()->create([
                'course_class_id' => $courseClass->getKey(),
                'user_id' => $user->getKey(),
                'assigned_at' => now(),
            ]);

            return;
        }

        if ($assignment->trashed()) {
            $assignment->restore();
            $assignment->forceFill(['assigned_at' => now()])->save();
        }
    }
}
