<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\TrainingPathEnrollment;
use App\Models\UserCertificate;
use App\Services\ScormService;
use App\Services\TrainingPathEnrollmentSyncService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Throwable;

class DevelopmentToolController extends Controller
{
    public function __construct(
        private readonly TrainingPathEnrollmentSyncService $trainingPathEnrollmentSyncService,
        private readonly ScormService $scormService,
    ) {}

    public function resetEnrollments(): View
    {
        return view('admin.development-tools.reset-enrollments');
    }

    public function forceDeleteEnrollments(): View
    {
        return view('admin.development-tools.force-delete-enrollments');
    }

    public function performReset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', 'string', 'in:module,course,training_path'],
            'target_id' => ['required', 'integer', 'min:1'],
            'force_reset' => ['nullable', 'boolean'],
        ]);

        $targetType = $validated['target_type'];
        $targetId = (int) $validated['target_id'];
        $forceReset = (bool) ($validated['force_reset'] ?? false);

        try {
            $riskAssessment = $this->assessResetSafety($targetType, $targetId);

            if (! $forceReset && $riskAssessment['has_blockers']) {
                return back()
                    ->withInput()
                    ->with('reset_risk', $riskAssessment)
                    ->with('warning', __('Sono presenti vincoli di sicurezza. Verifica i dettagli e conferma per procedere.'));
            }

            match ($targetType) {
                'module' => $this->resetModuleProgress($targetId),
                'course' => $this->resetCourseEnrollment($targetId),
                'training_path' => $this->resetTrainingPathEnrollment($targetId),
            };
        } catch (Throwable $throwable) {
            report($throwable);

            return back()->with('error', __('Reset non completato. Verifica ID e relazione dei dati.'));
        }

        return back()->with('status', __('Reset completato con successo.'));
    }

    public function performForceDelete(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'target_type' => ['required', 'string', 'in:course,training_path'],
            'target_id' => ['required', 'integer', 'min:1'],
        ]);

        try {
            match ($validated['target_type']) {
                'course' => $this->forceDeleteCourseEnrollment((int) $validated['target_id']),
                'training_path' => $this->forceDeleteTrainingPathEnrollment((int) $validated['target_id']),
            };
        } catch (Throwable $throwable) {
            report($throwable);

            return back()->with('error', __('Force delete non completato. Verifica ID e relazione dei dati.'));
        }

        return back()->with('status', __('Force delete completato con successo.'));
    }

    /**
     * @return array{has_blockers: bool, target_type: string, target_id: int, warnings: array<int, array{code: string, message: string}>}
     */
    private function assessResetSafety(string $targetType, int $targetId): array
    {
        $affectedEnrollments = $this->resolveAffectedCourseEnrollments($targetType, $targetId);
        $warnings = [];

        foreach ($affectedEnrollments as $enrollment) {
            $enrollmentWarnings = $this->warningsForEnrollment($enrollment);

            foreach ($enrollmentWarnings as $warning) {
                $warnings[$warning['code'].'-'.$enrollment->getKey()] = $warning;
            }
        }

        if ($targetType === 'training_path') {
            $trainingPathEnrollment = TrainingPathEnrollment::query()
                ->with('trainingPath:id,title')
                ->findOrFail($targetId);

            if ($affectedEnrollments->isNotEmpty()) {
                $warnings['training-path-impact-'.$targetId] = [
                    'code' => 'training_path_impact',
                    'message' => __('Il reset del percorso coinvolgera :count iscrizioni corso collegate al percorso ":path".', [
                        'count' => $affectedEnrollments->count(),
                        'path' => $trainingPathEnrollment->trainingPath?->title ?? '#'.$targetId,
                    ]),
                ];
            }
        }

        return [
            'has_blockers' => ! empty($warnings),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'warnings' => array_values($warnings),
        ];
    }

    /**
     * @return Collection<int, CourseEnrollment>
     */
    private function resolveAffectedCourseEnrollments(string $targetType, int $targetId): Collection
    {
        if ($targetType === 'module') {
            $progress = ModuleProgress::query()
                ->with(['courseEnrollment.course:id,title', 'courseEnrollment.user:id'])
                ->findOrFail($targetId);

            $enrollment = $progress->courseEnrollment;

            abort_unless($enrollment instanceof CourseEnrollment, 404);

            return collect([$enrollment]);
        }

        if ($targetType === 'course') {
            $enrollment = CourseEnrollment::query()
                ->with(['course:id,title', 'user:id'])
                ->findOrFail($targetId);

            return collect([$enrollment]);
        }

        $trainingPathEnrollment = TrainingPathEnrollment::query()
            ->with(['trainingPath.courses:id,status', 'user:id'])
            ->findOrFail($targetId);

        $trainingPath = $trainingPathEnrollment->trainingPath;
        abort_unless($trainingPath !== null, 404);

        $courseIds = $trainingPath->courses
            ->where('status', 'published')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values();

        return CourseEnrollment::query()
            ->with(['course:id,title', 'user:id'])
            ->where('user_id', $trainingPathEnrollment->user_id)
            ->whereIn('course_id', $courseIds->all())
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * @return array<int, array{code: string, message: string}>
     */
    private function warningsForEnrollment(CourseEnrollment $enrollment): array
    {
        $enrollment->loadMissing(['course.riskBasedRequirements', 'user:id']);

        $courseTitle = $enrollment->course?->title ?? '#'.$enrollment->course_id;
        $warnings = [];

        $internalCertificatesCount = UserCertificate::query()
            ->where('user_id', $enrollment->user_id)
            ->where('internal_course_id', $enrollment->course_id)
            ->count();

        if ($internalCertificatesCount > 0) {
            $warnings[] = [
                'code' => 'internal_certificates',
                'message' => __('Sono presenti :count attestati interni collegati al corso ":course". Il reset potrebbe rendere incoerente lo storico certificazioni.', [
                    'count' => $internalCertificatesCount,
                    'course' => $courseTitle,
                ]),
            ];
        }

        $isCompleted = $enrollment->status === CourseEnrollment::STATUS_COMPLETED || $enrollment->completed_at !== null;
        $hasRiskRequirements = (bool) $enrollment->course?->riskBasedRequirements?->isNotEmpty();

        if ($isCompleted && $hasRiskRequirements) {
            $warnings[] = [
                'code' => 'completed_risk_course',
                'message' => __('Il corso ":course" risulta completato ed e associato a requisiti di rischio. Il reset puo impattare idoneita e tracciamento normativo.', [
                    'course' => $courseTitle,
                ]),
            ];
        }

        if ($isCompleted) {
            $warnings[] = [
                'code' => 'completed_history',
                'message' => __('Il corso ":course" risulta gia completato. Il reset modifica storico, audit trail e metriche di completamento.', [
                    'course' => $courseTitle,
                ]),
            ];
        }

        return $warnings;
    }

    private function resetModuleProgress(int $moduleProgressId): void
    {
        $userId = DB::transaction(function () use ($moduleProgressId): int {
            $progress = ModuleProgress::query()
                ->with(['courseEnrollment.course'])
                ->lockForUpdate()
                ->findOrFail($moduleProgressId);

            $enrollment = $progress->courseEnrollment;
            $course = $enrollment?->course;

            abort_unless($enrollment instanceof CourseEnrollment && $course !== null, 404);

            $orderedModules = $course->modules()->orderBy('order')->get();
            $targetModule = $orderedModules->first(fn (Module $module): bool => (int) $module->getKey() === (int) $progress->module_id);

            abort_unless($targetModule instanceof Module, 404);

            $progressByModuleId = $enrollment->moduleProgresses()
                ->get()
                ->keyBy(fn (ModuleProgress $item): int => (int) $item->module_id);

            foreach ($orderedModules as $module) {
                if ($module->order < $targetModule->order) {
                    continue;
                }

                $moduleProgress = $progressByModuleId->get((int) $module->getKey());

                if (! $moduleProgress instanceof ModuleProgress) {
                    $moduleProgress = $enrollment->moduleProgresses()->create([
                        'module_id' => $module->getKey(),
                        'status' => ModuleProgress::STATUS_LOCKED,
                    ]);
                }

                $moduleProgress->forceFill([
                    'status' => (int) $module->getKey() === (int) $targetModule->getKey()
                        ? ModuleProgress::STATUS_AVAILABLE
                        : ModuleProgress::STATUS_LOCKED,
                    'started_at' => null,
                    'completed_at' => null,
                    'last_accessed_at' => null,
                    'time_spent_seconds' => 0,
                    'video_current_second' => null,
                    'video_max_second' => null,
                    'quiz_attempts' => 0,
                    'quiz_score' => null,
                    'quiz_total_score' => null,
                    'passed_at' => null,
                ])->saveQuietly();
            }

            $this->scormService->purgeEnrollmentRuntimeData(
                $enrollment,
                $orderedModules
                    ->filter(fn (Module $module): bool => $module->order >= $targetModule->order)
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->values()
            );

            $enrollment->forceFill([
                'current_module_id' => $targetModule->getKey(),
                'completed_at' => null,
            ])->saveQuietly();

            $enrollment->syncProgressState();

            return (int) $enrollment->user_id;
        });

        $this->trainingPathEnrollmentSyncService->syncAllEnrollmentsForUser($userId);
    }

    private function resetCourseEnrollment(int $courseEnrollmentId): void
    {
        $userId = DB::transaction(function () use ($courseEnrollmentId): int {
            $enrollment = CourseEnrollment::query()
                ->with('course')
                ->lockForUpdate()
                ->findOrFail($courseEnrollmentId);

            $this->resetCourseEnrollmentState($enrollment);

            return (int) $enrollment->user_id;
        });

        $this->trainingPathEnrollmentSyncService->syncAllEnrollmentsForUser($userId);
    }

    private function resetTrainingPathEnrollment(int $trainingPathEnrollmentId): void
    {
        $trainingPathEnrollment = DB::transaction(function () use ($trainingPathEnrollmentId): TrainingPathEnrollment {
            $trainingPathEnrollment = TrainingPathEnrollment::query()
                ->with(['trainingPath.courses'])
                ->lockForUpdate()
                ->findOrFail($trainingPathEnrollmentId);

            $trainingPath = $trainingPathEnrollment->trainingPath;
            abort_unless($trainingPath !== null, 404);

            $courseIds = $trainingPath->courses
                ->where('status', 'published')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values();

            $courseEnrollments = CourseEnrollment::query()
                ->where('user_id', $trainingPathEnrollment->user_id)
                ->whereIn('course_id', $courseIds->all())
                ->whereNull('deleted_at')
                ->get();

            foreach ($courseEnrollments as $courseEnrollment) {
                $this->resetCourseEnrollmentState($courseEnrollment);
            }

            $trainingPathEnrollment->forceFill([
                'current_course_id' => null,
            ])->saveQuietly();

            return $trainingPathEnrollment;
        });

        $this->trainingPathEnrollmentSyncService->syncEnrollment($trainingPathEnrollment->fresh() ?? $trainingPathEnrollment);
    }

    private function resetCourseEnrollmentState(CourseEnrollment $enrollment): void
    {
        $course = $enrollment->course()->firstOrFail();
        $orderedModules = $course->modules()->orderBy('order')->get();
        $firstModule = $orderedModules->first();

        $this->scormService->purgeEnrollmentRuntimeData(
            $enrollment,
            $orderedModules->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()
        );

        $enrollment->moduleProgresses()->whereNotIn('module_id', $orderedModules->pluck('id')->all())->delete();

        $progressByModuleId = $enrollment->moduleProgresses()
            ->get()
            ->keyBy(fn (ModuleProgress $progress): int => (int) $progress->module_id);

        foreach ($orderedModules as $module) {
            $progress = $progressByModuleId->get((int) $module->getKey());

            if (! $progress instanceof ModuleProgress) {
                $progress = $enrollment->moduleProgresses()->create([
                    'module_id' => $module->getKey(),
                    'status' => ModuleProgress::STATUS_LOCKED,
                ]);
            }

            $progress->forceFill([
                'status' => $firstModule !== null && (int) $module->getKey() === (int) $firstModule->getKey()
                    ? ModuleProgress::STATUS_AVAILABLE
                    : ModuleProgress::STATUS_LOCKED,
                'started_at' => null,
                'completed_at' => null,
                'last_accessed_at' => null,
                'time_spent_seconds' => 0,
                'video_current_second' => null,
                'video_max_second' => null,
                'quiz_attempts' => 0,
                'quiz_score' => null,
                'quiz_total_score' => null,
                'passed_at' => null,
            ])->saveQuietly();
        }

        $enrollment->forceFill([
            'current_module_id' => $firstModule?->getKey(),
            'status' => $firstModule === null
                ? CourseEnrollment::STATUS_COMPLETED
                : CourseEnrollment::STATUS_ASSIGNED,
            'started_at' => null,
            'completed_at' => $firstModule === null ? now() : null,
            'last_accessed_at' => null,
            'completion_percentage' => $firstModule === null ? 100 : 0,
        ])->saveQuietly();
    }

    private function forceDeleteCourseEnrollment(int $courseEnrollmentId): void
    {
        $userId = DB::transaction(function () use ($courseEnrollmentId): int {
            $enrollment = CourseEnrollment::query()
                ->lockForUpdate()
                ->findOrFail($courseEnrollmentId);

            $this->applyEnrollmentOriginsAfterForcedRemoval(
                $enrollment,
                directOrigin: false,
                pathwayOrigin: $this->hasOtherActivePathwayOriginForCourse($enrollment),
            );

            return (int) $enrollment->user_id;
        });

        $this->trainingPathEnrollmentSyncService->syncAllEnrollmentsForUser($userId);
    }

    private function forceDeleteTrainingPathEnrollment(int $trainingPathEnrollmentId): void
    {
        $userId = DB::transaction(function () use ($trainingPathEnrollmentId): int {
            $trainingPathEnrollment = TrainingPathEnrollment::query()
                ->with(['trainingPath.courses:id,status'])
                ->lockForUpdate()
                ->findOrFail($trainingPathEnrollmentId);

            $trainingPath = $trainingPathEnrollment->trainingPath;
            abort_unless($trainingPath !== null, 404);

            $courseIds = $trainingPath->courses
                ->where('status', 'published')
                ->pluck('id')
                ->map(fn (mixed $id): int => (int) $id)
                ->values();

            $remainingPathwayOriginsByCourseId = $this->remainingPathwayOriginsByCourseId(
                $trainingPathEnrollment,
                $courseIds,
            );

            $courseEnrollments = CourseEnrollment::query()
                ->where('user_id', $trainingPathEnrollment->user_id)
                ->whereIn('course_id', $courseIds->all())
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->get();

            foreach ($courseEnrollments as $courseEnrollment) {
                $this->applyEnrollmentOriginsAfterForcedRemoval(
                    $courseEnrollment,
                    directOrigin: (bool) $courseEnrollment->direct_origin,
                    pathwayOrigin: ((int) $remainingPathwayOriginsByCourseId->get((int) $courseEnrollment->course_id, 0)) > 0,
                );
            }

            $trainingPathEnrollment->forceDelete();

            return (int) $trainingPathEnrollment->user_id;
        });

        $this->trainingPathEnrollmentSyncService->syncAllEnrollmentsForUser($userId);
    }

    private function applyEnrollmentOriginsAfterForcedRemoval(
        CourseEnrollment $enrollment,
        bool $directOrigin,
        bool $pathwayOrigin,
    ): void {
        if (! $directOrigin && ! $pathwayOrigin) {
            $this->scormService->purgeEnrollmentRuntimeData($enrollment);
            $enrollment->forceDelete();

            return;
        }

        $enrollment->forceFill([
            'direct_origin' => $directOrigin,
            'pathway_origin' => $pathwayOrigin,
        ])->save();
    }

    private function hasOtherActivePathwayOriginForCourse(CourseEnrollment $enrollment): bool
    {
        return DB::table('training_path_user')
            ->join('training_path_course', 'training_path_course.training_path_id', '=', 'training_path_user.training_path_id')
            ->where('training_path_user.user_id', $enrollment->user_id)
            ->whereNull('training_path_user.deleted_at')
            ->where('training_path_course.course_id', $enrollment->course_id)
            ->exists();
    }

    /**
     * @param  Collection<int, int>  $courseIds
     * @return Collection<int, int>
     */
    private function remainingPathwayOriginsByCourseId(
        TrainingPathEnrollment $trainingPathEnrollment,
        Collection $courseIds,
    ): Collection {
        if ($courseIds->isEmpty()) {
            return collect();
        }

        return DB::table('training_path_user')
            ->join('training_path_course', 'training_path_course.training_path_id', '=', 'training_path_user.training_path_id')
            ->where('training_path_user.user_id', $trainingPathEnrollment->user_id)
            ->whereNull('training_path_user.deleted_at')
            ->where('training_path_user.id', '!=', (int) $trainingPathEnrollment->getKey())
            ->whereIn('training_path_course.course_id', $courseIds->all())
            ->selectRaw('training_path_course.course_id, COUNT(DISTINCT training_path_user.id) as aggregate')
            ->groupBy('training_path_course.course_id')
            ->pluck('aggregate', 'training_path_course.course_id')
            ->mapWithKeys(fn (mixed $count, mixed $courseId): array => [(int) $courseId => (int) $count]);
    }
}
