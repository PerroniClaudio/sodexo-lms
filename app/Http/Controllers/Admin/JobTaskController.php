<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobTaskRequest;
use App\Http\Requests\UpdateJobTaskRequest;
use App\Models\JobSector;
use App\Models\JobTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobTaskController extends Controller
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

        return view('admin.job-task.index', [
            'tasks' => JobTask::query()
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
        return view('admin.job-task.create');
    }

    public function store(StoreJobTaskRequest $request): RedirectResponse
    {
        $task = JobTask::query()->create($request->validated());

        return redirect()
            ->route('admin.job-tasks.edit', $task)
            ->with('status', __('Mansione creata con successo.'));
    }

    public function edit(JobTask $jobTask): View
    {
        $jobTask->load('jobSectors');

        return view('admin.job-task.edit', [
            'task' => $jobTask,
            'allSectors' => JobSector::query()->orderBy('name')->get(),
        ]);
    }

    public function update(UpdateJobTaskRequest $request, JobTask $jobTask): RedirectResponse
    {
        $jobTask->update($request->validated());

        return redirect()
            ->route('admin.job-tasks.edit', $jobTask)
            ->with('status', __('Mansione aggiornata con successo.'));
    }

    public function destroy(JobTask $jobTask): RedirectResponse
    {
        $jobTask->delete();

        return redirect()
            ->route('admin.job-tasks.index')
            ->with('status', __('Mansione eliminata con successo.'));
    }

    public function restore($id): RedirectResponse
    {
        $task = JobTask::withTrashed()->findOrFail($id);
        $task->restore();

        return redirect()
            ->route('admin.job-tasks.index')
            ->with('status', __('Mansione ripristinata con successo.'));
    }

    public function attachSector(Request $request, JobTask $jobTask): RedirectResponse
    {
        $validated = $request->validate([
            'job_sector_id' => ['required', 'exists:job_sectors,id'],
            'task_risk_level' => ['required', 'in:low,medium,high'],
        ]);

        if ($jobTask->jobSectors()->where('job_sector_id', $validated['job_sector_id'])->exists()) {
            return redirect()
                ->route('admin.job-tasks.edit', $jobTask)
                ->with('error', __('Questo settore è già associato a questa mansione.'));
        }

        $jobTask->jobSectors()->attach($validated['job_sector_id'], [
            'task_risk_level' => $validated['task_risk_level'],
        ]);

        return redirect()
            ->route('admin.job-tasks.edit', $jobTask)
            ->with('status', __('Settore associato con successo.'));
    }

    public function detachSector(JobTask $jobTask, JobSector $jobSector): RedirectResponse
    {
        $jobTask->jobSectors()->detach($jobSector->id);

        return redirect()
            ->route('admin.job-tasks.edit', $jobTask)
            ->with('status', __('Settore rimosso con successo.'));
    }

    public function updateSectorRisk(Request $request, JobTask $jobTask, JobSector $jobSector): RedirectResponse
    {
        $validated = $request->validate([
            'task_risk_level' => ['required', 'in:low,medium,high'],
        ]);

        $jobTask->jobSectors()->updateExistingPivot($jobSector->id, [
            'task_risk_level' => $validated['task_risk_level'],
        ]);

        return redirect()
            ->route('admin.job-tasks.edit', $jobTask)
            ->with('status', __('Rischio aggiornato con successo.'));
    }
}
