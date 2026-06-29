<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ScormTrackingArchive;
use App\Services\ScormService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CourseEnrollmentDetailController extends Controller
{
    public function __construct(
        private readonly ScormService $scormService,
    ) {}

    public function show(Course $course, CourseEnrollment $enrollment): View
    {
        $this->abortIfEnrollmentDoesNotBelongToCourse($course, $enrollment);

        $course->loadMissing('categories:id,name', 'venue:id,address');
        $enrollment->loadMissing('user:id,name,surname,email,fiscal_code');

        $modules = $course->modules()
            ->with([
                'progressRecords' => fn ($query) => $query->where('course_user_id', $enrollment->getKey()),
                'video:id,duration_seconds',
                'quizSubmissions' => fn ($query) => $query
                    ->where('course_enrollment_id', $enrollment->getKey())
                    ->with([
                        'finalizedBy:id,name,surname',
                        'uploadedBy:id,name,surname',
                    ])
                    ->orderByDesc('submitted_at')
                    ->orderByDesc('id'),
                'scormPackages:id,module_id,title,status,version',
            ])
            ->withCount('quizQuestions')
            ->orderBy('order')
            ->get();

        $scormResetCountsByModuleId = ScormTrackingArchive::query()
            ->where('course_user_id', $enrollment->getKey())
            ->selectRaw('module_id, COUNT(DISTINCT reset_batch_uuid) as aggregate')
            ->groupBy('module_id')
            ->pluck('aggregate', 'module_id')
            ->map(fn (mixed $count): int => (int) $count);

        $moduleRows = $modules->values()->map(function (Module $module, int $index) use ($course, $enrollment, $modules, $scormResetCountsByModuleId): array {
            /** @var ModuleProgress|null $progress */
            $progress = $module->progressRecords->first();
            $status = $progress?->status ?? ModuleProgress::STATUS_LOCKED;
            $previousModulesCompleted = $modules
                ->take($index)
                ->every(function (Module $previousModule): bool {
                    /** @var ModuleProgress|null $progress */
                    $progress = $previousModule->progressRecords->first();

                    return $progress?->status === ModuleProgress::STATUS_COMPLETED;
                });

            return [
                'module' => $module,
                'progress' => $progress,
                'status' => $status,
                'status_label' => $this->moduleStatusLabel($status),
                'detail_status_label' => $this->moduleDetailStatusLabel($status),
                'is_current' => (int) $enrollment->current_module_id === (int) $module->getKey(),
                'is_completed' => $status === ModuleProgress::STATUS_COMPLETED,
                'can_review' => $status === ModuleProgress::STATUS_COMPLETED && $module->isVideo(),
                'previous_modules_completed' => $previousModulesCompleted,
                'reset_count' => $scormResetCountsByModuleId->get((int) $module->getKey(), 0),
                'details' => $this->moduleDetails($course, $enrollment, $module, $progress),
                'actions' => [
                    'can_reset_scorm' => $module->isScorm(),
                    'can_reset_quiz_attempts' => $module->isLearningQuiz()
                        && (($progress?->quiz_attempts ?? 0) > 0 || $module->quizSubmissions->isNotEmpty()),
                    'can_block' => $progress !== null
                        && ! in_array($progress->status, [ModuleProgress::STATUS_LOCKED, ModuleProgress::STATUS_COMPLETED], true),
                    'can_unlock' => $progress !== null
                        && $progress->status === ModuleProgress::STATUS_LOCKED
                        && ($index === 0 || $previousModulesCompleted),
                ],
            ];
        });

        return view('admin.course-enrollments.show', [
            'course' => $course,
            'enrollment' => $enrollment,
            'moduleRows' => $moduleRows,
            'moduleTypeLabels' => Module::availableTypeLabels(),
            'isSuperadmin' => request()->user()?->hasRole('superadmin') ?? false,
        ]);
    }

    public function resetScorm(Course $course, CourseEnrollment $enrollment, Module $module): RedirectResponse
    {
        $this->abortIfEnrollmentDoesNotBelongToCourse($course, $enrollment);
        $this->abortIfModuleDoesNotBelongToCourse($course, $module);
        abort_unless($module->isScorm(), 404);

        $this->resetModuleProgressFrom($enrollment, $module, request()->user()?->getKey());

        return back()->with('status', __('Tracciamento SCORM azzerato con successo.'));
    }

    public function resetQuizAttempts(Course $course, CourseEnrollment $enrollment, Module $module): RedirectResponse
    {
        $this->abortIfEnrollmentDoesNotBelongToCourse($course, $enrollment);
        $this->abortIfModuleDoesNotBelongToCourse($course, $module);
        abort_unless($module->isLearningQuiz(), 404);

        $progress = $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->firstOrFail();

        DB::transaction(function () use ($enrollment, $module, $progress): void {
            $progress->forceFill([
                'status' => ModuleProgress::STATUS_AVAILABLE,
                'started_at' => null,
                'completed_at' => null,
                'last_accessed_at' => null,
                'quiz_attempts' => 0,
                'quiz_score' => null,
                'quiz_total_score' => null,
                'passed_at' => null,
            ])->saveQuietly();

            $enrollment->forceFill([
                'current_module_id' => $module->getKey(),
                'completed_at' => null,
            ])->saveQuietly();

            $enrollment->syncProgressState();
        });

        return back()->with('status', __('Tentativi quiz azzerati con successo.'));
    }

    public function blockModule(Course $course, CourseEnrollment $enrollment, Module $module): RedirectResponse
    {
        $this->abortIfEnrollmentDoesNotBelongToCourse($course, $enrollment);
        $this->abortIfModuleDoesNotBelongToCourse($course, $module);

        $progress = $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->firstOrFail();

        abort_if($progress->status === ModuleProgress::STATUS_COMPLETED, 422, __('Non puoi bloccare un modulo completato.'));

        $progress->forceFill([
            'status' => ModuleProgress::STATUS_LOCKED,
        ])->saveQuietly();

        $enrollment->syncProgressState();

        return back()->with('status', __('Modulo bloccato con successo.'));
    }

    public function unlockModule(Course $course, CourseEnrollment $enrollment, Module $module): RedirectResponse
    {
        $this->abortIfEnrollmentDoesNotBelongToCourse($course, $enrollment);
        $this->abortIfModuleDoesNotBelongToCourse($course, $module);

        abort_if(! $this->canUnlockModule($enrollment, $module), 422, __('Il modulo non può essere sbloccato finché i precedenti non risultano completati.'));

        $progress = $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->firstOrFail();

        $progress->forceFill([
            'status' => ModuleProgress::STATUS_AVAILABLE,
        ])->saveQuietly();

        $enrollment->forceFill([
            'current_module_id' => $enrollment->current_module_id ?? $module->getKey(),
        ])->saveQuietly();
        $enrollment->syncProgressState();

        return back()->with('status', __('Modulo sbloccato con successo.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function moduleDetails(
        Course $course,
        CourseEnrollment $enrollment,
        Module $module,
        ?ModuleProgress $progress,
    ): array {
        $baseDetails = [
            'started_at' => $progress?->started_at?->format('d/m/Y H:i'),
            'completed_at' => $progress?->completed_at?->format('d/m/Y H:i'),
            'last_accessed_at' => $progress?->last_accessed_at?->format('d/m/Y H:i'),
            'time_spent_label' => $this->formatDuration((int) ($progress?->time_spent_seconds ?? 0)),
        ];

        if ($module->isVideo()) {
            $durationSeconds = (int) ($module->video?->duration_seconds ?? 0);

            return array_merge($baseDetails, [
                'type' => 'video',
                'duration_label' => $durationSeconds > 0 ? $this->formatDuration($durationSeconds) : null,
                'current_position_label' => $progress?->video_current_second !== null
                    ? $this->formatDuration((int) $progress->video_current_second)
                    : null,
                'max_position_label' => $progress?->video_max_second !== null
                    ? $this->formatDuration((int) $progress->video_max_second)
                    : null,
            ]);
        }

        if ($module->isLearningQuiz()) {
            return array_merge($baseDetails, [
                'type' => 'learning_quiz',
                'attempts_used' => (int) ($progress?->quiz_attempts ?? 0),
                'attempts_max' => $module->max_attempts,
                'passing_score' => $module->passing_score,
                'max_score' => $module->max_score,
                'score' => $progress?->quiz_score,
                'total_score' => $progress?->quiz_total_score,
                'passed' => $progress?->status === ModuleProgress::STATUS_COMPLETED,
                'submissions' => $module->quizSubmissions->map(fn ($submission): array => [
                    'status' => $submission->status,
                    'score' => $submission->score,
                    'total_score' => $submission->total_score,
                    'submitted_at' => $submission->submitted_at?->format('d/m/Y H:i'),
                ])->all(),
            ]);
        }

        if ($module->isScorm()) {
            return array_merge($baseDetails, [
                'type' => 'scorm',
                'packages' => $this->scormService->getLearnerPackageSummaries(
                    $enrollment->user()->firstOrFail(),
                    $course,
                    $module,
                    $enrollment,
                ),
            ]);
        }

        return array_merge($baseDetails, [
            'type' => $module->type,
            'appointment_label' => $module->appointment_start_time?->format('d/m/Y H:i'),
        ]);
    }

    private function resetModuleProgressFrom(CourseEnrollment $enrollment, Module $targetModule, ?int $actorUserId): void
    {
        DB::transaction(function () use ($enrollment, $targetModule, $actorUserId): void {
            $course = $enrollment->course()->firstOrFail();
            $orderedModules = $course->modules()->orderBy('order')->get();
            $progressByModuleId = $enrollment->moduleProgresses()
                ->get()
                ->keyBy(fn (ModuleProgress $progress): int => (int) $progress->module_id);

            foreach ($orderedModules as $module) {
                if ($module->order < $targetModule->order) {
                    continue;
                }

                $progress = $progressByModuleId->get((int) $module->getKey());

                if (! $progress instanceof ModuleProgress) {
                    $progress = $enrollment->moduleProgresses()->create([
                        'module_id' => $module->getKey(),
                        'status' => ModuleProgress::STATUS_LOCKED,
                    ]);
                }

                $progress->forceFill([
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
                    ->values(),
                $actorUserId,
            );

            $enrollment->forceFill([
                'current_module_id' => $targetModule->getKey(),
                'completed_at' => null,
            ])->saveQuietly();

            $enrollment->syncProgressState();
        });
    }

    private function canUnlockModule(CourseEnrollment $enrollment, Module $module): bool
    {
        $previousModuleIds = $enrollment->course()
            ->firstOrFail()
            ->modules()
            ->where('order', '<', $module->order)
            ->pluck('id');

        if ($previousModuleIds->isEmpty()) {
            return true;
        }

        $completedPreviousModules = $enrollment->moduleProgresses()
            ->whereIn('module_id', $previousModuleIds->all())
            ->where('status', ModuleProgress::STATUS_COMPLETED)
            ->count();

        return $completedPreviousModules === $previousModuleIds->count();
    }

    private function abortIfEnrollmentDoesNotBelongToCourse(Course $course, CourseEnrollment $enrollment): void
    {
        abort_unless((int) $enrollment->course_id === (int) $course->getKey(), 404);
    }

    private function abortIfModuleDoesNotBelongToCourse(Course $course, Module $module): void
    {
        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);
    }

    private function moduleStatusLabel(string $status): string
    {
        return match ($status) {
            ModuleProgress::STATUS_LOCKED => __('Bloccato'),
            ModuleProgress::STATUS_AVAILABLE => __('Da iniziare'),
            ModuleProgress::STATUS_IN_PROGRESS => __('Da continuare'),
            ModuleProgress::STATUS_COMPLETED => __('Completato'),
            ModuleProgress::STATUS_FAILED => __('Non superato'),
            default => $status,
        };
    }

    private function moduleDetailStatusLabel(string $status): string
    {
        return match ($status) {
            ModuleProgress::STATUS_LOCKED => __('Bloccato'),
            ModuleProgress::STATUS_AVAILABLE => __('Disponibile'),
            ModuleProgress::STATUS_IN_PROGRESS => __('In corso'),
            ModuleProgress::STATUS_COMPLETED => __('Completato'),
            ModuleProgress::STATUS_FAILED => __('Non superato'),
            default => $status,
        };
    }

    private function formatDuration(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%02d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
