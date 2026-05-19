<?php

namespace App\Services;

use App\Models\CourseClass;
use App\Models\Module;
use App\Models\User;
use Carbon\CarbonInterface;

class CourseClassScheduleResolver
{
    public function forUser(Module $module, User $user): ?CourseClass
    {
        if (! $this->usesClassSchedule($module)) {
            return null;
        }

        return $this->resolveClassForAssignment($module, 'userAssignments', $user);
    }

    public function forTeacher(Module $module, User $user): ?CourseClass
    {
        if (! $this->usesClassSchedule($module)) {
            return null;
        }

        return $this->resolveClassForAssignment($module, 'teacherAssignments', $user);
    }

    public function effectiveStartsAt(Module $module, User $user): ?CarbonInterface
    {
        return $this->resolveClass($module, $user)?->scheduledStartAt()
            ?? $module->appointment_start_time;
    }

    public function effectiveEndsAt(Module $module, User $user): ?CarbonInterface
    {
        return $this->resolveClass($module, $user)?->scheduledEndAt()
            ?? $module->appointment_end_time;
    }

    private function resolveClass(Module $module, User $user): ?CourseClass
    {
        if ($user->hasRole('teacher')) {
            return $this->forTeacher($module, $user);
        }

        if ($user->hasRole('user')) {
            return $this->forUser($module, $user);
        }

        return null;
    }

    private function usesClassSchedule(Module $module): bool
    {
        $module->loadMissing('course');

        return $module->course?->supportsClasses() === true
            && in_array($module->type, [Module::TYPE_LIVE, Module::TYPE_RESIDENTIAL], true);
    }

    private function resolveClassForAssignment(Module $module, string $relation, User $user): ?CourseClass
    {
        return $module->classes()
            ->with('schedules')
            ->whereHas($relation, fn ($query) => $query->where('user_id', $user->getKey()))
            ->get()
            ->sortBy(function (CourseClass $courseClass): array {
                $schedule = $courseClass->resolvedSchedule();

                return [
                    $schedule?->starts_at?->timestamp ?? PHP_INT_MAX,
                    $courseClass->getKey(),
                ];
            })
            ->first();
    }
}
