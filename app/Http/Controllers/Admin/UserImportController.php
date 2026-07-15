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
use App\Models\LanguageLevel;
use App\Models\WorldCountry;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
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
        'Mansione (nome; separa con ;)',
        'Unità lavorativa (nome)',
        'Straniero',
        'Data di assunzione',
        'Data di cessazione',
        'Livello conoscenza lingua di lavoro',
    ];

    /**
     * @var array<int, string>
     */
    private const QUICK_TEMPLATE_HEADERS = [
        'Codice fiscale',
        'Nome',
        'Cognome',
        'Tipo di account',
        'Genere',
        'Nazionalità',
        'Settore',
        'Ruolo',
        'Mansione (nome; separa con ;)',
        'Unità lavorativa (nome)',
        'Straniero',
        'Data di assunzione',
    ];

    public function index(): View
    {
        return view('admin.imports.users', [
            'recentImports' => $this->recentImports(),
            'title' => __('Import utenti completo'),
            'templateRoute' => 'admin.imports.users.template',
            'storeRoute' => 'admin.imports.users.store',
            'statusCardRoute' => 'admin.imports.users.status-card',
            'statusTitle' => __('Import utenti completo recenti'),
            'rules' => [
                __('Obbligatori sempre: tipo di account, nome, cognome, codice fiscale.'),
                __('Obbligatori se tra i ruoli c’è User: settore, ruolo, mansione, unità lavorativa, straniero, data assunzione, livello lingua.'),
                __('Tipo di account multiplo separato da punto e virgola (;). Valori attesi: User, Docente, Tutor, Admin.'),
                __('Categoria lavoro, settore, livello e ruolo devono esistere.'),
                __('Mansione accetta più nomi separati da ;. Unità lavorativa è risolta per nome.'),
            ],
        ]);
    }

    public function quick(): View
    {
        return view('admin.imports.users', [
            'recentImports' => $this->recentImports(Importazione::TYPE_USERS_QUICK),
            'title' => __('Import utenti rapido'),
            'templateRoute' => 'admin.imports.users.quick.template',
            'storeRoute' => 'admin.imports.users.quick.store',
            'statusCardRoute' => 'admin.imports.users.quick.status-card',
            'statusTitle' => __('Import utenti rapido recenti'),
            'rules' => [
                __('Colonne incluse: codice fiscale, nome, cognome, tipo di account, genere, settore, ruolo, mansione, unità lavorativa, straniero e data di assunzione.'),
                __('Settore, ruolo e unità lavorativa sono disponibili come menu a tendina nel template.'),
                __('Tipo di account multiplo separato da punto e virgola (;). Valori attesi: User, Docente, Tutor, Admin.'),
            ],
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
            'Nazione',
            'Genere',
            'Settore',
            'Categoria di lavoro',
            'Livello di inquadramento',
            'Ruolo',
            'Mansione (nome; separa con ;)',
            'Unità lavorativa (nome)',
            'Straniero',
            'Livello conoscenza lingua di lavoro',
        ]]);

        $lookupRows = max(
            1,
            $exampleData['job_categories']->count(),
            $exampleData['job_levels']->count(),
            $exampleData['job_roles']->count(),
            $exampleData['job_tasks']->count(),
            $exampleData['job_units']->count(),
            $exampleData['countries']->count(),
            $exampleData['genders']->count(),
            $exampleData['job_sectors']->count(),
            $exampleData['foreigner_values']->count(),
            $exampleData['language_levels']->count(),
        );

        for ($index = 0; $index < $lookupRows; $index++) {
            $lookupSheet->fromArray([[
                $exampleData['countries']->get($index),
                $exampleData['genders']->get($index),
                $exampleData['job_sectors']->get($index),
                $exampleData['job_categories']->get($index),
                $exampleData['job_levels']->get($index),
                $exampleData['job_roles']->get($index),
                $exampleData['job_tasks']->get($index),
                $exampleData['job_units']->get($index),
                $exampleData['foreigner_values']->get($index),
                $exampleData['language_levels']->get($index),
            ]], null, 'A'.($index + 2));
        }

        $this->addListValidation($sheet, 'H', "'Valori disponibili'!\$A\$2:\$A\$".($exampleData['countries']->count() + 1));
        $this->addListValidation($sheet, 'O', "'Valori disponibili'!\$B\$2:\$B\$".($exampleData['genders']->count() + 1));
        $this->addListValidation($sheet, 'P', "'Valori disponibili'!\$C\$2:\$C\$".($exampleData['job_sectors']->count() + 1));
        $this->addListValidation($sheet, 'Q', "'Valori disponibili'!\$D\$2:\$D\$".($exampleData['job_categories']->count() + 1));
        $this->addListValidation($sheet, 'R', "'Valori disponibili'!\$E\$2:\$E\$".($exampleData['job_levels']->count() + 1));
        $this->addListValidation($sheet, 'S', "'Valori disponibili'!\$F\$2:\$F\$".($exampleData['job_roles']->count() + 1));
        $this->addListValidation($sheet, 'U', "'Valori disponibili'!\$H\$2:\$H\$".($exampleData['job_units']->count() + 1));
        $this->addListValidation($sheet, 'V', "'Valori disponibili'!\$I\$2:\$I\$".($exampleData['foreigner_values']->count() + 1));
        $this->addListValidation($sheet, 'Y', "'Valori disponibili'!\$J\$2:\$J\$".($exampleData['language_levels']->count() + 1));

        return $this->downloadSpreadsheet($spreadsheet, 'template-import-utenti.xlsx');
    }

    public function downloadQuickTemplate(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import utenti rapido');
        $exampleData = $this->templateExampleData();
        $sheet->fromArray([self::QUICK_TEMPLATE_HEADERS]);
        $sheet->fromArray([[
            'RSSMRA80A01H501Z',
            'Mario',
            'Rossi',
            'User',
            'M',
            'IT',
            $exampleData['job_sector'],
            $exampleData['job_role'],
            $exampleData['job_task_codes']->take(2)->implode(';'),
            $exampleData['job_unit_code'],
            'NO',
            '01/01/2024',
        ]], null, 'A2');

        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Valori disponibili');
        $lookupSheet->fromArray([['Nazionalità', 'Settore', 'Ruolo', 'Mansione (nome)', 'Unità lavorativa (nome)', 'Straniero']]);

        $lookupRows = max(1, $exampleData['countries']->count(), $exampleData['job_sectors']->count(), $exampleData['job_roles']->count(), $exampleData['job_tasks']->count(), $exampleData['job_units']->count(), $exampleData['foreigner_values']->count());

        for ($index = 0; $index < $lookupRows; $index++) {
            $lookupSheet->fromArray([[
                $exampleData['countries']->get($index),
                $exampleData['job_sectors']->get($index),
                $exampleData['job_roles']->get($index),
                $exampleData['job_tasks']->get($index),
                $exampleData['job_units']->get($index),
                $exampleData['foreigner_values']->get($index),
            ]], null, 'A'.($index + 2));
        }

        $this->addListValidation($sheet, 'F', "'Valori disponibili'!\$A\$2:\$A\$".($exampleData['countries']->count() + 1));
        $this->addListValidation($sheet, 'G', "'Valori disponibili'!\$B\$2:\$B\$".($exampleData['job_sectors']->count() + 1));
        $this->addListValidation($sheet, 'H', "'Valori disponibili'!\$C\$2:\$C\$".($exampleData['job_roles']->count() + 1));
        $this->addListValidation($sheet, 'J', "'Valori disponibili'!\$E\$2:\$E\$".($exampleData['job_units']->count() + 1));
        $this->addListValidation($sheet, 'K', "'Valori disponibili'!\$F\$2:\$F\$".($exampleData['foreigner_values']->count() + 1));

        return $this->downloadSpreadsheet($spreadsheet, 'template-import-utenti-rapido.xlsx');
    }

    public function statusCard(Request $request): ViewContract
    {
        return view('components.admin.imports.users-status-card', [
            'recentImports' => $this->recentImports(),
            'title' => __('Import utenti completo recenti'),
        ]);
    }

    public function quickStatusCard(Request $request): ViewContract
    {
        return view('components.admin.imports.users-status-card', [
            'recentImports' => $this->recentImports(Importazione::TYPE_USERS_QUICK),
            'title' => __('Import utenti rapido recenti'),
        ]);
    }

    public function store(StoreUserImportRequest $request): RedirectResponse
    {
        return $this->storeImport($request, Importazione::TYPE_USERS, 'admin.imports.users', __('Import utenti completo'));
    }

    public function storeQuick(StoreUserImportRequest $request): RedirectResponse
    {
        return $this->storeImport($request, Importazione::TYPE_USERS_QUICK, 'admin.imports.users.quick', __('Import utenti rapido'));
    }

    private function storeImport(StoreUserImportRequest $request, string $importType, string $route, string $label): RedirectResponse
    {
        $file = $request->file('file');
        $storedPath = $file->store('imports/users');

        $importazione = Importazione::query()->create([
            'import_type' => $importType,
            'created_by' => Auth::id(),
            'file_path' => $storedPath,
            'original_file_name' => $file->getClientOriginalName(),
        ]);

        ImportUsersJob::dispatch($importazione->getKey());

        return redirect()
            ->route($route)
            ->with('status', __(':label accodato. Controlla il monitor importazioni per l\'esito.', ['label' => $label]));
    }

    private function recentImports(string $type = Importazione::TYPE_USERS): Collection
    {
        return Importazione::query()
            ->where('import_type', $type)
            ->where('created_by', Auth::id())
            ->latest()
            ->limit(8)
            ->get();
    }

    /**
     * @return array{
     *     countries: Collection<int, string>,
     *     genders: Collection<int, string>,
     *     job_sectors: Collection<int, string>,
     *     foreigner_values: Collection<int, string>,
     *     language_levels: Collection<int, string>,
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
        $countries = WorldCountry::query()
            ->orderBy('name')
            ->pluck('code')
            ->filter()
            ->map(fn (string $code): string => mb_strtoupper($code))
            ->values();
        $genders = collect(['M', 'F']);
        $foreignerValues = collect(['SI', 'NO']);
        $jobSectors = JobSector::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();
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
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();
        $jobUnits = JobUnit::query()
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();
        $languageLevels = LanguageLevel::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name')
            ->filter()
            ->values();

        return [
            'countries' => $countries,
            'genders' => $genders,
            'job_sectors' => $jobSectors,
            'foreigner_values' => $foreignerValues,
            'language_levels' => $languageLevels,
            'job_sector' => $jobSectors->first() ?? 'Scuole',
            'job_category' => $jobCategories->first() ?? 'Categoria esistente',
            'job_level' => $jobLevels->first() ?? 'Livello esistente',
            'job_role' => $jobRoles->first() ?? 'Ruolo esistente',
            'job_task_code' => $jobTasks->first() ?? 'Mansione esistente',
            'job_task_codes' => $jobTasks,
            'job_unit_code' => $jobUnits->first() ?? 'Unità lavorativa esistente',
            'job_categories' => $jobCategories,
            'job_levels' => $jobLevels,
            'job_roles' => $jobRoles,
            'job_tasks' => $jobTasks,
            'job_units' => $jobUnits,
        ];
    }

    private function addListValidation(Worksheet $sheet, string $column, string $formula): void
    {
        for ($row = 2; $row <= 1000; $row++) {
            $validation = $sheet->getCell("{$column}{$row}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1($formula);
        }
    }

    private function downloadSpreadsheet(Spreadsheet $spreadsheet, string $filename): BinaryFileResponse
    {
        $temporaryFile = tempnam(sys_get_temp_dir(), 'user-import-template-');

        if ($temporaryFile === false) {
            abort(500, 'Impossibile generare template import utenti.');
        }

        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download(
            $temporaryFile,
            $filename,
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }
}
