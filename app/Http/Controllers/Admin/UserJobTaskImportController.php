<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserJobTaskImportRequest;
use App\Jobs\ImportUserJobTasksJob;
use App\Models\Importazione;
use App\Models\JobTask;
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

class UserJobTaskImportController extends Controller
{
    public function index(): View
    {
        return view('admin.imports.user-job-tasks', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $jobTasks = JobTask::query()
            ->orderBy('code')
            ->get(['id', 'name', 'code']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Associa mansioni utenti');

        $headers = ['Codice fiscale', ...$jobTasks->pluck('code')->filter()->values()->all()];

        $sheet->fromArray([$headers]);

        if ($headers !== ['Codice fiscale']) {
            $exampleRow = array_fill(0, count($headers), null);
            $exampleRow[0] = $this->exampleFiscalCode();
            $exampleRow[1] = 'SI';

            if (isset($exampleRow[2])) {
                $exampleRow[2] = 'X';
            }

            $sheet->fromArray([$exampleRow], null, 'A2');
        }

        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Mansioni disponibili');
        $lookupSheet->fromArray([['Codice', 'Nome']]);

        foreach ($jobTasks->values() as $index => $jobTask) {
            $lookupSheet->fromArray([[
                $jobTask->code,
                $jobTask->name,
            ]], null, 'A'.($index + 2));
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'user-job-task-import-template-');

        if ($temporaryFile === false) {
            abort(500, 'Impossibile generare template associazione utenti mansioni.');
        }

        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download(
            $temporaryFile,
            'template-import-associazione-utenti-mansioni.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function statusCard(): ViewContract
    {
        return view('components.admin.imports.user-job-tasks-status-card', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function store(StoreUserJobTaskImportRequest $request): RedirectResponse
    {
        $storedPath = $request->file('file')->store('imports/user-job-tasks');

        $importazione = Importazione::query()->create([
            'import_type' => Importazione::TYPE_USER_JOB_TASKS,
            'created_by' => Auth::id(),
            'file_path' => $storedPath,
            'original_file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        ImportUserJobTasksJob::dispatch($importazione->getKey());

        return redirect()
            ->route('admin.imports.user-job-tasks')
            ->with('status', __('Import associazione utenti mansioni accodato. Controlla il monitor importazioni per l\'esito.'));
    }

    private function recentImports(): Collection
    {
        return Importazione::query()
            ->where('import_type', Importazione::TYPE_USER_JOB_TASKS)
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
