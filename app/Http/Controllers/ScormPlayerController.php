<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ScormPackage;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class ScormPlayerController extends Controller
{
    public function launch(Request $request, Course $course, Module $module, ScormPackage $scormPackage): RedirectResponse
    {
        [$enrollment, $moduleProgress] = $this->resolveLearnerContext($request, $course, $module, $scormPackage);

        if ($moduleProgress->status === ModuleProgress::STATUS_AVAILABLE) {
            $moduleProgress->start();
        } else {
            $moduleProgress->forceFill([
                'started_at' => $moduleProgress->started_at ?? now(),
                'last_accessed_at' => now(),
            ])->save();

            $enrollment->markAsInProgress();
        }

        return redirect()->route('user.courses.modules.scorm.player', [$course, $module, $scormPackage]);
    }

    public function player(Request $request, Course $course, Module $module, ScormPackage $scormPackage): View
    {
        [$enrollment, $moduleProgress] = $this->resolveLearnerContext($request, $course, $module, $scormPackage);

        return view('scorm.player', [
            'course' => $course,
            'module' => $module,
            'package' => $scormPackage,
            'enrollment' => $enrollment,
            'moduleProgress' => $moduleProgress,
            'scormPlayerConfig' => [
                'version' => $scormPackage->version,
                'entryPointUrl' => route('user.courses.modules.scorm.asset', [
                    'course' => $course,
                    'module' => $module,
                    'scormPackage' => $scormPackage,
                    'path' => $scormPackage->entry_point,
                ]),
                'runtime' => [
                    'initialize' => route('user.courses.modules.scorm.runtime.initialize', [$course, $module, $scormPackage]),
                    'getValue' => route('user.courses.modules.scorm.runtime.get-value', [$course, $module, $scormPackage]),
                    'setValue' => route('user.courses.modules.scorm.runtime.set-value', [$course, $module, $scormPackage]),
                    'commit' => route('user.courses.modules.scorm.runtime.commit', [$course, $module, $scormPackage]),
                    'terminate' => route('user.courses.modules.scorm.runtime.terminate', [$course, $module, $scormPackage]),
                    'getLastError' => route('user.courses.modules.scorm.runtime.get-last-error', [$course, $module, $scormPackage]),
                    'getErrorString' => route('user.courses.modules.scorm.runtime.get-error-string', [$course, $module, $scormPackage]),
                    'getDiagnostic' => route('user.courses.modules.scorm.runtime.get-diagnostic', [$course, $module, $scormPackage]),
                ],
                'defaultScoIdentifier' => data_get($scormPackage->sco_data, '0.identifier')
                    ?? data_get($scormPackage->manifest_data, 'default_organization')
                    ?? $scormPackage->identifier
                    ?? 'default-sco',
                'csrfToken' => csrf_token(),
            ],
        ]);
    }

    /**
     * @throws FileNotFoundException
     */
    public function asset(
        Request $request,
        Course $course,
        Module $module,
        ScormPackage $scormPackage,
        string $path,
    ): BinaryFileResponse {
        $this->resolveLearnerContext($request, $course, $module, $scormPackage);

        $normalizedPath = $this->normalizeAssetPath($path);
        $disk = Storage::disk('local');
        $storagePath = sprintf('%s/%s', $scormPackage->extracted_path, $normalizedPath);

        abort_unless($disk->exists($storagePath), Response::HTTP_NOT_FOUND);

        return response()->file($disk->path($storagePath), [
            'Content-Type' => $disk->mimeType($storagePath) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }

    /**
     * @return array{CourseEnrollment, ModuleProgress}
     */
    private function resolveLearnerContext(
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

        return [$enrollment, $moduleProgress];
    }

    private function normalizeAssetPath(string $path): string
    {
        $normalizedPath = trim(str_replace('\\', '/', $path), '/');
        abort_if($normalizedPath === '' || Str::contains($normalizedPath, ['../', '..\\', "\0"]), Response::HTTP_NOT_FOUND);

        return $normalizedPath;
    }
}
