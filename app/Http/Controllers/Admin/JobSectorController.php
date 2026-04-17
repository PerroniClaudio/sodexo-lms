<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobSectorRequest;
use App\Http\Requests\UpdateJobSectorRequest;
use App\Models\JobSector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobSectorController extends Controller
{
    public function index(Request $request): View
    {
        $allowedSorts = ['id', 'name', 'code', 'is_active'];
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'id';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());

        return view('admin.job-sector.index', [
            'sectors' => JobSector::query()
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
                })
                ->orderBy($sort, $direction)
                ->paginate(20)
                ->withQueryString(),
            'tableSort' => $sort,
            'tableDirection' => $direction,
            'tableSearch' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.job-sector.create');
    }

    public function store(StoreJobSectorRequest $request): RedirectResponse
    {
        $sector = JobSector::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.job-sectors.edit', $sector)
            ->with('status', __('Settore creato con successo.'));
    }

    public function edit(JobSector $jobSector): View
    {
        return view('admin.job-sector.edit', [
            'sector' => $jobSector,
        ]);
    }

    public function update(UpdateJobSectorRequest $request, JobSector $jobSector): RedirectResponse
    {
        $jobSector->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', false),
        ]);

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
}
