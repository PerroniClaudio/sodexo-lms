<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\DispenseDownload;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleTeachingMaterial;
use App\Services\AuditTrail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DispenseModuleController extends Controller
{
    public function __construct(private readonly AuditTrail $auditTrail) {}

    public function status(Course $course, Module $module): JsonResponse
    {
        [$enrollment, $progress] = $this->resolveContext($course, $module);

        return response()->json($this->state($module, $enrollment, $progress));
    }

    public function download(
        Course $course,
        Module $module,
        ModuleTeachingMaterial $moduleTeachingMaterial,
    ): StreamedResponse {
        [$enrollment, $progress] = $this->resolveContext($course, $module);
        abort_unless($moduleTeachingMaterial->module_id === $module->getKey(), Response::HTTP_NOT_FOUND);

        $disk = Storage::disk($moduleTeachingMaterial->disk);
        abort_unless($disk->exists($moduleTeachingMaterial->path), Response::HTTP_NOT_FOUND);

        if ($progress->status === ModuleProgress::STATUS_AVAILABLE) {
            $progress->start();
        }

        DispenseDownload::query()->firstOrCreate([
            'course_enrollment_id' => $enrollment->getKey(),
            'module_teaching_material_id' => $moduleTeachingMaterial->getKey(),
        ], [
            'downloaded_at' => now(),
        ]);

        $this->auditTrail->record(
            action: 'downloaded',
            subjectType: class_basename($moduleTeachingMaterial),
            subjectId: $moduleTeachingMaterial->getKey(),
            subjectLabel: $moduleTeachingMaterial->original_name,
            metadata: [
                'course_id' => $course->getKey(),
                'module_id' => $module->getKey(),
                'course_enrollment_id' => $enrollment->getKey(),
                'mime_type' => $moduleTeachingMaterial->mime_type,
                'size_bytes' => $moduleTeachingMaterial->size_bytes,
            ],
            origin: 'user_ui',
        );

        return $disk->download(
            $moduleTeachingMaterial->path,
            $moduleTeachingMaterial->original_name,
            ['Content-Type' => $moduleTeachingMaterial->mime_type ?: 'application/octet-stream'],
        );
    }

    public function complete(Course $course, Module $module): JsonResponse
    {
        [$enrollment, $progress] = $this->resolveContext($course, $module);
        $state = $this->state($module, $enrollment, $progress);

        if ($progress->status === ModuleProgress::STATUS_COMPLETED) {
            return response()->json(['completed' => true] + $state);
        }

        if (! $state['can_proceed']) {
            return response()->json(['message' => __('Scarica tutti i file e attendi il tempo previsto prima di proseguire.')] + $state, 422);
        }

        $progress->markCompleted();

        return response()->json(['completed' => true] + $this->state($module, $enrollment, $progress->fresh()));
    }

    /**
     * @return array{CourseEnrollment, ModuleProgress}
     */
    private function resolveContext(Course $course, Module $module): array
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), Response::HTTP_NOT_FOUND);
        abort_unless($module->isDispense(), Response::HTTP_NOT_FOUND);

        $enrollment = request()->user()?->courseEnrollments()
            ->where('course_id', $course->getKey())
            ->first();
        abort_unless($enrollment instanceof CourseEnrollment, Response::HTTP_FORBIDDEN);

        $progress = $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->first();
        abort_unless($progress instanceof ModuleProgress, Response::HTTP_NOT_FOUND);
        abort_if($progress->status === ModuleProgress::STATUS_LOCKED, Response::HTTP_FORBIDDEN);

        return [$enrollment, $progress];
    }

    /**
     * @return array{downloaded_ids: array<int, int>, missing_ids: array<int, int>, all_downloaded: bool, remaining_seconds: int, can_proceed: bool}
     */
    private function state(Module $module, CourseEnrollment $enrollment, ModuleProgress $progress): array
    {
        $materialIds = $module->teachingMaterials()->pluck('id');
        $downloads = DispenseDownload::query()
            ->where('course_enrollment_id', $enrollment->getKey())
            ->whereIn('module_teaching_material_id', $materialIds)
            ->get(['module_teaching_material_id', 'downloaded_at']);
        $downloadedIds = $downloads->pluck('module_teaching_material_id')->map(fn (mixed $id): int => (int) $id);
        $missingIds = $materialIds->map(fn (mixed $id): int => (int) $id)->diff($downloadedIds)->values();
        $allDownloaded = $materialIds->isNotEmpty() && $missingIds->isEmpty();
        $availableAt = $allDownloaded
            ? $downloads->max('downloaded_at')?->copy()->addSeconds($module->minimum_duration_seconds)
            : null;
        $remainingSeconds = $availableAt?->isFuture()
            ? (int) now()->diffInSeconds($availableAt)
            : 0;

        return [
            'downloaded_ids' => $downloadedIds->values()->all(),
            'missing_ids' => $missingIds->all(),
            'all_downloaded' => $allDownloaded,
            'remaining_seconds' => $remainingSeconds,
            'can_proceed' => $progress->status === ModuleProgress::STATUS_COMPLETED
                || ($allDownloaded && $remainingSeconds === 0),
        ];
    }
}
