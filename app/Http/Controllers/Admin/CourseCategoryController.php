<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseCategoryRequest;
use App\Http\Requests\UpdateCourseCategoryRequest;
use App\Models\CourseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CourseCategoryController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('search')->toString());
        $showTrashed = $request->boolean('show_trashed');

        return view('admin.course-category.index', [
            'courseCategories' => CourseCategory::query()
                ->when($showTrashed, fn ($query) => $query->withTrashed())
                ->when($search !== '', function ($query) use ($search): void {
                    $query->where(function ($courseCategoryQuery) use ($search): void {
                        $courseCategoryQuery
                            ->where('id', 'like', "%{$search}%")
                            ->orWhere('name', 'like', "%{$search}%");
                    });
                })
                ->orderBy('name')
                ->paginate(20)
                ->withQueryString(),
            'showTrashed' => $showTrashed,
            'tableSearch' => $search,
        ]);
    }

    public function create(): View
    {
        return view('admin.course-category.create');
    }

    public function store(StoreCourseCategoryRequest $request): RedirectResponse
    {
        $courseCategory = CourseCategory::query()->create($request->validated());

        return redirect()
            ->route('admin.course-categories.edit', $courseCategory)
            ->with('status', __('Categoria corso creata con successo.'));
    }

    public function edit(CourseCategory $courseCategory): View
    {
        $courseCategory->load([
            'courses' => fn ($query) => $query->orderBy('title'),
        ]);

        return view('admin.course-category.edit', [
            'courseCategory' => $courseCategory,
        ]);
    }

    public function update(UpdateCourseCategoryRequest $request, CourseCategory $courseCategory): RedirectResponse
    {
        $courseCategory->update($request->validated());

        return redirect()
            ->route('admin.course-categories.edit', $courseCategory)
            ->with('status', __('Categoria corso aggiornata con successo.'));
    }

    public function destroy(CourseCategory $courseCategory): RedirectResponse
    {
        $courseCategory->delete();

        return redirect()
            ->route('admin.course-categories.index')
            ->with('status', __('Categoria corso eliminata con successo.'));
    }

    public function restore(int $id): RedirectResponse
    {
        $courseCategory = CourseCategory::withTrashed()->findOrFail($id);
        $courseCategory->restore();

        return redirect()
            ->route('admin.course-categories.index')
            ->with('status', __('Categoria corso ripristinata con successo.'));
    }
}
