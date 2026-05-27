<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RiskLevel;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRiskBasedRequirementRequest;
use App\Http\Requests\UpdateRiskBasedRequirementRequest;
use App\Models\RiskBasedRequirement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RiskBasedRequirementController extends Controller
{
    public function index(Request $request): View
    {
        $allowedSorts = ['id', 'name', 'is_limited_validity', 'validity_months'];
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'id';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());
        $showTrashed = $request->boolean('show_trashed');

        return view('admin.risk-based-requirement.index', [
            'riskBasedRequirements' => RiskBasedRequirement::query()
                ->when($showTrashed, function ($query) {
                    $query->withTrashed();
                })
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%");
                    });
                })
                ->orderBy($sort, $direction)
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
        return view('admin.risk-based-requirement.create', [
            'riskLevels' => RiskLevel::cases(),
        ]);
    }

    public function store(StoreRiskBasedRequirementRequest $request): RedirectResponse
    {
        $riskBasedRequirement = RiskBasedRequirement::create($request->validated());

        return redirect()
            ->route('admin.risk-based-requirements.edit', $riskBasedRequirement)
            ->with('status', __('Requisito di rischio creato con successo.'));
    }

    public function edit(RiskBasedRequirement $riskBasedRequirement): View
    {
        return view('admin.risk-based-requirement.edit', [
            'riskBasedRequirement' => $riskBasedRequirement,
            'riskLevels' => RiskLevel::cases(),
        ]);
    }

    public function update(UpdateRiskBasedRequirementRequest $request, RiskBasedRequirement $riskBasedRequirement): RedirectResponse
    {
        $riskBasedRequirement->update($request->validated());

        return redirect()
            ->route('admin.risk-based-requirements.edit', $riskBasedRequirement)
            ->with('status', __('Requisito di rischio aggiornato con successo.'));
    }

    public function destroy(RiskBasedRequirement $riskBasedRequirement): RedirectResponse
    {
        $riskBasedRequirement->delete();

        return redirect()
            ->route('admin.risk-based-requirements.index')
            ->with('status', __('Requisito di rischio eliminato con successo.'));
    }

    public function restore($id): RedirectResponse
    {
        $riskBasedRequirement = RiskBasedRequirement::withTrashed()->findOrFail($id);
        $riskBasedRequirement->restore();

        return redirect()
            ->route('admin.risk-based-requirements.index')
            ->with('status', __('Requisito di rischio ripristinato con successo.'));
    }
}
