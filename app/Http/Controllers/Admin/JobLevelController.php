<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobLevelRequest;
use App\Http\Requests\UpdateJobLevelRequest;
use App\Models\JobLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobLevelController extends Controller
{
    public function index(Request $request): View
    {
        $allowedSorts = ['id', 'name', 'code', 'status'];
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'id';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());
        $showTrashed = $request->boolean('show_trashed');

        return view('admin.job-level.index', [
            'levels' => JobLevel::query()
                ->when($showTrashed, function ($query) {
                    $query->withTrashed();
                })
                ->when($search !== '', function ($query) use ($search) {
                    $query->where(function ($query) use ($search) {
                        $query
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
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
        return view('admin.job-level.create');
    }

    public function store(StoreJobLevelRequest $request): RedirectResponse
    {
        $level = JobLevel::query()->create($request->validated());

        return redirect()
            ->route('admin.job-levels.edit', $level)
            ->with('status', __('Livello di inquadramento creato con successo.'));
    }

    public function edit(JobLevel $jobLevel): View
    {
        return view('admin.job-level.edit', [
            'level' => $jobLevel,
        ]);
    }

    public function update(UpdateJobLevelRequest $request, JobLevel $jobLevel): RedirectResponse
    {
        $jobLevel->update($request->validated());

        return redirect()
            ->route('admin.job-levels.edit', $jobLevel)
            ->with('status', __('Livello di inquadramento aggiornato con successo.'));
    }

    public function destroy(JobLevel $jobLevel): RedirectResponse
    {
        $jobLevel->delete();

        return redirect()
            ->route('admin.job-levels.index')
            ->with('status', __('Livello di inquadramento eliminato con successo.'));
    }

    public function restore($id): RedirectResponse
    {
        $level = JobLevel::withTrashed()->findOrFail($id);
        $level->restore();

        return redirect()
            ->route('admin.job-levels.index')
            ->with('status', __('Livello di inquadramento ripristinato con successo.'));
    }
}
