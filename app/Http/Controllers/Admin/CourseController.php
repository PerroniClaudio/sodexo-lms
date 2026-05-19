<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use App\Models\Module;
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
                            ->orWhere('status', 'like', "%{$search}%")
                            ->orWhere('year', 'like', "%{$search}%");
                    });
                })
                ->orderBy($sort, $direction)
                ->paginate(20)
                ->through(function (Course $course) use ($courseStatusLabels): Course {
                    $course->status = $courseStatusLabels[$course->status] ?? $course->status;

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
        $courseClasses = $course->classes()
            ->with([
                'userAssignments.user',
                'teacherAssignments.user',
            ])
            ->orderBy('starts_at')
            ->get();

        return view('admin.course.edit', [
            'course' => $course,
            'courseStatusLabels' => Course::availableStatusLabels(),
            'moduleTypeLabels' => collect(Module::availableTypeLabels())
                ->only(Module::creatableTypes())
                ->all(),
            'moduleStatusLabels' => Module::availableStatusLabels(),
            'modules' => $course->modules()->get(),
            'assignedTeachers' => $course->getTeachers(),
            'assignedTutors' => $course->getTutors(),
            'courseValidator' => $this->courseValidator,
            'activeSatisfactionSurveyTemplate' => SatisfactionSurveyTemplate::active(),
            'supportsClasses' => $course->supportsClasses(),
            'courseClasses' => $courseClasses,
            'courseClassPayloads' => $courseClasses->map(fn ($courseClass): array => [
                'id' => $courseClass->getKey(),
                'name' => $courseClass->name,
                'starts_at_label' => $courseClass->starts_at?->format('d/m/Y H:i'),
                'starts_at_date' => $courseClass->starts_at?->format('Y-m-d'),
                'starts_at_time' => $courseClass->starts_at?->format('H:i'),
                'ends_at_label' => $courseClass->ends_at?->format('d/m/Y H:i'),
                'ends_at_date' => $courseClass->ends_at?->format('Y-m-d'),
                'ends_at_time' => $courseClass->ends_at?->format('H:i'),
                'users_count' => $courseClass->userAssignments->count(),
                'teachers_count' => $courseClass->teacherAssignments->count(),
                'remaining_user_slots' => $courseClass->remainingUserSlots(),
                'users' => $courseClass->userAssignments->map(fn ($assignment): array => [
                    'assignment_id' => $assignment->getKey(),
                    'delete_url' => route('admin.courses.classes.users.destroy', [$course, $courseClass, $assignment]),
                    'id' => $assignment->user?->getKey(),
                    'full_name' => $assignment->user?->full_name,
                    'email' => $assignment->user?->email,
                    'fiscal_code' => $assignment->user?->fiscal_code,
                ])->values(),
                'teachers' => $courseClass->teacherAssignments->map(fn ($assignment): array => [
                    'assignment_id' => $assignment->getKey(),
                    'delete_url' => route('admin.courses.classes.teachers.destroy', [$course, $courseClass, $assignment]),
                    'id' => $assignment->user?->getKey(),
                    'full_name' => $assignment->user?->full_name,
                    'email' => $assignment->user?->email,
                    'fiscal_code' => $assignment->user?->fiscal_code,
                ])->values(),
                'routes' => [
                    'update' => route('admin.courses.classes.update', [$course, $courseClass]),
                    'delete' => route('admin.courses.classes.destroy', [$course, $courseClass]),
                    'users_store' => route('admin.courses.classes.users.store', [$course, $courseClass]),
                    'users_destroy_many' => route('admin.courses.classes.users.destroy-many', [$course, $courseClass]),
                    'teachers_store' => route('admin.courses.classes.teachers.store', [$course, $courseClass]),
                    'teachers_destroy_many' => route('admin.courses.classes.teachers.destroy-many', [$course, $courseClass]),
                ],
            ])->values(),
        ]);
    }

    public function update(
        UpdateCourseRequest $request,
        Course $course,
        SyncCourseSatisfactionSurvey $syncCourseSatisfactionSurvey,
    ): RedirectResponse {
        try {
            $validated = $request->validated();
            $attributes = [
                ...$validated,
                'has_satisfaction_survey' => (bool) ($validated['has_satisfaction_survey'] ?? false),
                'satisfaction_survey_required_for_certificate' => (bool) (($validated['has_satisfaction_survey'] ?? false)
                    && ($validated['satisfaction_survey_required_for_certificate'] ?? false)),
            ];

            if ($this->isPublishedCourseStatusOnlyUpdate($course, $attributes)) {
                $attributes = [
                    'status' => $validated['status'],
                ];
            }

            $course->update($attributes);
            $syncCourseSatisfactionSurvey->handle($course);

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
