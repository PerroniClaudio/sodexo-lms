<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleQuizSubmission;
use App\Models\SatisfactionSurveySubmissionAnswer;
use App\Models\SatisfactionSurveyTemplate;
use App\Models\User;
use App\Models\UserCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $followUpInactiveDays = $this->normalizeInactiveDays($request);

        return view('admin.dashboard', [
            'overview' => $this->buildOverview(),
            'followUpUsers' => $this->followUpUsers($followUpInactiveDays),
            'followUpInactiveDays' => $followUpInactiveDays,
            'compliance' => $this->buildComplianceSummary(),
            'evaluation' => $this->buildEvaluationSummary(),
            'recentResidentialWithoutDocuments' => $this->buildRecentResidentialWithoutDocuments(),
            'surveySummary' => $this->buildSurveySummary(),
            'certificateSummary' => $this->buildCertificateSummary(),
        ]);
    }

    public function calendarEvents(): JsonResponse
    {
        $residentialEvents = CourseClass::query()
            ->with([
                'module' => fn ($query) => $query
                    ->select(['id', 'title', 'type', 'belongsTo'])
                    ->with('course:id,title,type'),
                'schedules' => fn ($query) => $query
                    ->select(['id', 'course_class_id', 'starts_at', 'ends_at'])
                    ->orderBy('starts_at'),
            ])
            ->whereHas('module', function ($query): void {
                $query
                    ->where('type', Module::TYPE_RESIDENTIAL)
                    ->whereHas('course', fn ($courseQuery) => $courseQuery->where('type', 'res'));
            })
            ->get()
            ->flatMap(function (CourseClass $courseClass): Collection {
                $courseTitle = $courseClass->module?->course?->title ?? __('Corso senza titolo');
                $courseType = $courseClass->module?->course?->type;
                $moduleTitle = $courseClass->module?->title ?? __('Modulo senza titolo');

                return $courseClass->schedules->map(function ($schedule) use ($courseClass, $courseTitle, $courseType, $moduleTitle): array {
                    return [
                        'id' => sprintf('admin-class-%d-schedule-%d', $courseClass->getKey(), $schedule->getKey()),
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

        $asyncEvents = Module::query()
            ->select([
                'id',
                'title',
                'type',
                'belongsTo',
                'appointment_start_time',
                'appointment_end_time',
            ])
            ->with('course:id,title,type')
            ->where('type', Module::TYPE_SCORM)
            ->whereNotNull('appointment_start_time')
            ->whereHas('course', fn ($query) => $query->where('type', 'async'))
            ->get()
            ->map(function (Module $module): array {
                return [
                    'id' => sprintf('admin-module-%d', $module->getKey()),
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

    public function exportFollowUpUsers(Request $request): StreamedResponse
    {
        $followUpUsers = $this->followUpUsers($this->normalizeInactiveDays($request));
        $fileName = sprintf('utenti-da-sollecitare-%s.csv', now()->format('YmdHis'));

        return response()->streamDownload(function () use ($followUpUsers): void {
            $stream = fopen('php://output', 'w');

            if ($stream === false) {
                return;
            }

            fputcsv($stream, [
                'Utente',
                'Email',
                'Corsi aperti',
                'Ultimo accesso',
                'Corso aperto piu vecchio',
                'Avanzamento medio',
            ]);

            foreach ($followUpUsers as $user) {
                fputcsv($stream, [
                    $user['full_name'],
                    $user['email'],
                    $user['open_courses_count'],
                    $user['last_accessed_at_label'],
                    $user['oldest_open_course_title'],
                    $user['average_completion_percentage'].'%',
                ]);
            }

            fclose($stream);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @return array{
     *     learners_count: int,
     *     active_learners_count: int,
     *     published_courses_count: int,
     *     course_completion_average: int,
     *     completions_last_30_days: int,
     *     enrollment_statuses: array<string, int>,
     *     top_courses: array<int, array{
     *         title: string,
     *         total_enrollments: int,
     *         in_progress_enrollments: int,
     *         completed_enrollments: int
     *     }>
     * }
     */
    private function buildOverview(): array
    {
        $enrollmentStatuses = CourseEnrollment::query()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $topCourses = Course::query()
            ->select(['courses.id', 'courses.title'])
            ->withCount([
                'enrollments as total_enrollments',
                'enrollments as in_progress_enrollments' => fn ($query) => $query->where('status', CourseEnrollment::STATUS_IN_PROGRESS),
                'enrollments as completed_enrollments' => fn ($query) => $query->where('status', CourseEnrollment::STATUS_COMPLETED),
            ])
            ->orderByDesc('total_enrollments')
            ->orderBy('title')
            ->limit(5)
            ->get()
            ->map(fn (Course $course): array => [
                'title' => $course->title,
                'total_enrollments' => (int) $course->total_enrollments,
                'in_progress_enrollments' => (int) $course->in_progress_enrollments,
                'completed_enrollments' => (int) $course->completed_enrollments,
            ])
            ->all();

        return [
            'learners_count' => User::role('user')->count(),
            'active_learners_count' => User::role('user')
                ->where('account_state', 'active')
                ->count(),
            'published_courses_count' => Course::query()
                ->where('status', 'published')
                ->count(),
            'course_completion_average' => (int) round(
                CourseEnrollment::query()->avg('completion_percentage') ?? 0
            ),
            'completions_last_30_days' => CourseEnrollment::query()
                ->whereNotNull('completed_at')
                ->where('completed_at', '>=', now()->subDays(30))
                ->count(),
            'enrollment_statuses' => [
                CourseEnrollment::STATUS_ASSIGNED => (int) ($enrollmentStatuses[CourseEnrollment::STATUS_ASSIGNED] ?? 0),
                CourseEnrollment::STATUS_IN_PROGRESS => (int) ($enrollmentStatuses[CourseEnrollment::STATUS_IN_PROGRESS] ?? 0),
                CourseEnrollment::STATUS_COMPLETED => (int) ($enrollmentStatuses[CourseEnrollment::STATUS_COMPLETED] ?? 0),
                CourseEnrollment::STATUS_EXPIRED => (int) ($enrollmentStatuses[CourseEnrollment::STATUS_EXPIRED] ?? 0),
                CourseEnrollment::STATUS_CANCELLED => (int) ($enrollmentStatuses[CourseEnrollment::STATUS_CANCELLED] ?? 0),
            ],
            'top_courses' => $topCourses,
        ];
    }

    /**
     * @return Collection<int, array{
     *     full_name: string,
     *     email: string,
     *     open_courses_count: int,
     *     average_completion_percentage: int,
     *     last_accessed_at_label: string,
     *     last_accessed_at: ?Carbon,
     *     oldest_open_course_title: string,
     *     oldest_open_course_started_at_label: string
     * }>
     */
    private function followUpUsers(?int $inactiveDays = null): Collection
    {
        $users = User::role('user')
            ->select(['id', 'name', 'surname', 'email'])
            ->whereHas('courseEnrollments', function ($query) use ($inactiveDays): void {
                $query
                    ->whereNotNull('started_at')
                    ->whereNull('completed_at')
                    ->whereIn('status', [
                        CourseEnrollment::STATUS_ASSIGNED,
                        CourseEnrollment::STATUS_IN_PROGRESS,
                    ]);

                if ($inactiveDays !== null) {
                    $threshold = now()->subDays($inactiveDays);

                    $query->where(function ($inactiveQuery) use ($threshold): void {
                        $inactiveQuery
                            ->whereNull('last_accessed_at')
                            ->orWhere('last_accessed_at', '<', $threshold);
                    });
                }
            })
            ->with([
                'courseEnrollments' => function ($query): void {
                    $query
                        ->select([
                            'id',
                            'user_id',
                            'course_id',
                            'status',
                            'started_at',
                            'completed_at',
                            'last_accessed_at',
                            'completion_percentage',
                        ])
                        ->whereNotNull('started_at')
                        ->whereNull('completed_at')
                        ->whereIn('status', [
                            CourseEnrollment::STATUS_ASSIGNED,
                            CourseEnrollment::STATUS_IN_PROGRESS,
                        ])
                        ->with('course:id,title');
                },
            ])
            ->get()
            ->map(function (User $user): array {
                $openCourses = $user->courseEnrollments
                    ->filter(fn (CourseEnrollment $enrollment): bool => $enrollment->started_at !== null && $enrollment->completed_at === null)
                    ->sortBy('started_at')
                    ->values();
                $lastAccessedAt = $openCourses
                    ->pluck('last_accessed_at')
                    ->filter()
                    ->sortByDesc(fn (Carbon $date): int => $date->timestamp)
                    ->first();
                $oldestOpenCourse = $openCourses->first();

                return [
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'open_courses_count' => $openCourses->count(),
                    'average_completion_percentage' => (int) round($openCourses->avg('completion_percentage') ?? 0),
                    'last_accessed_at_label' => $lastAccessedAt?->format('d/m/Y H:i') ?? __('Mai'),
                    'last_accessed_at' => $lastAccessedAt,
                    'oldest_open_course_title' => $oldestOpenCourse?->course?->title ?? __('Corso senza titolo'),
                    'oldest_open_course_started_at_label' => $oldestOpenCourse?->started_at?->format('d/m/Y') ?? __('n/d'),
                ];
            })
            ->sortBy([
                ['open_courses_count', 'desc'],
                ['last_accessed_at', 'asc'],
            ])
            ->values();

        return $users;
    }

    /**
     * @return array{
     *     users_with_missing_requirements: int,
     *     users_with_expired_requirements: int,
     *     critical_users: array<int, array{full_name: string, missing_count: int, expired_count: int}>,
     *     top_requirements: array<int, array{name: string, affected_users_count: int}>
     * }
     */
    private function buildComplianceSummary(): array
    {
        $users = User::role('user')
            ->with(['jobSector', 'jobTasks', 'userCertificates.riskBasedRequirements'])
            ->get();

        $criticalUsers = collect();
        $requirementCounters = collect();
        $usersWithMissingRequirements = 0;
        $usersWithExpiredRequirements = 0;

        foreach ($users as $user) {
            $complianceItems = rescue(
                fn (): Collection => $user->checkRiskBasedRequirementsCompliance(),
                collect(),
                false,
            );

            $missingCount = $complianceItems->where('status', 'missing')->count();
            $expiredCount = $complianceItems->where('status', 'expired')->count();

            if ($missingCount > 0) {
                $usersWithMissingRequirements++;
            }

            if ($expiredCount > 0) {
                $usersWithExpiredRequirements++;
            }

            $complianceItems
                ->whereIn('status', ['missing', 'expired'])
                ->each(function (array $item) use ($requirementCounters): void {
                    $name = $item['risk_based_requirement_name'] ?? __('Requisito');
                    $requirementCounters->put($name, ($requirementCounters->get($name, 0) + 1));
                });

            if ($missingCount > 0 || $expiredCount > 0) {
                $criticalUsers->push([
                    'full_name' => $user->full_name,
                    'missing_count' => $missingCount,
                    'expired_count' => $expiredCount,
                ]);
            }
        }

        return [
            'users_with_missing_requirements' => $usersWithMissingRequirements,
            'users_with_expired_requirements' => $usersWithExpiredRequirements,
            'critical_users' => $criticalUsers
                ->sortByDesc(fn (array $item): int => $item['missing_count'] + $item['expired_count'])
                ->take(5)
                ->values()
                ->all(),
            'top_requirements' => $requirementCounters
                ->sortDesc()
                ->take(5)
                ->map(fn (int $count, string $name): array => [
                    'name' => $name,
                    'affected_users_count' => $count,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array{
     *     submissions_to_review_count: int,
     *     finalized_submissions_count: int,
     *     pass_rate: int,
     *     recent_modules: array<int, array{module_title: string, course_title: string, pending_reviews: int}>
     * }
     */
    private function buildEvaluationSummary(): array
    {
        $submissionsToReviewCount = ModuleQuizSubmission::query()
            ->where('status', ModuleQuizSubmission::STATUS_NEEDS_REVIEW)
            ->count();

        $finalizedSubmissionsCount = ModuleQuizSubmission::query()
            ->where('status', ModuleQuizSubmission::STATUS_FINALIZED)
            ->count();

        $passedFinalizedSubmissionsCount = ModuleQuizSubmission::query()
            ->where('status', ModuleQuizSubmission::STATUS_FINALIZED)
            ->whereColumn('score', '>=', 'module_quiz_submissions.total_score')
            ->whereNotNull('total_score')
            ->count();

        $recentModules = Module::query()
            ->select(['modules.id', 'modules.title', 'modules.belongsTo'])
            ->with([
                'course:id,title',
                'quizSubmissions' => fn ($query) => $query
                    ->select(['id', 'module_id', 'status'])
                    ->where('status', ModuleQuizSubmission::STATUS_NEEDS_REVIEW),
            ])
            ->whereHas('quizSubmissions', fn ($query) => $query->where('status', ModuleQuizSubmission::STATUS_NEEDS_REVIEW))
            ->limit(5)
            ->get()
            ->map(fn (Module $module): array => [
                'module_title' => $module->title,
                'course_title' => $module->course?->title ?? __('Corso senza titolo'),
                'pending_reviews' => $module->quizSubmissions->count(),
            ])
            ->all();

        return [
            'submissions_to_review_count' => $submissionsToReviewCount,
            'finalized_submissions_count' => $finalizedSubmissionsCount,
            'pass_rate' => $finalizedSubmissionsCount > 0
                ? (int) round(($passedFinalizedSubmissionsCount / $finalizedSubmissionsCount) * 100)
                : 0,
            'recent_modules' => $recentModules,
        ];
    }

    /**
     * @return Collection<int, array{
     *     course_title: string,
     *     module_title: string,
     *     latest_schedule_end_label: string,
     *     participants_count: int
     * }>
     */
    private function buildRecentResidentialWithoutDocuments(): Collection
    {
        return Module::query()
            ->select(['modules.id', 'modules.title', 'modules.belongsTo'])
            ->with([
                'course:id,title,type',
                'classes.schedules:id,course_class_id,starts_at,ends_at',
                'classes.userAssignments:id,course_class_id,user_id',
            ])
            ->where('type', Module::TYPE_RESIDENTIAL)
            ->whereHas('course', fn ($query) => $query->where('type', 'res'))
            ->whereDoesntHave('documentUploads')
            ->whereHas('classes.schedules', function ($query): void {
                $query
                    ->where('ends_at', '<=', now())
                    ->where('ends_at', '>=', now()->subDays(14));
            })
            ->get()
            ->map(function (Module $module): array {
                $latestScheduleEndAt = $module->classes
                    ->flatMap(fn (CourseClass $courseClass): Collection => $courseClass->schedules)
                    ->max('ends_at');
                $participantsCount = $module->classes
                    ->flatMap(fn (CourseClass $courseClass): Collection => $courseClass->userAssignments)
                    ->pluck('user_id')
                    ->unique()
                    ->count();

                return [
                    'course_title' => $module->course?->title ?? __('Corso senza titolo'),
                    'module_title' => $module->title,
                    'latest_schedule_end_label' => $latestScheduleEndAt?->format('d/m/Y H:i') ?? __('n/d'),
                    'participants_count' => $participantsCount,
                ];
            })
            ->sortByDesc('latest_schedule_end_label')
            ->values();
    }

    /**
     * @return array{
     *     submissions_count: int,
     *     questions: array<int, array{
     *         question: string,
     *         answers: array<int, array{
     *             label: string,
     *             count: int,
     *             percentage: int,
     *             is_top_answer: bool
     *         }>
     *     }>
     * }
     */
    private function buildSurveySummary(): array
    {
        $activeTemplate = SatisfactionSurveyTemplate::active();

        if (! $activeTemplate instanceof SatisfactionSurveyTemplate) {
            return [
                'submissions_count' => 0,
                'questions' => [],
            ];
        }

        $distributionRows = SatisfactionSurveySubmissionAnswer::query()
            ->select([
                'satisfaction_survey_question_id',
                'satisfaction_survey_answer_id',
                DB::raw('COUNT(*) as total'),
            ])
            ->whereIn(
                'satisfaction_survey_question_id',
                $activeTemplate->questions
                    ->filter(fn ($question) => $question->usesRadio())
                    ->pluck('id')
            )
            ->whereNotNull('satisfaction_survey_answer_id')
            ->groupBy('satisfaction_survey_question_id', 'satisfaction_survey_answer_id')
            ->get()
            ->groupBy('satisfaction_survey_question_id');

        $questions = $activeTemplate->questions
            ->filter(fn ($question) => $question->usesRadio())
            ->map(function ($question) use ($distributionRows): array {
                $rows = $distributionRows->get($question->getKey(), collect());
                $countsByAnswerId = $rows->pluck('total', 'satisfaction_survey_answer_id');
                $totalAnswers = (int) $rows->sum('total');
                $topCount = (int) $rows->max('total');

                return [
                    'question' => $question->text,
                    'answers' => $question->answers->map(function ($answer) use ($countsByAnswerId, $totalAnswers, $topCount): array {
                        $count = (int) ($countsByAnswerId[$answer->getKey()] ?? 0);

                        return [
                            'label' => $answer->text,
                            'count' => $count,
                            'percentage' => $totalAnswers > 0
                                ? (int) round(($count / $totalAnswers) * 100)
                                : 0,
                            'is_top_answer' => $count > 0 && $count === $topCount,
                        ];
                    })->all(),
                ];
            })->all();

        return [
            'submissions_count' => $activeTemplate->submissions()->count(),
            'questions' => $questions,
        ];
    }

    /**
     * @return array{
     *     issued_last_30_days_count: int,
     *     expiring_next_30_days_count: int,
     *     completed_without_certificate_count: int
     * }
     */
    private function buildCertificateSummary(): array
    {
        return [
            'issued_last_30_days_count' => UserCertificate::query()
                ->where('issued_at', '>=', now()->subDays(30)->toDateString())
                ->count(),
            'expiring_next_30_days_count' => UserCertificate::query()
                ->whereBetween('expires_at', [
                    now()->toDateString(),
                    now()->addDays(30)->toDateString(),
                ])
                ->count(),
            'completed_without_certificate_count' => CourseEnrollment::query()
                ->whereNotNull('completed_at')
                ->whereDoesntHave('user.userCertificates', function ($query): void {
                    $query->whereColumn('user_certificates.internal_course_id', 'course_user.course_id');
                })
                ->count(),
        ];
    }

    private function normalizeInactiveDays(Request $request): ?int
    {
        $days = $request->integer('inactive_days');

        return in_array($days, [7, 15, 30], true) ? $days : null;
    }

    private function asynchronousCourseLabel(): string
    {
        return __('FAD Asincrona');
    }
}
