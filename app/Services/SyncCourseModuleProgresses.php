<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SyncCourseModuleProgresses
{
    public function handle(Course $course): void
    {
        DB::transaction(function () use ($course): void {
            $course->refresh()->load('modules');

            CourseEnrollment::query()
                ->where('course_id', $course->getKey())
                ->get()
                ->each(fn (CourseEnrollment $enrollment): bool => $this->syncEnrollment($course, $enrollment));
        });
    }

    private function syncEnrollment(Course $course, CourseEnrollment $enrollment): bool
    {
        /** @var Collection<int, Module> $modules */
        $modules = $course->modules->sortBy('order')->values();
        $moduleIds = $modules
            ->pluck('id')
            ->map(fn (mixed $moduleId): int => (int) $moduleId)
            ->values();

        if ($moduleIds->isEmpty()) {
            $enrollment->moduleProgresses()->delete();
            $enrollment->refreshCurrentModulePointer();
            $enrollment->syncProgressState();

            return true;
        }

        $enrollment->moduleProgresses()
            ->whereNotIn('module_id', $moduleIds->all())
            ->delete();

        /** @var Collection<int, ModuleProgress> $progressesByModuleId */
        $progressesByModuleId = $enrollment->moduleProgresses()
            ->get()
            ->keyBy(fn (ModuleProgress $progress): int => (int) $progress->module_id);

        $firstPendingResolved = false;

        $modules->each(function (Module $module) use ($enrollment, $progressesByModuleId, &$firstPendingResolved): void {
            $progress = $progressesByModuleId->get((int) $module->getKey());

            if (! $progress instanceof ModuleProgress) {
                $progress = $enrollment->moduleProgresses()->create([
                    'module_id' => $module->getKey(),
                    'status' => ModuleProgress::STATUS_LOCKED,
                ]);

                $progressesByModuleId->put((int) $module->getKey(), $progress);
            }

            if ($progress->status === ModuleProgress::STATUS_COMPLETED) {
                return;
            }

            $desiredStatus = $firstPendingResolved
                ? ModuleProgress::STATUS_LOCKED
                : $this->firstPendingStatus($progress);

            if ($progress->status !== $desiredStatus) {
                $progress->forceFill([
                    'status' => $desiredStatus,
                ])->save();
            }

            $firstPendingResolved = true;
        });

        $enrollment->refreshCurrentModulePointer();
        $enrollment->syncProgressState();

        return true;
    }

    private function firstPendingStatus(ModuleProgress $progress): string
    {
        return match ($progress->status) {
            ModuleProgress::STATUS_IN_PROGRESS => ModuleProgress::STATUS_IN_PROGRESS,
            ModuleProgress::STATUS_FAILED => ModuleProgress::STATUS_FAILED,
            ModuleProgress::STATUS_AVAILABLE => ModuleProgress::STATUS_AVAILABLE,
            default => ModuleProgress::STATUS_AVAILABLE,
        };
    }
}
