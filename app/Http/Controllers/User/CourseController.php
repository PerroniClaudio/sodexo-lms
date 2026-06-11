<?php

namespace App\Http\Controllers\User;

use App\Actions\AbandonLearningQuizAttempt;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleQuizSubmission;
use App\Models\User;
use App\Services\Certificates\UserCourseCertificateLocator;
use App\Services\CourseClassScheduleResolver;
use App\Services\SyncCourseModuleProgresses;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseController extends Controller
{
    private const ATTACHMENTS_DISK = 's3';

    public function __construct(
        private readonly AbandonLearningQuizAttempt $abandonLearningQuizAttempt,
        private readonly SyncCourseModuleProgresses $syncCourseModuleProgresses,
    ) {}

    public function index(): View
    {
        $user = $this->authUser();

        return match ($this->routeArea()) {
            'teacher' => $this->teacherIndex($user),
            'tutor' => $this->tutorIndex($user),
            default => $this->userIndex($user),
        };
    }

    public function show(Course $course): View
    {
        $user = $this->authUser();

        return match ($this->routeArea()) {
            'teacher' => $this->teacherShow($user, $course),
            'tutor' => $this->tutorShow($user, $course),
            default => $this->userShow($user, $course),
        };
    }

    public function completed(UserCourseCertificateLocator $userCourseCertificateLocator): View
    {
        $user = $this->authUser();

        $completedEnrollments = $user->courseEnrollments()
            ->with('course')
            ->whereHas('course')
            ->where('status', CourseEnrollment::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->get()
            ->map(function (CourseEnrollment $enrollment) use ($userCourseCertificateLocator): array {
                return [
                    'enrollment' => $enrollment,
                    'certificates' => $userCourseCertificateLocator->locateAll($enrollment),
                ];
            });

        return view('user.courses.completed', [
            'completedEnrollments' => $completedEnrollments,
        ]);
    }

    public function downloadCertificate(
        Request $request,
        CourseEnrollment $courseEnrollment,
        UserCourseCertificateLocator $userCourseCertificateLocator
    ): StreamedResponse {
        $user = $this->authUser();

        abort_unless((int) $courseEnrollment->user_id === (int) $user->getKey(), 404);
        abort_unless($courseEnrollment->status === CourseEnrollment::STATUS_COMPLETED, 404);

        $courseEnrollment->loadMissing('course', 'user');

        $certificate = $userCourseCertificateLocator->locate(
            $courseEnrollment,
            $request->string('type')->toString() ?: null
        );

        abort_unless($certificate !== null, 404);

        return $certificate['disk']->download($certificate['path'], $certificate['download_name']);
    }

    public function showModule(Course $course, Module $module): View
    {
        $user = $this->authUser();

        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);

        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();
        abort_unless($enrollment !== null, 403);

        $module->loadMissing('video');

        $progress = $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->first();

        if ($progress === null) {
            $this->syncCourseModuleProgresses->handle($course);

            $progress = $enrollment->fresh()
                ?->moduleProgresses()
                ->where('module_id', $module->getKey())
                ->first();
        }

        abort_unless($progress !== null, 404);

        abort_if($progress->status === 'locked', 403);

        if ($module->isLearningQuiz()) {
            $activeSubmission = $module->quizSubmissions()
                ->where('course_enrollment_id', $enrollment->getKey())
                ->where('source_type', ModuleQuizSubmission::SOURCE_ONLINE)
                ->whereIn('status', [
                    ModuleQuizSubmission::STATUS_STARTED,
                    ModuleQuizSubmission::STATUS_IN_PROGRESS,
                ])
                ->first();

            if ($activeSubmission !== null) {
                ($this->abandonLearningQuizAttempt)(
                    $activeSubmission,
                    $progress,
                    'Tentativo abbandonato (ricaricamento pagina o ritorno al corso).'
                );

                $progress->refresh();
            }
        }

        $modules = $this->loadCourseModulesForEnrollment($course, $enrollment, $user);

        $nextModule = $course->modules()
            ->where('order', '>', $module->order)
            ->orderBy('order')
            ->first();

        return view('user.courses.module', compact('course', 'module', 'enrollment', 'progress', 'nextModule', 'modules'));
    }

    public function downloadPosterPdf(Course $course): StreamedResponse
    {
        $user = $this->authUser();
        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();

        abort_unless($enrollment !== null, Response::HTTP_FORBIDDEN);
        abort_unless($course->poster_pdf_path !== null, Response::HTTP_NOT_FOUND);

        $disk = Storage::disk(self::ATTACHMENTS_DISK);
        abort_unless($disk->exists($course->poster_pdf_path), Response::HTTP_NOT_FOUND);

        $downloadName = str($course->title)->slug('-')->append('-locandina.pdf')->toString();

        return response()->streamDownload(
            static function () use ($disk, $course): void {
                echo $disk->get($course->poster_pdf_path);
            },
            $downloadName,
            [
                'Content-Type' => $disk->mimeType($course->poster_pdf_path) ?: 'application/pdf',
            ],
        );
    }

    public function showCoverImage(Course $course): StreamedResponse
    {
        $user = $this->authUser();
        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();

        abort_unless($enrollment !== null, Response::HTTP_FORBIDDEN);
        abort_unless($course->cover_image_path !== null, Response::HTTP_NOT_FOUND);

        $disk = Storage::disk(self::ATTACHMENTS_DISK);
        abort_unless($disk->exists($course->cover_image_path), Response::HTTP_NOT_FOUND);

        return response()->streamDownload(
            static function () use ($disk, $course): void {
                echo $disk->get($course->cover_image_path);
            },
            basename($course->cover_image_path),
            [
                'Content-Type' => $disk->mimeType($course->cover_image_path) ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.basename($course->cover_image_path).'"',
            ],
        );
    }

    private function userIndex(User $user): View
    {
        $enrollments = $user->courseEnrollments()
            ->with('course')
            ->whereHas('course')
            ->get();

        return view('user.courses.index', compact('enrollments'));
    }

    private function teacherIndex(User $user): View
    {
        $courses = $user->getTeachingCourses();

        return view('teacher.courses.index', compact('courses'));
    }

    private function tutorIndex(User $user): View
    {
        $courses = $user->getTutoringCourses();

        return view('tutor.courses.index', compact('courses'));
    }

    private function userShow(User $user, Course $course): View
    {
        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();
        abort_unless($enrollment !== null, 403);

        $modules = $this->loadCourseModulesForEnrollment($course, $enrollment, $user);

        return view('user.courses.show', compact('course', 'enrollment', 'modules'));
    }

    /**
     * @return EloquentCollection<int, Module>
     */
    private function loadCourseModulesForEnrollment(Course $course, CourseEnrollment $enrollment, User $user): EloquentCollection
    {
        $modules = $course->modules()
            ->with([
                'progressRecords' => function ($query) use ($enrollment) {
                    $query->where('course_user_id', $enrollment->id);
                },
                'video:id,duration_seconds',
            ])
            ->withCount('quizQuestions')
            ->orderBy('order')
            ->get();

        $scheduleResolver = app(CourseClassScheduleResolver::class);

        $modules->each(function (Module $module) use ($scheduleResolver, $user): void {
            /** @var ModuleProgress|null $moduleProgress */
            $moduleProgress = $module->progressRecords->first();

            $module->pivot = (object) [
                'status' => $moduleProgress?->status ?? ModuleProgress::STATUS_LOCKED,
                'quiz_attempts' => $moduleProgress?->quiz_attempts ?? 0,
            ];
            $module->effective_starts_at = $scheduleResolver->effectiveStartsAt($module, $user);
            $module->effective_ends_at = $scheduleResolver->effectiveEndsAt($module, $user);
        });

        return $modules;
    }

    private function teacherShow(User $user, Course $course): View
    {
        $assignedCourse = $user->getTeachingCoursesQuery()
            ->whereKey($course->getKey())
            ->first();

        abort_unless($assignedCourse !== null, 403);

        $assignedModules = $user->teachingModules()
            ->where('belongsTo', (string) $course->getKey())
            ->whereNull('modules.deleted_at')
            ->orderBy('order')
            ->get();
        $modules = $course->modules()->get();

        $this->normalizeAssignedModuleDates($assignedModules);
        $this->decorateStaffCourseModules($modules, $assignedModules);

        return view('teacher.courses.show', compact('course', 'assignedModules', 'modules'));
    }

    private function tutorShow(User $user, Course $course): View
    {
        $assignedCourse = $user->getTutoringCoursesQuery()
            ->whereKey($course->getKey())
            ->first();

        abort_unless($assignedCourse !== null, 403);

        $assignedModules = $user->tutoringModules()
            ->where('belongsTo', (string) $course->getKey())
            ->whereNull('modules.deleted_at')
            ->orderBy('order')
            ->get();
        $modules = $course->modules()->get();

        $this->normalizeAssignedModuleDates($assignedModules);
        $this->decorateStaffCourseModules($modules, $assignedModules);

        return view('tutor.courses.show', compact('course', 'assignedModules', 'modules'));
    }

    private function normalizeAssignedModuleDates($assignedModules): void
    {
        $assignedModules->each(function (Module $module): void {
            $assignedAt = $module->pivot->assigned_at ?? null;
            $module->assigned_at_display = filled($assignedAt)
                ? CarbonImmutable::parse($assignedAt)->format('d/m/Y H:i')
                : null;
        });
    }

    private function decorateStaffCourseModules(EloquentCollection $modules, EloquentCollection $assignedModules): void
    {
        $assignedModulesById = $assignedModules->keyBy(fn (Module $module): int => (int) $module->getKey());

        $modules->each(function (Module $module) use ($assignedModulesById): void {
            /** @var Module|null $assignedModule */
            $assignedModule = $assignedModulesById->get((int) $module->getKey());

            $module->is_assigned_to_staff = $assignedModule !== null;
            $module->assigned_at_display = $assignedModule?->assigned_at_display;
        });
    }

    private function authUser(): User
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function routeArea(): string
    {
        $routeName = request()->route()?->getName() ?? '';

        if (str_starts_with($routeName, 'teacher.')) {
            return 'teacher';
        }

        if (str_starts_with($routeName, 'tutor.')) {
            return 'tutor';
        }

        return 'user';
    }
}
