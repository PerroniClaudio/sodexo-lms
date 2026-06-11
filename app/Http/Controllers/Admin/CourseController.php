<?php

namespace App\Http\Controllers\Admin;

use App\Actions\DuplicateCourse;
use App\Actions\DuplicateCourseStructure;
use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\DuplicateCourseStructureRequest;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseAttachmentsRequest;
use App\Http\Requests\UpdateCourseCertificatesRequest;
use App\Http\Requests\UpdateCourseDetailsRequest;
use App\Http\Requests\UpdateCourseDurationRequest;
use App\Http\Requests\UpdateCourseSurveyRequest;
use App\Models\Course;
use App\Models\Module;
use App\Models\RiskBasedRequirement;
use App\Models\SatisfactionSurveyTemplate;
use App\Services\CourseValidation\CourseValidatorService;
use App\Services\SyncCourseSatisfactionSurvey;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CourseController extends Controller
{
    private const ATTACHMENTS_DISK = 's3';

    public function __construct(
        private readonly CourseValidatorService $courseValidator
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

        return view('admin.course.index', [
            'courses' => Course::query()
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
            'tableSearch' => $search,
        ]);
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

    public function edit(Course $course): View
    {
        $modules = $course->modules()->get();
        $course->load('riskBasedRequirements');

        return view('admin.course.edit', [
            'course' => $course,
            'courseTypeLabels' => Course::availableTypeLabels(),
            'courseStatusLabels' => Course::availableStatusLabels(),
            'courseRiskRequirementValidityTypeLabels' => CourseRiskRequirementValidityType::labels(),
            'moduleTypeLabels' => collect(Module::availableTypeLabels()),
            'creatableModuleTypeLabels' => collect(Module::availableTypeLabels())
                ->only(Module::creatableTypes())
                ->all(),
            'moduleStatusLabels' => Module::availableStatusLabels(),
            'modules' => $modules,
            'courseValidator' => $this->courseValidator,
            'activeSatisfactionSurveyTemplate' => SatisfactionSurveyTemplate::active(),
            'riskBasedRequirements' => RiskBasedRequirement::query()->orderBy('name')->get(),
            'riskLevels' => RiskLevel::ordered(),
        ]);
    }

    public function updateDetails(
        UpdateCourseDetailsRequest $request,
        Course $course,
        SyncCourseSatisfactionSurvey $syncCourseSatisfactionSurvey,
    ): RedirectResponse {
        try {
            $validated = $request->validated();
            $attributes = $validated;

            if ($this->isPublishedCourseDetailsStatusOnlyUpdate($course, $attributes)) {
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

            return $this->redirectToSection($course, 'details', __('Corso aggiornato con successo.'));
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

    public function previewCoverImage(Course $course): StreamedResponse
    {
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

    public function previewPosterPdf(Course $course): StreamedResponse
    {
        abort_unless($course->poster_pdf_path !== null, Response::HTTP_NOT_FOUND);

        $disk = Storage::disk(self::ATTACHMENTS_DISK);
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
            'status' => (string) ($attributes['status'] ?? ''),
        ];

        return collect($normalizedOriginal)
            ->except('status')
            ->all() === collect($normalizedIncoming)
            ->except('status')
            ->all()
            && $normalizedIncoming['status'] !== $course->status;
    }

    private function syncAttachments(Request $request, Course $course): void
    {
        $disk = Storage::disk(self::ATTACHMENTS_DISK);

        if ($request->hasFile('cover_image')) {
            $currentPath = $course->cover_image_path;
            $newPath = $request->file('cover_image')->storeAs(
                $this->attachmentDirectory($course),
                'cover-image.'.$request->file('cover_image')->extension(),
                self::ATTACHMENTS_DISK,
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
                self::ATTACHMENTS_DISK,
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
