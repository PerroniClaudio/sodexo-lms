<?php

namespace App\Actions;

use App\Models\CourseClass;
use App\Models\CourseClassTeacher;
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
