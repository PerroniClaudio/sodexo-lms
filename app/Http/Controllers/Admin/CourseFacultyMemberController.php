<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseFacultyMember;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;

class CourseFacultyMemberController extends Controller
{
    public function indexApi(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'show_trashed' => ['nullable', 'boolean'],
            'sort' => ['nullable', 'string', 'in:name,surname,fiscal_code,role,affiliation,compensation_amount'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sort = $validated['sort'] ?? 'surname';
        $direction = $validated['direction'] ?? 'asc';

        $query = CourseFacultyMember::query()
            ->whereBelongsTo($course, 'course')
            ->with(['user' => fn ($query) => $query->withTrashed()->select(['id', 'name', 'surname', 'email', 'fiscal_code', 'deleted_at'])]);

        if ((bool) ($validated['show_trashed'] ?? false)) {
            $query->withTrashed();
        }

        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('fiscal_code', 'like', "%{$search}%")
                    ->orWhere('role', 'like', "%{$search}%")
                    ->orWhere('affiliation', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->withTrashed()->where('email', 'like', "%{$search}%"));
            });
        }

        $members = $query
            ->orderBy($sort, $direction)
            ->orderBy('id')
            ->paginate(10)
            ->through(fn (CourseFacultyMember $member): array => $this->payload($course, $member));

        return response()->json([
            'data' => $members->items(),
            'meta' => [
                'current_page' => $members->currentPage(),
                'last_page' => $members->lastPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
                'from' => $members->firstItem(),
                'to' => $members->lastItem(),
            ],
        ]);
    }

    public function searchUsersApi(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:255'],
        ]);

        $search = trim($validated['search']);

        $users = User::query()
            ->where(function ($query) use ($search): void {
                $query
                    ->where('id', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('surname', 'like', "%{$search}%")
                    ->orWhere('fiscal_code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            })
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
        $validated = $this->validatedData($request);
        $user = isset($validated['user_id']) ? User::query()->findOrFail($validated['user_id']) : null;
        $attributes = $this->attributes($course, $validated, $user);

        $deletedMember = $this->matchingDeletedMember($course, $attributes);

        if ($deletedMember !== null) {
            return response()->json([
                'success' => false,
                'requires_restore' => true,
                'message' => __('Esiste già un membro Faculty eliminato con lo stesso ruolo. Vuoi ripristinarlo?'),
                'restore_url' => route('admin.api.courses.faculty-members.restore', [$course, $deletedMember->getKey()]),
            ], 409);
        }

        try {
            CourseFacultyMember::query()->create($attributes);
        } catch (DomainException) {
            return response()->json([
                'success' => false,
                'message' => __('Il membro Faculty è già presente per questo corso e ruolo.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Membro Faculty aggiunto con successo.'),
        ], 201);
    }

    public function updateApi(Request $request, Course $course, CourseFacultyMember $facultyMember): JsonResponse
    {
        abort_unless((int) $facultyMember->course_id === (int) $course->getKey(), 404);

        $validated = $this->validatedData($request);
        $user = isset($validated['user_id']) ? User::query()->findOrFail($validated['user_id']) : null;

        try {
            $facultyMember->update($this->attributes($course, $validated, $user));
        } catch (DomainException) {
            return response()->json([
                'success' => false,
                'message' => __('Il membro Faculty è già presente per questo corso e ruolo.'),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => __('Membro Faculty aggiornato con successo.'),
        ]);
    }

    public function restoreApi(Course $course, int $facultyMember): JsonResponse
    {
        $member = CourseFacultyMember::withTrashed()
            ->whereBelongsTo($course, 'course')
            ->whereKey($facultyMember)
            ->firstOrFail();

        if (! $member->trashed()) {
            return response()->json([
                'success' => false,
                'message' => __('Il membro Faculty è già attivo.'),
            ], 422);
        }

        $member->restore();

        return response()->json([
            'success' => true,
            'message' => __('Membro Faculty ripristinato con successo.'),
        ]);
    }

    public function destroyApi(Course $course, CourseFacultyMember $facultyMember): JsonResponse
    {
        abort_unless((int) $facultyMember->course_id === (int) $course->getKey(), 404);

        $facultyMember->delete();

        return response()->json([
            'success' => true,
            'message' => __('Membro Faculty rimosso con successo.'),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'surname' => ['required_without:user_id', 'nullable', 'string', 'max:255'],
            'fiscal_code' => ['required_without:user_id', 'nullable', 'string', 'max:32'],
            'role' => ['required', 'string', Rule::in(CourseFacultyMember::roles())],
            'affiliation' => ['nullable', 'string', 'max:255'],
            'has_compensation' => ['nullable', 'boolean'],
            'compensation_amount' => ['nullable', 'required_if:has_compensation,1,true', 'numeric', 'min:0', 'max:99999999.99'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function attributes(Course $course, array $validated, ?User $user): array
    {
        $hasCompensation = (bool) ($validated['has_compensation'] ?? false);

        return [
            'course_id' => $course->getKey(),
            'user_id' => $user?->getKey(),
            'name' => $user?->name ?? $validated['name'],
            'surname' => $user?->surname ?? $validated['surname'],
            'fiscal_code' => $user?->fiscal_code ?? $validated['fiscal_code'],
            'role' => $validated['role'],
            'affiliation' => $validated['affiliation'] ?? null,
            'has_compensation' => $hasCompensation,
            'compensation_amount' => $hasCompensation ? $validated['compensation_amount'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function matchingDeletedMember(Course $course, array $attributes): ?CourseFacultyMember
    {
        $query = CourseFacultyMember::withTrashed()
            ->whereBelongsTo($course, 'course')
            ->where('role', $attributes['role'])
            ->whereNotNull('deleted_at');

        if ($attributes['user_id'] !== null) {
            $query->where('user_id', $attributes['user_id']);
        } else {
            $query
                ->whereNull('user_id')
                ->where('name', $attributes['name'])
                ->where('surname', $attributes['surname'])
                ->where('fiscal_code', $attributes['fiscal_code']);
        }

        return $query->orderByDesc('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Course $course, CourseFacultyMember $member): array
    {
        $roleLabels = CourseFacultyMember::roleLabels();
        $roleBadgeLabels = [
            ...$roleLabels,
            CourseFacultyMember::ROLE_RPF => 'RPF',
        ];

        return [
            'id' => $member->getKey(),
            'is_deleted' => $member->trashed(),
            'name' => $member->name,
            'surname' => $member->surname,
            'fiscal_code' => $member->fiscal_code,
            'role' => [
                'key' => $member->role,
                'label' => $roleBadgeLabels[$member->role] ?? $member->role,
            ],
            'affiliation' => $member->affiliation,
            'has_compensation' => $member->has_compensation,
            'compensation_amount' => $member->compensation_amount,
            'compensation_amount_formatted' => $member->compensation_amount !== null
                ? Number::currency((float) $member->compensation_amount, 'EUR', locale: 'it')
                : Number::currency(0, 'EUR', locale: 'it'),
            'user' => [
                'id' => $member->user?->getKey(),
                'email' => $member->user?->email,
            ],
            'actions' => [
                'update_url' => route('admin.api.courses.faculty-members.update', [$course, $member]),
                'delete_url' => route('admin.api.courses.faculty-members.destroy', [$course, $member]),
                'can_delete' => ! $member->trashed(),
                'restore_url' => route('admin.api.courses.faculty-members.restore', [$course, $member->getKey()]),
                'can_restore' => $member->trashed(),
            ],
        ];
    }
}
