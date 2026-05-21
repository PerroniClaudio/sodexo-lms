<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobTitleRequest;
use App\Http\Requests\UpdateJobTitleRequest;
use App\Models\JobSector;
use App\Models\JobTitle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobTitleController extends Controller
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

        return view('admin.job-title.index', [
            'titles' => JobTitle::query()
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
        return view('admin.job-title.create');
    }

    public function store(StoreJobTitleRequest $request): RedirectResponse
    {
        $title = JobTitle::query()->create($request->validated());

        return redirect()
            ->route('admin.job-titles.edit', $title)
            ->with('status', __('Mansione creata con successo.'));
    }

    public function edit(JobTitle $jobTitle): View
    {
        $jobTitle->load('jobSectors');

        // Get all sectors for the dropdown
        $allSectors = JobSector::orderBy('name')->get();

        return view('admin.job-title.edit', [
            'title' => $jobTitle,
            'allSectors' => $allSectors,
        ]);
    }

    public function update(UpdateJobTitleRequest $request, JobTitle $jobTitle): RedirectResponse
    {
        $jobTitle->update($request->validated());

        return redirect()
            ->route('admin.job-titles.edit', $jobTitle)
            ->with('status', __('Mansione aggiornata con successo.'));
    }

    public function destroy(JobTitle $jobTitle): RedirectResponse
    {
        $jobTitle->delete();

        return redirect()
            ->route('admin.job-titles.index')
            ->with('status', __('Mansione eliminata con successo.'));
    }

    public function restore($id): RedirectResponse
    {
        $title = JobTitle::withTrashed()->findOrFail($id);
        $title->restore();

        return redirect()
            ->route('admin.job-titles.index')
            ->with('status', __('Mansione ripristinata con successo.'));
    }

    /**
     * Attach a sector to the job title with a specific risk level
     */
    public function attachSector(Request $request, JobTitle $jobTitle): RedirectResponse
    {
        $validated = $request->validate([
            'job_sector_id' => ['required', 'exists:job_sectors,id'],
            'title_risk_level' => ['required', 'in:low,medium,high'],
        ]);

        // Check if already attached
        if ($jobTitle->jobSectors()->where('job_sector_id', $validated['job_sector_id'])->exists()) {
            return redirect()
                ->route('admin.job-titles.edit', $jobTitle)
                ->with('error', __('Questo settore è già associato a questa mansione.'));
        }

        $jobTitle->jobSectors()->attach($validated['job_sector_id'], [
            'title_risk_level' => $validated['title_risk_level'],
        ]);

        return redirect()
            ->route('admin.job-titles.edit', $jobTitle)
            ->with('status', __('Settore associato con successo.'));
    }

    /**
     * Detach a sector from the job title
     */
    public function detachSector(JobTitle $jobTitle, JobSector $jobSector): RedirectResponse
    {
        $jobTitle->jobSectors()->detach($jobSector->id);

        return redirect()
            ->route('admin.job-titles.edit', $jobTitle)
            ->with('status', __('Settore rimosso con successo.'));
    }

    /**
     * Update the risk level for a sector association
     */
    public function updateSectorRisk(Request $request, JobTitle $jobTitle, JobSector $jobSector): RedirectResponse
    {
        $validated = $request->validate([
            'title_risk_level' => ['required', 'in:low,medium,high'],
        ]);

        $jobTitle->jobSectors()->updateExistingPivot($jobSector->id, [
            'title_risk_level' => $validated['title_risk_level'],
        ]);

        return redirect()
            ->route('admin.job-titles.edit', $jobTitle)
            ->with('status', __('Rischio aggiornato con successo.'));
    }
}
