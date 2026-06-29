<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserImportRequest;
use App\Jobs\ImportUsersJob;
use App\Models\Importazione;
use App\Models\JobCategory;
use App\Models\JobLevel;
use App\Models\JobRole;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Models\JobUnit;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class UserImportController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const TEMPLATE_HEADERS = [
        'Email',
        'Tipo di account',
        'Nome',
        'Cognome',
        'Prefisso nazionale',
        'Numero di telefono',
        'Codice fiscale',
        'Nazione di residenza/domicilio',
        'Regione di residenza/domicilio',
        'Provincia di residenza/domicilio',
        'Indirizzo di residenza/domicilio',
        'Codice postale di residenza/domicilio',
        'Data di nascita',
        'Luogo di nascita',
        'Genere',
        'Settore',
        'Categoria di lavoro',
        'Livello di inquadramento',
        'Ruolo',
        'Mansione (codice o ID; separa con ;)',
        'Unità lavorativa (codice)',
        'Straniero',
        'Data di assunzione',
        'Data di cessazione',
        'Livello conoscenza lingua di lavoro',
    ];

    public function index(): View
    {
        return view('admin.imports.users', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import utenti');
        $exampleData = $this->templateExampleData();
        $sheet->fromArray([self::TEMPLATE_HEADERS]);
        $sheet->fromArray([[
            'mario.rossi@example.test',
            'User;Docente',
            'Mario',
            'Rossi',
            '+39',
            '3331234567',
            'RSSMRA80A01H501Z',
            'IT',
            'Lazio',
            'RM',
            'Via Roma 1',
            '00100',
            '10/02/1980',
            'Roma',
            'M',
            $exampleData['job_sector'],
            $exampleData['job_category'],
            $exampleData['job_level'],
            $exampleData['job_role'],
            $exampleData['job_task_codes']->take(2)->implode(';'),
            $exampleData['job_unit_code'],
            'NO',
            '01/01/2024',
            null,
            'A2',
        ]], null, 'A2');

        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Valori disponibili');
        $lookupSheet->fromArray([[
            'Categoria di lavoro',
            'Livello di inquadramento',
            'Ruolo',
            'Mansione (codice o ID; separa con ;)',
            'Unità lavorativa (codice)',
        ]]);

        $lookupRows = max(
            1,
            $exampleData['job_categories']->count(),
            $exampleData['job_levels']->count(),
            $exampleData['job_roles']->count(),
            $exampleData['job_tasks']->count(),
            $exampleData['job_units']->count(),
        );

        for ($index = 0; $index < $lookupRows; $index++) {
            $lookupSheet->fromArray([[
                $exampleData['job_categories']->get($index),
                $exampleData['job_levels']->get($index),
                $exampleData['job_roles']->get($index),
                $exampleData['job_tasks']->get($index),
                $exampleData['job_units']->get($index),
            ]], null, 'A'.($index + 2));
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'user-import-template-');

        if ($temporaryFile === false) {
            abort(500, 'Impossibile generare template import utenti.');
        }

        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download(
            $temporaryFile,
            'template-import-utenti.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function statusCard(Request $request): ViewContract
    {
        return view('admin.imports.partials.users-status-card', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function store(StoreUserImportRequest $request): RedirectResponse
    {
        $storedPath = $request->file('file')->store('imports/users', Importazione::storageDisk());

        $importazione = Importazione::query()->create([
            'import_type' => Importazione::TYPE_USERS,
            'created_by' => Auth::id(),
            'file_path' => $storedPath,
            'original_file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        ImportUsersJob::dispatch($importazione->getKey());

        return redirect()
            ->route('admin.imports.users')
            ->with('status', __('Import utenti accodato. Controlla il monitor importazioni per l\'esito.'));
    }

    private function recentImports(): Collection
    {
        return Importazione::query()
            ->where('import_type', Importazione::TYPE_USERS)
            ->where('created_by', Auth::id())
            ->latest()
            ->limit(8)
            ->get();
    }

    /**
     * @return array{
     *     job_sector: string,
     *     job_category: string,
     *     job_level: string,
     *     job_role: string,
     *     job_task_code: string,
     *     job_task_codes: Collection<int, string>,
     *     job_unit_code: string,
     *     job_categories: Collection<int, string>,
     *     job_levels: Collection<int, string>,
     *     job_roles: Collection<int, string>,
     *     job_tasks: Collection<int, string>,
     *     job_units: Collection<int, string>
     * }
     */
    private function templateExampleData(): array
    {
        $jobCategories = JobCategory::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();
        $jobLevels = JobLevel::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();
        $jobRoles = JobRole::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();
        $jobTasks = JobTask::query()
            ->orderBy('code')
            ->pluck('code')
            ->filter()
            ->values();
        $jobUnits = JobUnit::query()
            ->orderBy('unit_code')
            ->pluck('unit_code')
            ->filter()
            ->values();

        return [
            'job_sector' => JobSector::query()->orderBy('name')->value('name') ?? 'Scuole',
            'job_category' => $jobCategories->first() ?? 'Categoria esistente',
            'job_level' => $jobLevels->first() ?? 'Livello esistente',
            'job_role' => $jobRoles->first() ?? 'Ruolo esistente',
            'job_task_code' => $jobTasks->first() ?? 'MANSIONE-CODICE',
            'job_task_codes' => $jobTasks,
            'job_unit_code' => $jobUnits->first() ?? 'UNITA-CODICE',
            'job_categories' => $jobCategories,
            'job_levels' => $jobLevels,
            'job_roles' => $jobRoles,
            'job_tasks' => $jobTasks,
            'job_units' => $jobUnits,
        ];
    }
}
