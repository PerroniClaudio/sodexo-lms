<?php

namespace App\Actions;

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassTeacher;
use App\Models\CourseTeacherEnrollment;
use App\Models\Module;
use App\Models\ModuleTeacherEnrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SyncCourseClassTeachers
{
    /**
     * @param  array<int, int>  $teacherIds
     */
    public function handle(CourseClass $courseClass, array $teacherIds): void
    {
        DB::transaction(function () use ($courseClass, $teacherIds): void {
            $courseClass->loadMissing('module');

            User::query()
                ->whereKey($teacherIds)
                ->get()
                ->each(function (User $teacher) use ($courseClass): void {
                    $this->ensureClassAssignment($courseClass, $teacher);
                    $this->ensureCourseAssignment($courseClass, $teacher);
                    $this->ensureModuleAssignments($courseClass, $teacher);
                });
        });
    }

    private function ensureClassAssignment(CourseClass $courseClass, User $teacher): void
    {
        $assignment = CourseClassTeacher::withTrashed()
            ->where('course_class_id', $courseClass->getKey())
            ->where('user_id', $teacher->getKey())
            ->first();

        if ($assignment === null) {
            CourseClassTeacher::query()->create([
                'course_class_id' => $courseClass->getKey(),
                'user_id' => $teacher->getKey(),
                'assigned_at' => now(),
            ]);

            return;
        }

        if ($assignment->trashed()) {
            $assignment->restore();
            $assignment->forceFill(['assigned_at' => now()])->save();
        }
    }

    private function ensureCourseAssignment(CourseClass $courseClass, User $teacher): void
    {
        $course = $courseClass->module?->course;

        if (! $course instanceof Course) {
            return;
        }

        $assignment = CourseTeacherEnrollment::withTrashed()
            ->where('course_id', $course->getKey())
            ->where('user_id', $teacher->getKey())
            ->first();

        if ($assignment === null) {
            CourseTeacherEnrollment::query()->create([
                'course_id' => $course->getKey(),
                'user_id' => $teacher->getKey(),
                'assigned_at' => now(),
            ]);

            return;
        }

        if ($assignment->trashed()) {
            $assignment->restore();
            $assignment->forceFill(['assigned_at' => now()])->save();
        }
    }

    private function ensureModuleAssignments(CourseClass $courseClass, User $teacher): void
    {
        $module = $courseClass->module;

        if (! $module instanceof Module || ! in_array($module->type, [Module::TYPE_LIVE, Module::TYPE_RESIDENTIAL], true)) {
            return;
        }

        $assignment = ModuleTeacherEnrollment::withTrashed()
            ->where('module_id', $module->getKey())
            ->where('user_id', $teacher->getKey())
            ->first();

        if ($assignment === null) {
            ModuleTeacherEnrollment::query()->create([
                'module_id' => $module->getKey(),
                'user_id' => $teacher->getKey(),
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
