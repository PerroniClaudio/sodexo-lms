<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ScormPackage;
use App\Services\ScormService;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        $launchSco = app(ScormService::class)->resolveLaunchSco(
            $scormPackage,
            $request->query('sco')
        );
        $modules = $this->loadCourseModulesForEnrollment($course, $enrollment);

        return view('scorm.player', [
            'course' => $course,
            'module' => $module,
            'package' => $scormPackage,
            'launchSco' => $launchSco,
            'modules' => $modules,
            'enrollment' => $enrollment,
            'moduleProgress' => $moduleProgress,
            'moduleTypeMeta' => $this->moduleTypeMeta(),
            'scormPlayerConfig' => [
                'version' => $scormPackage->version,
                'entryPointUrl' => route('user.courses.modules.scorm.asset', [
                    'course' => $course,
                    'module' => $module,
                    'scormPackage' => $scormPackage,
                    'path' => $launchSco['entry_point'],
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
                'defaultScoIdentifier' => $launchSco['sco_identifier'],
                'currentPlayerUrl' => route('user.courses.modules.scorm.player', [
                    $course,
                    $module,
                    $scormPackage,
                    'sco' => $launchSco['sco_identifier'],
                ]),
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
    ): HttpResponse|StreamedResponse {
        $this->resolveLearnerContext($request, $course, $module, $scormPackage);

        $normalizedPath = $this->normalizeAssetPath($path);
        $disk = Storage::disk('local');
        $storagePath = sprintf('%s/%s', $scormPackage->extracted_path, $normalizedPath);

        abort_unless($disk->exists($storagePath), Response::HTTP_NOT_FOUND);

        $mimeType = $this->resolveAssetMimeType($normalizedPath, $disk->mimeType($storagePath) ?: null);

        if ($this->isHtmlAsset($normalizedPath)) {
            return $this->streamHtmlAsset($disk, $storagePath, $mimeType);
        }

        return $this->streamStaticAsset($request, $disk, $storagePath, $mimeType);
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

        $moduleProgress = $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->firstOrFail();

        abort_unless($moduleProgress->status !== ModuleProgress::STATUS_LOCKED, Response::HTTP_FORBIDDEN);

        return [$enrollment, $moduleProgress];
    }

    private function moduleTypeMeta(): array
    {
        return [
            'video' => [
                'label' => __('Video'),
                'icon' => 'lucide-clapperboard',
                'badge' => 'badge-primary',
            ],
            'res' => [
                'label' => __('Sessione in aula'),
                'icon' => 'lucide-users',
                'badge' => 'badge-accent',
            ],
            'live' => [
                'label' => __('Live'),
                'icon' => 'lucide-monitor-play',
                'badge' => 'badge-secondary',
            ],
            'scorm' => [
                'label' => __('SCORM'),
                'icon' => 'lucide-package',
                'badge' => 'badge-info',
            ],
            'learning_quiz' => [
                'label' => __('Quiz'),
                'icon' => 'lucide-badge-help',
                'badge' => 'badge-error',
            ],
            'satisfaction_quiz' => [
                'label' => __('Gradimento'),
                'icon' => 'lucide-message-square-heart',
                'badge' => 'badge-success',
            ],
        ];
    }

    private function loadCourseModulesForEnrollment(Course $course, CourseEnrollment $enrollment)
    {
        $modules = $course->modules()
            ->with([
                'progressRecords' => function ($query) use ($enrollment) {
                    $query->where('course_user_id', $enrollment->id);
                },
            ])
            ->orderBy('order')
            ->get();

        $modules->each(function (Module $courseModule): void {
            /** @var ModuleProgress|null $moduleProgress */
            $moduleProgress = $courseModule->progressRecords->first();

            $courseModule->pivot = (object) [
                'status' => $moduleProgress?->status ?? ModuleProgress::STATUS_LOCKED,
                'quiz_attempts' => $moduleProgress?->quiz_attempts ?? 0,
            ];
        });

        return $modules;
    }

    private function normalizeAssetPath(string $path): string
    {
        $normalizedPath = trim(str_replace('\\', '/', $path), '/');
        abort_if($normalizedPath === '' || Str::contains($normalizedPath, ['../', '..\\', "\0"]), Response::HTTP_NOT_FOUND);

        return $normalizedPath;
    }

    private function isHtmlAsset(string $path): bool
    {
        return Str::endsWith(Str::lower($path), ['.html', '.htm', '.xhtml']);
    }

    private function resolveAssetMimeType(string $path, ?string $detectedMimeType): string
    {
        $extension = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'css' => 'text/css',
            'gif' => 'image/gif',
            'htm', 'html' => 'text/html',
            'jpeg', 'jpg' => 'image/jpeg',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'm4a' => 'audio/mp4',
            'm4v', 'mp4' => 'video/mp4',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain',
            'webm' => 'video/webm',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'xhtml' => 'application/xhtml+xml',
            'xml' => 'application/xml',
            default => $detectedMimeType ?: 'application/octet-stream',
        };
    }

    private function streamHtmlAsset(FilesystemAdapter $disk, string $storagePath, string $mimeType): StreamedResponse
    {
        $contents = $this->injectScormBridge($disk->get($storagePath));

        return $this->streamAssetResponse(function () use ($contents): void {
            echo $contents;
        }, $this->uncachedAssetHeaders($mimeType));
    }

    private function streamStaticAsset(
        Request $request,
        FilesystemAdapter $disk,
        string $storagePath,
        string $mimeType,
    ): HttpResponse|StreamedResponse {
        $headers = $this->cacheableAssetHeaders($disk, $storagePath, $mimeType);

        if ($this->assetWasNotModified($request, $headers['ETag'], (int) $disk->lastModified($storagePath))) {
            return response('', Response::HTTP_NOT_MODIFIED, $headers);
        }

        $stream = $disk->readStream($storagePath);

        abort_unless(is_resource($stream), Response::HTTP_NOT_FOUND);

        return $this->streamAssetResponse(function () use ($stream): void {
            try {
                fpassthru($stream);
            } finally {
                fclose($stream);
            }
        }, $headers);
    }

    /**
     * @param  callable(): void  $callback
     */
    private function streamAssetResponse(callable $callback, array $headers): StreamedResponse
    {
        return response()->stream(function () use ($callback): void {
            $this->clearOutputBuffers();
            $callback();
        }, Response::HTTP_OK, $headers);
    }

    /**
     * @return array<string, string>
     */
    private function uncachedAssetHeaders(string $mimeType): array
    {
        return [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function cacheableAssetHeaders(FilesystemAdapter $disk, string $storagePath, string $mimeType): array
    {
        $lastModified = (int) $disk->lastModified($storagePath);
        $size = (int) $disk->size($storagePath);
        $etag = sprintf('W/"%s"', sha1($storagePath.'|'.$size.'|'.$lastModified));

        return [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'private, max-age=31536000, immutable',
            'ETag' => $etag,
            'Last-Modified' => gmdate(DATE_RFC7231, $lastModified),
        ];
    }

    private function assetWasNotModified(Request $request, string $etag, int $lastModified): bool
    {
        $ifNoneMatch = $request->headers->get('if-none-match');

        if (is_string($ifNoneMatch) && $ifNoneMatch !== '') {
            $clientEtags = collect(explode(',', $ifNoneMatch))
                ->map(fn (string $value): string => trim($value))
                ->filter();

            if ($clientEtags->contains('*') || $clientEtags->contains($etag)) {
                return true;
            }
        }

        $ifModifiedSince = $request->headers->get('if-modified-since');

        if (! is_string($ifModifiedSince) || $ifModifiedSince === '') {
            return false;
        }

        $timestamp = strtotime($ifModifiedSince);

        return $timestamp !== false && $lastModified <= $timestamp;
    }

    private function clearOutputBuffers(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    private function injectScormBridge(string $contents): string
    {
        $bridgeScript = <<<'HTML'
<script>
(function () {
    if (!window.parent || window.parent === window) {
        return;
    }

    if ('serviceWorker' in navigator) {
        var serviceWorker = navigator.serviceWorker;

        if (typeof serviceWorker.getRegistrations === 'function') {
            serviceWorker.getRegistrations().then(function (registrations) {
                registrations.forEach(function (registration) {
                    registration.unregister();
                });
            }).catch(function () {});
        } else if (typeof serviceWorker.getRegistration === 'function') {
            serviceWorker.getRegistration().then(function (registration) {
                if (registration) {
                    registration.unregister();
                }
            }).catch(function () {});
        }

        if (typeof serviceWorker.register === 'function') {
            try {
                serviceWorker.register = function () {
                    return Promise.resolve();
                };
            } catch (error) {}
        }
    }

    if ('caches' in window && typeof window.caches.keys === 'function') {
        window.caches.keys().then(function (keys) {
            return Promise.all(keys.map(function (key) {
                return window.caches.delete(key);
            }));
        }).catch(function () {});
    }

    var forward = function (name) {
        return function () {
            var fn = window.parent[name];

            if (typeof fn !== 'function') {
                return '';
            }

            return fn.apply(window.parent, arguments);
        };
    };

    window.API = window.parent.API;
    window.API_1484_11 = window.parent.API_1484_11;
    window.LMSInitialize = forward('LMSInitialize');
    window.LMSGetValue = forward('LMSGetValue');
    window.LMSSetValue = forward('LMSSetValue');
    window.LMSCommit = forward('LMSCommit');
    window.LMSFinish = forward('LMSFinish');
    window.LMSGetLastError = forward('LMSGetLastError');
    window.LMSGetErrorString = forward('LMSGetErrorString');
    window.LMSGetDiagnostic = forward('LMSGetDiagnostic');
    window.Initialize = forward('Initialize');
    window.GetValue = forward('GetValue');
    window.SetValue = forward('SetValue');
    window.Commit = forward('Commit');
    window.Terminate = forward('Terminate');
    window.GetLastError = forward('GetLastError');
    window.GetErrorString = forward('GetErrorString');
    window.GetDiagnostic = forward('GetDiagnostic');
    window.ScormProcessInitialize = forward('ScormProcessInitialize');
    window.ScormProcessGetValue = forward('ScormProcessGetValue');
    window.ScormProcessSetValue = forward('ScormProcessSetValue');
    window.ScormProcessCommit = forward('ScormProcessCommit');
    window.ScormProcessFinish = forward('ScormProcessFinish');
    window.ScormProcessTerminate = forward('ScormProcessTerminate');
    window.ScormProcessGetLastError = forward('ScormProcessGetLastError');
    window.ScormProcessGetErrorString = forward('ScormProcessGetErrorString');
    window.ScormProcessGetDiagnostic = forward('ScormProcessGetDiagnostic');
    window.doInitialize = forward('doInitialize');
    window.doGetValue = forward('doGetValue');
    window.doSetValue = forward('doSetValue');
    window.doCommit = forward('doCommit');
    window.doTerminate = forward('doTerminate');
    window.GetAPI = function () { return window.API; };
    window.GetAPI_1484_11 = function () { return window.API_1484_11; };
    window.AddLicenseInfo = function () {
        if (typeof window.parent.AddLicenseInfo === 'function') {
            return window.parent.AddLicenseInfo.apply(window.parent, arguments);
        }

        return true;
    };
})();
</script>
HTML;

        if (preg_match('/<head\b[^>]*>/i', $contents) === 1) {
            return preg_replace('/<head\b[^>]*>/i', '$0'.$bridgeScript, $contents, 1) ?? $contents;
        }

        return $bridgeScript.$contents;
    }
}
