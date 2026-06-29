<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ActivateCustomCertificateTemplate;
use App\Actions\DuplicateCourse;
use App\Actions\DuplicateCourseStructure;
use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmCourseAttendanceRequest;
use App\Http\Requests\DuplicateCourseStructureRequest;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseAttachmentsRequest;
use App\Http\Requests\UpdateCourseCategoriesRequest;
use App\Http\Requests\UpdateCourseCertificatesRequest;
use App\Http\Requests\UpdateCourseCertificateTemplateRequest;
use App\Http\Requests\UpdateCourseDetailsRequest;
use App\Http\Requests\UpdateCourseDurationRequest;
use App\Http\Requests\UpdateCoursePartnersRequest;
use App\Http\Requests\UpdateCourseProgramRequest;
use App\Http\Requests\UpdateCourseRecipientsRequest;
use App\Http\Requests\UpdateCourseSurveyRequest;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\CourseDocument;
use App\Models\CustomCertificate;
use App\Models\FundingEntity;
use App\Models\JobRole;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\LanguageLevel;
use App\Models\Module;
use App\Models\Partner;
use App\Models\Province;
use App\Models\RiskBasedRequirement;
use App\Models\SatisfactionSurveyTemplate;
use App\Models\TrainingPath;
use App\Models\Venue;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use App\Services\AsyncLiveAuditAttendanceService;
use App\Services\Certificates\CustomCertificateResolver;
use App\Services\CourseValidation\CourseValidatorService;
use App\Services\ResCourseAttendanceService;
use App\Services\SyncCourseSatisfactionSurvey;
use App\Services\TrainingPathEnrollmentSyncService;
use App\Support\CloudStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseController extends Controller
{
    public function __construct(
        private readonly CourseValidatorService $courseValidator,
        private readonly TrainingPathEnrollmentSyncService $trainingPathEnrollmentSyncService,
    ) {}

    public function create(): View
    {
        return view('admin.course.create', [
            'courseTypeLabels' => Course::availableTypeLabels(),
        ]);
    }

    public function index(Request $request): View
    {
        $allowedSorts = ['id', 'title', 'status', 'year'];
        $courseStatusLabels = Course::availableStatusLabels();
        $courseTypeLabels = Course::availableTypeLabels();
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'id';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());
        $showTrashed = $request->boolean('show_trashed');

        return view('admin.course.index', [
            'courses' => Course::query()
                ->select(['id', 'title', 'type', 'status', 'year', 'deleted_at'])
                ->when($showTrashed, function ($query) {
                    $query->withTrashed();
                })
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%")
                            ->orWhere('type', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%")
                            ->orWhere('year', 'like', "%{$search}%");
                    });
                })
                ->orderBy($sort, $direction)
                ->paginate(20)
                ->through(function (Course $course) use ($courseStatusLabels, $courseTypeLabels): Course {
                    $course->status = $courseStatusLabels[$course->status] ?? $course->status;
                    $course->type = $courseTypeLabels[$course->type] ?? $course->type;

                    return $course;
                })
                ->withQueryString(),
            'tableSort' => $sort,
            'tableDirection' => $direction,
            'showTrashed' => $showTrashed,
            'tableSearch' => $search,
        ]);
    }

    public function restore(int $id): RedirectResponse
    {
        $course = Course::withTrashed()->findOrFail($id);
        $course->restore();

        return redirect()
            ->route('admin.courses.index')
            ->with('status', __('Corso ripristinato con successo.'));
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $course = Course::query()->create([
            ...$validated,
            'description' => '',
            'year' => now()->year,
            'expiry_date' => now()->endOfYear(),
            'status' => 'draft',
            'required_language_level_id' => $validated['required_language_level_id'] ?? LanguageLevel::defaultOrFirst()?->getKey(),
            'has_satisfaction_survey' => (bool) ($validated['has_satisfaction_survey'] ?? false),
            'satisfaction_survey_required_for_certificate' => (bool) ($validated['satisfaction_survey_required_for_certificate'] ?? false),
            'hasMany' => '0',
        ]);

        if (blank($validated['code'] ?? null)) {
            $course->forceFill([
                'code' => 'CRS-'.$course->getKey(),
            ])->save();
        }

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('status', __('Corso creato con successo.'));
    }

    public function edit(Course $course, CustomCertificateResolver $customCertificateResolver): View
    {
        $modules = $course->modules()->get();
        $course->load([
            'categories',
            'documents',
            'jobUnit',
            'jobRoles',
            'jobTasks',
            'jobUnits',
            'partners',
            'riskBasedRequirements',
            'venue',
        ]);
        $courseCertificateTemplates = collect(CustomCertificate::availableTypes())
            ->mapWithKeys(function (string $type) use ($course, $customCertificateResolver): array {
                $specificCertificate = CustomCertificate::query()
                    ->active()
                    ->ofType($type)
                    ->get()
                    ->first(fn (CustomCertificate $customCertificate): bool => ! $customCertificate->isGeneric()
                        && $customCertificate->supportsCourse((int) $course->getKey()));

                return [
                    $type => [
                        'specific' => $specificCertificate,
                        'resolved' => $customCertificateResolver->resolve($type, $course),
                    ],
                ];
            });

        return view('admin.course.edit', [
            'course' => $course,
            'publishedTrainingPathCount' => $course->trainingPaths()->where('status', 'published')->count(),
            'unpublishedTrainingPathCount' => $course->trainingPaths()->where('status', '!=', 'published')->count(),
            'activeEnrollmentCount' => $course->enrollments()->count(),
            'courseCertificateTemplates' => $courseCertificateTemplates,
            'courseTypeLabels' => Course::availableTypeLabels(),
            'courseStatusLabels' => Course::availableStatusLabels(),
            'courseEventTypeLabels' => Course::availableEventTypeLabels(),
            'courseProgramTeachingMethodLabels' => Course::availableProgramTeachingMethodLabels(),
            'courseDocumentCategoryLabels' => CourseDocument::categoryLabels(),
            'courseDocumentFileTypeLabels' => CourseDocument::fileTypeLabels(),
            'courseRiskRequirementValidityTypeLabels' => CourseRiskRequirementValidityType::labels(),
            'customCertificateTypeLabels' => CustomCertificate::availableTypeLabels(),
            'languageLevels' => LanguageLevel::query()->ordered()->get(['id', 'name', 'sort_order', 'is_default']),
            'moduleTypeLabels' => collect(Module::availableTypeLabels()),
            'creatableModuleTypeLabels' => collect(Module::availableTypeLabels())
                ->only(Module::creatableTypes())
                ->all(),
            'moduleStatusLabels' => Module::availableStatusLabels(),
            'modules' => $modules,
            'courseValidator' => $this->courseValidator,
            'activeSatisfactionSurveyTemplate' => SatisfactionSurveyTemplate::active(),
            'riskBasedRequirements' => RiskBasedRequirement::query()
                ->orderBy('name')
                ->get(['id', 'name', 'description', 'risk_levels']),
            'riskLevels' => RiskLevel::ordered(),
            'fundingEntities' => FundingEntity::query()->orderBy('company_name')->get(['id', 'company_name']),
            'courseCategories' => CourseCategory::query()->orderBy('name')->get(['id', 'name']),
            'partners' => Partner::query()->orderBy('ragione_sociale')->get(['id', 'ragione_sociale']),
            'jobRoles' => JobRole::query()->orderBy('name')->get(['id', 'name']),
            'jobTasks' => JobTask::query()->orderBy('name')->get(['id', 'name']),
            'jobUnits' => JobUnit::query()
                ->with('city:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'unit_code', 'address', 'postal_code', 'city_id']),
            'venues' => Venue::query()
                ->with([
                    'city:id,name',
                    'province:id,name',
                    'region:id,name',
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'address', 'postal_code', 'city_id', 'province_id', 'region_id']),
            'attendanceRows' => request('section') === 'attendees' ? $this->attendanceRows($course) : collect(),
            'resAttendanceModules' => request('section') === 'attendees'
                ? $modules->where('type', Module::TYPE_RESIDENTIAL)->sortBy('order')->values()
                : collect(),
            'asyncAttendanceModules' => request('section') === 'attendees'
                ? $modules->where('type', Module::TYPE_LIVE)->sortBy('order')->values()
                : collect(),
        ]);
    }

    public function confirmAttendance(
        ConfirmCourseAttendanceRequest $request,
        Course $course,
        ResCourseAttendanceService $resCourseAttendanceService,
        AsyncLiveAuditAttendanceService $asyncLiveAuditAttendanceService,
    ): RedirectResponse {
        abort_unless(in_array($course->type, ['res', 'blended', 'async'], true), 404);

        $module = $course->modules()
            ->whereKey($request->moduleId())
            ->where('type', $course->type === 'async' ? Module::TYPE_LIVE : Module::TYPE_RESIDENTIAL)
            ->firstOrFail();

        $stats = $course->type === 'async'
            ? $asyncLiveAuditAttendanceService->confirmAttendance(
                $module,
                $request->effectiveStartAt(),
                $request->effectiveEndAt(),
                $request->minimumAttendancePercentage(),
            )
            : $resCourseAttendanceService->confirmAttendance(
                $module,
                $request->minimumAttendancePercentage(),
            );

        return $this->redirectToSection($course, 'attendees', $this->attendanceConfirmationStatusMessage($stats));
    }

    private function attendanceRows(Course $course): Collection
    {
        if ($course->type === 'async') {
            return $this->asyncAttendanceRows($course);
        }

        if (! in_array($course->type, ['res', 'blended'], true)) {
            return collect();
        }

        $completedResidentialUserIds = DB::table('module_user')
            ->join('course_user', 'course_user.id', '=', 'module_user.course_user_id')
            ->join('modules', 'modules.id', '=', 'module_user.module_id')
            ->where('course_user.course_id', $course->getKey())
            ->whereNull('course_user.deleted_at')
            ->where('modules.type', Module::TYPE_RESIDENTIAL)
            ->where('module_user.status', 'completed')
            ->pluck('course_user.user_id')
            ->flip();

        return DB::table('course_attendance_records')
            ->join('course_user', function ($join): void {
                $join
                    ->on('course_user.user_id', '=', 'course_attendance_records.user_id')
                    ->on('course_user.course_id', '=', 'course_attendance_records.course_id')
                    ->whereNull('course_user.deleted_at');
            })
            ->join('users', 'users.id', '=', 'course_attendance_records.user_id')
            ->where('course_attendance_records.course_id', $course->getKey())
            ->select([
                'users.id as user_id',
                'users.name',
                'users.surname',
                'users.email',
                'course_attendance_records.type',
                'course_attendance_records.recorded_at',
            ])
            ->orderBy('users.surname')
            ->orderBy('users.name')
            ->orderBy('course_attendance_records.recorded_at')
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $records) use ($completedResidentialUserIds): array {
                $firstRecord = $records->first();
                $lastEntryAt = null;
                $totalSeconds = 0;

                foreach ($records as $record) {
                    if ($record->type === 'entry') {
                        $lastEntryAt = Carbon::parse($record->recorded_at);

                        continue;
                    }

                    if ($record->type === 'exit' && $lastEntryAt !== null) {
                        $totalSeconds += $lastEntryAt->diffInSeconds(Carbon::parse($record->recorded_at), false);
                        $lastEntryAt = null;
                    }
                }

                return [
                    'user' => trim($firstRecord->surname.' '.$firstRecord->name),
                    'email' => $firstRecord->email,
                    'records_count' => $records->count(),
                    'attendance_seconds' => (int) max(0, $totalSeconds),
                    'completed' => $completedResidentialUserIds->has($firstRecord->user_id),
                ];
            })
            ->values();
    }

    private function asyncAttendanceRows(Course $course): Collection
    {
        $completedLiveUserIds = DB::table('module_user')
            ->join('course_user', 'course_user.id', '=', 'module_user.course_user_id')
            ->join('modules', 'modules.id', '=', 'module_user.module_id')
            ->where('course_user.course_id', $course->getKey())
            ->whereNull('course_user.deleted_at')
            ->where('modules.type', Module::TYPE_LIVE)
            ->where('module_user.status', 'completed')
            ->pluck('course_user.user_id')
            ->flip();

        return DB::table('live_stream_audit_events')
            ->join('modules', 'modules.id', '=', 'live_stream_audit_events.module_id')
            ->join('course_user', function ($join): void {
                $join
                    ->on('course_user.user_id', '=', 'live_stream_audit_events.user_id')
                    ->on('course_user.course_id', '=', 'modules.belongsTo')
                    ->whereNull('course_user.deleted_at');
            })
            ->join('users', 'users.id', '=', 'live_stream_audit_events.user_id')
            ->where('modules.belongsTo', (string) $course->getKey())
            ->where('modules.type', Module::TYPE_LIVE)
            ->whereIn('live_stream_audit_events.event_type', [
                'participant_joined',
                'participant_disconnected',
            ])
            ->select([
                'users.id as user_id',
                'users.name',
                'users.surname',
                'users.email',
                'live_stream_audit_events.event_type',
                'live_stream_audit_events.occurred_at',
            ])
            ->orderBy('users.surname')
            ->orderBy('users.name')
            ->orderBy('live_stream_audit_events.occurred_at')
            ->get()
            ->groupBy('user_id')
            ->map(function (Collection $events) use ($completedLiveUserIds): array {
                $firstEvent = $events->first();
                $joinedAt = null;
                $totalSeconds = 0;

                foreach ($events as $event) {
                    if ($event->event_type === 'participant_joined') {
                        $joinedAt = Carbon::parse($event->occurred_at);

                        continue;
                    }

                    if ($event->event_type === 'participant_disconnected' && $joinedAt !== null) {
                        $totalSeconds += $joinedAt->diffInSeconds(Carbon::parse($event->occurred_at), false);
                        $joinedAt = null;
                    }
                }

                return [
                    'user' => trim($firstEvent->surname.' '.$firstEvent->name),
                    'email' => $firstEvent->email,
                    'records_count' => $events->count(),
                    'attendance_seconds' => (int) max(0, $totalSeconds),
                    'completed' => $completedLiveUserIds->has($firstEvent->user_id),
                ];
            })
            ->values();
    }

    public function updateDetails(
        UpdateCourseDetailsRequest $request,
        Course $course,
        SyncCourseSatisfactionSurvey $syncCourseSatisfactionSurvey,
    ): RedirectResponse {
        try {
            $validated = $request->validated();
            $venueAttributes = $this->resolveCourseVenueAttributes($course, $validated);
            $attributes = [
                ...$this->courseDetailsAttributes($validated),
                ...$venueAttributes,
            ];

            if ($this->isPublishedCourseDetailsStatusOnlyUpdate($course, $attributes)) {
                $targetStatus = (string) ($validated['status'] ?? $course->status);

                if ($course->status === 'published' && $targetStatus !== 'published') {
                    $publishedTrainingPathCount = $course->trainingPaths()
                        ->where('status', 'published')
                        ->count();

                    if ($publishedTrainingPathCount > 0) {
                        throw new RuntimeException(__('Non puoi cambiare stato a un corso pubblicato associato a percorsi formativi pubblicati.'));
                    }

                    $unpublishedPathIds = $course->trainingPaths()
                        ->where('status', '!=', 'published')
                        ->pluck('training_paths.id');

                    if ($unpublishedPathIds->isNotEmpty()) {
                        $shouldDetach = (bool) ($validated['detach_from_unpublished_training_paths'] ?? false);

                        if (! $shouldDetach) {
                            throw new RuntimeException(__('Per cambiare stato devi prima confermare la rimozione del corso dai percorsi non pubblicati che lo contengono.'));
                        }

                        $course->trainingPaths()->detach($unpublishedPathIds->all());

                        TrainingPath::query()
                            ->whereIn('id', $unpublishedPathIds->all())
                            ->get()
                            ->each(function (TrainingPath $trainingPath): void {
                                $this->trainingPathEnrollmentSyncService->syncAllEnrollmentsForPath($trainingPath);
                            });
                    }
                }

                $attributes = [
                    'status' => $validated['status'],
                ];
            }

            $targetStatus = (string) ($attributes['status'] ?? $course->status);
            $shouldPrepareSurveyBeforePublishing = $targetStatus === 'published'
                && $course->status !== 'published'
                && (bool) $course->has_satisfaction_survey;

            if ($shouldPrepareSurveyBeforePublishing) {
                $course->update([
                    ...$attributes,
                    'status' => 'draft',
                ]);
            } else {
                $course->update($attributes);
            }

            if ($shouldPrepareSurveyBeforePublishing) {
                $syncCourseSatisfactionSurvey->handle($course);
            }

            if ($shouldPrepareSurveyBeforePublishing) {
                $course->modules()
                    ->where('type', Module::TYPE_SATISFACTION_QUIZ)
                    ->update(['status' => 'published']);

                $course->unsetRelation('modules');
                $course->refresh();

                $course->update([
                    'status' => 'published',
                ]);
            }

            $section = $request->input('update_section') === 'venue' ? 'venue' : 'details';

            return $this->redirectToSection($course, $section, __('Corso aggiornato con successo.'));
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function updateDuration(UpdateCourseDurationRequest $request, Course $course): RedirectResponse
    {
        $course->update($request->validated());

        return $this->redirectToSection($course, 'duration', __('Durata del corso aggiornata con successo.'));
    }

    public function updateProgram(UpdateCourseProgramRequest $request, Course $course): RedirectResponse
    {
        $course->update([
            'program_schedule' => $request->programSchedule(),
        ]);

        return $this->redirectToSection($course, 'program', __('Programma corso aggiornato con successo.'));
    }

    public function updateAttachments(UpdateCourseAttachmentsRequest $request, Course $course): RedirectResponse
    {
        $this->syncAttachments($request, $course);

        return $this->redirectToSection($course, 'attachments', __('Allegati del corso aggiornati con successo.'));
    }

    public function updateSurvey(
        UpdateCourseSurveyRequest $request,
        Course $course,
        SyncCourseSatisfactionSurvey $syncCourseSatisfactionSurvey,
    ): RedirectResponse {
        $validated = $request->validated();

        $course->update([
            'has_satisfaction_survey' => (bool) ($validated['has_satisfaction_survey'] ?? false),
            'satisfaction_survey_required_for_certificate' => (bool) (($validated['has_satisfaction_survey'] ?? false)
                && ($validated['satisfaction_survey_required_for_certificate'] ?? false)),
        ]);

        $syncCourseSatisfactionSurvey->handle($course);

        return $this->redirectToSection($course, 'survey', __('Questionario di gradimento aggiornato con successo.'));
    }

    public function updateCertificates(UpdateCourseCertificatesRequest $request, Course $course): RedirectResponse
    {
        $course->riskBasedRequirements()->sync(
            $this->buildRiskBasedRequirementSyncPayload($request->validated())
        );

        return $this->redirectToSection($course, 'certificates', __('Abilitazioni del corso aggiornate con successo.'));
    }

    public function updateCertificateTemplate(
        UpdateCourseCertificateTemplateRequest $request,
        Course $course,
        ActivateCustomCertificateTemplate $activateCustomCertificateTemplate,
    ): RedirectResponse {
        $validated = $request->validated();

        $activateCustomCertificateTemplate->handle(
            type: $validated['type'],
            uploadedFile: $request->file('template'),
            courseIds: [(int) $course->getKey()],
        );

        return $this->redirectToSection($course, 'certificate-templates', __('Template attestato aggiornato con successo.'));
    }

    public function updateCategories(UpdateCourseCategoriesRequest $request, Course $course): RedirectResponse
    {
        $validated = $request->validated();
        $categoryIds = collect($validated['category_ids'] ?? [])
            ->map(fn (mixed $categoryId): int => (int) $categoryId)
            ->unique()
            ->values()
            ->all();

        $course->update([
            'event_type' => $validated['event_type'] ?? null,
        ]);
        $course->categories()->sync($categoryIds);

        return $this->redirectToSection($course, 'categorization', __('Categorie del corso aggiornate con successo.'));
    }

    public function updatePartners(UpdateCoursePartnersRequest $request, Course $course): RedirectResponse
    {
        $partnerIds = collect($request->validated('partner_ids', []))
            ->map(fn (mixed $partnerId): int => (int) $partnerId)
            ->unique()
            ->values()
            ->all();

        $course->partners()->sync($partnerIds);

        return $this->redirectToSection($course, 'partners', __('Partner del corso aggiornati con successo.'));
    }

    public function updateRecipients(UpdateCourseRecipientsRequest $request, Course $course): RedirectResponse
    {
        try {
            $validated = $request->validated();

            $course->update([
                'visible_to_all' => (bool) ($validated['visible_to_all'] ?? false),
            ]);

            $course->jobRoles()->sync($this->uniqueIds($validated['job_role_ids'] ?? []));
            $course->jobTasks()->sync($this->uniqueIds($validated['job_task_ids'] ?? []));
            $course->jobUnits()->sync($this->uniqueIds($validated['job_unit_ids'] ?? []));

            return $this->redirectToSection($course, 'recipients', __('Destinatari del corso aggiornati con successo.'));
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
    }

    public function previewCoverImage(Course $course): StreamedResponse
    {
        abort_unless($course->cover_image_path !== null, Response::HTTP_NOT_FOUND);

        $disk = Storage::disk(CloudStorage::disk());
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

    public function previewPosterPdf(Course $course): StreamedResponse
    {
        abort_unless($course->poster_pdf_path !== null, Response::HTTP_NOT_FOUND);

        $disk = Storage::disk(CloudStorage::disk());
        abort_unless($disk->exists($course->poster_pdf_path), Response::HTTP_NOT_FOUND);

        return response()->streamDownload(
            static function () use ($disk, $course): void {
                echo $disk->get($course->poster_pdf_path);
            },
            basename($course->poster_pdf_path),
            [
                'Content-Type' => $disk->mimeType($course->poster_pdf_path) ?: 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.basename($course->poster_pdf_path).'"',
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<int, array{course_validity_types: string, integrative_start_risk_levels: ?string}>
     */
    private function buildRiskBasedRequirementSyncPayload(array $validated): array
    {
        $selectedRequirementIds = collect($validated['risk_based_requirement_ids'] ?? [])
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $validityTypes = collect($validated['risk_based_requirement_validity_types'] ?? []);
        $integrativeStartLevels = collect($validated['risk_based_requirement_integrative_start_levels'] ?? []);

        return $selectedRequirementIds
            ->mapWithKeys(function (int $riskBasedRequirementId) use ($integrativeStartLevels, $validityTypes): array {
                $courseValidityTypes = array_map(
                    static fn (CourseRiskRequirementValidityType $validityType): string => $validityType->value,
                    CourseRiskRequirementValidityType::normalizeMany(
                        collect($validityTypes->get((string) $riskBasedRequirementId, []))
                            ->filter()
                            ->values()
                            ->all()
                    )
                );

                $startRiskLevels = collect($integrativeStartLevels->get((string) $riskBasedRequirementId, []))
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();
                $hasIntegrativeValidity = in_array(CourseRiskRequirementValidityType::Integrative->value, $courseValidityTypes, true);

                return [
                    $riskBasedRequirementId => [
                        'course_validity_types' => json_encode($courseValidityTypes),
                        'integrative_start_risk_levels' => $hasIntegrativeValidity
                            ? json_encode($startRiskLevels)
                            : null,
                    ],
                ];
            })
            ->all();
    }

    private function redirectToSection(Course $course, string $section, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.courses.edit', [
                'course' => $course,
                'section' => $section,
            ])
            ->with('status', $message);
    }

    /**
     * @param  array<string, int>  $stats
     */
    private function attendanceConfirmationStatusMessage(array $stats): string
    {
        return __('Presenze confermate. :confirmed utenti abilitati, :alreadyCompleted già completati, :notCurrent sopra soglia ma non ancora sul modulo corrente.', [
            'confirmed' => $stats['confirmed'],
            'alreadyCompleted' => $stats['already_completed'],
            'notCurrent' => $stats['skipped_not_current'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function courseDetailsAttributes(array $validated): array
    {
        return collect($validated)
            ->except(['venue_mode', 'job_unit_id', 'venue_id', 'venue_name', 'country', 'region', 'province', 'city', 'address', 'postal_code'])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{job_unit_id: int|null, venue_id: int|null}|array{}
     */
    private function resolveCourseVenueAttributes(Course $course, array $validated): array
    {
        if (! in_array($course->type, ['res', 'blended'], true) || blank($validated['venue_mode'] ?? null)) {
            return [];
        }

        if ($validated['venue_mode'] === 'job_unit') {
            return [
                'job_unit_id' => (int) $validated['job_unit_id'],
                'venue_id' => null,
            ];
        }

        if (filled($validated['venue_id'] ?? null)) {
            return [
                'job_unit_id' => null,
                'venue_id' => (int) $validated['venue_id'],
            ];
        }

        $venue = Venue::query()->create([
            'name' => $validated['venue_name'],
            ...$this->convertGeographicNamesToIds($validated),
            'address' => $validated['address'] ?? null,
            'postal_code' => $validated['postal_code'] ?? null,
        ]);

        return [
            'job_unit_id' => null,
            'venue_id' => (int) $venue->getKey(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{country_id: int|null, region_id: int|null, province_id: int|null, city_id: int|null}
     */
    private function convertGeographicNamesToIds(array $data): array
    {
        $country = filled($data['country'] ?? null)
            ? WorldCountry::query()->where('code', $data['country'])->first()
            : null;
        $region = filled($data['region'] ?? null)
            ? WorldDivision::query()->where('name', $data['region'])->first()
            : null;
        $province = filled($data['province'] ?? null)
            ? Province::query()->where('name', $data['province'])->first()
            : null;
        $city = filled($data['city'] ?? null)
            ? WorldCity::query()->where('name', $data['city'])->first()
            : null;

        return [
            'country_id' => $country?->getKey(),
            'region_id' => $region?->getKey(),
            'province_id' => $province?->getKey(),
            'city_id' => $city?->getKey(),
        ];
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, int>
     */
    private function uniqueIds(array $ids): array
    {
        return collect($ids)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function isPublishedCourseDetailsStatusOnlyUpdate(Course $course, array $attributes): bool
    {
        if ($course->status !== 'published') {
            return false;
        }

        $normalizedOriginal = [
            'title' => (string) $course->title,
            'code' => (string) $course->code,
            'description' => (string) $course->description,
            'teaching_material' => (string) ($course->teaching_material ?? ''),
            'max_participants' => $course->max_participants === null ? null : (int) $course->max_participants,
            'internal_notes' => (string) ($course->internal_notes ?? ''),
            'training_objective' => (string) ($course->training_objective ?? ''),
            'knowledge' => (string) ($course->knowledge ?? ''),
            'skills' => (string) ($course->skills ?? ''),
            'competences' => (string) ($course->competences ?? ''),
            'regulatory_reference' => (string) ($course->regulatory_reference ?? ''),
            'year' => (int) $course->year,
            'is_financed' => (bool) $course->is_financed,
            'funding_entity_id' => $course->funding_entity_id === null ? null : (int) $course->funding_entity_id,
            'status' => (string) $course->status,
        ];

        $normalizedIncoming = [
            'title' => (string) ($attributes['title'] ?? ''),
            'code' => (string) ($attributes['code'] ?? ''),
            'description' => (string) ($attributes['description'] ?? ''),
            'teaching_material' => (string) ($attributes['teaching_material'] ?? ''),
            'max_participants' => array_key_exists('max_participants', $attributes) && $attributes['max_participants'] !== null
                ? (int) $attributes['max_participants']
                : null,
            'internal_notes' => (string) ($attributes['internal_notes'] ?? ''),
            'training_objective' => (string) ($attributes['training_objective'] ?? ''),
            'knowledge' => (string) ($attributes['knowledge'] ?? ''),
            'skills' => (string) ($attributes['skills'] ?? ''),
            'competences' => (string) ($attributes['competences'] ?? ''),
            'regulatory_reference' => (string) ($attributes['regulatory_reference'] ?? ''),
            'year' => (int) ($attributes['year'] ?? 0),
            'is_financed' => (bool) ($attributes['is_financed'] ?? false),
            'funding_entity_id' => array_key_exists('funding_entity_id', $attributes) && $attributes['funding_entity_id'] !== null
                ? (int) $attributes['funding_entity_id']
                : null,
            'status' => (string) ($attributes['status'] ?? ''),
        ];

        return collect($normalizedOriginal)
            ->except(['status', 'is_financed', 'funding_entity_id'])
            ->all() === collect($normalizedIncoming)
            ->except(['status', 'is_financed', 'funding_entity_id'])
            ->all()
            && $normalizedIncoming['status'] !== $course->status;
    }

    private function syncAttachments(Request $request, Course $course): void
    {
        $disk = Storage::disk(CloudStorage::disk());

        if ($request->hasFile('cover_image')) {
            $currentPath = $course->cover_image_path;
            $newPath = $request->file('cover_image')->storeAs(
                $this->attachmentDirectory($course),
                'cover-image.'.$request->file('cover_image')->extension(),
                CloudStorage::disk(),
            );

            $course->forceFill([
                'cover_image_path' => $newPath,
            ])->save();

            if ($currentPath !== null && $currentPath !== $newPath) {
                $disk->delete($currentPath);
            }
        }

        if ($request->hasFile('poster_pdf')) {
            $currentPath = $course->poster_pdf_path;
            $newPath = $request->file('poster_pdf')->storeAs(
                $this->attachmentDirectory($course),
                'poster-'.Str::slug(pathinfo($request->file('poster_pdf')->getClientOriginalName(), PATHINFO_FILENAME) ?: 'course').'.pdf',
                CloudStorage::disk(),
            );

            $course->forceFill([
                'poster_pdf_path' => $newPath,
            ])->save();

            if ($currentPath !== null && $currentPath !== $newPath) {
                $disk->delete($currentPath);
            }
        }
    }

    private function attachmentDirectory(Course $course): string
    {
        return 'courses/'.$course->getKey().'/attachments';
    }

    public function destroy(Course $course): RedirectResponse
    {
        $hasActiveEnrollments = $course->enrollments()->whereNull('deleted_at')->exists();
        $shouldCascadeDeleteEnrollments = request()->boolean('cascade_delete_enrollments');

        if ($hasActiveEnrollments && ! $shouldCascadeDeleteEnrollments) {
            return back()->with('error', __('Il corso ha iscrizioni attive. Conferma l\'eliminazione anche delle iscrizioni associate per procedere.'));
        }

        if ($hasActiveEnrollments && $shouldCascadeDeleteEnrollments) {
            $course->enrollments()
                ->whereNull('deleted_at')
                ->get()
                ->each
                ->delete();
        }

        $course->delete();

        return redirect()
            ->route('admin.courses.index')
            ->with('status', __('Corso eliminato con successo.'));
    }

    public function duplicate(Course $course, DuplicateCourse $duplicateCourse): RedirectResponse
    {
        $duplicatedCourse = $duplicateCourse->handle($course);

        return redirect()
            ->route('admin.courses.edit', $duplicatedCourse)
            ->with('status', __('Corso duplicato con successo.'));
    }

    public function duplicateStructure(
        DuplicateCourseStructureRequest $request,
        Course $course,
        DuplicateCourseStructure $duplicateCourseStructure,
    ): RedirectResponse {
        $duplicatedCourse = $duplicateCourseStructure->handle(
            $course,
            (string) $request->validated('new_code')
        );

        return redirect()
            ->route('admin.courses.edit', $duplicatedCourse)
            ->with('status', __('Struttura corso duplicata con successo.'));
    }
}
