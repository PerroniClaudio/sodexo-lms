<?php

namespace App\Http\Controllers\User;

use App\Actions\AbandonLearningQuizAttempt;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleQuizSubmission;
use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use App\Services\Certificates\UserCourseCertificateLocator;
use App\Services\CourseClassScheduleResolver;
use App\Services\QuizAccessDelayService;
use App\Services\SyncCourseModuleProgresses;
use App\Services\TrainingPathCourseOrderService;
use App\Support\LanguageVerificationGate;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseController extends Controller
{
    public function __construct(
        private readonly AbandonLearningQuizAttempt $abandonLearningQuizAttempt,
        private readonly SyncCourseModuleProgresses $syncCourseModuleProgresses,
        private readonly QuizAccessDelayService $quizAccessDelayService,
        private readonly LanguageVerificationGate $languageVerificationGate,
        private readonly TrainingPathCourseOrderService $trainingPathCourseOrderService,
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

    public function show(Course $course): View|RedirectResponse
    {
        $user = $this->authUser();

        return match ($this->routeArea()) {
            'teacher' => $this->teacherShow($user, $course),
            'tutor' => $this->tutorShow($user, $course),
            default => $this->userShow($user, $course, null),
        };
    }

    public function showWithinTrainingPath(TrainingPathEnrollment $trainingPathEnrollment, Course $course): View|RedirectResponse
    {
        $user = $this->authUser();

        abort_unless($this->routeArea() === 'user', 404);

        return $this->userShow($user, $course, $trainingPathEnrollment);
    }

    public function completed(UserCourseCertificateLocator $userCourseCertificateLocator): View
    {
        $user = $this->authUser();

        $completedEnrollments = $user->courseEnrollments()
            ->select(['id', 'user_id', 'course_id', 'status', 'completed_at'])
            ->with('course:id,title')
            ->whereHas('course')
            ->where('status', CourseEnrollment::STATUS_COMPLETED)
            ->orderByDesc('completed_at')
            ->get()
            ->map(function (CourseEnrollment $enrollment) use ($userCourseCertificateLocator): array {
                return [
                    'enrollment' => $enrollment,
                    'certificates' => $userCourseCertificateLocator->locateAll($enrollment),
                    'hasPendingCertificateGeneration' => $userCourseCertificateLocator->hasPendingGeneration($enrollment),
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

        return Storage::download($certificate['path'], $certificate['download_name']);
    }

    public function showModule(Course $course, Module $module): View|RedirectResponse
    {
        $user = $this->authUser();

        return $this->showUserModule($user, $course, $module, null);
    }

    public function showModuleWithinTrainingPath(TrainingPathEnrollment $trainingPathEnrollment, Course $course, Module $module): View|RedirectResponse
    {
        $user = $this->authUser();

        abort_unless($this->routeArea() === 'user', 404);

        return $this->showUserModule($user, $course, $module, $trainingPathEnrollment);
    }

    private function showUserModule(User $user, Course $course, Module $module, ?TrainingPathEnrollment $trainingPathEnrollment): View|RedirectResponse
    {
        $courseShowRouteName = 'user.courses.show';

        if ($trainingPathEnrollment !== null) {
            $this->ensureUserTrainingPathContext($user, $trainingPathEnrollment, $course);
            $courseShowRouteName = 'user.training-paths.courses.show';
        }

        $courseOrderLock = $this->trainingPathCourseOrderService->lockForCourse($user, $course);

        if ($courseOrderLock !== null && ! empty($courseOrderLock['current_course_id'])) {
            if ($trainingPathEnrollment !== null && $this->courseBelongsToTrainingPathEnrollment($trainingPathEnrollment, (int) $courseOrderLock['current_course_id'])) {
                return redirect()
                    ->route('user.training-paths.courses.show', [$trainingPathEnrollment, (int) $courseOrderLock['current_course_id']])
                    ->with('error', $courseOrderLock['message']);
            }

            return redirect()
                ->route('user.courses.show', (int) $courseOrderLock['current_course_id'])
                ->with('error', $courseOrderLock['message']);
        }

        abort_unless((string) $module->belongsTo === (string) $course->getKey(), 404);

        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();
        abort_unless($enrollment !== null, 403);

        if ($this->languageVerificationGate->resolveBlockedEnrollment($enrollment) !== null) {
            return redirect()
                ->route('user.courses.show', $course)
                ->with('error', __('Devi prima completare la verifica della lingua richiesta.'));
        }

        $module->loadMissing('video', 'teachingMaterials');

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

        $quizAccessGate = $this->quizAccessDelayService->resolve($enrollment, $module);

        $modulePlayerRouteName = $trainingPathEnrollment !== null
            ? 'user.training-paths.courses.modules.player'
            : 'user.courses.modules.player';

        return view('user.courses.module', compact('course', 'module', 'enrollment', 'progress', 'nextModule', 'modules', 'quizAccessGate', 'trainingPathEnrollment', 'courseShowRouteName', 'modulePlayerRouteName'));
    }

    public function downloadPosterPdf(Course $course): StreamedResponse
    {
        $user = $this->authUser();

        abort_unless($this->canAccessCourseAssets($user, $course), Response::HTTP_FORBIDDEN);
        abort_unless($course->poster_pdf_path !== null, Response::HTTP_NOT_FOUND);

        abort_unless(Storage::exists($course->poster_pdf_path), Response::HTTP_NOT_FOUND);

        $downloadName = str($course->title)->slug('-')->append('-locandina.pdf')->toString();

        return response()->streamDownload(
            static function () use ($course): void {
                echo Storage::get($course->poster_pdf_path);
            },
            $downloadName,
            [
                'Content-Type' => Storage::mimeType($course->poster_pdf_path) ?: 'application/pdf',
            ],
        );
    }

    public function showCoverImage(Course $course): StreamedResponse
    {
        $user = $this->authUser();

        abort_unless($this->canAccessCourseAssets($user, $course), Response::HTTP_FORBIDDEN);
        abort_unless($course->cover_image_path !== null, Response::HTTP_NOT_FOUND);

        abort_unless(Storage::exists($course->cover_image_path), Response::HTTP_NOT_FOUND);

        return response()->streamDownload(
            static function () use ($course): void {
                echo Storage::get($course->cover_image_path);
            },
            basename($course->cover_image_path),
            [
                'Content-Type' => Storage::mimeType($course->cover_image_path) ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.basename($course->cover_image_path).'"',
            ],
        );
    }

    private function userIndex(User $user): View
    {
        $enrollments = $user->courseEnrollments()
            ->select([
                'id',
                'user_id',
                'course_id',
                'status',
                'completion_percentage',
                'last_accessed_at',
                'expires_at',
                'direct_origin',
            ])
            ->with([
                'course:id,title,type',
                'course.categories:id,name',
            ])
            ->where('direct_origin', true)
            ->whereHas('course', fn ($query) => $query->visibleToUser($user))
            ->get();

        $courseOrderLocks = $this->trainingPathCourseOrderService->locksByCourseId($user);

        return view('user.courses.index', compact('enrollments', 'courseOrderLocks'));
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

    public function tutorAttendance(Course $course): View
    {
        $user = $this->authUser();

        abort_unless($this->routeArea() === 'tutor', 404);
        $this->ensureTutorCanAccessResidentialCourse($user, $course);

        $enrollments = CourseEnrollment::query()
            ->whereBelongsTo($course, 'course')
            ->leftJoin('users', 'users.id', '=', 'course_user.user_id')
            ->select('course_user.*')
            ->with([
                'user' => fn ($query) => $query->select(['id', 'name', 'surname']),
            ])
            ->orderBy('users.surname')
            ->orderBy('users.name')
            ->orderBy('course_user.id')
            ->get();

        $selectedAttendanceUserId = request()->filled('attendance_user_id')
            ? request()->integer('attendance_user_id')
            : null;
        $attendanceUserOptions = $enrollments
            ->filter(fn (CourseEnrollment $enrollment): bool => $enrollment->user !== null)
            ->map(fn (CourseEnrollment $enrollment): array => [
                'id' => (int) $enrollment->user_id,
                'label' => trim(($enrollment->user?->surname ?? '').' '.($enrollment->user?->name ?? '')),
            ])
            ->unique('id')
            ->values();

        if ($selectedAttendanceUserId !== null && ! $attendanceUserOptions->contains('id', $selectedAttendanceUserId)) {
            $selectedAttendanceUserId = null;
        }

        return view('tutor.courses.attendance', [
            'course' => $course,
            'enrollments' => $enrollments,
            'attendanceRecords' => $this->tutorAttendanceRecords($course, $selectedAttendanceUserId),
            'attendanceUserOptions' => $attendanceUserOptions,
            'selectedAttendanceUserId' => $selectedAttendanceUserId,
        ]);
    }

    public function storeTutorAttendance(Request $request, Course $course, CourseEnrollment $enrollment): RedirectResponse
    {
        $user = $this->authUser();

        abort_unless($this->routeArea() === 'tutor', 404);
        $this->ensureTutorCanAccessResidentialCourse($user, $course);
        abort_unless((int) $enrollment->course_id === (int) $course->getKey(), 404);
        abort_if($enrollment->trashed(), 404);

        $validated = $request->validate([
            'type' => ['required', 'in:entry,exit'],
        ]);

        $type = $validated['type'];

        $this->createAttendanceRecord(
            $course,
            $enrollment,
            $user,
            $type,
        );

        return back()->with('status', $type === 'entry'
            ? __('Entrata registrata con successo.')
            : __('Uscita registrata con successo.'));
    }

    public function scanTutorAttendanceQr(Request $request, Course $course): JsonResponse
    {
        $user = $this->authUser();

        abort_unless($this->routeArea() === 'tutor', 404);
        $this->ensureTutorCanAccessResidentialCourse($user, $course);

        $validated = $request->validate([
            'qr_content' => ['required', 'string'],
        ]);

        $qrPayload = $this->decodeResidentialAttendanceQrPayload($validated['qr_content']);

        if ($qrPayload === null) {
            return response()->json([
                'message' => __('QR code non valido.'),
            ], 422);
        }

        $enrollment = CourseEnrollment::withTrashed()
            ->with('user')
            ->find($qrPayload['enrollment_id']);

        if ($enrollment === null || (int) $enrollment->user_id !== $qrPayload['user_id']) {
            return response()->json([
                'message' => __('Utente non registrato.'),
            ], 422);
        }

        if ((int) $enrollment->course_id !== (int) $course->getKey()) {
            return response()->json([
                'message' => __('Utente non registrato a questo corso.'),
            ], 422);
        }

        if (! $this->isTutorAttendanceEnrollmentValid($enrollment)) {
            return response()->json([
                'message' => __('Iscrizione non valida.'),
            ], 422);
        }

        $type = $this->inferAttendanceTypeForToday($course, $enrollment);
        $this->createAttendanceRecord($course, $enrollment, $user, $type);

        return response()->json([
            'message' => $type === 'entry'
                ? __('Entrata registrata con successo.')
                : __('Uscita registrata con successo.'),
            'type' => $type,
            'user' => trim(($enrollment->user?->name ?? '').' '.($enrollment->user?->surname ?? '')),
        ]);
    }

    private function userShow(User $user, Course $course, ?TrainingPathEnrollment $trainingPathEnrollment): View|RedirectResponse
    {
        $courseShowRouteName = 'user.courses.show';
        $modulePlayerRouteName = 'user.courses.modules.player';
        $trainingPathContext = null;

        if ($trainingPathEnrollment !== null) {
            $this->ensureUserTrainingPathContext($user, $trainingPathEnrollment, $course);

            $courseShowRouteName = 'user.training-paths.courses.show';
            $modulePlayerRouteName = 'user.training-paths.courses.modules.player';
            $trainingPathContext = $this->trainingPathContextData($user, $trainingPathEnrollment, $course);
        }

        $courseOrderLock = $this->trainingPathCourseOrderService->lockForCourse($user, $course);

        if ($courseOrderLock !== null && ! empty($courseOrderLock['current_course_id'])) {
            if ($trainingPathEnrollment !== null && $this->courseBelongsToTrainingPathEnrollment($trainingPathEnrollment, (int) $courseOrderLock['current_course_id'])) {
                return redirect()
                    ->route('user.training-paths.courses.show', [$trainingPathEnrollment, (int) $courseOrderLock['current_course_id']])
                    ->with('error', $courseOrderLock['message']);
            }

            return redirect()
                ->route('user.courses.show', (int) $courseOrderLock['current_course_id'])
                ->with('error', $courseOrderLock['message']);
        }

        $course->loadMissing('categories', 'venue');

        $enrollment = $user->courseEnrollments()->where('course_id', $course->id)->first();
        abort_unless($enrollment !== null, 403);

        $enrollment->loadMissing('course');

        if ($course->isLanguageVerificationCourse()
            && $enrollment->status === CourseEnrollment::STATUS_COMPLETED
            && $enrollment->origin_course_id !== null
            && (int) $enrollment->origin_course_id !== (int) $course->getKey()) {
            return redirect()->route('user.courses.show', $enrollment->origin_course_id);
        }

        $languageVerificationBlock = $this->languageVerificationGate->resolveBlockedEnrollment($enrollment);

        $modules = $this->loadCourseModulesForEnrollment($course, $enrollment, $user);
        $residentialAttendanceQrCode = $course->type === Module::TYPE_RESIDENTIAL
            ? $this->buildResidentialAttendanceQrCode($user, $enrollment)
            : null;

        return view('user.courses.show', [
            'course' => $course,
            'enrollment' => $enrollment,
            'languageVerificationBlock' => $languageVerificationBlock,
            'modules' => $modules,
            'courseOrderLock' => $courseOrderLock,
            'residentialAttendanceQrCodeContent' => $residentialAttendanceQrCode['content'] ?? null,
            'residentialAttendanceQrCodeDataUri' => $residentialAttendanceQrCode['data_uri'] ?? null,
            'trainingPathEnrollment' => $trainingPathEnrollment,
            'trainingPathContext' => $trainingPathContext,
            'courseShowRouteName' => $courseShowRouteName,
            'modulePlayerRouteName' => $modulePlayerRouteName,
        ]);
    }

    private function ensureUserTrainingPathContext(User $user, TrainingPathEnrollment $trainingPathEnrollment, Course $course): void
    {
        abort_unless((int) $trainingPathEnrollment->user_id === (int) $user->getKey(), 404);
        abort_if($trainingPathEnrollment->trashed(), 404);

        $belongsToPath = $trainingPathEnrollment->trainingPath()
            ->whereHas('courses', fn ($query) => $query->whereKey($course->getKey()))
            ->exists();

        abort_unless($belongsToPath, 404);
    }

    private function courseBelongsToTrainingPathEnrollment(TrainingPathEnrollment $trainingPathEnrollment, int $courseId): bool
    {
        return $trainingPathEnrollment->trainingPath()
            ->whereHas('courses', fn ($query) => $query->whereKey($courseId))
            ->exists();
    }

    /**
     * @return array{training_path: TrainingPath, completed_courses: int, total_courses: int, completion_percentage: int, current_course_id: int|null, next_course: Course|null}
     */
    private function trainingPathContextData(User $user, TrainingPathEnrollment $trainingPathEnrollment, Course $currentCourse): array
    {
        $trainingPathEnrollment->loadMissing([
            'trainingPath:id,title,code,enforce_course_order,status',
            'trainingPath.courses:id,title,status',
        ]);

        $trainingPath = $trainingPathEnrollment->trainingPath;
        abort_unless($trainingPath !== null, 404);

        $orderedCourses = $trainingPath->courses
            ->where('status', 'published')
            ->values();

        $orderedCourseIds = $orderedCourses
            ->pluck('id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->values();

        $completedCourses = 0;
        $totalCourses = $orderedCourseIds->count();

        if ($totalCourses > 0) {
            $completedCourses = CourseEnrollment::query()
                ->where('user_id', $user->getKey())
                ->whereIn('course_id', $orderedCourseIds->all())
                ->whereNull('deleted_at')
                ->where('status', CourseEnrollment::STATUS_COMPLETED)
                ->count();
        }

        $currentIndex = $orderedCourses->search(fn (Course $course): bool => (int) $course->getKey() === (int) $currentCourse->getKey());
        $nextCourse = $currentIndex === false ? null : $orderedCourses->slice(((int) $currentIndex) + 1)->first();

        return [
            'training_path' => $trainingPath,
            'completed_courses' => (int) $completedCourses,
            'total_courses' => $totalCourses,
            'completion_percentage' => $totalCourses > 0
                ? (int) round(($completedCourses / $totalCourses) * 100)
                : 0,
            'current_course_id' => $trainingPathEnrollment->current_course_id !== null ? (int) $trainingPathEnrollment->current_course_id : null,
            'next_course' => $nextCourse instanceof Course ? $nextCourse : null,
        ];
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
        $this->ensureTutorCanAccessCourse($user, $course);

        $assignedModules = $user->tutoringModules()
            ->where('belongsTo', (string) $course->getKey())
            ->whereNull('modules.deleted_at')
            ->orderBy('order')
            ->get();
        $modules = $course->modules()->orderBy('order')->get();

        if ($course->type === Module::TYPE_RESIDENTIAL) {
            $course->loadMissing('categories', 'venue');

            return view('tutor.courses.show-residential', [
                'course' => $course,
                'modules' => $modules,
                'firstResidentialStartAt' => $modules
                    ->first(fn (Module $module) => $module->type === Module::TYPE_RESIDENTIAL && $module->appointment_start_time !== null)
                    ?->appointment_start_time,
            ]);
        }

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

    private function canAccessCourseAssets(User $user, Course $course): bool
    {
        return match ($this->routeArea()) {
            'teacher' => $user->getTeachingCoursesQuery()->whereKey($course->getKey())->exists(),
            'tutor' => $user->getTutoringCoursesQuery()->whereKey($course->getKey())->exists(),
            default => $user->courseEnrollments()->where('course_id', $course->getKey())->exists(),
        };
    }

    private function ensureTutorCanAccessCourse(User $user, Course $course): void
    {
        abort_unless(
            $user->getTutoringCoursesQuery()->whereKey($course->getKey())->exists(),
            403,
        );
    }

    private function ensureTutorCanAccessResidentialCourse(User $user, Course $course): void
    {
        $this->ensureTutorCanAccessCourse($user, $course);
        abort_unless($course->type === Module::TYPE_RESIDENTIAL, 404);
    }

    private function isTutorAttendanceEnrollmentValid(CourseEnrollment $enrollment): bool
    {
        return ! $enrollment->trashed()
            && $enrollment->user !== null
            && ! in_array($enrollment->status, [
                CourseEnrollment::STATUS_CANCELLED,
                CourseEnrollment::STATUS_EXPIRED,
            ], true);
    }

    /**
     * @return array{user_id: int, enrollment_id: int}|null
     */
    private function decodeResidentialAttendanceQrPayload(string $qrContent): ?array
    {
        $decoded = base64_decode(trim($qrContent), true);

        if ($decoded === false) {
            return null;
        }

        [$userId, $enrollmentId] = array_pad(explode('*', $decoded, 2), 2, null);

        if (! is_string($userId) || ! ctype_digit($userId) || ! is_string($enrollmentId) || ! ctype_digit($enrollmentId)) {
            return null;
        }

        return [
            'user_id' => (int) $userId,
            'enrollment_id' => (int) $enrollmentId,
        ];
    }

    private function inferAttendanceTypeForToday(Course $course, CourseEnrollment $enrollment): string
    {
        $latestRecord = $this->latestAttendanceRecordForToday($course, $enrollment);

        return $latestRecord?->type === 'entry' ? 'exit' : 'entry';
    }

    private function createAttendanceRecord(
        Course $course,
        CourseEnrollment $enrollment,
        User $recordingUser,
        string $type,
    ): void {
        $latestRecord = $this->latestAttendanceRecordForToday($course, $enrollment);

        $sessionId = $type === 'exit' && $latestRecord?->type === 'entry' && filled($latestRecord->session_id)
            ? $latestRecord->session_id
            : (string) Str::uuid();

        DB::table('course_attendance_records')->insert([
            'user_id' => $enrollment->user_id,
            'course_id' => $course->getKey(),
            'type' => $type,
            'session_id' => $sessionId,
            'created_by_user_id' => $recordingUser->getKey(),
            'recorded_at' => now(),
        ]);
    }

    private function latestAttendanceRecordForToday(Course $course, CourseEnrollment $enrollment): ?object
    {
        return DB::table('course_attendance_records')
            ->where('course_id', $course->getKey())
            ->where('user_id', $enrollment->user_id)
            ->whereBetween('recorded_at', [now()->startOfDay(), now()->endOfDay()])
            ->orderByDesc('recorded_at')
            ->orderByDesc('id')
            ->first(['type', 'session_id', 'recorded_at']);
    }

    private function tutorAttendanceRecords(Course $course, ?int $selectedAttendanceUserId = null): Collection
    {
        return DB::table('course_attendance_records')
            ->join('users', 'users.id', '=', 'course_attendance_records.user_id')
            ->where('course_attendance_records.course_id', $course->getKey())
            ->when($selectedAttendanceUserId !== null, function ($query) use ($selectedAttendanceUserId): void {
                $query->where('course_attendance_records.user_id', $selectedAttendanceUserId);
            })
            ->select([
                'course_attendance_records.user_id',
                'users.name',
                'users.surname',
                'course_attendance_records.type',
                'course_attendance_records.session_id',
                'course_attendance_records.recorded_at',
            ])
            ->orderBy('users.surname')
            ->orderBy('users.name')
            ->orderBy('course_attendance_records.user_id')
            ->orderBy('course_attendance_records.recorded_at')
            ->orderBy('course_attendance_records.id')
            ->get()
            ->map(function (object $record): array {
                return [
                    'user_id' => (int) $record->user_id,
                    'name' => $record->name,
                    'surname' => $record->surname,
                    'type' => $record->type === 'entry' ? __('Entrata') : __('Uscita'),
                    'session_id' => $record->session_id,
                    'recorded_at' => CarbonImmutable::parse($record->recorded_at)->format('d/m/Y H:i'),
                ];
            });
    }

    /**
     * @return array{content: string, data_uri: string}
     */
    private function buildResidentialAttendanceQrCode(User $user, CourseEnrollment $enrollment): array
    {
        $content = base64_encode($user->getAuthIdentifier().'*'.$enrollment->getKey());
        $svgMarkup = $this->qrCodeWriter()->writeString($content);
        $svg = trim(substr($svgMarkup, strpos($svgMarkup, "\n") + 1));

        return [
            'content' => $content,
            'data_uri' => 'data:image/svg+xml;base64,'.base64_encode($svg),
        ];
    }

    private function qrCodeWriter(): Writer
    {
        return new Writer(
            new ImageRenderer(
                new RendererStyle(220, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(17, 24, 39))),
                new SvgImageBackEnd
            )
        );
    }
}
