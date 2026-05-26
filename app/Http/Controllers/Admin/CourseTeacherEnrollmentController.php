<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseTeacherEnrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseTeacherEnrollmentController extends Controller
{
    public function indexApi(Request $request, Course $course): JsonResponse
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
            'assigned_at' => 'course_teacher_enrollments.assigned_at',
        ];

        $query = CourseTeacherEnrollment::query()
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
                ->leftJoin('users', 'users.id', '=', 'course_teacher_enrollments.user_id')
                ->select('course_teacher_enrollments.*')
                ->orderBy($sortableColumns[$sort], $direction)
                ->orderBy('course_teacher_enrollments.id', 'desc');
        } else {
            $query
                ->leftJoin('users', 'users.id', '=', 'course_teacher_enrollments.user_id')
                ->select('course_teacher_enrollments.*')
                ->orderBy('users.surname')
                ->orderBy('users.name')
                ->orderBy('course_teacher_enrollments.id');
        }

        $enrollments = $query
            ->paginate(10)
            ->through(function (CourseTeacherEnrollment $enrollment) use ($course): array {
                $user = $enrollment->user;

                return [
                    'id' => $enrollment->getKey(),
                    'is_deleted' => $enrollment->trashed(),
                    'deleted_at' => $enrollment->deleted_at?->toAtomString(),
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
                        'delete_url' => route('admin.api.courses.teacher-enrollments.destroy', [$course, $enrollment]),
                        'can_delete' => ! $enrollment->trashed(),
                        'restore_url' => route('admin.api.courses.teacher-enrollments.restore', [$course, $enrollment]),
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
        ]);
    }

    public function searchUsersApi(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $search = trim($validated['search']);
        $activeTeacherIds = CourseTeacherEnrollment::query()
            ->whereBelongsTo($course, 'course')
            ->whereNull('deleted_at')
            ->pluck('user_id');

        $users = User::query()
            ->where(function ($query) use ($search): void {
                $query
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('fiscal_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
            ->whereNotIn('id', $activeTeacherIds)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', ['teacher', 'docente']))
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

    public function storeApi(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $isEligibleTeacher = $user->hasAnyRole(['teacher', 'docente']);

        if (! $isEligibleTeacher) {
            return response()->json([
                'success' => false,
                'message' => __('Puoi assegnare come docente del corso solo utenti con ruolo docente.'),
            ], 422);
        }

        $existingEnrollment = CourseTeacherEnrollment::withTrashed()
            ->whereBelongsTo($course, 'course')
            ->where('user_id', $user->getKey())
            ->orderByDesc('id')
            ->first();

        if ($existingEnrollment !== null && ! $existingEnrollment->trashed()) {
            return response()->json([
                'success' => false,
                'message' => __('L\'utente è già assegnato come docente a questo corso.'),
            ], 422);
        }

        if ($existingEnrollment !== null && $existingEnrollment->trashed()) {
            return response()->json([
                'success' => false,
                'requires_restore' => true,
                'message' => __('Esiste già una assegnazione docente eliminata per questo utente. Vuoi ripristinarla?'),
                'restore_url' => route('admin.api.courses.teacher-enrollments.restore', [$course, $existingEnrollment->getKey()]),
            ], 409);
        }

        CourseTeacherEnrollment::enroll($user, $course);

        return response()->json([
            'success' => true,
            'message' => __('Docente assegnato al corso con successo.'),
        ], 201);
    }

    public function restoreApi(Course $course, int $enrollment): JsonResponse
    {
        $existingEnrollment = CourseTeacherEnrollment::withTrashed()
            ->whereBelongsTo($course, 'course')
            ->whereKey($enrollment)
            ->firstOrFail();

        if (! $existingEnrollment->trashed()) {
            return response()->json([
                'success' => false,
                'message' => __('L\'assegnazione docente è già attiva.'),
            ], 422);
        }

        $anotherActiveEnrollmentExists = CourseTeacherEnrollment::query()
            ->whereBelongsTo($course, 'course')
            ->where('user_id', $existingEnrollment->user_id)
            ->whereNull('deleted_at')
            ->exists();

        if ($anotherActiveEnrollmentExists) {
            return response()->json([
                'success' => false,
                'message' => __('Esiste già una assegnazione docente attiva per questo utente nel corso.'),
            ], 422);
        }

        $existingEnrollment->restore();
        $existingEnrollment->forceFill(['assigned_at' => now()])->save();

        return response()->json([
            'success' => true,
            'message' => __('Assegnazione docente ripristinata con successo.'),
        ]);
    }

    public function destroyApi(Course $course, CourseTeacherEnrollment $enrollment): JsonResponse
    {
        abort_unless((int) $enrollment->course_id === (int) $course->getKey(), 404);

        if ($enrollment->trashed()) {
            return response()->json([
                'success' => false,
                'message' => __('L\'assegnazione docente risulta già eliminata.'),
            ], 422);
        }

        $enrollment->delete();

        return response()->json([
            'success' => true,
            'message' => __('Docente rimosso dal corso con successo.'),
        ]);
    }
}
