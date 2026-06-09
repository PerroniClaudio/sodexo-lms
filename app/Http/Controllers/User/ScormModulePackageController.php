<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Services\ScormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScormModulePackageController extends Controller
{
    public function index(
        Request $request,
        Course $course,
        Module $module,
        ScormService $scormService,
    ): JsonResponse {
        [$enrollment, $moduleProgress] = $this->resolveLearnerContext($request, $course, $module);

        return response()->json([
            'packages' => $scormService->getLearnerPackageSummaries($request->user(), $course, $module),
            'module_progress' => [
                'status' => $moduleProgress->status,
                'time_spent_seconds' => $moduleProgress->time_spent_seconds,
                'completed_at' => $moduleProgress->completed_at?->toIso8601String(),
                'completion_label' => $moduleProgress->completed_at?->format('d/m/Y H:i'),
            ],
            'enrollment' => [
                'status' => $enrollment->status,
                'completion_percentage' => $enrollment->completion_percentage,
            ],
        ]);
    }

    /**
     * @return array{CourseEnrollment, ModuleProgress}
     */
    private function resolveLearnerContext(Request $request, Course $course, Module $module): array
    {
        abort_unless($module->belongsTo === (string) $course->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless($module->isScorm(), Response::HTTP_NOT_FOUND);

        $enrollment = CourseEnrollment::query()
            ->where('course_id', $course->getKey())
            ->where('user_id', $request->user()->getKey())
            ->whereNull('deleted_at')
            ->firstOrFail();

        $moduleProgress = $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->firstOrFail();

        abort_unless($moduleProgress->status !== ModuleProgress::STATUS_LOCKED, Response::HTTP_FORBIDDEN);

        return [$enrollment, $moduleProgress];
    }
}
