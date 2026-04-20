<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobRoleRequest;
use App\Http\Requests\UpdateJobRoleRequest;
use App\Models\JobRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobRoleController extends Controller
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

        return view('admin.job-role.index', [
            'roles' => JobRole::query()
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
        return view('admin.job-role.create');
    }

    public function store(StoreJobRoleRequest $request): RedirectResponse
    {
        $role = JobRole::query()->create($request->validated());

        return redirect()
            ->route('admin.job-roles.edit', $role)
            ->with('status', __('Ruolo creato con successo.'));
    }

    public function edit(JobRole $jobRole): View
    {
        return view('admin.job-role.edit', [
            'role' => $jobRole,
        ]);
    }

    public function update(UpdateJobRoleRequest $request, JobRole $jobRole): RedirectResponse
    {
        $jobRole->update($request->validated());

        return redirect()
            ->route('admin.job-roles.edit', $jobRole)
            ->with('status', __('Ruolo aggiornato con successo.'));
    }

    public function destroy(JobRole $jobRole): RedirectResponse
    {
        $jobRole->delete();

        return redirect()
            ->route('admin.job-roles.index')
            ->with('status', __('Ruolo eliminato con successo.'));
    }

    public function restore($id): RedirectResponse
    {
        $role = JobRole::withTrashed()->findOrFail($id);
        $role->restore();

        return redirect()
            ->route('admin.job-roles.index')
            ->with('status', __('Ruolo ripristinato con successo.'));
    }
}
