<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassSchedule;
use App\Models\CourseClassTutor;
use App\Models\CourseEnrollment;
use App\Models\CourseTeacherEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Support\UserGeographyMapper;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Controller solo per gestione utenti da frontend (Blade, no API)
 * Gestione del profilo utente autenticato
 */
class UserController extends Controller
{
    public function __construct(
        private readonly UserGeographyMapper $userGeographyMapper,
    ) {}

    /**
     * Mostra dashboard utente autenticato
     */
    public function dashboard(): View
    {
        return view('user.dashboard', [
            'recentCourses' => $this->recentCoursesFor($this->authUser()),
        ]);
    }

    public function teacherDashboard(Request $request): View
    {
        $events = $request->boolean('test')
            ? $this->fakeTeacherEvents()
            : $this->teacherEventsFor();

        return view('teacher.dashboard', [
            'nextEvents' => $this->formattedTeacherEvents($events, 5),
        ]);
    }

    public function tutorDashboard(): View
    {
        return view('tutor.dashboard');
    }

    public function teacherAllEvents(Request $request): View
    {
        $events = $request->boolean('test')
            ? $this->fakeTeacherEvents()
            : $this->teacherEventsFor();

        return view('teacher.events', [
            'events' => $this->formattedTeacherEvents($events),
        ]);
    }

    /**
     * Mostra la pagina di modifica del proprio profilo utente
     */
    public function editOwnProfile(): View
    {
        $user = $this->authUser();
        $userRole = $user->roles()->first()?->name;

        // Dato che per ora possono fare le stesse modifiche nel profilo, manteniamo la stess view.
        return view('user.profile.edit', compact('user'));
    }

    /**
     * Aggiorna i dati personali dell'utente autenticato (profilo proprio)
     */
    public function updateOwnProfile(Request $request): RedirectResponse
    {
        $user = $this->authUser();
        $userRole = $user->roles()->first()?->name;

        $validated = $request->validate([
            'phone_prefix' => ['nullable', 'string', 'max:8'],
            'phone' => ['nullable', 'string', 'max:32'],
            'birth_date' => ['nullable', 'date'],
            'birth_place' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'string', 'max:1'],
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:16'],
        ]);

        // Conversione geografica come in update
        $data = $this->userGeographyMapper->toHomeIds($validated);

        $user->update($data);

        // Reindirizzamento in base al ruolo
        return redirect()->route($userRole.'.profile.edit')->with('status', __('Profilo aggiornato con successo!'));
    }

    public function coursesStats(): JsonResponse
    {
        $user = $this->authUser();
        $enrollments = $user->courseEnrollments()
            ->with('course:id,title')
            ->orderByDesc('last_accessed_at')
            ->orderByDesc('assigned_at')
            ->get([
                'id',
                'user_id',
                'course_id',
                'completion_percentage',
                'assigned_at',
                'last_accessed_at',
            ]);

        $overallProgress = (int) round($enrollments->avg('completion_percentage') ?? 0);

        return response()->json([
            'overall_progress' => $overallProgress,
            'remaining_progress' => max(0, 100 - $overallProgress),
            'courses' => $enrollments
                ->take(4)
                ->map(fn (CourseEnrollment $enrollment): array => [
                    'title' => $enrollment->course?->title ?? __('Corso senza titolo'),
                    'progress' => (int) ($enrollment->completion_percentage ?? 0),
                ])
                ->values(),
            'weekly_activity' => $this->weeklyActivityFor($user),
        ]);
    }

    public function calendarEvents(): JsonResponse
    {
        $user = $this->authUser();

        $assignedClasses = $user->courseClassAssignments()
            ->with([
                'courseClass' => fn ($query) => $query
                    ->select(['id', 'module_id', 'name'])
                    ->with([
                        'module' => fn ($moduleQuery) => $moduleQuery
                            ->select(['id', 'title', 'type', 'belongsTo'])
                            ->with('course:id,title,type'),
                        'schedules' => fn ($scheduleQuery) => $scheduleQuery
                            ->select(['id', 'course_class_id', 'starts_at', 'ends_at'])
                            ->orderBy('starts_at'),
                    ]),
            ])
            ->whereHas('courseClass.module', fn ($query) => $query->whereIn('type', [
                Module::TYPE_LIVE,
                Module::TYPE_RESIDENTIAL,
            ]))
            ->get()
            ->pluck('courseClass')
            ->filter(fn (?CourseClass $courseClass): bool => $courseClass instanceof CourseClass)
            ->unique(fn (CourseClass $courseClass): int => (int) $courseClass->getKey())
            ->values();

        $events = $assignedClasses
            ->flatMap(function (CourseClass $courseClass) {
                $courseTitle = $courseClass->module?->course?->title ?? __('Corso senza titolo');
                $courseType = $courseClass->module?->course?->type;
                $moduleTitle = $courseClass->module?->title ?? __('Modulo senza titolo');
                $eventType = $courseClass->module?->type;

                return $courseClass->schedules
                    ->map(function (CourseClassSchedule $schedule) use ($courseClass, $courseTitle, $courseType, $moduleTitle, $eventType): array {
                        return [
                            'id' => sprintf('class-%d-schedule-%d', $courseClass->getKey(), $schedule->getKey()),
                            'title' => $moduleTitle,
                            'start' => $schedule->starts_at->toAtomString(),
                            'end' => $schedule->ends_at->toAtomString(),
                            'allDay' => false,
                            'extendedProps' => [
                                'type' => $eventType,
                                'course_title' => $courseTitle,
                                'course_type' => $courseType,
                                'course_url' => $courseClass->module?->course !== null
                                    ? route('user.courses.show', $courseClass->module->course)
                                    : null,
                                'class_name' => $courseClass->name,
                                'module_id' => $courseClass->module?->getKey(),
                                'course_class_id' => $courseClass->getKey(),
                            ],
                        ];
                    });
            })
            ->sortBy('start')
            ->values();

        return response()->json([
            'events' => $events,
        ]);
    }

    public function fakeCalendarEvents(): JsonResponse
    {
        $baseDate = CarbonImmutable::today()->startOfMonth()->addDays(8);

        return response()->json([
            'events' => [
                [
                    'id' => 'fake-live-1',
                    'title' => 'Lezione Live: Deploy Vercel',
                    'start' => $baseDate->setTime(18, 0)->toAtomString(),
                    'end' => $baseDate->setTime(19, 30)->toAtomString(),
                    'allDay' => false,
                    'extendedProps' => [
                        'type' => Module::TYPE_LIVE,
                        'course_title' => 'React & Next.js',
                        'course_type' => 'fad',
                        'class_name' => 'Classe Live Serale',
                        'module_id' => 101,
                        'course_class_id' => 201,
                    ],
                ],
                [
                    'id' => 'fake-live-2',
                    'title' => 'Lezione Live: Q&A Tutor',
                    'start' => $baseDate->setTime(21, 0)->toAtomString(),
                    'end' => $baseDate->setTime(22, 0)->toAtomString(),
                    'allDay' => false,
                    'extendedProps' => [
                        'type' => Module::TYPE_LIVE,
                        'course_title' => 'React & Next.js',
                        'course_type' => 'fad',
                        'class_name' => 'Classe Live Serale',
                        'module_id' => 102,
                        'course_class_id' => 201,
                    ],
                ],
                [
                    'id' => 'fake-res-1',
                    'title' => 'Corso RES: Sicurezza Antincendio',
                    'start' => $baseDate->addDays(2)->setTime(9, 0)->toAtomString(),
                    'end' => $baseDate->addDays(2)->setTime(13, 0)->toAtomString(),
                    'allDay' => false,
                    'extendedProps' => [
                        'type' => Module::TYPE_RESIDENTIAL,
                        'course_title' => 'Formazione Obbligatoria',
                        'course_type' => 'res',
                        'class_name' => 'Aula Milano 1',
                        'module_id' => 103,
                        'course_class_id' => 202,
                    ],
                ],
                [
                    'id' => 'fake-live-3',
                    'title' => 'Webinar: Aggiornamento HACCP',
                    'start' => $baseDate->addDays(6)->setTime(17, 30)->toAtomString(),
                    'end' => $baseDate->addDays(6)->setTime(18, 30)->toAtomString(),
                    'allDay' => false,
                    'extendedProps' => [
                        'type' => Module::TYPE_LIVE,
                        'course_title' => 'Aggiornamenti Normativi',
                        'course_type' => 'fad',
                        'class_name' => 'Webinar Nazionale',
                        'module_id' => 104,
                        'course_class_id' => 203,
                    ],
                ],
                [
                    'id' => 'fake-res-2',
                    'title' => 'Corso RES: Primo Soccorso',
                    'start' => $baseDate->addDays(6)->setTime(10, 0)->toAtomString(),
                    'end' => $baseDate->addDays(6)->setTime(12, 30)->toAtomString(),
                    'allDay' => false,
                    'extendedProps' => [
                        'type' => Module::TYPE_RESIDENTIAL,
                        'course_title' => 'Formazione Presenziale',
                        'course_type' => 'res',
                        'class_name' => 'Aula Roma 2',
                        'module_id' => 105,
                        'course_class_id' => 204,
                    ],
                ],
            ],
        ]);
    }

    public function tutorCalendarEvents(): JsonResponse
    {
        $assignedClasses = CourseClassTutor::query()
            ->with([
                'courseClass' => fn ($query) => $query
                    ->select(['id', 'module_id', 'name'])
                    ->with([
                        'module' => fn ($moduleQuery) => $moduleQuery
                            ->select(['id', 'title', 'type', 'belongsTo'])
                            ->with('course:id,title,type'),
                        'schedules' => fn ($scheduleQuery) => $scheduleQuery
                            ->select(['id', 'course_class_id', 'starts_at', 'ends_at'])
                            ->orderBy('starts_at'),
                    ]),
            ])
            ->where('user_id', $this->authUser()->getKey())
            ->whereHas('courseClass.module', fn ($query) => $query->whereIn('type', [
                Module::TYPE_LIVE,
                Module::TYPE_RESIDENTIAL,
            ]))
            ->get()
            ->pluck('courseClass')
            ->filter(fn (?CourseClass $courseClass): bool => $courseClass instanceof CourseClass)
            ->unique(fn (CourseClass $courseClass): int => (int) $courseClass->getKey())
            ->values();

        $events = $assignedClasses
            ->flatMap(function (CourseClass $courseClass) {
                $courseTitle = $courseClass->module?->course?->title ?? __('Corso senza titolo');
                $courseType = $courseClass->module?->course?->type;
                $moduleTitle = $courseClass->module?->title ?? __('Modulo senza titolo');
                $eventType = $courseClass->module?->type;

                return $courseClass->schedules
                    ->map(function (CourseClassSchedule $schedule) use ($courseClass, $courseTitle, $courseType, $moduleTitle, $eventType): array {
                        return [
                            'id' => sprintf('tutor-class-%d-schedule-%d', $courseClass->getKey(), $schedule->getKey()),
                            'title' => $moduleTitle,
                            'start' => $schedule->starts_at->toAtomString(),
                            'end' => $schedule->ends_at->toAtomString(),
                            'allDay' => false,
                            'extendedProps' => [
                                'type' => $eventType,
                                'course_title' => $courseTitle,
                                'course_type' => $courseType,
                                'course_url' => $courseClass->module?->course !== null
                                    ? route('tutor.courses.show', $courseClass->module->course)
                                    : null,
                                'class_name' => $courseClass->name,
                                'module_id' => $courseClass->module?->getKey(),
                                'course_class_id' => $courseClass->getKey(),
                            ],
                        ];
                    });
            })
            ->sortBy('start')
            ->values();

        return response()->json([
            'events' => $events,
        ]);
    }

    public function teacherCalendarEvents(): JsonResponse
    {
        $user = $this->authUser();

        $assignedResidentialClasses = $user->teachingCourseClassAssignments()
            ->with([
                'courseClass' => fn ($query) => $query
                    ->select(['id', 'module_id', 'name'])
                    ->with([
                        'module' => fn ($moduleQuery) => $moduleQuery
                            ->select(['id', 'title', 'type', 'belongsTo'])
                            ->with('course:id,title,type'),
                        'schedules' => fn ($scheduleQuery) => $scheduleQuery
                            ->select(['id', 'course_class_id', 'starts_at', 'ends_at'])
                            ->orderBy('starts_at'),
                    ]),
            ])
            ->whereHas('courseClass.module', fn ($query) => $query->where('type', Module::TYPE_RESIDENTIAL))
            ->get()
            ->pluck('courseClass')
            ->filter(fn (?CourseClass $courseClass): bool => $courseClass instanceof CourseClass)
            ->unique(fn (CourseClass $courseClass): int => (int) $courseClass->getKey())
            ->values();

        $residentialEvents = $assignedResidentialClasses
            ->flatMap(function (CourseClass $courseClass) {
                $courseTitle = $courseClass->module?->course?->title ?? __('Corso senza titolo');
                $courseType = $courseClass->module?->course?->type;
                $moduleTitle = $courseClass->module?->title ?? __('Modulo senza titolo');

                return $courseClass->schedules
                    ->map(function (CourseClassSchedule $schedule) use ($courseClass, $courseTitle, $courseType, $moduleTitle): array {
                        return [
                            'id' => sprintf('teacher-class-%d-schedule-%d', $courseClass->getKey(), $schedule->getKey()),
                            'title' => $moduleTitle,
                            'start' => $schedule->starts_at->toAtomString(),
                            'end' => $schedule->ends_at->toAtomString(),
                            'allDay' => false,
                            'extendedProps' => [
                                'type' => Module::TYPE_RESIDENTIAL,
                                'course_title' => $courseTitle,
                                'course_type' => $courseType,
                                'class_name' => $courseClass->name,
                                'module_id' => $courseClass->module?->getKey(),
                                'course_class_id' => $courseClass->getKey(),
                            ],
                        ];
                    });
            });

        $assignedAsyncModules = $user->moduleTeacherEnrollments()
            ->with([
                'module' => fn ($query) => $query
                    ->select([
                        'id',
                        'title',
                        'type',
                        'belongsTo',
                        'appointment_start_time',
                        'appointment_end_time',
                    ])
                    ->with('course:id,title,type'),
            ])
            ->whereHas('module', function ($query): void {
                $query
                    ->where('type', Module::TYPE_SCORM)
                    ->whereHas('course', fn ($courseQuery) => $courseQuery->where('type', 'async'));
            })
            ->get()
            ->pluck('module')
            ->filter(fn (?Module $module): bool => $module instanceof Module && $module->appointment_start_time !== null)
            ->unique(fn (Module $module): int => (int) $module->getKey())
            ->values();

        $asyncEvents = $assignedAsyncModules->map(function (Module $module): array {
            return [
                'id' => sprintf('teacher-module-%d', $module->getKey()),
                'title' => $module->title ?? __('Modulo senza titolo'),
                'start' => $module->appointment_start_time?->toAtomString(),
                'end' => $module->appointment_end_time?->toAtomString(),
                'allDay' => false,
                'extendedProps' => [
                    'type' => 'async',
                    'course_title' => $module->course?->title ?? __('Corso senza titolo'),
                    'course_type' => $module->course?->type,
                    'class_name' => $this->asynchronousCourseLabel(),
                    'module_id' => $module->getKey(),
                    'course_class_id' => null,
                ],
            ];
        });

        return response()->json([
            'events' => $residentialEvents
                ->concat($asyncEvents)
                ->sortBy('start')
                ->values(),
        ]);
    }

    public function fakeTeacherCalendarEvents(): JsonResponse
    {
        return response()->json([
            'events' => $this->fakeTeacherEvents(),
        ]);
    }

    public function teacherCourses(): JsonResponse
    {
        return response()->json([
            'courses' => $this->teacherCourseCardsFor($this->authUser()),
        ]);
    }

    public function fakeTeacherCourses(): JsonResponse
    {
        return response()->json([
            'courses' => $this->fakeTeacherCourseCards(),
        ]);
    }

    public function teacherUserEngagement(): JsonResponse
    {
        return response()->json(
            $this->teacherUserEngagementFor($this->authUser())
        );
    }

    public function fakeTeacherUserEngagement(): JsonResponse
    {
        return response()->json(
            $this->fakeTeacherUserEngagementData()
        );
    }

    public function teacherUserActivity(): JsonResponse
    {
        return response()->json([
            'activities' => $this->teacherUserActivityFor($this->authUser()),
        ]);
    }

    public function fakeTeacherUserActivity(): JsonResponse
    {
        return response()->json([
            'activities' => $this->fakeTeacherUserActivityData(),
        ]);
    }

    public function testTeacherNextEvents(): View
    {
        return view('teacher.test-next-events', [
            'nextEvents' => $this->formattedTeacherEvents($this->fakeTeacherEvents(), 5),
        ]);
    }

    /**
     * @return array{
     *     labels: array<int, string>,
     *     hours: array<int, float>
     * }
     */
    private function weeklyActivityFor(User $user): array
    {
        $today = CarbonImmutable::today();
        $startDate = $today->subDays(6)->startOfDay();

        $hoursByDate = ModuleProgress::query()
            ->join('course_user', 'course_user.id', '=', 'module_user.course_user_id')
            ->where('course_user.user_id', $user->getKey())
            ->whereNotNull('module_user.last_accessed_at')
            ->where('module_user.last_accessed_at', '>=', $startDate)
            ->selectRaw('DATE(module_user.last_accessed_at) as activity_date')
            ->selectRaw('SUM(module_user.time_spent_seconds) as total_time_spent_seconds')
            ->groupBy('activity_date')
            ->pluck('total_time_spent_seconds', 'activity_date');

        $days = collect(range(0, 6))
            ->map(fn (int $offset): CarbonImmutable => $startDate->addDays($offset));

        return [
            'labels' => $days
                ->map(fn (CarbonImmutable $day): string => ucfirst($day->locale(app()->getLocale())->translatedFormat('D')))
                ->all(),
            'hours' => $days
                ->map(function (CarbonImmutable $day) use ($hoursByDate): float {
                    $seconds = (int) ($hoursByDate[$day->toDateString()] ?? 0);

                    return round($seconds / 3600, 2);
                })
                ->all(),
        ];
    }

    /**
     * @return array{
     *     labels: array<int, string>,
     *     active_users: array<int, int>,
     *     completed_users: array<int, int>,
     *     totals: array{
     *         active_week: int,
     *         completed_week: int,
     *         active_today: int,
     *         completed_today: int
     *     }
     * }
     */
    private function teacherUserEngagementFor(User $user): array
    {
        $today = CarbonImmutable::today();
        $startDate = $today->subDays(6)->startOfDay();

        $courseIds = CourseTeacherEnrollment::query()
            ->join('courses', 'courses.id', '=', 'course_teacher_enrollments.course_id')
            ->where('course_teacher_enrollments.user_id', $user->getKey())
            ->whereNull('course_teacher_enrollments.deleted_at')
            ->whereNull('courses.deleted_at')
            ->where('courses.status', 'published')
            ->distinct()
            ->pluck('course_teacher_enrollments.course_id');

        $days = collect(range(0, 6))
            ->map(fn (int $offset): CarbonImmutable => $startDate->addDays($offset));

        if ($courseIds->isEmpty()) {
            $zeroSeries = $days->map(fn (): int => 0)->all();

            return [
                'labels' => $days
                    ->map(fn (CarbonImmutable $day): string => ucfirst($day->locale(app()->getLocale())->translatedFormat('D')))
                    ->all(),
                'active_users' => $zeroSeries,
                'completed_users' => $zeroSeries,
                'totals' => [
                    'active_week' => 0,
                    'completed_week' => 0,
                    'active_today' => 0,
                    'completed_today' => 0,
                ],
            ];
        }

        $activeUsersByDate = CourseEnrollment::query()
            ->whereIn('course_id', $courseIds)
            ->whereNull('deleted_at')
            ->whereIn('status', [
                CourseEnrollment::STATUS_ASSIGNED,
                CourseEnrollment::STATUS_IN_PROGRESS,
            ])
            ->whereNotNull('last_accessed_at')
            ->where('last_accessed_at', '>=', $startDate)
            ->selectRaw('DATE(last_accessed_at) as activity_date')
            ->selectRaw('COUNT(DISTINCT user_id) as active_users')
            ->groupBy('activity_date')
            ->pluck('active_users', 'activity_date');

        $completedUsersByDate = CourseEnrollment::query()
            ->whereIn('course_id', $courseIds)
            ->whereNull('deleted_at')
            ->where('status', CourseEnrollment::STATUS_COMPLETED)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', $startDate)
            ->selectRaw('DATE(completed_at) as completion_date')
            ->selectRaw('COUNT(DISTINCT user_id) as completed_users')
            ->groupBy('completion_date')
            ->pluck('completed_users', 'completion_date');

        $activeUsers = $days
            ->map(fn (CarbonImmutable $day): int => (int) ($activeUsersByDate[$day->toDateString()] ?? 0))
            ->all();

        $completedUsers = $days
            ->map(fn (CarbonImmutable $day): int => (int) ($completedUsersByDate[$day->toDateString()] ?? 0))
            ->all();

        return [
            'labels' => $days
                ->map(fn (CarbonImmutable $day): string => ucfirst($day->locale(app()->getLocale())->translatedFormat('D')))
                ->all(),
            'active_users' => $activeUsers,
            'completed_users' => $completedUsers,
            'totals' => [
                'active_week' => array_sum($activeUsers),
                'completed_week' => array_sum($completedUsers),
                'active_today' => $activeUsers[array_key_last($activeUsers)] ?? 0,
                'completed_today' => $completedUsers[array_key_last($completedUsers)] ?? 0,
            ],
        ];
    }

    /**
     * @return array{
     *     labels: array<int, string>,
     *     active_users: array<int, int>,
     *     completed_users: array<int, int>,
     *     totals: array{
     *         active_week: int,
     *         completed_week: int,
     *         active_today: int,
     *         completed_today: int
     *     }
     * }
     */
    private function fakeTeacherUserEngagementData(): array
    {
        $labels = ['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'];
        $activeUsers = [312, 278, 450, 392, 518, 182, 140];
        $completedUsers = [46, 39, 62, 55, 78, 21, 16];

        return [
            'labels' => $labels,
            'active_users' => $activeUsers,
            'completed_users' => $completedUsers,
            'totals' => [
                'active_week' => array_sum($activeUsers),
                'completed_week' => array_sum($completedUsers),
                'active_today' => 140,
                'completed_today' => 16,
            ],
        ];
    }

    /**
     * @return Collection<int, array{
     *     type: string,
     *     label: string,
     *     message: string,
     *     context: string,
     *     occurred_at: string,
     *     occurred_at_label: string
     * }>
     */
    private function teacherUserActivityFor(User $user): Collection
    {
        $teacherModules = Module::query()
            ->select(['modules.id', 'modules.belongsTo', 'modules.title'])
            ->with('course:id,title,status')
            ->whereNull('modules.deleted_at')
            ->where(function ($query) use ($user): void {
                $query
                    ->whereHas('teacherEnrollments', function ($teacherEnrollmentQuery) use ($user): void {
                        $teacherEnrollmentQuery
                            ->where('user_id', $user->getKey())
                            ->whereNull('deleted_at');
                    })
                    ->orWhereHas('classes.teacherAssignments', function ($classTeacherQuery) use ($user): void {
                        $classTeacherQuery
                            ->where('user_id', $user->getKey())
                            ->whereNull('deleted_at');
                    });
            })
            ->get();

        if ($teacherModules->isEmpty()) {
            return collect();
        }

        $teacherModuleIds = $teacherModules
            ->pluck('id')
            ->map(fn (mixed $moduleId): int => (int) $moduleId)
            ->values();

        $teacherCourseIds = $teacherModules
            ->pluck('belongsTo')
            ->filter()
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->unique()
            ->values();

        $moduleActivities = ModuleProgress::query()
            ->join('course_user', 'course_user.id', '=', 'module_user.course_user_id')
            ->join('users', 'users.id', '=', 'course_user.user_id')
            ->join('modules', 'modules.id', '=', 'module_user.module_id')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->whereIn('module_user.module_id', $teacherModuleIds)
            ->where('course_user.user_id', '!=', $user->getKey())
            ->whereNotNull('module_user.completed_at')
            ->whereNull('course_user.deleted_at')
            ->whereNull('courses.deleted_at')
            ->where('courses.status', 'published')
            ->orderByDesc('module_user.completed_at')
            ->limit(5)
            ->get([
                'users.name as user_name',
                'users.surname as user_surname',
                'modules.title as module_title',
                'courses.title as course_title',
                'module_user.completed_at as occurred_at',
            ])
            ->map(function (object $activity): array {
                $occurredAt = Carbon::parse($activity->occurred_at);
                $displayName = $this->pointedUserName(
                    (string) $activity->user_name,
                    (string) $activity->user_surname
                );
                $moduleTitle = (string) ($activity->module_title ?: __('Modulo senza titolo'));
                $courseTitle = (string) ($activity->course_title ?: __('Corso senza titolo'));

                return [
                    'type' => 'module_completed',
                    'label' => __('Modulo completato'),
                    'message' => __(':user ha completato :module', [
                        'user' => $displayName,
                        'module' => $moduleTitle,
                    ]),
                    'context' => $courseTitle,
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'occurred_at_label' => $this->humanizeActivityTimestamp($occurredAt),
                ];
            });

        $courseActivities = CourseEnrollment::query()
            ->join('users', 'users.id', '=', 'course_user.user_id')
            ->join('courses', 'courses.id', '=', 'course_user.course_id')
            ->whereIn('course_user.course_id', $teacherCourseIds)
            ->where('course_user.user_id', '!=', $user->getKey())
            ->where('course_user.status', CourseEnrollment::STATUS_COMPLETED)
            ->whereNotNull('course_user.completed_at')
            ->whereNull('course_user.deleted_at')
            ->whereNull('courses.deleted_at')
            ->where('courses.status', 'published')
            ->orderByDesc('course_user.completed_at')
            ->limit(5)
            ->get([
                'users.name as user_name',
                'users.surname as user_surname',
                'courses.title as course_title',
                'course_user.completed_at as occurred_at',
            ])
            ->map(function (object $activity): array {
                $occurredAt = Carbon::parse($activity->occurred_at);
                $displayName = $this->pointedUserName(
                    (string) $activity->user_name,
                    (string) $activity->user_surname
                );
                $courseTitle = (string) ($activity->course_title ?: __('Corso senza titolo'));

                return [
                    'type' => 'course_completed',
                    'label' => __('Corso completato'),
                    'message' => __(':user ha completato il corso', [
                        'user' => $displayName,
                    ]),
                    'context' => $courseTitle,
                    'occurred_at' => $occurredAt->toIso8601String(),
                    'occurred_at_label' => $this->humanizeActivityTimestamp($occurredAt),
                ];
            });

        return $moduleActivities
            ->concat($courseActivities)
            ->sortByDesc('occurred_at')
            ->take(5)
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     type: string,
     *     label: string,
     *     message: string,
     *     context: string,
     *     occurred_at: string,
     *     occurred_at_label: string
     * }>
     */
    private function fakeTeacherUserActivityData(): Collection
    {
        return collect([
            [
                'type' => 'module_completed',
                'label' => 'Modulo completato',
                'message' => 'Sofia R. ha completato Introduzione a React',
                'context' => 'React Avanzato',
                'occurred_at' => now()->subMinutes(4)->toIso8601String(),
                'occurred_at_label' => '4 min fa',
            ],
            [
                'type' => 'course_completed',
                'label' => 'Corso completato',
                'message' => 'Luca B. ha completato il corso',
                'context' => 'Node.js per Team Leader',
                'occurred_at' => now()->subMinutes(18)->toIso8601String(),
                'occurred_at_label' => '18 min fa',
            ],
            [
                'type' => 'module_completed',
                'label' => 'Modulo completato',
                'message' => 'Giulia P. ha completato Quiz Finale HACCP',
                'context' => 'Aggiornamento HACCP 2026',
                'occurred_at' => now()->subHour()->toIso8601String(),
                'occurred_at_label' => '1 h fa',
            ],
            [
                'type' => 'module_completed',
                'label' => 'Modulo completato',
                'message' => 'Marco T. ha completato Simulazione antincendio',
                'context' => 'Sicurezza in Negozio',
                'occurred_at' => now()->subHours(2)->toIso8601String(),
                'occurred_at_label' => '2 h fa',
            ],
            [
                'type' => 'course_completed',
                'label' => 'Corso completato',
                'message' => 'Anna C. ha completato il corso',
                'context' => 'Onboarding Store Manager',
                'occurred_at' => now()->subHours(5)->toIso8601String(),
                'occurred_at_label' => '5 h fa',
            ],
        ]);
    }

    private function pointedUserName(string $name, string $surname): string
    {
        $trimmedName = trim($name);
        $trimmedSurname = trim($surname);

        if ($trimmedSurname === '') {
            return $trimmedName;
        }

        $surnameInitial = mb_strtoupper(mb_substr($trimmedSurname, 0, 1));

        return sprintf('%s %s.', $trimmedName, $surnameInitial);
    }

    private function humanizeActivityTimestamp(Carbon $timestamp): string
    {
        return $timestamp
            ->locale(app()->getLocale())
            ->diffForHumans(now(), short: true, parts: 1);
    }

    /**
     * @return Collection<int, array{
     *     title: string,
     *     type: string,
     *     type_label: string,
     *     class_name: string,
     *     participants_count: int,
     *     participants_label: string,
     *     occupancy_label: string,
     *     completion_percentage: int
     * }>
     */
    private function teacherCourseCardsFor(User $user): Collection
    {
        $typeLabels = Course::availableTypeLabels();

        $courseClasses = CourseClass::query()
            ->withCount('users')
            ->with([
                'module' => fn ($query) => $query
                    ->select(['id', 'belongsTo', 'type'])
                    ->with([
                        'course' => fn ($courseQuery) => $courseQuery->select(['id', 'title', 'type', 'status']),
                    ]),
            ])
            ->whereHas('teacherAssignments', function ($query) use ($user): void {
                $query
                    ->where('user_id', $user->getKey())
                    ->whereNull('deleted_at');
            })
            ->whereHas('module', function ($query): void {
                $query
                    ->whereNull('modules.deleted_at')
                    ->whereHas('course', function ($courseQuery): void {
                        $courseQuery
                            ->where('status', 'published')
                            ->whereNull('courses.deleted_at');
                    });
            })
            ->get();

        $completionPercentages = CourseEnrollment::query()
            ->select('course_class_users.course_class_id')
            ->selectRaw('COALESCE(ROUND(AVG(course_user.completion_percentage)), 0) as completion_percentage')
            ->join('course_class_users', function ($join): void {
                $join
                    ->on('course_class_users.user_id', '=', 'course_user.user_id')
                    ->whereNull('course_class_users.deleted_at');
            })
            ->join('course_classes', 'course_classes.id', '=', 'course_class_users.course_class_id')
            ->join('modules', 'modules.id', '=', 'course_classes.module_id')
            ->whereIn('course_class_users.course_class_id', $courseClasses->pluck('id'))
            ->whereNull('course_user.deleted_at')
            ->whereColumn('course_user.course_id', 'modules.belongsTo')
            ->groupBy('course_class_users.course_class_id')
            ->pluck('completion_percentage', 'course_class_users.course_class_id');

        return $courseClasses
            ->sortBy(function (CourseClass $courseClass): string {
                return sprintf(
                    '%s|%s',
                    mb_strtolower((string) ($courseClass->module?->course?->title ?? '')),
                    mb_strtolower($courseClass->name)
                );
            })
            ->values()
            ->map(function (CourseClass $courseClass) use ($completionPercentages, $typeLabels): array {
                $participantsCount = (int) ($courseClass->users_count ?? 0);
                $completionPercentage = (int) ($completionPercentages[$courseClass->getKey()] ?? 0);

                return [
                    'title' => $courseClass->module?->course?->title ?? __('Corso senza titolo'),
                    'type' => $courseClass->module?->course?->type ?? 'unknown',
                    'type_label' => $typeLabels[$courseClass->module?->course?->type ?? ''] ?? strtoupper((string) ($courseClass->module?->course?->type ?? __('Corso'))),
                    'class_name' => $courseClass->name,
                    'participants_count' => $participantsCount,
                    'participants_label' => trans_choice('{1} :count partecipante|[2,*] :count partecipanti', $participantsCount, ['count' => $participantsCount]),
                    'occupancy_label' => __(':count/:max posti', ['count' => $participantsCount, 'max' => CourseClass::MAX_USERS]),
                    'completion_percentage' => $completionPercentage,
                ];
            });
    }

    /**
     * @return Collection<int, array{
     *     title: string,
     *     type: string,
     *     type_label: string,
     *     class_name: string,
     *     participants_count: int,
     *     participants_label: string,
     *     occupancy_label: string,
     *     completion_percentage: int
     * }>
     */
    private function fakeTeacherCourseCards(): Collection
    {
        return collect([
            [
                'title' => 'React Avanzato',
                'type' => 'fad',
                'type_label' => 'FAD',
                'class_name' => 'Frontend Pro - Edizione Mattina',
                'participants_count' => 18,
                'participants_label' => '18 partecipanti',
                'occupancy_label' => '18/30 posti',
                'completion_percentage' => 72,
            ],
            [
                'title' => 'Sicurezza in Negozio',
                'type' => 'res',
                'type_label' => 'RES',
                'class_name' => 'Aula Milano 2',
                'participants_count' => 24,
                'participants_label' => '24 partecipanti',
                'occupancy_label' => '24/30 posti',
                'completion_percentage' => 46,
            ],
            [
                'title' => 'Onboarding Store Manager',
                'type' => 'async',
                'type_label' => 'FAD ASINCRONA',
                'class_name' => 'Percorso Nazionale Giugno',
                'participants_count' => 12,
                'participants_label' => '12 partecipanti',
                'occupancy_label' => '12/30 posti',
                'completion_percentage' => 84,
            ],
        ]);
    }

    /**
     * @return Collection<int, array{
     *     title: string,
     *     type: string,
     *     type_label: string,
     *     status: string,
     *     progress: int,
     *     last_accessed_at: ?string,
     *     modules_count: int,
     *     open_url: string
     * }>
     */
    private function recentCoursesFor(User $user): Collection
    {
        $typeLabels = Course::availableTypeLabels();

        return $user->courseEnrollments()
            ->with([
                'course' => fn ($query) => $query
                    ->select(['id', 'title', 'type', 'expiry_date'])
                    ->withCount('modules'),
            ])
            ->whereNotNull('last_accessed_at')
            ->orderByDesc('last_accessed_at')
            ->limit(4)
            ->get([
                'id',
                'user_id',
                'course_id',
                'current_module_id',
                'status',
                'completion_percentage',
                'last_accessed_at',
            ])
            ->sort(function (CourseEnrollment $first, CourseEnrollment $second): int {
                $firstPriority = $this->dashboardStatusPriority($first->status);
                $secondPriority = $this->dashboardStatusPriority($second->status);

                if ($firstPriority !== $secondPriority) {
                    return $firstPriority <=> $secondPriority;
                }

                return $second->last_accessed_at?->getTimestamp() <=> $first->last_accessed_at?->getTimestamp();
            })
            ->map(function (CourseEnrollment $enrollment) use ($typeLabels): array {
                $course = $enrollment->course;

                return [
                    'title' => $course?->title ?? __('Corso senza titolo'),
                    'type' => (string) ($course?->type ?? 'unknown'),
                    'type_label' => $typeLabels[$course?->type ?? ''] ?? strtoupper((string) $course?->type),
                    'status' => (string) $enrollment->status,
                    'progress' => (int) ($enrollment->completion_percentage ?? 0),
                    'last_accessed_at' => $enrollment->last_accessed_at?->format('d/m/Y H:i'),
                    'expiry_date' => $course?->expiry_date?->format('d/m/Y'),
                    'modules_count' => (int) ($course?->modules_count ?? 0),
                    'open_url' => $course === null
                        ? route('user.courses.index')
                        : route('user.courses.show', $course),
                ];
            })
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function teacherEventsFor(): Collection
    {
        $events = $this->teacherCalendarEvents()->getData(true)['events'] ?? [];

        return collect($events);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function fakeTeacherEvents(): Collection
    {
        $today = CarbonImmutable::today();

        return collect([
            [
                'id' => 'teacher-async-1',
                'title' => 'Lezione FAD Asincrona: React Hooks',
                'start' => $today->addDay()->setTime(10, 0)->toAtomString(),
                'end' => $today->addDay()->setTime(11, 30)->toAtomString(),
                'allDay' => false,
                'extendedProps' => [
                    'type' => 'async',
                    'course_title' => 'React Avanzato',
                    'course_type' => 'async',
                    'class_name' => $this->asynchronousCourseLabel(),
                    'module_id' => 601,
                    'course_class_id' => 701,
                ],
            ],
            [
                'id' => 'teacher-async-1',
                'title' => 'Scadenza consegna progetto',
                'start' => $today->addDays(4)->setTime(23, 59)->toAtomString(),
                'end' => $today->addDays(4)->setTime(23, 59)->toAtomString(),
                'allDay' => false,
                'extendedProps' => [
                    'type' => 'async',
                    'course_title' => 'Node.js Masterclass',
                    'course_type' => 'async',
                    'class_name' => $this->asynchronousCourseLabel(),
                    'module_id' => 602,
                    'course_class_id' => null,
                ],
            ],
            [
                'id' => 'teacher-async-2',
                'title' => 'Lezione FAD Asincrona: API Testing',
                'start' => $today->addDays(6)->setTime(14, 0)->toAtomString(),
                'end' => $today->addDays(6)->setTime(15, 30)->toAtomString(),
                'allDay' => false,
                'extendedProps' => [
                    'type' => 'async',
                    'course_title' => 'Testing & TDD',
                    'course_type' => 'async',
                    'class_name' => $this->asynchronousCourseLabel(),
                    'module_id' => 603,
                    'course_class_id' => 702,
                ],
            ],
            [
                'id' => 'teacher-res-1',
                'title' => 'Workshop in aula: Leadership',
                'start' => $today->addDays(8)->setTime(16, 0)->toAtomString(),
                'end' => $today->addDays(8)->setTime(18, 0)->toAtomString(),
                'allDay' => false,
                'extendedProps' => [
                    'type' => Module::TYPE_RESIDENTIAL,
                    'course_title' => 'People Management',
                    'course_type' => 'res',
                    'class_name' => 'Aula Milano 3',
                    'module_id' => 604,
                    'course_class_id' => 703,
                ],
            ],
            [
                'id' => 'teacher-res-2',
                'title' => 'Laboratorio: Public Speaking',
                'start' => $today->addDays(11)->setTime(9, 0)->toAtomString(),
                'end' => $today->addDays(11)->setTime(10, 0)->toAtomString(),
                'allDay' => false,
                'extendedProps' => [
                    'type' => Module::TYPE_RESIDENTIAL,
                    'course_title' => 'Soft Skills Sprint',
                    'course_type' => 'res',
                    'class_name' => 'Aula Roma 2',
                    'module_id' => 605,
                    'course_class_id' => 704,
                ],
            ],
        ])->sortBy('start')->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $events
     * @return Collection<int, array<string, mixed>>
     */
    private function formattedTeacherEvents(Collection $events, ?int $limit = null): Collection
    {
        $formattedEvents = $events
            ->filter(function (array $event): bool {
                $start = Arr::get($event, 'start');

                return is_string($start) && CarbonImmutable::parse($start)->greaterThanOrEqualTo(CarbonImmutable::now()->startOfDay());
            })
            ->sortBy('start')
            ->values()
            ->map(function (array $event): array {
                $start = CarbonImmutable::parse((string) $event['start']);
                $end = is_string($event['end'] ?? null)
                    ? CarbonImmutable::parse((string) $event['end'])
                    : null;
                $className = Arr::get($event, 'extendedProps.class_name');
                $timeLabel = $end instanceof CarbonImmutable && ! $start->equalTo($end)
                    ? $start->format('H:i').' - '.$end->format('H:i')
                    : $start->format('H:i');

                return [
                    'id' => (string) $event['id'],
                    'title' => (string) $event['title'],
                    'type' => (string) Arr::get($event, 'extendedProps.type', 'unknown'),
                    'course_type' => (string) Arr::get($event, 'extendedProps.course_type', 'unknown'),
                    'course_title' => (string) Arr::get($event, 'extendedProps.course_title', __('Corso senza titolo')),
                    'class_name' => is_string($className) ? $className : null,
                    'date_label' => $start->isToday()
                        ? __('Oggi')
                        : ucfirst($start->locale(app()->getLocale())->translatedFormat('j F')),
                    'time_label' => $timeLabel,
                    'is_today' => $start->isToday(),
                ];
            });

        return $limit === null
            ? $formattedEvents
            : $formattedEvents->take($limit)->values();
    }

    private function dashboardStatusPriority(string $status): int
    {
        return match ($status) {
            CourseEnrollment::STATUS_IN_PROGRESS,
            CourseEnrollment::STATUS_ASSIGNED => 0,
            CourseEnrollment::STATUS_COMPLETED => 1,
            default => 2,
        };
    }

    private function asynchronousCourseLabel(): string
    {
        return __('FAD Asincrona');
    }

    private function authUser(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }
}
