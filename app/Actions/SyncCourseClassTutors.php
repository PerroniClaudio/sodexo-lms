<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassTutor;
use App\Models\CourseTutorEnrollment;
use App\Models\Module;
use App\Models\ModuleTutorEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncCourseClassTutors
{
    /**
     * @param  array<int, int>  $tutorIds
     */
    public function handle(CourseClass $courseClass, array $tutorIds): void
    {
        DB::transaction(function () use ($courseClass, $tutorIds): void {
            $courseClass->loadMissing('module');

            User::query()
                ->whereKey($tutorIds)
                ->get()
                ->each(function (User $tutor) use ($courseClass): void {
                    $this->ensureClassAssignment($courseClass, $tutor);
                    $this->ensureCourseAssignment($courseClass, $tutor);
                    $this->ensureModuleAssignments($courseClass, $tutor);
                });
        });
    }

    private function ensureClassAssignment(CourseClass $courseClass, User $tutor): void
    {
        $assignment = CourseClassTutor::withTrashed()
            ->where('course_class_id', $courseClass->getKey())
            ->where('user_id', $tutor->getKey())
            ->first();

        if ($assignment === null) {
            CourseClassTutor::query()->create([
                'course_class_id' => $courseClass->getKey(),
                'user_id' => $tutor->getKey(),
                'assigned_at' => now(),
            ]);

            return;
        }

        if ($assignment->trashed()) {
            $assignment->restore();
            $assignment->forceFill(['assigned_at' => now()])->save();
        }
    }

    private function ensureCourseAssignment(CourseClass $courseClass, User $tutor): void
    {
        $course = $courseClass->module?->course;

        if (! $course instanceof Course) {
            return;
        }

        $assignment = CourseTutorEnrollment::withTrashed()
            ->where('course_id', $course->getKey())
            ->where('user_id', $tutor->getKey())
            ->first();

        if ($assignment === null) {
            CourseTutorEnrollment::query()->create([
                'course_id' => $course->getKey(),
                'user_id' => $tutor->getKey(),
                'assigned_at' => now(),
            ]);

            return;
        }

        if ($assignment->trashed()) {
            $assignment->restore();
            $assignment->forceFill(['assigned_at' => now()])->save();
        }
    }

    private function ensureModuleAssignments(CourseClass $courseClass, User $tutor): void
    {
        $module = $courseClass->module;

        if (! $module instanceof Module || ! in_array($module->type, [Module::TYPE_LIVE, Module::TYPE_RESIDENTIAL], true)) {
            return;
        }

        $assignment = ModuleTutorEnrollment::withTrashed()
            ->where('module_id', $module->getKey())
            ->where('user_id', $tutor->getKey())
            ->first();

        if ($assignment === null) {
            ModuleTutorEnrollment::query()->create([
                'module_id' => $module->getKey(),
                'user_id' => $tutor->getKey(),
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
