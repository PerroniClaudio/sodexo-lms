<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFundingEntityRequest;
use App\Http\Requests\UpdateFundingEntityRequest;
use App\Models\FundingEntity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FundingEntityController extends Controller
{
    public function index(Request $request): View
    {
        $allowedSorts = ['id', 'company_name', 'vat_number', 'fiscal_code', 'pec', 'status'];
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'id';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());
        $showTrashed = $request->boolean('show_trashed');

        return view('admin.funding-entity.index', [
            'fundingEntities' => FundingEntity::query()
                ->when($showTrashed, fn ($query) => $query->withTrashed())
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($fundingEntityQuery) use ($search): void {
                        $fundingEntityQuery
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('company_name', 'like', "%{$search}%")
                            ->orWhere('vat_number', 'like', "%{$search}%")
                            ->orWhere('fiscal_code', 'like', "%{$search}%")
                            ->orWhere('pec', 'like', "%{$search}%");
                    });
                })
                ->when($sort === 'status', function ($query) use ($direction): void {
                    if ($direction === 'asc') {
                        $query->orderByRaw('deleted_at IS NOT NULL ASC, deleted_at ASC');
                    } else {
                        $query->orderByRaw('deleted_at IS NULL ASC, deleted_at DESC');
                    }
                }, function ($query) use ($sort, $direction): void {
                    $query->orderBy($sort, $direction);
                })
                ->paginate(10)
                ->withQueryString(),
            'tableSort' => $sort,
            'tableDirection' => $direction,
            'tableSearch' => $search,
            'showTrashed' => $showTrashed,
        ]);
    }

    public function create(): View
    {
        return view('admin.funding-entity.create');
    }

    public function store(StoreFundingEntityRequest $request): RedirectResponse
    {
        $fundingEntity = FundingEntity::query()->create($request->validated());

        return redirect()
            ->route('admin.funding-entities.edit', $fundingEntity)
            ->with('status', __('Ente finanziatore creato con successo.'));
    }

    public function edit(FundingEntity $fundingEntity): View
    {
        return view('admin.funding-entity.edit', [
            'fundingEntity' => $fundingEntity,
        ]);
    }

    public function update(UpdateFundingEntityRequest $request, FundingEntity $fundingEntity): RedirectResponse
    {
        $fundingEntity->update($request->validated());

        return redirect()
            ->route('admin.funding-entities.edit', $fundingEntity)
            ->with('status', __('Ente finanziatore aggiornato con successo.'));
    }

    public function destroy(FundingEntity $fundingEntity): RedirectResponse
    {
        $fundingEntity->delete();

        return redirect()
            ->route('admin.funding-entities.index')
            ->with('status', __('Ente finanziatore eliminato con successo.'));
    }

    public function restore(int $id): RedirectResponse
    {
        $fundingEntity = FundingEntity::withTrashed()->findOrFail($id);
        $fundingEntity->restore();

        return redirect()
            ->route('admin.funding-entities.index')
            ->with('status', __('Ente finanziatore ripristinato con successo.'));
    }
}
