<?php

namespace App\Services;

use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use Carbon\CarbonInterface;

class QuizAccessDelayService
{
    /**
     * @return array{
     *     active: bool,
     *     delay_minutes: int,
     *     previous_module_id: int,
     *     previous_module_title: string,
     *     previous_completed_at: string,
     *     available_at: string,
     *     remaining_seconds: int
     * }|null
     */
    public function resolve(CourseEnrollment $enrollment, Module $module): ?array
    {
        if (! $module->isQuiz() || ! $module->hasAccessDelay()) {
            return null;
        }

        $previousModule = $module->course()
            ->first()?->modules()
            ->where('order', '<', $module->order)
            ->orderByDesc('order')
            ->first();

        if (! $previousModule instanceof Module) {
            return null;
        }

        /** @var ModuleProgress|null $previousProgress */
        $previousProgress = $enrollment->moduleProgresses()
            ->where('module_id', $previousModule->getKey())
            ->first();

        $completedAt = $previousProgress?->completed_at;

        if (! $completedAt instanceof CarbonInterface) {
            return null;
        }

        $availableAt = $completedAt->copy()->addMinutes($module->access_delay_minutes ?? 0);
        $remainingSeconds = max(0, now()->diffInSeconds($availableAt, false));

        return [
            'active' => $remainingSeconds > 0,
            'delay_minutes' => (int) $module->access_delay_minutes,
            'previous_module_id' => (int) $previousModule->getKey(),
            'previous_module_title' => $previousModule->title,
            'previous_completed_at' => $completedAt->toIso8601String(),
            'available_at' => $availableAt->toIso8601String(),
            'remaining_seconds' => $remainingSeconds,
        ];
    }
}
