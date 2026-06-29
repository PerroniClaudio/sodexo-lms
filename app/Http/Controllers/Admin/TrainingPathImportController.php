<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingPathImportRequest;
use App\Jobs\ImportTrainingPathsJob;
use App\Models\Importazione;
use App\Models\TrainingPath;
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

class TrainingPathImportController extends Controller
{
    public function index(): View
    {
        return view('admin.imports.user-training-paths', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $trainingPaths = TrainingPath::query()
            ->orderBy('code')
            ->orderBy('title')
            ->get(['id', 'title', 'code', 'status']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Associa percorsi utenti');
        $sheet->fromArray([['Codice fiscale', 'Codice percorso formativo']]);

        $exampleTrainingPathCode = $trainingPaths->firstWhere('status', 'published')?->code
            ?? $trainingPaths->first()?->code
            ?? 'PATH-001';

        $sheet->fromArray([[
            $this->exampleFiscalCode(),
            $exampleTrainingPathCode,
        ]], null, 'A2');

        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Percorsi disponibili');
        $lookupSheet->fromArray([['Codice', 'Titolo', 'Stato']]);

        foreach ($trainingPaths->values() as $index => $trainingPath) {
            $lookupSheet->fromArray([[
                $trainingPath->code,
                $trainingPath->title,
                $trainingPath->status,
            ]], null, 'A'.($index + 2));
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'user-training-path-import-template-');

        if ($temporaryFile === false) {
            abort(500, 'Impossibile generare template associazione utenti percorsi formativi.');
        }

        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download(
            $temporaryFile,
            'template-import-associazione-utenti-percorsi-formativi.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function statusCard(): ViewContract
    {
        return view('admin.imports.partials.user-training-paths-status-card', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function store(StoreTrainingPathImportRequest $request): RedirectResponse
    {
        $storedPath = $request->file('file')->store('imports/user-training-paths');

        $importazione = Importazione::query()->create([
            'import_type' => Importazione::TYPE_USER_TRAINING_PATHS,
            'created_by' => Auth::id(),
            'file_path' => $storedPath,
            'original_file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        ImportTrainingPathsJob::dispatch($importazione->getKey());

        return redirect()
            ->route('admin.imports.user-training-paths')
            ->with('status', __('Import associazione utenti percorsi formativi accodato. Controlla il monitor importazioni per l\'esito.'));
    }

    private function recentImports(): Collection
    {
        return Importazione::query()
            ->where('import_type', Importazione::TYPE_USER_TRAINING_PATHS)
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
