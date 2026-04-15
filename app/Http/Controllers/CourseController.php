<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCourseRequest;
use App\Http\Requests\UpdateCourseRequest;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function create(): View
    {
        return view('admin.course.create', [
            'courseTypeLabels' => Course::availableTypeLabels(),
        ]);
    }

    public function index(): View
    {
        return view('admin.course.index', [
            'courses' => Course::query()
                ->orderByDesc('id')
                ->paginate(20),
        ]);
    }

    public function store(StoreCourseRequest $request): RedirectResponse
    {
        $course = Course::query()->create([
            ...$request->validated(),
            'description' => '',
            'year' => now()->year,
            'expiry_date' => now()->endOfYear(),
            'status' => 'draft',
            'hasMany' => '0',
        ]);

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('status', __('Corso creato con successo.'));
    }

    public function edit(Course $course): View
    {
        return view('admin.course.edit', [
            'course' => $course,
            'courseStatusLabels' => Course::availableStatusLabels(),
        ]);
    }

    public function update(UpdateCourseRequest $request, Course $course): RedirectResponse
    {
        $course->update($request->validated());

        return redirect()
            ->route('admin.courses.edit', $course)
            ->with('status', __('Corso aggiornato con successo.'));
    }
}
