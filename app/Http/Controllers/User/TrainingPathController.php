<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\TrainingPath;
use App\Models\TrainingPathCourseApproval;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use App\Services\TrainingPathCourseOrderService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\LaravelPdf\Facades\Pdf;

class TrainingPathController extends Controller
{
    public function __construct(
        private readonly TrainingPathCourseOrderService $trainingPathCourseOrderService,
    ) {}

    public function index(): View
    {
        $user = $this->authUser();

        $enrollments = TrainingPathEnrollment::query()
            ->where('user_id', $user->getKey())
            ->whereNull('deleted_at')
            ->with([
                'trainingPath:id,title,code,status,enforce_course_order',
                'trainingPath.courses:id,title,status',
            ])
            ->orderByDesc('assigned_at')
            ->orderByDesc('id')
            ->get()
            ->filter(fn (TrainingPathEnrollment $enrollment): bool => $enrollment->trainingPath !== null)
            ->values();

        $progressByEnrollmentId = $this->progressByEnrollmentId($user, $enrollments);

        return view('user.training-paths.index', [
            'enrollments' => $enrollments,
            'progressByEnrollmentId' => $progressByEnrollmentId,
        ]);
    }

    public function show(TrainingPathEnrollment $trainingPathEnrollment): View|RedirectResponse
    {
        $user = $this->authUser();

        $trainingPathEnrollment = $this->ownedEnrollment($trainingPathEnrollment, $user);

        $trainingPathEnrollment->loadMissing([
            'trainingPath:id,title,code,status,enforce_course_order,description',
            'trainingPath.courses:id,title,type,status',
        ]);

        $trainingPath = $trainingPathEnrollment->trainingPath;
        abort_unless($trainingPath !== null, 404);

        $courses = $trainingPath->courses
            ->where('status', 'published')
            ->values();

        $courseIds = $courses
            ->pluck('id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->values();

        $enrollmentsByCourseId = CourseEnrollment::query()
            ->where('user_id', $user->getKey())
            ->whereIn('course_id', $courseIds->all())
            ->whereNull('deleted_at')
            ->get(['course_id', 'status', 'completion_percentage'])
            ->keyBy(fn (CourseEnrollment $enrollment): int => (int) $enrollment->course_id);

        $skippedCourseIds = $this->skippedCourseIdsFor($user, $trainingPath);
        $requiredCourseIds = $courseIds->reject(fn (int $courseId): bool => $skippedCourseIds->contains($courseId));

        $completedCourses = $enrollmentsByCourseId
            ->filter(fn (CourseEnrollment $enrollment): bool => $requiredCourseIds->contains((int) $enrollment->course_id)
                && $enrollment->status === CourseEnrollment::STATUS_COMPLETED)
            ->count();

        $totalCourses = $requiredCourseIds->count();
        $completionPercentage = $totalCourses > 0
            ? (int) round(($completedCourses / $totalCourses) * 100)
            : 100;

        $courseOrderLocks = $this->trainingPathCourseOrderService->locksByCourseId($user);

        return view('user.training-paths.show', [
            'trainingPathEnrollment' => $trainingPathEnrollment,
            'trainingPath' => $trainingPath,
            'courses' => $courses,
            'enrollmentsByCourseId' => $enrollmentsByCourseId,
            'completedCourses' => $completedCourses,
            'totalCourses' => $totalCourses,
            'completionPercentage' => $completionPercentage,
            'courseOrderLocks' => $courseOrderLocks,
            'skippedCourseIds' => $skippedCourseIds,
        ]);
    }

    public function downloadProgram(TrainingPathEnrollment $trainingPathEnrollment)
    {
        $user = $this->authUser();

        [$trainingPathEnrollment, $trainingPath, $courses] = $this->programContext(
            $this->ownedEnrollment($trainingPathEnrollment, $user)
        );

        return Pdf::view('pdf.training-path-program', [
            'trainingPath' => $trainingPath,
            'courseTypeLabels' => Course::availableTypeLabels(),
            'courseStatusLabels' => Course::availableStatusLabels(),
            'courseEventTypeLabels' => Course::availableEventTypeLabels(),
        ])
            ->driver('dompdf')
            ->download($this->programDownloadFileName($trainingPath));
    }

    public function downloadProgramWithProgress(TrainingPathEnrollment $trainingPathEnrollment)
    {
        $user = $this->authUser();

        [$trainingPathEnrollment, $trainingPath, $courses] = $this->programContext(
            $this->ownedEnrollment($trainingPathEnrollment, $user)
        );

        $enrollmentsByCourseId = CourseEnrollment::query()
            ->where('user_id', $user->getKey())
            ->whereIn('course_id', $courses->pluck('id')->all())
            ->whereNull('deleted_at')
            ->get(['course_id', 'status', 'completion_percentage'])
            ->keyBy(fn (CourseEnrollment $enrollment): int => (int) $enrollment->course_id);

        $skippedCourseIds = $this->skippedCourseIdsFor($user, $trainingPath);
        $requiredCourseIds = $courses
            ->pluck('id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->reject(fn (int $courseId): bool => $skippedCourseIds->contains($courseId));

        $completedCourses = $enrollmentsByCourseId
            ->filter(fn (CourseEnrollment $enrollment): bool => $requiredCourseIds->contains((int) $enrollment->course_id)
                && $enrollment->status === CourseEnrollment::STATUS_COMPLETED)
            ->count();

        $totalCourses = $requiredCourseIds->count();
        $completionPercentage = $totalCourses > 0
            ? (int) round(($completedCourses / $totalCourses) * 100)
            : 100;

        return Pdf::view('pdf.training-path-program', [
            'trainingPath' => $trainingPath,
            'courseTypeLabels' => Course::availableTypeLabels(),
            'courseStatusLabels' => Course::availableStatusLabels(),
            'courseEventTypeLabels' => Course::availableEventTypeLabels(),
            'trainingPathProgress' => [
                'completed_courses' => $completedCourses,
                'total_courses' => $totalCourses,
                'completion_percentage' => $completionPercentage,
            ],
            'courseProgressByCourseId' => $enrollmentsByCourseId->mapWithKeys(
                fn (CourseEnrollment $enrollment, int $courseId): array => [
                    $courseId => [
                        'status' => $enrollment->status,
                        'status_label' => $this->courseEnrollmentStatusLabel($enrollment->status),
                        'completion_percentage' => (int) $enrollment->completion_percentage,
                    ],
                ]
            )->all(),
        ])
            ->driver('dompdf')
            ->download($this->programWithProgressDownloadFileName($trainingPath));
    }

    /**
     * @param  EloquentCollection<int, TrainingPathEnrollment>  $enrollments
     * @return array<int, array{completed_courses:int,total_courses:int,completion_percentage:int}>
     */
    private function progressByEnrollmentId(User $user, EloquentCollection $enrollments): array
    {
        $skippedCourseIdsByTrainingPathId = TrainingPathCourseApproval::query()
            ->where('user_id', $user->getKey())
            ->whereIn('training_path_id', $enrollments->pluck('training_path_id'))
            ->where('status', TrainingPathCourseApproval::STATUS_APPROVED)
            ->get(['training_path_id', 'course_id'])
            ->groupBy('training_path_id')
            ->map(fn (Collection $approvals): Collection => $approvals
                ->pluck('course_id')
                ->map(fn (mixed $courseId): int => (int) $courseId));

        $courseIdsByEnrollmentId = $enrollments->mapWithKeys(function (TrainingPathEnrollment $enrollment) use ($skippedCourseIdsByTrainingPathId): array {
            $skippedCourseIds = $skippedCourseIdsByTrainingPathId->get((int) $enrollment->training_path_id, collect());
            $courseIds = $enrollment->trainingPath?->courses
                ?->where('status', 'published')
                ->pluck('id')
                ->map(fn (mixed $courseId): int => (int) $courseId)
                ->reject(fn (int $courseId): bool => $skippedCourseIds->contains($courseId))
                ->unique()
                ->values()
                ->all() ?? [];

            return [(int) $enrollment->getKey() => $courseIds];
        });

        $allCourseIds = $courseIdsByEnrollmentId
            ->flatten()
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->unique()
            ->values();

        $completedByCourseId = DB::table('course_user')
            ->where('user_id', $user->getKey())
            ->whereIn('course_id', $allCourseIds->all())
            ->whereNull('deleted_at')
            ->where('status', CourseEnrollment::STATUS_COMPLETED)
            ->pluck('course_id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->flip();

        return $courseIdsByEnrollmentId->mapWithKeys(function (mixed $courseIds, mixed $enrollmentId) use ($completedByCourseId): array {
            $totalCourses = count($courseIds);
            $completedCourses = collect($courseIds)
                ->filter(fn (mixed $courseId): bool => $completedByCourseId->has((int) $courseId))
                ->count();

            return [
                (int) $enrollmentId => [
                    'completed_courses' => $completedCourses,
                    'total_courses' => $totalCourses,
                    'completion_percentage' => $totalCourses > 0
                        ? (int) round(($completedCourses / $totalCourses) * 100)
                        : 100,
                ],
            ];
        })->all();
    }

    private function authUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function ownedEnrollment(TrainingPathEnrollment $trainingPathEnrollment, User $user): TrainingPathEnrollment
    {
        abort_unless((int) $trainingPathEnrollment->user_id === (int) $user->getKey(), 404);
        abort_if($trainingPathEnrollment->trashed(), 404);

        return $trainingPathEnrollment;
    }

    /**
     * @return array{0: TrainingPathEnrollment, 1: TrainingPath, 2: Collection<int, Course>}
     */
    private function programContext(TrainingPathEnrollment $trainingPathEnrollment): array
    {
        $trainingPathEnrollment->loadMissing([
            'trainingPath:id,title,code,status,enforce_course_order,description',
            'trainingPath.courses' => fn ($query) => $query
                ->where('status', 'published')
                ->with([
                    'categories:id,name',
                    'partners:id,ragione_sociale',
                    'riskBasedRequirements:id,name,risk_levels',
                    'teacherEnrollments' => fn ($teacherQuery) => $teacherQuery
                        ->whereNull('deleted_at')
                        ->with('user:id,name,surname'),
                    'tutorEnrollments' => fn ($tutorQuery) => $tutorQuery
                        ->whereNull('deleted_at')
                        ->with('user:id,name,surname'),
                ]),
        ]);

        $trainingPath = $trainingPathEnrollment->trainingPath;
        abort_unless($trainingPath !== null, 404);

        $courses = $trainingPath->courses->values();
        $trainingPath->setRelation('courses', $courses);

        return [$trainingPathEnrollment, $trainingPath, $courses];
    }

    private function courseEnrollmentStatusLabel(string $status): string
    {
        return [
            CourseEnrollment::STATUS_ASSIGNED => __('Assegnato'),
            CourseEnrollment::STATUS_IN_PROGRESS => __('In corso'),
            CourseEnrollment::STATUS_COMPLETED => __('Completato'),
            CourseEnrollment::STATUS_EXPIRED => __('Scaduto'),
            CourseEnrollment::STATUS_CANCELLED => __('Annullato'),
        ][$status] ?? $status;
    }

    /**
     * @return Collection<int, int>
     */
    private function skippedCourseIdsFor(User $user, TrainingPath $trainingPath): Collection
    {
        return TrainingPathCourseApproval::query()
            ->whereBelongsTo($user)
            ->whereBelongsTo($trainingPath)
            ->where('status', TrainingPathCourseApproval::STATUS_APPROVED)
            ->pluck('course_id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->unique()
            ->values();
    }

    private function programDownloadFileName(TrainingPath $trainingPath): string
    {
        return sprintf(
            '%s-programma-formativo.pdf',
            Str::slug($trainingPath->title) ?: 'training-path'
        );
    }

    private function programWithProgressDownloadFileName(TrainingPath $trainingPath): string
    {
        return sprintf(
            '%s-programma-formativo-avanzamento.pdf',
            Str::slug($trainingPath->title) ?: 'training-path'
        );
    }
}
