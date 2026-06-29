<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobTaskRiskAssociationImportRequest;
use App\Jobs\ImportJobTaskRiskAssociationsJob;
use App\Models\Importazione;
use App\Models\JobSector;
use App\Models\JobTask;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JobTaskRiskAssociationImportController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const TEMPLATE_HEADERS = [
        'Codice mansione',
        'Nome settore',
        'Livello di rischio',
        'Sovrascrivi rischio settore',
    ];

    public function index(): View
    {
        return view('admin.imports.job-task-risk-associations', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Associazione mansioni rischio');
        $exampleData = $this->templateExampleData();
        $sheet->fromArray([self::TEMPLATE_HEADERS]);
        $sheet->fromArray([[
            $exampleData['job_task_code'],
            $exampleData['job_sector_name'],
            'medio',
            'SI',
        ]], null, 'A2');

        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Valori disponibili');
        $lookupSheet->fromArray([[
            'Codice mansione',
            'Nome settore',
            'Livello di rischio',
            'Sovrascrivi rischio settore',
        ]]);

        $lookupRows = max(
            1,
            $exampleData['job_task_codes']->count(),
            $exampleData['job_sector_names']->count(),
        );

        for ($index = 0; $index < $lookupRows; $index++) {
            $lookupSheet->fromArray([[
                $exampleData['job_task_codes']->get($index),
                $exampleData['job_sector_names']->get($index),
                ['basso', 'medio', 'alto'][$index] ?? null,
                ['SI', 'NO'][$index] ?? null,
            ]], null, 'A'.($index + 2));
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'job-task-risk-association-import-template-');

        if ($temporaryFile === false) {
            abort(500, 'Impossibile generare template associazione mansioni rischio.');
        }

        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download(
            $temporaryFile,
            'template-associazione-mansioni-rischio.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function statusCard(): ViewContract
    {
        return view('admin.imports.partials.job-task-risk-associations-status-card', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function store(StoreJobTaskRiskAssociationImportRequest $request): RedirectResponse
    {
        $storedPath = $request->file('file')->store('imports/job-task-risk-associations');

        $importazione = Importazione::query()->create([
            'import_type' => Importazione::TYPE_JOB_TASK_RISK_ASSOCIATIONS,
            'created_by' => Auth::id(),
            'file_path' => $storedPath,
            'original_file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        ImportJobTaskRiskAssociationsJob::dispatch($importazione->getKey());

        return redirect()
            ->route('admin.imports.job-task-risk-associations')
            ->with('status', __('Import associazione mansioni rischio accodato. Controlla il monitor importazioni per l\'esito.'));
    }

    private function recentImports(): Collection
    {
        return Importazione::query()
            ->where('import_type', Importazione::TYPE_JOB_TASK_RISK_ASSOCIATIONS)
            ->where('created_by', Auth::id())
            ->latest()
            ->limit(8)
            ->get();
    }

    /**
     * @return array{
     *     job_task_code: string,
     *     job_sector_name: string,
     *     job_task_codes: Collection<int, string>,
     *     job_sector_names: Collection<int, string>
     * }
     */
    private function templateExampleData(): array
    {
        $jobTaskCodes = JobTask::query()
            ->orderBy('code')
            ->pluck('code')
            ->filter()
            ->values();
        $jobSectorNames = JobSector::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        return [
            'job_task_code' => $jobTaskCodes->first() ?? 'TASK-001',
            'job_sector_name' => $jobSectorNames->first() ?? 'Logistica',
            'job_task_codes' => $jobTaskCodes,
            'job_sector_names' => $jobSectorNames,
        ];
    }
}
