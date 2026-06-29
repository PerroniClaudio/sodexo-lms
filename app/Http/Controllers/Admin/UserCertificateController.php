<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserCertificateRequest;
use App\Models\User;
use App\Models\UserCertificate;
use App\Models\UserCertificateFile;
use App\Support\CloudStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                'riskBasedRequirements:id,name',
                'internalCourse:id,title',
                'documentType',
                'latestActiveFile',
            ]);
        $query->withCount([
            'files as active_files_count',
            'allFiles as total_files_count',
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
                    'document_type_id' => $certificate->document_type_id,
                    'document_type_name' => $certificate->documentType?->name,
                    'document_type_is_deleted' => $certificate->documentType?->trashed() ?? false,
                    'internal_course_id' => $certificate->internal_course_id,
                    'issued_at' => $certificate->issued_at?->format('d/m/Y'),
                    'issued_at_iso' => $certificate->issued_at?->toDateString(),
                    'expires_at' => $certificate->expires_at?->format('d/m/Y'),
                    'expires_at_iso' => $certificate->expires_at?->toDateString(),
                    'is_internal' => $certificate->is_internal,
                    'type_label' => $certificate->is_internal ? __('Interno') : __('Esterno'),
                    'internal_course' => $certificate->internalCourse?->title,
                    'active_files_count' => (int) ($certificate->active_files_count ?? 0),
                    'total_files_count' => (int) ($certificate->total_files_count ?? 0),
                    'latest_active_file' => $certificate->latestActiveFile === null
                        ? null
                        : $this->serializeCertificateFile($user, $certificate, $certificate->latestActiveFile),
                    'risk_based_requirements' => $certificate->riskBasedRequirements
                        ->map(fn ($riskBasedRequirement): array => [
                            'id' => $riskBasedRequirement->getKey(),
                            'name' => $riskBasedRequirement->name,
                        ])
                        ->values()
                        ->all(),
                    'actions' => [
                        'update_url' => route('admin.api.users.certificates.update', [$user, $certificate]),
                        'delete_url' => route('admin.api.users.certificates.destroy', [$user, $certificate]),
                        'files_index_url' => route('admin.api.users.certificates.files.index', [$user, $certificate]),
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

    public function filesIndexApi(Request $request, User $user, UserCertificate $userCertificate): JsonResponse
    {
        abort_unless((int) $userCertificate->user_id === (int) $user->getKey(), 404);

        $showDeletedFiles = $request->boolean('show_deleted_files');
        $filesQuery = $showDeletedFiles
            ? $userCertificate->allFiles()
            : $userCertificate->files();

        $files = $filesQuery
            ->latest('id')
            ->get()
            ->map(fn (UserCertificateFile $file): array => $this->serializeCertificateFile($user, $userCertificate, $file))
            ->values()
            ->all();

        return response()->json([
            'data' => $files,
            'meta' => [
                'show_deleted_files' => $showDeletedFiles,
                'active_files_count' => $userCertificate->files()->count(),
                'total_files_count' => $userCertificate->allFiles()->count(),
            ],
        ]);
    }

    public function storeApi(StoreUserCertificateRequest $request, User $user): JsonResponse
    {
        $validated = $request->validated();

        $certificate = DB::transaction(fn (): UserCertificate => $this->persistCertificate(
            $user->userCertificates()->make(),
            $validated,
            (int) $request->user()->getAuthIdentifier(),
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
            (int) $request->user()->getAuthIdentifier(),
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

    public function deleteFileApi(
        User $user,
        UserCertificate $userCertificate,
        UserCertificateFile $userCertificateFile,
    ): JsonResponse {
        $this->abortUnlessCertificateFileBelongsToUser($user, $userCertificate, $userCertificateFile);

        if (! $userCertificateFile->trashed()) {
            $userCertificateFile->delete();
        }

        return response()->json([
            'success' => true,
            'message' => __('File certificato eliminato con successo.'),
        ]);
    }

    public function previewFileApi(
        User $user,
        UserCertificate $userCertificate,
        UserCertificateFile $userCertificateFile,
    ): StreamedResponse {
        $this->abortUnlessCertificateFileBelongsToUser($user, $userCertificate, $userCertificateFile);

        abort_unless(Storage::disk($userCertificateFile->disk)->exists($userCertificateFile->path), Response::HTTP_NOT_FOUND);

        return Storage::disk($userCertificateFile->disk)->response(
            $userCertificateFile->path,
            $userCertificateFile->original_name,
            [
                'Content-Type' => $userCertificateFile->mime_type ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$userCertificateFile->original_name.'"',
            ],
        );
    }

    public function downloadFileApi(
        User $user,
        UserCertificate $userCertificate,
        UserCertificateFile $userCertificateFile,
    ): StreamedResponse {
        $this->abortUnlessCertificateFileBelongsToUser($user, $userCertificate, $userCertificateFile);

        abort_unless(Storage::disk($userCertificateFile->disk)->exists($userCertificateFile->path), Response::HTTP_NOT_FOUND);

        return Storage::disk($userCertificateFile->disk)->download(
            $userCertificateFile->path,
            $userCertificateFile->original_name,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function persistCertificate(UserCertificate $certificate, array $validated, int $uploadedByUserId): UserCertificate
    {
        $certificate->fill([
            'internal_course_id' => $validated['internal_course_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'document_type_id' => $validated['document_type_id'] ?? null,
            'is_internal' => filled($validated['internal_course_id'] ?? null),
            'issued_at' => $validated['issued_at'],
            'expires_at' => $validated['expires_at'] ?? null,
        ]);
        $certificate->save();
        $certificate->riskBasedRequirements()->sync($validated['risk_based_requirement_ids'] ?? []);

        if (! empty($validated['files']) && is_array($validated['files'])) {
            $this->replaceCertificateFiles($certificate, $validated['files'], $uploadedByUserId);
        }

        return $certificate->load(['riskBasedRequirements:id,name', 'internalCourse:id,title', 'documentType', 'latestActiveFile']);
    }

    /**
     * @param  array<int, UploadedFile>  $uploadedFiles
     */
    private function replaceCertificateFiles(UserCertificate $certificate, array $uploadedFiles, int $uploadedByUserId): void
    {
        $certificate->files()->update([
            'deleted_at' => now(),
        ]);

        foreach ($uploadedFiles as $uploadedFile) {
            $storedPath = $uploadedFile->store(
                sprintf('users/%d/certificates/file', $certificate->user_id),
                CloudStorage::disk(),
            );

            $certificate->allFiles()->create([
                'uploaded_by' => $uploadedByUserId,
                'disk' => CloudStorage::disk(),
                'path' => $storedPath,
                'original_name' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploadedFile->getClientMimeType(),
                'size' => $uploadedFile->getSize(),
            ]);
        }
    }

    private function abortUnlessCertificateFileBelongsToUser(
        User $user,
        UserCertificate $userCertificate,
        UserCertificateFile $userCertificateFile,
    ): void {
        abort_unless((int) $userCertificate->user_id === (int) $user->getKey(), 404);
        abort_unless((int) $userCertificateFile->user_certificate_id === (int) $userCertificate->getKey(), 404);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCertificateFile(
        User $user,
        UserCertificate $userCertificate,
        UserCertificateFile $userCertificateFile,
    ): array {
        return [
            'id' => $userCertificateFile->getKey(),
            'original_name' => $userCertificateFile->original_name,
            'mime_type' => $userCertificateFile->mime_type,
            'size' => $userCertificateFile->size,
            'size_label' => $this->formatBytes($userCertificateFile->size),
            'uploaded_at' => $userCertificateFile->created_at?->format('d/m/Y H:i'),
            'deleted_at' => $userCertificateFile->deleted_at?->format('d/m/Y H:i'),
            'is_deleted' => $userCertificateFile->trashed(),
            'actions' => [
                'preview_url' => route('admin.api.users.certificates.files.preview', [$user, $userCertificate, $userCertificateFile]),
                'download_url' => route('admin.api.users.certificates.files.download', [$user, $userCertificate, $userCertificateFile]),
                'delete_url' => route('admin.api.users.certificates.files.delete', [$user, $userCertificate, $userCertificateFile]),
            ],
        ];
    }

    private function formatBytes(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $kilobytes = $bytes / 1024;

        if ($kilobytes < 1024) {
            return number_format($kilobytes, 1, ',', '.').' KB';
        }

        return number_format($kilobytes / 1024, 1, ',', '.').' MB';
    }
}
