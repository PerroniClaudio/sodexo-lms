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
        $allowedSorts = ['id', 'name', 'code', 'is_active'];
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'id';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());

        return view('admin.job-role.index', [
            'roles' => JobRole::query()
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
        return view('admin.job-role.create');
    }

    public function store(StoreJobRoleRequest $request): RedirectResponse
    {
        $role = JobRole::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

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
        $jobRole->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', false),
        ]);

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
}
