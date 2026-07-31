<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseStaffImportRequest;
use App\Jobs\ImportCourseStaffJob;
use App\Models\Course;
use App\Models\Importazione;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseStaffImportController extends Controller
{
    public function index(): View
    {
        return view('admin.imports.course-staff', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $courses = Course::query()
            ->orderBy('code')
            ->orderBy('title')
            ->get(['title', 'code', 'status']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Docenti e tutor corsi');
        $sheet->fromArray([
            ['Email', 'Codice corso', 'Ruolo'],
            ['docente@example.com', $courses->first()?->code ?? 'COURSE-001', 'docente'],
        ]);

        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Corsi disponibili');
        $lookupSheet->fromArray([['Codice', 'Titolo', 'Stato']]);

        foreach ($courses->values() as $index => $course) {
            $lookupSheet->fromArray([[
                $course->code,
                $course->title,
                $course->status,
            ]], null, 'A'.($index + 2));
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'course-staff-import-template-');

        if ($temporaryFile === false) {
            abort(500, 'Impossibile generare il template docenti e tutor corsi.');
        }

        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download(
            $temporaryFile,
            'template-import-docenti-tutor-corsi.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function statusCard(): ViewContract
    {
        return view('components.admin.imports.course-staff-status-card', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function store(StoreCourseStaffImportRequest $request): RedirectResponse
    {
        $storedPath = $request->file('file')->store('imports/course-staff');

        $importazione = Importazione::query()->create([
            'import_type' => Importazione::TYPE_COURSE_STAFF,
            'created_by' => Auth::id(),
            'file_path' => $storedPath,
            'original_file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        ImportCourseStaffJob::dispatch($importazione->getKey());

        return redirect()
            ->route('admin.imports.course-staff')
            ->with('status', __('Import docenti e tutor corsi accodato. Controlla il monitor importazioni per l\'esito.'));
    }

    private function recentImports(): Collection
    {
        return Importazione::query()
            ->where('import_type', Importazione::TYPE_COURSE_STAFF)
            ->where('created_by', Auth::id())
            ->latest()
            ->limit(8)
            ->get();
    }
}
