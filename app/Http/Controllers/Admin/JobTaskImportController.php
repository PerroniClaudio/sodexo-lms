<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobTaskImportRequest;
use App\Jobs\ImportJobTasksJob;
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

class JobTaskImportController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const TEMPLATE_HEADERS = [
        'Nome',
        'Breve descrizione',
        'Codice',
    ];

    public function index(): View
    {
        return view('admin.imports.job-tasks', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import mansioni');
        $sheet->fromArray([self::TEMPLATE_HEADERS]);
        $sheet->fromArray([[
            'Addetto magazzino',
            'Gestione merce e movimentazione interna',
            'TASK-001',
        ]], null, 'A2');

        $temporaryFile = tempnam(sys_get_temp_dir(), 'job-task-import-template-');

        if ($temporaryFile === false) {
            abort(500, 'Impossibile generare template import mansioni.');
        }

        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download(
            $temporaryFile,
            'template-import-mansioni.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function statusCard(): ViewContract
    {
        return view('admin.imports.partials.job-tasks-status-card', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function store(StoreJobTaskImportRequest $request): RedirectResponse
    {
        $storedPath = $request->file('file')->store('imports/job-tasks', Importazione::storageDisk());

        $importazione = Importazione::query()->create([
            'import_type' => Importazione::TYPE_JOB_TASKS,
            'created_by' => Auth::id(),
            'file_path' => $storedPath,
            'original_file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        ImportJobTasksJob::dispatch($importazione->getKey());

        return redirect()
            ->route('admin.imports.job-tasks')
            ->with('status', __('Import mansioni accodato. Controlla il monitor importazioni per l\'esito.'));
    }

    private function recentImports(): Collection
    {
        return Importazione::query()
            ->where('import_type', Importazione::TYPE_JOB_TASKS)
            ->where('created_by', Auth::id())
            ->latest()
            ->limit(8)
            ->get();
    }
}
