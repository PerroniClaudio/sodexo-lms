<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportCourseProgramRequest;
use App\Models\Course;
use App\Services\CourseProgramSpreadsheet;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseProgramImportController extends Controller
{
    public function downloadTemplate(CourseProgramSpreadsheet $spreadsheet): BinaryFileResponse
    {
        return $spreadsheet->downloadTemplate();
    }

    public function store(
        ImportCourseProgramRequest $request,
        Course $course,
        CourseProgramSpreadsheet $spreadsheet,
    ): RedirectResponse {
        $course->update([
            'program_schedule' => $spreadsheet->import($request->file('file')->getRealPath()),
        ]);

        return redirect()
            ->route('admin.courses.edit', [$course, 'section' => 'program'])
            ->with('status', __('Programma corso importato con successo.'));
    }
}
