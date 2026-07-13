<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCourseEnrollmentImportRequest;
use App\Jobs\ImportCourseEnrollmentsJob;
use App\Models\Course;
use App\Models\Importazione;
use App\Models\User;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CourseEnrollmentImportController extends Controller
{
    public function index(): View
    {
        return view('admin.imports.user-courses', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $courses = Course::query()
            ->orderBy('code')
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'status']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Associa corsi utenti');
        $sheet->fromArray([['Codice fiscale', 'Codice corso']]);

        $exampleCourseCode = $courses->firstWhere('status', 'published')?->code
            ?? $courses->first()?->code
            ?? 'COURSE-001';

        $sheet->fromArray([[
            $this->exampleFiscalCode(),
            $exampleCourseCode,
        ]], null, 'A2');

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

        $temporaryFile = tempnam(sys_get_temp_dir(), 'user-course-import-template-');

        if ($temporaryFile === false) {
            abort(500, 'Impossibile generare template associazione utenti corsi.');
        }

        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download(
            $temporaryFile,
            'template-import-associazione-utenti-corsi.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function statusCard(): ViewContract
    {
        return view('components.admin.imports.user-courses-status-card', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function store(StoreCourseEnrollmentImportRequest $request): RedirectResponse
    {
        $storedPath = $request->file('file')->store('imports/user-courses', Importazione::STORAGE_DISK);

        $importazione = Importazione::query()->create([
            'import_type' => Importazione::TYPE_USER_COURSES,
            'created_by' => Auth::id(),
            'file_path' => $storedPath,
            'original_file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        ImportCourseEnrollmentsJob::dispatch($importazione->getKey());

        return redirect()
            ->route('admin.imports.user-courses')
            ->with('status', __('Import associazione utenti corsi accodato. Controlla il monitor importazioni per l\'esito.'));
    }

    private function recentImports(): Collection
    {
        return Importazione::query()
            ->where('import_type', Importazione::TYPE_USER_COURSES)
            ->where('created_by', Auth::id())
            ->latest()
            ->limit(8)
            ->get();
    }

    private function exampleFiscalCode(): string
    {
        return User::query()->orderBy('id')->value('fiscal_code') ?? 'RSSMRA80A01H501Z';
    }
}
