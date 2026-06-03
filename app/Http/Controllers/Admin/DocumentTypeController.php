<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDocumentTypeRequest;
use App\Http\Requests\UpdateDocumentTypeRequest;
use App\Models\DocumentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentTypeController extends Controller
{
    public function index(Request $request): View
    {
        $allowedSorts = ['id', 'name', 'status'];
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'id';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());
        $showTrashed = $request->boolean('show_trashed');

        return view('admin.document-type.index', [
            'documentTypes' => DocumentType::query()
                ->when($showTrashed, fn ($query) => $query->withTrashed())
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                })
                ->when($sort === 'status', function ($query) use ($direction) {
                    if ($direction === 'asc') {
                        $query->orderByRaw('deleted_at IS NOT NULL ASC, deleted_at ASC');
                    } else {
                        $query->orderByRaw('deleted_at IS NULL ASC, deleted_at DESC');
                    }
                }, fn ($query) => $query->orderBy($sort, $direction))
                ->paginate(10)
                ->withQueryString(),
            'tableSort' => $sort,
            'tableDirection' => $direction,
            'showTrashed' => $showTrashed,
            'tableSearch' => $search,
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
}
