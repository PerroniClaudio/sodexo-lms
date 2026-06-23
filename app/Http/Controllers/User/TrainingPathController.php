<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use App\Services\TrainingPathCourseOrderService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

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

        abort_unless((int) $trainingPathEnrollment->user_id === (int) $user->getKey(), 404);
        abort_if($trainingPathEnrollment->trashed(), 404);

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

        $completedCourses = $enrollmentsByCourseId
            ->filter(fn (CourseEnrollment $enrollment): bool => $enrollment->status === CourseEnrollment::STATUS_COMPLETED)
            ->count();

        $totalCourses = $courses->count();
        $completionPercentage = $totalCourses > 0
            ? (int) round(($completedCourses / $totalCourses) * 100)
            : 0;

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
        ]);
    }

    /**
     * @param  EloquentCollection<int, TrainingPathEnrollment>  $enrollments
     * @return array<int, array{completed_courses:int,total_courses:int,completion_percentage:int}>
     */
    private function progressByEnrollmentId(User $user, EloquentCollection $enrollments): array
    {
        $courseIdsByEnrollmentId = $enrollments->mapWithKeys(function (TrainingPathEnrollment $enrollment): array {
            $courseIds = $enrollment->trainingPath?->courses
                ?->where('status', 'published')
                ->pluck('id')
                ->map(fn (mixed $courseId): int => (int) $courseId)
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
                        : 0,
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
}
