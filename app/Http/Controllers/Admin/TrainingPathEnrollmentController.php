<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CourseEnrollment;
use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use App\Services\TrainingPathEnrollmentSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TrainingPathEnrollmentController extends Controller
{
    public function __construct(
        private readonly TrainingPathEnrollmentSyncService $trainingPathEnrollmentSyncService,
    ) {}

    public function indexApi(Request $request, TrainingPath $trainingPath): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'show_trashed' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:name,surname,email,fiscal_code,assigned_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $showTrashed = (bool) ($validated['show_trashed'] ?? false);
        $sort = $validated['sort'] ?? null;
        $direction = $validated['direction'] ?? null;

        if ($sort === null || $direction === null) {
            $sort = null;
            $direction = null;
        }

        $sortableColumns = [
            'name' => 'users.name',
            'surname' => 'users.surname',
            'email' => 'users.email',
            'fiscal_code' => 'users.fiscal_code',
            'assigned_at' => 'training_path_user.assigned_at',
        ];

        $query = TrainingPathEnrollment::query()
            ->whereBelongsTo($trainingPath, 'trainingPath')
            ->with([
                'user' => fn ($userQuery) => $userQuery
                    ->withTrashed()
                    ->select(['id', 'name', 'surname', 'email', 'fiscal_code', 'deleted_at']),
            ]);

        if ($showTrashed) {
            $query->withTrashed();
        }

        if ($search !== '') {
            $query->whereHas('user', function ($userQuery) use ($search): void {
                $userQuery
                    ->withTrashed()
                    ->where(function ($nestedQuery) use ($search): void {
                        $nestedQuery
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->orWhere('fiscal_code', 'like', "%{$search}%");
                    });
            });
        }

        if ($sort !== null && array_key_exists($sort, $sortableColumns)) {
            $query
                ->leftJoin('users', 'users.id', '=', 'training_path_user.user_id')
                ->select('training_path_user.*')
                ->orderBy($sortableColumns[$sort], $direction)
                ->orderBy('training_path_user.id', 'desc');
        } else {
            $query
                ->leftJoin('users', 'users.id', '=', 'training_path_user.user_id')
                ->select('training_path_user.*')
                ->orderBy('users.surname')
                ->orderBy('users.name')
                ->orderBy('training_path_user.id');
        }

        $enrollments = $query->paginate(10);
        $progressByUserId = $this->progressByUserId($trainingPath, collect($enrollments->items()));

        return response()->json([
            'data' => $enrollments->through(function (TrainingPathEnrollment $enrollment) use ($progressByUserId, $trainingPath): array {
                $user = $enrollment->user;
                $progress = $progressByUserId->get((int) $enrollment->user_id, [
                    'completed_courses' => 0,
                    'total_courses' => 0,
                    'completion_percentage' => 0,
                    'status' => 'assigned',
                    'status_label' => __('Assegnato'),
                ]);

                return [
                    'id' => $enrollment->getKey(),
                    'is_deleted' => $enrollment->trashed(),
                    'deleted_at' => $enrollment->deleted_at?->toAtomString(),
                    'status' => [
                        'key' => $progress['status'],
                        'label' => $progress['status_label'],
                    ],
                    'completion_percentage' => $progress['completion_percentage'],
                    'completed_courses' => $progress['completed_courses'],
                    'total_courses' => $progress['total_courses'],
                    'assigned_at' => $enrollment->assigned_at?->format('d/m/Y H:i'),
                    'assigned_at_iso' => $enrollment->assigned_at?->toAtomString(),
                    'user' => [
                        'id' => $user?->getKey(),
                        'name' => $user?->name,
                        'surname' => $user?->surname,
                        'email' => $user?->email,
                        'fiscal_code' => $user?->fiscal_code,
                        'is_deleted' => $user?->trashed() ?? false,
                    ],
                    'actions' => [
                        'edit_url' => $user !== null ? route('admin.users.edit', $user) : null,
                        'delete_url' => route('admin.api.training-paths.enrollments.destroy', [$trainingPath, $enrollment]),
                        'can_delete' => ! $enrollment->trashed(),
                        'restore_url' => route('admin.api.training-paths.enrollments.restore', [$trainingPath, $enrollment]),
                        'can_restore' => $enrollment->trashed(),
                    ],
                ];
            })->items(),
            'meta' => [
                'current_page' => $enrollments->currentPage(),
                'last_page' => $enrollments->lastPage(),
                'per_page' => $enrollments->perPage(),
                'total' => $enrollments->total(),
                'from' => $enrollments->firstItem(),
                'to' => $enrollments->lastItem(),
            ],
            'query' => [
                'search' => $search,
                'show_trashed' => $showTrashed,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function searchUsersApi(Request $request, TrainingPath $trainingPath): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $search = trim($validated['search']);
        $activeEnrolledUserIds = TrainingPathEnrollment::query()
            ->whereBelongsTo($trainingPath, 'trainingPath')
            ->whereNull('deleted_at')
            ->pluck('user_id');

        $users = User::query()
            ->withTrashed()
            ->where(function ($query) use ($search): void {
                $query
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('fiscal_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->whereNotIn('id', $activeEnrolledUserIds)
            ->orderBy('surname')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'name', 'surname', 'fiscal_code', 'email', 'deleted_at']);

        return response()->json([
            'data' => $users->map(fn (User $user): array => [
                'id' => $user->getKey(),
                'name' => $user->name,
                'surname' => $user->surname,
                'fiscal_code' => $user->fiscal_code,
                'email' => $user->email,
                'is_deleted' => $user->trashed(),
            ])->values(),
        ]);
    }

    public function storeApi(Request $request, TrainingPath $trainingPath): JsonResponse
    {
        if ($trainingPath->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => __('Non puoi iscrivere utenti a un percorso non pubblicato.'),
            ], 422);
        }

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);

        $existingEnrollment = TrainingPathEnrollment::withTrashed()
            ->whereBelongsTo($trainingPath, 'trainingPath')
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->first();

        if ($existingEnrollment !== null && ! $existingEnrollment->trashed()) {
            $this->trainingPathEnrollmentSyncService->syncEnrollment($existingEnrollment);

            return response()->json([
                'success' => true,
                'message' => __('L\'utente era già iscritto al percorso: origine percorso aggiornata sui corsi collegati.'),
            ]);
        }

        if ($existingEnrollment !== null && $existingEnrollment->trashed()) {
            return response()->json([
                'success' => false,
                'requires_restore' => true,
                'message' => __('Esiste già un\'iscrizione eliminata per questo utente. Vuoi ripristinarla?'),
                'restore_url' => route('admin.api.training-paths.enrollments.restore', [$trainingPath, $existingEnrollment->getKey()]),
            ], 409);
        }

        $enrollment = TrainingPathEnrollment::enroll($user, $trainingPath);
        $this->trainingPathEnrollmentSyncService->syncEnrollment($enrollment);

        return response()->json([
            'success' => true,
            'message' => __('Iscrizione creata con successo.'),
        ], 201);
    }

    public function restoreApi(TrainingPath $trainingPath, int $enrollment): JsonResponse
    {
        if ($trainingPath->status !== 'published') {
            return response()->json([
                'success' => false,
                'message' => __('Non puoi ripristinare iscrizioni su un percorso non pubblicato.'),
            ], 422);
        }

        $existingEnrollment = TrainingPathEnrollment::withTrashed()
            ->whereBelongsTo($trainingPath, 'trainingPath')
            ->whereKey($enrollment)
            ->firstOrFail();

        if (! $existingEnrollment->trashed()) {
            return response()->json([
                'success' => false,
                'message' => __('L\'iscrizione è già attiva.'),
            ], 422);
        }

        $anotherActiveEnrollmentExists = TrainingPathEnrollment::query()
            ->whereBelongsTo($trainingPath, 'trainingPath')
            ->where('user_id', $existingEnrollment->user_id)
            ->whereNull('deleted_at')
            ->exists();

        if ($anotherActiveEnrollmentExists) {
            return response()->json([
                'success' => false,
                'message' => __('Esiste già un\'iscrizione attiva per questo utente nel percorso.'),
            ], 422);
        }

        $existingEnrollment->restore();
        $existingEnrollment->refresh();
        $this->trainingPathEnrollmentSyncService->syncEnrollment($existingEnrollment);

        return response()->json([
            'success' => true,
            'message' => __('Iscrizione ripristinata con successo.'),
        ]);
    }

    public function destroyApi(TrainingPath $trainingPath, TrainingPathEnrollment $enrollment): JsonResponse
    {
        abort_unless((int) $enrollment->training_path_id === (int) $trainingPath->getKey(), 404);

        if ($enrollment->trashed()) {
            return response()->json([
                'success' => false,
                'message' => __('L\'iscrizione risulta già eliminata.'),
            ], 422);
        }

        $courseIds = $trainingPath->courses()
            ->pluck('courses.id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->values();

        $this->trainingPathEnrollmentSyncService
            ->unsetPathwayOriginAndDeleteIfNeededForUser($trainingPath, (int) $enrollment->user_id, $courseIds);

        $enrollment->delete();

        return response()->json([
            'success' => true,
            'message' => __('Iscrizione eliminata con successo.'),
        ]);
    }

    /**
     * @param  Collection<int, TrainingPathEnrollment>  $enrollments
     * @return Collection<int, array{completed_courses: int, total_courses: int, completion_percentage: int, status: string, status_label: string}>
     */
    private function progressByUserId(TrainingPath $trainingPath, Collection $enrollments): Collection
    {
        $userIds = $enrollments
            ->pluck('user_id')
            ->map(fn (mixed $userId): int => (int) $userId)
            ->unique()
            ->values();
        $courseIds = $trainingPath->courses()
            ->pluck('courses.id')
            ->map(fn (mixed $courseId): int => (int) $courseId)
            ->values();
        $totalCourses = $courseIds->count();

        if ($userIds->isEmpty() || $totalCourses === 0) {
            return $userIds->mapWithKeys(fn (int $userId): array => [
                $userId => [
                    'completed_courses' => 0,
                    'total_courses' => $totalCourses,
                    'completion_percentage' => 0,
                    'status' => 'assigned',
                    'status_label' => __('Assegnato'),
                ],
            ]);
        }

        $completedCounts = DB::table('course_user')
            ->selectRaw('user_id, COUNT(DISTINCT course_id) as completed_courses')
            ->whereIn('user_id', $userIds)
            ->whereIn('course_id', $courseIds)
            ->whereNull('deleted_at')
            ->where('status', CourseEnrollment::STATUS_COMPLETED)
            ->groupBy('user_id')
            ->pluck('completed_courses', 'user_id');

        return $userIds->mapWithKeys(function (int $userId) use ($completedCounts, $totalCourses): array {
            $completedCourses = (int) ($completedCounts[$userId] ?? 0);
            $completionPercentage = (int) round(($completedCourses / $totalCourses) * 100);
            $status = $completedCourses === 0
                ? 'assigned'
                : ($completedCourses >= $totalCourses ? 'completed' : 'in_progress');

            return [
                $userId => [
                    'completed_courses' => $completedCourses,
                    'total_courses' => $totalCourses,
                    'completion_percentage' => $completionPercentage,
                    'status' => $status,
                    'status_label' => match ($status) {
                        'completed' => __('Completato'),
                        'in_progress' => __('In corso'),
                        default => __('Assegnato'),
                    },
                ],
            ];
        });
    }
}
