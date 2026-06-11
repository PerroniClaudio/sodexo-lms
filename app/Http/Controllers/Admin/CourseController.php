<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CourseRiskRequirementValidityType;
use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\Module;
use App\Models\RiskBasedRequirement;
use App\Models\SatisfactionSurveyTemplate;
use App\Services\CourseValidation\CourseValidatorService;
use App\Services\SyncCourseSatisfactionSurvey;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class CourseController extends Controller
{
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

    public function update(
        UpdateCourseRequest $request,
        Course $course,
        SyncCourseSatisfactionSurvey $syncCourseSatisfactionSurvey,
    ): RedirectResponse {
        try {
            $validated = $request->validated();
            $courseAttributes = collect($validated)->except([
                'risk_based_requirement_ids',
                'risk_based_requirement_validity_types',
                'risk_based_requirement_integrative_start_levels',
            ])->all();
            $attributes = [
                ...$courseAttributes,
                'has_satisfaction_survey' => (bool) ($validated['has_satisfaction_survey'] ?? false),
                'satisfaction_survey_required_for_certificate' => (bool) (($validated['has_satisfaction_survey'] ?? false)
                    && ($validated['satisfaction_survey_required_for_certificate'] ?? false)),
            ];

            if ($this->isPublishedCourseStatusOnlyUpdate($course, $attributes)) {
                $attributes = [
                    'status' => $validated['status'],
                ];
            }

            $targetStatus = (string) ($attributes['status'] ?? $course->status);
            $shouldPrepareSurveyBeforePublishing = $targetStatus === 'published'
                && ($course->status !== 'published' || ! $course->exists)
                && (bool) ($attributes['has_satisfaction_survey'] ?? false);

            if ($shouldPrepareSurveyBeforePublishing) {
                $course->update([
                    ...$attributes,
                    'status' => 'draft',
                ]);
            } else {
                $course->update($attributes);
            }

            $course->riskBasedRequirements()->sync(
                $this->buildRiskBasedRequirementSyncPayload($validated)
            );
            $syncCourseSatisfactionSurvey->handle($course);

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

            return redirect()
                ->route('admin.courses.edit', $course)
                ->with('status', __('Corso aggiornato con successo.'));
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function isPublishedCourseStatusOnlyUpdate(Course $course, array $attributes): bool
    {
        if ($course->status !== 'published') {
            return false;
        }

        $normalizedOriginal = [
            'title' => (string) $course->title,
            'description' => (string) $course->description,
            'teaching_material' => (string) ($course->teaching_material ?? ''),
            'max_participants' => $course->max_participants === null ? null : (int) $course->max_participants,
            'internal_notes' => (string) ($course->internal_notes ?? ''),
            'training_objective' => (string) ($course->training_objective ?? ''),
            'knowledge' => (string) ($course->knowledge ?? ''),
            'skills' => (string) ($course->skills ?? ''),
            'competences' => (string) ($course->competences ?? ''),
            'regulatory_reference' => (string) ($course->regulatory_reference ?? ''),
            'course_start_date' => $course->course_start_date?->format('Y-m-d'),
            'course_end_date' => $course->course_end_date?->format('Y-m-d'),
            'access_closure_date' => $course->access_closure_date?->format('Y-m-d'),
            'course_duration_hours' => $course->course_duration_hours === null ? null : (int) $course->course_duration_hours,
            'interaction_duration_minutes' => $course->interaction_duration_minutes === null
                ? null
                : (int) $course->interaction_duration_minutes,
            'year' => (int) $course->year,
            'expiry_date' => $course->expiry_date instanceof CarbonInterface
                ? $course->expiry_date->format('Y-m-d')
                : (string) $course->expiry_date,
            'has_satisfaction_survey' => (bool) $course->has_satisfaction_survey,
            'satisfaction_survey_required_for_certificate' => (bool) $course->satisfaction_survey_required_for_certificate,
        ];

        $normalizedIncoming = [
            'title' => (string) ($attributes['title'] ?? ''),
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
            'course_start_date' => $attributes['course_start_date'] ?? null,
            'course_end_date' => $attributes['course_end_date'] ?? null,
            'access_closure_date' => $attributes['access_closure_date'] ?? null,
            'course_duration_hours' => array_key_exists('course_duration_hours', $attributes) && $attributes['course_duration_hours'] !== null
                ? (int) $attributes['course_duration_hours']
                : null,
            'interaction_duration_minutes' => array_key_exists('interaction_duration_minutes', $attributes)
                && $attributes['interaction_duration_minutes'] !== null
                ? (int) $attributes['interaction_duration_minutes']
                : null,
            'year' => (int) ($attributes['year'] ?? 0),
            'expiry_date' => (string) ($attributes['expiry_date'] ?? ''),
            'has_satisfaction_survey' => (bool) ($attributes['has_satisfaction_survey'] ?? false),
            'satisfaction_survey_required_for_certificate' => (bool) ($attributes['satisfaction_survey_required_for_certificate'] ?? false),
        ];

        return $normalizedOriginal === $normalizedIncoming
            && ($attributes['status'] ?? null) !== $course->status;
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->delete();

        return redirect()
            ->route('admin.courses.index')
            ->with('status', __('Corso eliminato con successo.'));
    }
}
