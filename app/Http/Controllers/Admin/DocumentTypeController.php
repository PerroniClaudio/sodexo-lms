<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentTypeRequest;
use App\Http\Requests\UpdateDocumentTypeRequest;
use App\Models\DocumentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.document-type.index', [
            'tableSort' => $this->resolvedSort($request),
            'tableDirection' => $this->resolvedDirection($request),
            'showTrashed' => $request->boolean('show_trashed'),
            'tableSearch' => trim($request->string('search')->toString()),
        ]);
    }

    public function indexApi(Request $request): JsonResponse
    {
        $search = trim($request->string('search')->toString());
        $sort = $this->resolvedSort($request);
        $direction = $this->resolvedDirection($request);
        $showTrashed = $request->boolean('show_trashed');

        $documentTypes = DocumentType::query()
            ->when($showTrashed, fn ($query) => $query->withTrashed())
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($documentTypeQuery) use ($search): void {
                    $documentTypeQuery
                        ->where('id', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
                });
            });

        $this->applySorting($documentTypes, $sort, $direction);

        $paginatedDocumentTypes = $documentTypes
            ->paginate(10)
            ->through(fn (DocumentType $documentType): array => [
                'id' => $documentType->getKey(),
                'name' => $documentType->name,
                'description' => $documentType->description,
                'status_label' => $documentType->trashed() ? __('Eliminata') : __('Attiva'),
                'status_badge_class' => $documentType->trashed()
                    ? 'badge-outline badge-error'
                    : 'badge-outline badge-success',
                'is_deleted' => $documentType->trashed(),
                'actions' => [
                    'edit_url' => route('admin.document-types.edit', $documentType),
                    'delete_url' => route('admin.api.document-types.destroy', $documentType),
                    'restore_url' => route('admin.api.document-types.restore', $documentType->getKey()),
                ],
            ]);

        return response()->json([
            'data' => $paginatedDocumentTypes->items(),
            'meta' => [
                'current_page' => $paginatedDocumentTypes->currentPage(),
                'last_page' => $paginatedDocumentTypes->lastPage(),
                'per_page' => $paginatedDocumentTypes->perPage(),
                'total' => $paginatedDocumentTypes->total(),
                'from' => $paginatedDocumentTypes->firstItem(),
                'to' => $paginatedDocumentTypes->lastItem(),
            ],
            'query' => [
                'search' => $search,
                'sort' => $sort,
                'direction' => $direction,
                'show_trashed' => $showTrashed,
            ],
        ]);
    }

    public function create(): View
    {
        return view('admin.document-type.create');
    }

    public function store(StoreDocumentTypeRequest $request): RedirectResponse
    {
        $documentType = DocumentType::query()->create($request->validated());

        return redirect()
            ->route('admin.document-types.edit', $documentType)
            ->with('status', __('Tipologia documento creata con successo.'));
    }

    public function edit(DocumentType $documentType): View
    {
        return view('admin.document-type.edit', [
            'documentType' => $documentType,
        ]);
    }

    public function update(UpdateDocumentTypeRequest $request, DocumentType $documentType): RedirectResponse
    {
        $documentType->update($request->validated());

        return redirect()
            ->route('admin.document-types.edit', $documentType)
            ->with('status', __('Tipologia documento aggiornata con successo.'));
    }

    public function destroy(DocumentType $documentType): RedirectResponse
    {
        $documentType->delete();

        return redirect()
            ->route('admin.document-types.index')
            ->with('status', __('Tipologia documento eliminata con successo.'));
    }

    public function restore(int $id): RedirectResponse
    {
        $documentType = DocumentType::withTrashed()->findOrFail($id);
        $documentType->restore();

        return redirect()
            ->route('admin.document-types.index')
            ->with('status', __('Tipologia documento ripristinata con successo.'));
    }

    public function destroyApi(DocumentType $documentType): JsonResponse
    {
        $documentType->delete();

        return response()->json([
            'success' => true,
            'message' => __('Tipologia documento eliminata con successo.'),
        ]);
    }

    public function restoreApi(int $id): JsonResponse
    {
        $documentType = DocumentType::withTrashed()->findOrFail($id);
        $documentType->restore();

        return response()->json([
            'success' => true,
            'message' => __('Tipologia documento ripristinata con successo.'),
        ]);
    }

    private function resolvedSort(Request $request): string
    {
        $allowedSorts = ['id', 'name', 'status', 'description'];
        $requestedSort = $request->string('sort')->toString();

        return in_array($requestedSort, $allowedSorts, true) ? $requestedSort : 'id';
    }

    private function resolvedDirection(Request $request): string
    {
        return $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
    }

    private function applySorting($query, string $sort, string $direction): void
    {
        if ($sort === 'status') {
            if ($direction === 'asc') {
                $query->orderByRaw('deleted_at IS NOT NULL ASC, deleted_at ASC');
            } else {
                $query->orderByRaw('deleted_at IS NULL ASC, deleted_at DESC');
            }

            $query->orderByDesc('id');

            return;
        }

        $query
            ->orderBy($sort, $direction)
            ->orderByDesc('id');
    }
}
