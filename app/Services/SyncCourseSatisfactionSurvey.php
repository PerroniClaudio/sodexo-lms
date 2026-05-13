<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use Illuminate\Support\Facades\DB;

class SyncCourseSatisfactionSurvey
{
    public function handle(Course $course): void
    {
        DB::transaction(function () use ($course): void {
            $course->refresh();

            if (! $course->hasSatisfactionSurveyEnabled()) {
                $this->removeSurveyModules($course);

                return;
            }

            $surveyModule = $this->ensureSurveyModule($course);
            $this->ensureEnrollmentProgresses($course, $surveyModule);
        });
    }

    private function ensureSurveyModule(Course $course): Module
    {
        $course->loadMissing('modules');

        $surveyModule = $course->modules
            ->first(fn (Module $module): bool => $module->isSatisfactionQuiz());

        if ($surveyModule === null) {
            $surveyModule = $course->modules()->create([
                'title' => Module::defaultTitleForType(Module::TYPE_SATISFACTION_QUIZ),
                'description' => '',
                'type' => Module::TYPE_SATISFACTION_QUIZ,
                'order' => (int) $course->modules()->max('order') + 1,
                'appointment_date' => now(),
                'appointment_start_time' => now(),
                'appointment_end_time' => now()->addHour(),
                'status' => $course->status === 'published' ? 'published' : 'draft',
                'belongsTo' => (string) $course->getKey(),
            ]);
        }

        $lastOrder = (int) $course->modules()
            ->whereKeyNot($surveyModule->getKey())
            ->max('order');

        Module::query()
            ->whereKey($surveyModule->getKey())
            ->update([
                'title' => Module::defaultTitleForType(Module::TYPE_SATISFACTION_QUIZ),
                'order' => $lastOrder + 1,
            ]);

        $course->modules()
            ->where('type', Module::TYPE_SATISFACTION_QUIZ)
            ->whereKeyNot($surveyModule->getKey())
            ->get()
            ->each(function (Module $duplicateModule) use ($course): void {
                CourseEnrollment::query()
                    ->where('course_id', $course->getKey())
                    ->each(function (CourseEnrollment $enrollment) use ($duplicateModule): void {
                        $enrollment->moduleProgresses()
                            ->where('module_id', $duplicateModule->getKey())
                            ->delete();
                    });

                $duplicateModule->delete();
            });

        return $surveyModule->fresh();
    }

    private function ensureEnrollmentProgresses(Course $course, Module $surveyModule): void
    {
        $course->load('modules');

        CourseEnrollment::query()
            ->where('course_id', $course->getKey())
            ->get()
            ->each(function (CourseEnrollment $enrollment) use ($course, $surveyModule): void {
                $shouldUnlock = $course->modules
                    ->where('id', '!=', $surveyModule->getKey())
                    ->sortBy('order')
                    ->every(function (Module $module) use ($enrollment): bool {
                        return $enrollment->moduleProgresses()
                            ->where('module_id', $module->getKey())
                            ->value('status') === ModuleProgress::STATUS_COMPLETED;
                    });

                $progress = $enrollment->moduleProgresses()
                    ->where('module_id', $surveyModule->getKey())
                    ->first();

                if ($progress === null) {
                    $enrollment->moduleProgresses()->create([
                        'module_id' => $surveyModule->getKey(),
                        'status' => $shouldUnlock
                            ? ModuleProgress::STATUS_AVAILABLE
                            : ModuleProgress::STATUS_LOCKED,
                    ]);
                } elseif ($progress->status !== ModuleProgress::STATUS_COMPLETED) {
                    ModuleProgress::query()
                        ->whereKey($progress->getKey())
                        ->update([
                            'status' => $shouldUnlock
                                ? ModuleProgress::STATUS_AVAILABLE
                                : ModuleProgress::STATUS_LOCKED,
                        ]);
                }

                if ($shouldUnlock) {
                    $enrollment->forceFill([
                        'current_module_id' => $surveyModule->getKey(),
                    ])->save();
                }

                $enrollment->refreshCurrentModulePointer();
                $enrollment->syncProgressState();
            });
    }

    private function removeSurveyModules(Course $course): void
    {
        $surveyModules = $course->satisfactionModules()->get();

        if ($surveyModules->isEmpty()) {
            return;
        }

        $surveyModuleIds = $surveyModules->pluck('id');

        CourseEnrollment::query()
            ->where('course_id', $course->getKey())
            ->get()
            ->each(function (CourseEnrollment $enrollment) use ($surveyModuleIds): void {
                $enrollment->moduleProgresses()
                    ->whereIn('module_id', $surveyModuleIds)
                    ->delete();
            });

        $surveyModules->each(function (Module $module): void {
            $module->delete();
        });

        CourseEnrollment::query()
            ->where('course_id', $course->getKey())
            ->get()
            ->each(function (CourseEnrollment $enrollment): void {
                $enrollment->refreshCurrentModulePointer();
                $enrollment->syncProgressState();
            });
    }
}
