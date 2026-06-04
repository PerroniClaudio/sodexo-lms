<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassSchedule;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Support\UserGeographyMapper;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
                    ->select(['id', 'title', 'type'])
                    ->withCount('modules'),
                'currentModule:id,title,belongsTo',
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
                $currentModule = $enrollment->currentModule;

                return [
                    'title' => $course?->title ?? __('Corso senza titolo'),
                    'type' => (string) ($course?->type ?? 'unknown'),
                    'type_label' => $typeLabels[$course?->type ?? ''] ?? strtoupper((string) $course?->type),
                    'status' => (string) $enrollment->status,
                    'progress' => (int) ($enrollment->completion_percentage ?? 0),
                    'last_accessed_at' => $enrollment->last_accessed_at?->format('d/m/Y H:i'),
                    'modules_count' => (int) ($course?->modules_count ?? 0),
                    'open_url' => $course === null
                        ? route('user.courses.index')
                        : ($currentModule !== null
                            ? route('user.courses.modules.player', [$course, $currentModule])
                            : route('user.courses.show', $course)),
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
