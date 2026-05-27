<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserCertificateRequest;
use App\Models\User;
use App\Models\UserCertificate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserCertificateController extends Controller
{
    public function indexApi(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'string', 'in:name,issued_at,expires_at,is_internal,created_at'],
            'direction' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $search = trim((string) ($validated['search'] ?? ''));
        $sort = $validated['sort'] ?? 'issued_at';
        $direction = $validated['direction'] ?? 'desc';

        $sortableColumns = [
            'name' => 'user_certificates.name',
            'issued_at' => 'user_certificates.issued_at',
            'expires_at' => 'user_certificates.expires_at',
            'is_internal' => 'user_certificates.is_internal',
            'created_at' => 'user_certificates.created_at',
        ];

        $query = UserCertificate::query()
            ->whereBelongsTo($user)
            ->with([
                'requirements:id,name',
                'internalCourse:id,title',
            ]);

        if ($search !== '') {
            $query->where(function ($certificateQuery) use ($search): void {
                $certificateQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $query
            ->orderBy($sortableColumns[$sort] ?? $sortableColumns['issued_at'], $direction)
            ->orderByDesc('user_certificates.id');

        $certificates = $query
            ->paginate(10)
            ->through(function (UserCertificate $certificate) use ($user): array {
                return [
                    'id' => $certificate->getKey(),
                    'name' => $certificate->name,
                    'description' => $certificate->description,
                    'file_path' => $certificate->file_path,
                    'internal_course_id' => $certificate->internal_course_id,
                    'issued_at' => $certificate->issued_at?->format('d/m/Y'),
                    'issued_at_iso' => $certificate->issued_at?->toDateString(),
                    'expires_at' => $certificate->expires_at?->format('d/m/Y'),
                    'expires_at_iso' => $certificate->expires_at?->toDateString(),
                    'is_internal' => $certificate->is_internal,
                    'type_label' => $certificate->is_internal ? __('Interno') : __('Esterno'),
                    'internal_course' => $certificate->internalCourse?->title,
                    'requirements' => $certificate->requirements
                        ->map(fn ($requirement): array => [
                            'id' => $requirement->getKey(),
                            'name' => $requirement->name,
                        ])
                        ->values()
                        ->all(),
                    'actions' => [
                        'update_url' => route('admin.api.users.certificates.update', [$user, $certificate]),
                        'delete_url' => route('admin.api.users.certificates.destroy', [$user, $certificate]),
                    ],
                ];
            });

        return response()->json([
            'data' => $certificates->items(),
            'meta' => [
                'current_page' => $certificates->currentPage(),
                'last_page' => $certificates->lastPage(),
                'per_page' => $certificates->perPage(),
                'total' => $certificates->total(),
                'from' => $certificates->firstItem(),
                'to' => $certificates->lastItem(),
            ],
            'query' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    public function storeApi(StoreUserCertificateRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $certificate = DB::transaction(fn (): UserCertificate => $this->persistCertificate(
            $user->userCertificates()->make(),
            $validated,
        ));

        return response()->json([
            'success' => true,
            'message' => __('Certificato registrato con successo.'),
            'data' => [
                'id' => $certificate->getKey(),
                'name' => $certificate->name,
            ],
        ], 201);
    }

    public function updateApi(StoreUserCertificateRequest $request, User $user, UserCertificate $userCertificate): JsonResponse
    {
        abort_unless((int) $userCertificate->user_id === (int) $user->getKey(), 404);

        $validated = $request->validated();

        $certificate = DB::transaction(fn (): UserCertificate => $this->persistCertificate(
            $userCertificate,
            $validated,
        ));

        return response()->json([
            'success' => true,
            'message' => __('Certificato aggiornato con successo.'),
            'data' => [
                'id' => $certificate->getKey(),
                'name' => $certificate->name,
            ],
        ]);
    }

    public function destroyApi(User $user, UserCertificate $userCertificate): JsonResponse
    {
        abort_unless((int) $userCertificate->user_id === (int) $user->getKey(), 404);

        $userCertificate->delete();

        return response()->json([
            'success' => true,
            'message' => __('Certificato eliminato con successo.'),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persistCertificate(UserCertificate $certificate, array $validated): UserCertificate
    {
        $certificate->fill([
            'internal_course_id' => $validated['internal_course_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'file_path' => $validated['file_path'] ?? null,
            'is_internal' => filled($validated['internal_course_id'] ?? null),
            'issued_at' => $validated['issued_at'],
            'expires_at' => $validated['expires_at'] ?? null,
        ]);
        $certificate->save();
        $certificate->requirements()->sync($validated['requirements'] ?? []);

        return $certificate->load(['requirements:id,name', 'internalCourse:id,title']);
    }
}
