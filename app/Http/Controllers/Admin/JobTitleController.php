<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobTitleRequest;
use App\Http\Requests\UpdateJobTitleRequest;
use App\Models\JobTitle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobTitleController extends Controller
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

        return view('admin.job-title.index', [
            'titles' => JobTitle::query()
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
        return view('admin.job-title.edit', [
            'title' => $jobTitle,
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
}
