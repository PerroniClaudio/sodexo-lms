<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseEnrollmentController extends Controller
{
    public function indexApi(Request $request, Course $course): JsonResponse
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);
        abort_unless($this->canAccessCourse($user, $course), 403);

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
            ->through(function (CourseEnrollment $enrollment) use ($statusLabels): array {
                $enrollmentUser = $enrollment->user;

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
                        'id' => $enrollmentUser?->getKey(),
                        'name' => $enrollmentUser?->name,
                        'surname' => $enrollmentUser?->surname,
                        'email' => $enrollmentUser?->email,
                        'fiscal_code' => $enrollmentUser?->fiscal_code,
                        'is_deleted' => $enrollmentUser?->trashed() ?? false,
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

    private function canAccessCourse(User $user, Course $course): bool
    {
        $routeName = request()->route()?->getName() ?? '';

        if (str_starts_with($routeName, 'teacher.')) {
            return $user->getTeachingCoursesQuery()->whereKey($course->getKey())->exists();
        }

        if (str_starts_with($routeName, 'tutor.')) {
            return $user->getTutoringCoursesQuery()->whereKey($course->getKey())->exists();
        }

        return false;
    }
}
