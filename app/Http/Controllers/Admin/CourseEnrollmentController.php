<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseEnrollmentController extends Controller
{
    /**
     * Restituisce la lista paginata degli iscritti del corso per la tabella dinamica admin.
     */
    public function indexApi(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'show_trashed' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:name,surname,email,fiscal_code,status,completion_percentage,assigned_at,last_accessed_at'],
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
            'status' => 'course_user.status',
            'completion_percentage' => 'course_user.completion_percentage',
            'assigned_at' => 'course_user.assigned_at',
            'last_accessed_at' => 'course_user.last_accessed_at',
        ];

        $query = CourseEnrollment::query()
            ->whereBelongsTo($course, 'course')
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
                ->leftJoin('users', 'users.id', '=', 'course_user.user_id')
                ->select('course_user.*')
                ->orderBy($sortableColumns[$sort], $direction)
                ->orderBy('course_user.id', 'desc');
        } else {
            $query
                ->leftJoin('users', 'users.id', '=', 'course_user.user_id')
                ->select('course_user.*')
                ->orderBy('users.surname')
                ->orderBy('users.name')
                ->orderBy('course_user.id');
        }

        $statusLabels = [
            CourseEnrollment::STATUS_ASSIGNED => __('Assegnato'),
            CourseEnrollment::STATUS_IN_PROGRESS => __('In corso'),
            CourseEnrollment::STATUS_COMPLETED => __('Completato'),
            CourseEnrollment::STATUS_EXPIRED => __('Scaduto'),
            CourseEnrollment::STATUS_CANCELLED => __('Annullato'),
        ];

        $enrollments = $query
            ->paginate(10)
            ->through(function (CourseEnrollment $enrollment) use ($course, $statusLabels): array {
                $user = $enrollment->user;

                return [
                    'id' => $enrollment->getKey(),
                    'is_deleted' => $enrollment->trashed(),
                    'deleted_at' => $enrollment->deleted_at?->toAtomString(),
                    'status' => [
                        'key' => $enrollment->status,
                        'label' => $statusLabels[$enrollment->status] ?? $enrollment->status,
                    ],
                    'completion_percentage' => $enrollment->completion_percentage,
                    'assigned_at' => $enrollment->assigned_at?->format('d/m/Y H:i'),
                    'assigned_at_iso' => $enrollment->assigned_at?->toAtomString(),
                    'last_accessed_at' => $enrollment->last_accessed_at?->format('d/m/Y H:i'),
                    'last_accessed_at_iso' => $enrollment->last_accessed_at?->toAtomString(),
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
                        'delete_url' => route('admin.api.courses.enrollments.destroy', [$course, $enrollment]),
                        'can_delete' => ! $enrollment->trashed(),
                        'restore_url' => route('admin.api.courses.enrollments.restore', [$course, $enrollment]),
                        'can_restore' => $enrollment->trashed(),
                    ],
                ];
            });

        return response()->json([
            'data' => $enrollments->items(),
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

    /**
     * Cerca utenti candidati da iscrivere al corso.
     */
    public function searchUsersApi(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $search = trim($validated['search']);
        $activeEnrolledUserIds = CourseEnrollment::query()
            ->whereBelongsTo($course, 'course')
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

    /**
     * Crea una nuova iscrizione al corso per l'utente selezionato.
     */
    public function storeApi(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);

        $existingEnrollment = CourseEnrollment::withTrashed()
            ->whereBelongsTo($course, 'course')
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->first();

        if ($existingEnrollment !== null && ! $existingEnrollment->trashed()) {
            return response()->json([
                'success' => false,
                'message' => __('L\'utente è già iscritto a questo corso.'),
            ], 422);
        }

        if ($existingEnrollment !== null && $existingEnrollment->trashed()) {
            return response()->json([
                'success' => false,
                'requires_restore' => true,
                'message' => __('Esiste già un\'iscrizione eliminata per questo utente. Vuoi ripristinarla?'),
                'restore_url' => route('admin.api.courses.enrollments.restore', [$course, $existingEnrollment->getKey()]),
            ], 409);
        }

        CourseEnrollment::enroll($user, $course);

        return response()->json([
            'success' => true,
            'message' => __('Iscrizione creata con successo.'),
        ], 201);
    }

    /**
     * Ripristina una iscrizione soft deleted esistente.
     */
    public function restoreApi(Course $course, int $enrollment): JsonResponse
    {
        $existingEnrollment = CourseEnrollment::withTrashed()
            ->whereBelongsTo($course, 'course')
            ->whereKey($enrollment)
            ->firstOrFail();

        if (! $existingEnrollment->trashed()) {
            return response()->json([
                'success' => false,
                'message' => __('L\'iscrizione è già attiva.'),
            ], 422);
        }

        $anotherActiveEnrollmentExists = CourseEnrollment::query()
            ->whereBelongsTo($course, 'course')
            ->where('user_id', $existingEnrollment->user_id)
            ->whereNull('deleted_at')
            ->exists();

        if ($anotherActiveEnrollmentExists) {
            return response()->json([
                'success' => false,
                'message' => __('Esiste già un\'iscrizione attiva per questo utente nel corso.'),
            ], 422);
        }

        $existingEnrollment->restore();

        return response()->json([
            'success' => true,
            'message' => __('Iscrizione ripristinata con successo.'),
        ]);
    }

    /**
     * Soft delete di una iscrizione al corso.
     */
    public function destroyApi(Course $course, CourseEnrollment $enrollment): JsonResponse
    {
        abort_unless((int) $enrollment->course_id === (int) $course->getKey(), 404);

        if ($enrollment->trashed()) {
            return response()->json([
                'success' => false,
                'message' => __('L\'iscrizione risulta già eliminata.'),
            ], 422);
        }

        $enrollment->delete();

        return response()->json([
            'success' => true,
            'message' => __('Iscrizione eliminata con successo.'),
        ]);
    }
}
