<?php

namespace App\Http\Controllers;

use App\Http\Requests\ScormRuntimeInitializeRequest;
use App\Http\Requests\ScormRuntimeValueRequest;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ScormPackage;
use App\Models\ScormSession;
use App\Services\ScormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ScormRuntimeController extends Controller
{
    public function initialize(
        ScormRuntimeInitializeRequest $request,
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
        ScormService $scormService,
    ): JsonResponse {
        [$moduleProgress] = $this->resolveContext($request, $course, $module, $scormPackage);

        $snapshot = $scormService->initializeRuntime(
            $request->user(),
            $scormPackage,
            $moduleProgress,
            $request->validated('sco_identifier'),
            $request->validated('session_id'),
        );

        return response()->json([
            'success' => true,
            'error' => '0',
            'state' => $snapshot,
        ]);
    }

    public function getValue(
        ScormRuntimeValueRequest $request,
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
        ScormService $scormService,
    ): JsonResponse {
        [$moduleProgress] = $this->resolveContext($request, $course, $module, $scormPackage);
        $element = (string) $request->validated('element');
        $snapshot = $scormService->getRuntimeSnapshot(
            $request->user(),
            $scormPackage,
            $request->validated('sco_identifier'),
            $moduleProgress,
        );

        return response()->json([
            'success' => true,
            'error' => '0',
            'value' => $snapshot[$element] ?? '',
        ]);
    }

    public function setValue(
        ScormRuntimeValueRequest $request,
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
        ScormService $scormService,
    ): JsonResponse {
        [$moduleProgress] = $this->resolveContext($request, $course, $module, $scormPackage);

        $scormService->persistTrackingValue(
            $request->user(),
            $scormPackage,
            $request->validated('sco_identifier'),
            (string) $request->validated('element'),
            $request->validated('value'),
            $request->validated('session_id'),
            $moduleProgress,
        );

        return response()->json([
            'success' => true,
            'error' => '0',
        ]);
    }

    public function commit(
        ScormRuntimeValueRequest $request,
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
        ScormService $scormService,
    ): JsonResponse {
        [$moduleProgress] = $this->resolveContext($request, $course, $module, $scormPackage);

        $snapshot = $scormService->commitRuntime(
            $request->user(),
            $scormPackage,
            $moduleProgress,
            $request->validated('sco_identifier'),
            $request->validated('session_id'),
            $request->validated('values', []),
        );

        return response()->json([
            'success' => true,
            'error' => '0',
            'state' => $snapshot,
        ]);
    }

    public function terminate(
        ScormRuntimeValueRequest $request,
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
        ScormService $scormService,
    ): JsonResponse {
        [$moduleProgress] = $this->resolveContext($request, $course, $module, $scormPackage);

        $snapshot = $scormService->terminateRuntime(
            $request->user(),
            $scormPackage,
            $moduleProgress,
            $request->validated('sco_identifier'),
            $request->validated('session_id'),
            $request->validated('values', []),
        );

        return response()->json([
            'success' => true,
            'error' => '0',
            'state' => $snapshot,
        ]);
    }

    public function getLastError(
        ScormRuntimeValueRequest $request,
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
    ): JsonResponse {
        $this->resolveContext($request, $course, $module, $scormPackage);

        $lastError = ScormSession::query()
            ->where('session_id', $request->validated('session_id'))
            ->where('user_id', $request->user()->getKey())
            ->where('scorm_package_id', $scormPackage->getKey())
            ->value('last_error_code') ?? '0';

        return response()->json([
            'error' => $lastError,
        ]);
    }

    public function getErrorString(
        ScormRuntimeValueRequest $request,
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
        ScormService $scormService,
    ): JsonResponse {
        $this->resolveContext($request, $course, $module, $scormPackage);
        $code = $request->validated('code', '0');

        return response()->json([
            'error' => $code,
            'value' => $scormService->getErrorString($code),
        ]);
    }

    public function getDiagnostic(
        ScormRuntimeValueRequest $request,
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
        ScormService $scormService,
    ): JsonResponse {
        $this->resolveContext($request, $course, $module, $scormPackage);
        $code = $request->validated('code', '0');

        return response()->json([
            'error' => $code,
            'value' => $scormService->getDiagnostic($code),
        ]);
    }

    /**
     * @return array{ModuleProgress, CourseEnrollment}
     */
    private function resolveContext(
        Request $request,
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
    ): array {
        abort_unless($module->belongsTo === (string) $course->getKey(), 404);
        abort_unless($module->isScorm(), 404);
        abort_unless($scormPackage->course_id === $course->getKey(), 404);
        abort_unless($scormPackage->module_id === $module->getKey(), 404);
        abort_unless($scormPackage->isReady(), Response::HTTP_CONFLICT);

        $enrollment = CourseEnrollment::query()
            ->where('course_id', $course->getKey())
            ->where('user_id', $request->user()->getKey())
            ->whereNull('deleted_at')
            ->firstOrFail();

        abort_unless((int) $enrollment->current_module_id === (int) $module->getKey(), Response::HTTP_FORBIDDEN);

        $moduleProgress = $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->firstOrFail();

        abort_unless($moduleProgress->status !== ModuleProgress::STATUS_LOCKED, Response::HTTP_FORBIDDEN);

        return [$moduleProgress, $enrollment];
    }
}
