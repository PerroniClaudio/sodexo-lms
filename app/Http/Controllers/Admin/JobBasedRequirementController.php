<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreJobBasedRequirementRequest;
use App\Http\Requests\Admin\UpdateJobBasedRequirementRequest;
use App\Models\JobBasedRequirement;
use App\Models\JobRole;
use App\Models\JobTask;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobBasedRequirementController extends Controller
{
    public function index(Request $request): View
    {
        $allowedSorts = ['id', 'name', 'is_active', 'updated_at'];
        $requestedSort = $request->string('sort')->toString();
        $hasValidSort = in_array($requestedSort, $allowedSorts, true);
        $sort = $hasValidSort ? $requestedSort : 'updated_at';
        $direction = $hasValidSort
            ? ($request->string('direction')->toString() === 'asc' ? 'asc' : 'desc')
            : 'desc';
        $search = trim($request->string('search')->toString());
        $showTrashed = $request->boolean('show_trashed');

        return view('admin.job-based-requirement.index', [
            'jobBasedRequirements' => JobBasedRequirement::query()
                ->when($showTrashed, fn ($query) => $query->withTrashed())
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($query) use ($search): void {
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
            'tableSearch' => $search,
            'showTrashed' => $showTrashed,
        ]);
    }

    public function create(): View
    {
        return view('admin.job-based-requirement.create', [
            ...$this->formData(),
            'jobBasedRequirement' => new JobBasedRequirement,
        ]);
    }

    public function store(StoreJobBasedRequirementRequest $request): RedirectResponse
    {
        $jobBasedRequirement = JobBasedRequirement::create($request->validatedPayload());

        return redirect()
            ->route('admin.job-based-requirements.edit', $jobBasedRequirement)
            ->with('status', __('Requisito ruolo/mansione creato con successo.'));
    }

    public function edit(JobBasedRequirement $jobBasedRequirement): View
    {
        return view('admin.job-based-requirement.edit', [
            ...$this->formData(),
            'jobBasedRequirement' => $jobBasedRequirement,
        ]);
    }

    public function update(UpdateJobBasedRequirementRequest $request, JobBasedRequirement $jobBasedRequirement): RedirectResponse
    {
        $jobBasedRequirement->update($request->validatedPayload());

        return redirect()
            ->route('admin.job-based-requirements.edit', $jobBasedRequirement)
            ->with('status', __('Requisito ruolo/mansione aggiornato con successo.'));
    }

    public function destroy(JobBasedRequirement $jobBasedRequirement): RedirectResponse
    {
        $jobBasedRequirement->delete();

        return redirect()
            ->route('admin.job-based-requirements.index')
            ->with('status', __('Requisito ruolo/mansione eliminato con successo.'));
    }

    public function restore(int $id): RedirectResponse
    {
        $jobBasedRequirement = JobBasedRequirement::withTrashed()->findOrFail($id);
        $jobBasedRequirement->restore();

        return redirect()
            ->route('admin.job-based-requirements.index')
            ->with('status', __('Requisito ruolo/mansione ripristinato con successo.'));
    }

    /**
     * @return array{jobRoles: Collection<int, JobRole>, jobTasks: Collection<int, JobTask>}
     */
    private function formData(): array
    {
        return [
            'jobRoles' => JobRole::query()->orderBy('name')->get(['id', 'name']),
            'jobTasks' => JobTask::query()->orderBy('name')->get(['id', 'name']),
        ];
    }
}
