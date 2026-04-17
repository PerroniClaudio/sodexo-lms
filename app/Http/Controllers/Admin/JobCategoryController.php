<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobCategoryRequest;
use App\Http\Requests\UpdateJobCategoryRequest;
use App\Models\JobCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class JobCategoryController extends Controller
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

        return view('admin.job-category.index', [
            'categories' => JobCategory::query()
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
        return view('admin.job-category.create');
    }

    public function store(StoreJobCategoryRequest $request): RedirectResponse
    {
        $category = JobCategory::query()->create([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()
            ->route('admin.job-categories.edit', $category)
            ->with('status', __('Categoria di lavoro creata con successo.'));
    }

    public function edit(JobCategory $jobCategory): View
    {
        return view('admin.job-category.edit', [
            'category' => $jobCategory,
        ]);
    }

    public function update(UpdateJobCategoryRequest $request, JobCategory $jobCategory): RedirectResponse
    {
        $jobCategory->update([
            ...$request->validated(),
            'is_active' => $request->boolean('is_active', false),
        ]);

        return redirect()
            ->route('admin.job-categories.edit', $jobCategory)
            ->with('status', __('Categoria di lavoro aggiornata con successo.'));
    }

    public function destroy(JobCategory $jobCategory): RedirectResponse
    {
        $jobCategory->delete();

        return redirect()
            ->route('admin.job-categories.index')
            ->with('status', __('Categoria di lavoro eliminata con successo.'));
    }
}
