<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobSectorRequest;
use App\Http\Requests\UpdateJobSectorRequest;
use App\Models\JobSector;
use App\Models\NaceAteco;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobSectorController extends Controller
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

        return view('admin.job-sector.index', [
            'sectors' => JobSector::query()
                ->select(['id', 'name', 'deleted_at'])
                ->when($showTrashed, function ($query) {
                    $query->withTrashed();
                })
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                })
                ->when($sort === 'status', function ($query) use ($direction) {
                    // Per status: ordina per deleted_at IS NULL (attivi prima) poi per deleted_at
                    if ($direction === 'asc') {
                        $query->orderByRaw('deleted_at IS NOT NULL ASC, deleted_at ASC');
                    } else {
                        $query->orderByRaw('deleted_at IS NULL ASC, deleted_at DESC');
                    }
                }, function ($query) use ($sort, $direction) {
                    $query->orderBy($sort, $direction);
                })
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
        return view('admin.job-sector.create');
    }

    public function store(StoreJobSectorRequest $request): RedirectResponse
    {
        $sector = JobSector::query()->create($request->validated());

        return redirect()
            ->route('admin.job-sectors.edit', $sector)
            ->with('status', __('Settore creato con successo.'));
    }

    public function edit(JobSector $jobSector): View
    {
        $jobSector->load('naceAtecoCodes');

        // Get all ATECO codes ordered by hierarchy (1-6), then by code
        $allAtecoCodes = NaceAteco::orderBy('hierarchy')->orderBy('code')->get()->groupBy('hierarchy');

        return view('admin.job-sector.edit', [
            'sector' => $jobSector,
            'allAtecoCodes' => $allAtecoCodes,
        ]);
    }

    public function update(UpdateJobSectorRequest $request, JobSector $jobSector): RedirectResponse
    {
        $jobSector->update($request->validated());

        return redirect()
            ->route('admin.job-sectors.edit', $jobSector)
            ->with('status', __('Settore aggiornato con successo.'));
    }

    public function destroy(JobSector $jobSector): RedirectResponse
    {
        $jobSector->delete();

        return redirect()
            ->route('admin.job-sectors.index')
            ->with('status', __('Settore eliminato con successo.'));
    }

    public function restore($id): RedirectResponse
    {
        $sector = JobSector::withTrashed()->findOrFail($id);
        $sector->restore();

        return redirect()
            ->route('admin.job-sectors.index')
            ->with('status', __('Settore ripristinato con successo.'));
    }

    /**
     * Attach an ATECO code to the sector
     */
    public function attachAtecoCode(Request $request, JobSector $jobSector): RedirectResponse
    {
        $validated = $request->validate([
            'nace_ateco_code' => ['required', 'exists:nace_ateco,code'],
            'inclusion_type' => ['required', 'in:section,division,group,class,category,full_code'],
        ]);

        // Check if already attached
        if ($jobSector->naceAtecoCodes()->where('nace_ateco_code', $validated['nace_ateco_code'])->exists()) {
            return redirect()
                ->route('admin.job-sectors.edit', $jobSector)
                ->with('error', __('Questo codice ATECO è già associato a questo settore.'));
        }

        $jobSector->naceAtecoCodes()->attach($validated['nace_ateco_code'], [
            'inclusion_type' => $validated['inclusion_type'],
        ]);

        return redirect()
            ->route('admin.job-sectors.edit', $jobSector)
            ->with('status', __('Codice ATECO associato con successo.'));
    }

    /**
     * Get the calculated risk level for the sector (API endpoint)
     */
    public function getRiskLevel(JobSector $jobSector)
    {
        $riskLevel = $jobSector->getRiskLevel();

        return response()->json([
            'risk' => $riskLevel ? $riskLevel->value : null,
            'label' => $riskLevel ? $riskLevel->label() : 'N/D',
            'badgeColor' => $riskLevel ? $riskLevel->badgeColor() : 'badge-ghost',
        ]);
    }

    /**
     * Detach an ATECO code from the sector
     */
    public function detachAtecoCode(JobSector $jobSector, string $atecoCode): RedirectResponse
    {
        $jobSector->naceAtecoCodes()->detach($atecoCode);

        return redirect()
            ->route('admin.job-sectors.edit', $jobSector)
            ->with('status', __('Codice ATECO rimosso con successo.'));
    }
}
