<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingPathRequest;
use App\Http\Requests\UpdateTrainingPathCoursesRequest;
use App\Http\Requests\UpdateTrainingPathDetailsRequest;
use App\Http\Requests\UpdateTrainingPathRecipientsRequest;
use App\Models\Course;
use App\Models\JobRole;
use App\Models\JobTask;
use App\Models\JobUnit;
use App\Models\TrainingPath;
use App\Models\TrainingPathDocument;
use App\Services\TrainingPathEnrollmentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TrainingPathController extends Controller
{
    public function __construct(
        private readonly TrainingPathEnrollmentSyncService $trainingPathEnrollmentSyncService,
    ) {}

    public function index(Request $request): View
    {
        $allowedSorts = ['id', 'title', 'status', 'code'];
        $trainingPathStatusLabels = TrainingPath::availableStatusLabels();
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'id';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());

        return view('admin.training-path.index', [
            'trainingPaths' => TrainingPath::query()
                ->select(['id', 'title', 'code', 'status'])
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
                        $query
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('title', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%")
                            ->orWhere('status', 'like', "%{$search}%");
                    });
                })
                ->orderBy($sort, $direction)
                ->paginate(20)
                ->through(function (TrainingPath $trainingPath) use ($trainingPathStatusLabels): TrainingPath {
                    $trainingPath->status = $trainingPathStatusLabels[$trainingPath->status] ?? $trainingPath->status;

                    return $trainingPath;
                })
                ->withQueryString(),
            'tableSort' => $sort,
            'tableDirection' => $direction,
            'tableSearch' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.training-path.create');
    }

    public function store(StoreTrainingPathRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $trainingPath = TrainingPath::query()->create([
            'title' => $validated['title'],
            'code' => $validated['code'] ?? null,
            'description' => null,
            'status' => 'draft',
            'visible_to_all' => true,
            'enforce_course_order' => true,
        ]);

        if (blank($validated['code'] ?? null)) {
            $trainingPath->forceFill([
                'code' => 'PATH-'.$trainingPath->getKey(),
            ])->save();
        }

        return redirect()
            ->route('admin.training-paths.edit', $trainingPath)
            ->with('status', __('Percorso formativo creato con successo.'));
    }

    public function edit(TrainingPath $trainingPath): View
    {
        $trainingPath->load([
            'courses',
            'documents',
            'jobRoles',
            'jobTasks',
            'jobUnits',
        ]);

        $courseIds = $trainingPath->courses
            ->pluck('id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->values();

        $courseEnrollmentCleanupCounts = $this->trainingPathEnrollmentSyncService
            ->activeCourseEnrollmentCountsForPathUsers($trainingPath, $courseIds);

        return view('admin.training-path.edit', [
            'trainingPath' => $trainingPath,
            'courseStatusLabels' => Course::availableStatusLabels(),
            'courseTypeLabels' => Course::availableTypeLabels(),
            'trainingPathStatusLabels' => TrainingPath::availableStatusLabels(),
            'trainingPathDocumentCategoryLabels' => TrainingPathDocument::categoryLabels(),
            'trainingPathDocumentFileTypeLabels' => TrainingPathDocument::fileTypeLabels(),
            'availableCourses' => Course::query()
                ->where('status', 'published')
                ->orderBy('title')
                ->get(['id', 'title', 'code', 'type', 'status', 'year']),
            'jobRoles' => JobRole::query()->orderBy('name')->get(['id', 'name']),
            'jobTasks' => JobTask::query()->orderBy('name')->get(['id', 'name']),
            'jobUnits' => JobUnit::query()
                ->with('city:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'unit_code', 'address', 'postal_code', 'city_id']),
            'courseEnrollmentCleanupCounts' => $courseEnrollmentCleanupCounts,
        ]);
    }

    public function availableCoursesApi(Request $request, TrainingPath $trainingPath): JsonResponse
    {
        $trainingPath->loadMissing('courses:id');

        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:id,title,type,status,year'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $requestedSort = $validated['sort'] ?? null;
        $requestedDirection = $validated['direction'] ?? null;
        $search = trim((string) ($validated['search'] ?? ''));
        $sort = $requestedSort !== null && $requestedDirection !== null ? $requestedSort : 'id';
        $direction = $requestedSort !== null && $requestedDirection !== null ? $requestedDirection : 'desc';
        $courseStatusLabels = Course::availableStatusLabels();
        $courseTypeLabels = Course::availableTypeLabels();

        $courses = Course::query()
            ->select(['id', 'title', 'code', 'type', 'status', 'year'])
            ->where('status', 'published')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('title', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('type', 'like', "%{$search}%")
                        ->orWhere('status', 'like', "%{$search}%")
                        ->orWhere('year', 'like', "%{$search}%");
                });
            })
            ->orderBy($sort, $direction)
            ->paginate(10)
            ->through(fn (Course $course): array => [
                'id' => $course->getKey(),
                'title' => $course->title,
                'code' => $course->code,
                'type' => [
                    'key' => $course->type,
                    'label' => $courseTypeLabels[$course->type] ?? $course->type,
                ],
                'status' => [
                    'key' => $course->status,
                    'label' => $courseStatusLabels[$course->status] ?? $course->status,
                ],
                'year' => $course->year,
                'is_selected' => $trainingPath->courses->contains($course->getKey()),
            ]);

        return response()->json([
            'data' => $courses->items(),
            'meta' => [
                'current_page' => $courses->currentPage(),
                'last_page' => $courses->lastPage(),
                'per_page' => $courses->perPage(),
                'total' => $courses->total(),
                'from' => $courses->firstItem(),
                'to' => $courses->lastItem(),
            ],
            'query' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function updateDetails(UpdateTrainingPathDetailsRequest $request, TrainingPath $trainingPath): RedirectResponse
    {
        $validated = $request->validated();
        $targetStatus = (string) ($validated['status'] ?? $trainingPath->status);
        $isChangingStatus = $targetStatus !== $trainingPath->status;

        if ($trainingPath->status === 'published' && $isChangingStatus) {
            $hasActiveEnrollments = $trainingPath->enrollments()
                ->whereNull('deleted_at')
                ->exists();

            if ($hasActiveEnrollments) {
                return back()
                    ->withInput()
                    ->with('error', __('Non è possibile cambiare lo stato di un percorso pubblicato con iscrizioni attive.'));
            }
        }

        $trainingPath->update($validated);

        $this->trainingPathEnrollmentSyncService->syncAllEnrollmentsForPath($trainingPath->fresh());

        return $this->redirectToSection($trainingPath, 'details', __('Percorso formativo aggiornato con successo.'));
    }

    public function updateCourses(UpdateTrainingPathCoursesRequest $request, TrainingPath $trainingPath): RedirectResponse
    {
        $courseIds = collect($request->validated('course_ids', []))
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->unique()
            ->values();

        $existingCourseIds = $trainingPath->courses()
            ->pluck('courses.id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->values();

        $removedCourseIds = $this->removedCourseIds($existingCourseIds, $courseIds);
        $removedCourseEnrollmentsCount = $this->trainingPathEnrollmentSyncService
            ->countActiveCourseEnrollmentsForPathUsers($trainingPath, $removedCourseIds);

        if ($removedCourseEnrollmentsCount > 0 && ! $request->boolean('confirm_course_enrollment_cleanup')) {
            return $this->redirectToSection($trainingPath, 'courses', __('Per rimuovere i corsi selezionati devi confermare anche la rimozione delle iscrizioni collegate al percorso.'))
                ->withInput()
                ->with('error', __('Conferma la rimozione delle iscrizioni ai corsi rimossi per gli iscritti al percorso.'));
        }

        $unpublishedCourseIds = Course::query()
            ->whereIn('id', $courseIds->all())
            ->where('status', '!=', 'published')
            ->pluck('id');

        if ($unpublishedCourseIds->isNotEmpty()) {
            return back()
                ->withInput()
                ->with('error', __('Puoi associare al percorso solo corsi pubblicati.'));
        }

        $courseOrders = collect($request->validated('course_orders', []));

        $syncPayload = $courseIds
            ->mapWithKeys(fn (int $courseId, int $index): array => [
                $courseId => [
                    'sort_order' => max(1, (int) $courseOrders->get((string) $courseId, $index + 1)),
                ],
            ])
            ->all();

        $deletedEnrollmentsCount = DB::transaction(function () use ($removedCourseIds, $syncPayload, $trainingPath): int {
            $trainingPath->courses()->sync($syncPayload);

            return $this->trainingPathEnrollmentSyncService
                ->unsetPathwayOriginAndDeleteIfNeededForPathUsers($trainingPath, $removedCourseIds);
        });

        $this->trainingPathEnrollmentSyncService->syncAllEnrollmentsForPath($trainingPath->fresh());

        $message = __('Corsi associati aggiornati con successo.');

        if ($deletedEnrollmentsCount > 0) {
            $message = __('Corsi associati aggiornati con successo. Sono state eliminate :count iscrizioni ai corsi rimossi per utenti iscritti al percorso.', [
                'count' => $deletedEnrollmentsCount,
            ]);
        }

        return $this->redirectToSection($trainingPath, 'courses', $message);
    }

    public function updateRecipients(UpdateTrainingPathRecipientsRequest $request, TrainingPath $trainingPath): RedirectResponse
    {
        $validated = $request->validated();

        $trainingPath->update([
            'visible_to_all' => (bool) ($validated['visible_to_all'] ?? false),
        ]);

        $trainingPath->jobRoles()->sync($this->uniqueIds($validated['job_role_ids'] ?? []));
        $trainingPath->jobTasks()->sync($this->uniqueIds($validated['job_task_ids'] ?? []));
        $trainingPath->jobUnits()->sync($this->uniqueIds($validated['job_unit_ids'] ?? []));

        return $this->redirectToSection($trainingPath, 'recipients', __('Destinatari del percorso aggiornati con successo.'));
    }

    public function destroy(TrainingPath $trainingPath): RedirectResponse
    {
        $courseIds = $trainingPath->courses()
            ->pluck('courses.id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->values();

        $this->trainingPathEnrollmentSyncService
            ->unsetPathwayOriginAndDeleteIfNeededForPathUsers($trainingPath, $courseIds);

        $trainingPath->enrollments()
            ->whereNull('deleted_at')
            ->get()
            ->each
            ->delete();

        $trainingPath->delete();

        return redirect()
            ->route('admin.training-paths.index')
            ->with('status', __('Percorso formativo eliminato con successo.'));
    }

    private function redirectToSection(TrainingPath $trainingPath, string $section, string $message): RedirectResponse
    {
        return redirect()
            ->route('admin.training-paths.edit', [
                'trainingPath' => $trainingPath,
                'section' => $section,
            ])
            ->with('status', $message);
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
     * @param  Collection<int, int>  $existingCourseIds
     * @param  Collection<int, int>  $selectedCourseIds
     * @return Collection<int, int>
     */
    private function removedCourseIds(Collection $existingCourseIds, Collection $selectedCourseIds): Collection
    {
        return $existingCourseIds
            ->diff($selectedCourseIds)
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->unique()
            ->values();
    }
}
