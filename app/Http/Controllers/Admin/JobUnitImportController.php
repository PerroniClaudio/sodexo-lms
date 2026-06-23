<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreJobUnitImportRequest;
use App\Jobs\ImportJobUnitsJob;
use App\Models\Importazione;
use App\Models\Province;
use App\Models\WorldCity;
use App\Models\WorldCountry;
use App\Models\WorldDivision;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class JobUnitImportController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const TEMPLATE_HEADERS = [
        'Codice unità lavorativa',
        'Nome',
        'Paese',
        'Regione',
        'Provincia',
        'Città',
        'Indirizzo',
        'Codice postale',
        'Breve descrizione',
    ];

    public function index(): View
    {
        return view('admin.imports.job-units', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function downloadTemplate(): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Import unita lavorative');
        $exampleData = $this->templateExampleData();
        $sheet->fromArray([self::TEMPLATE_HEADERS]);
        $sheet->fromArray([[
            'UNIT-001',
            'Sede Roma Centro',
            'IT',
            $exampleData['region'],
            $exampleData['province'],
            $exampleData['city'],
            'Via Roma 1',
            $exampleData['postal_code'],
            'Sede operativa principale',
        ]], null, 'A2');

        $lookupSheet = $spreadsheet->createSheet();
        $lookupSheet->setTitle('Valori disponibili');
        $lookupSheet->fromArray([[
            'Paese',
            'Regione',
            'Provincia',
            'Città',
        ]]);
        $lookupSheet->fromArray([[
            'IT',
            $exampleData['region'],
            $exampleData['province'],
            $exampleData['city'],
        ]], null, 'A2');

        $temporaryFile = tempnam(sys_get_temp_dir(), 'job-unit-import-template-');

        if ($temporaryFile === false) {
            abort(500, 'Impossibile generare template import unità lavorative.');
        }

        (new Xlsx($spreadsheet))->save($temporaryFile);
        $spreadsheet->disconnectWorksheets();

        return Response::download(
            $temporaryFile,
            'template-import-unita-lavorative.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function statusCard(): ViewContract
    {
        return view('admin.imports.partials.job-units-status-card', [
            'recentImports' => $this->recentImports(),
        ]);
    }

    public function store(StoreJobUnitImportRequest $request): RedirectResponse
    {
        $storedPath = $request->file('file')->store('imports/job-units', Importazione::STORAGE_DISK);

        $importazione = Importazione::query()->create([
            'import_type' => Importazione::TYPE_JOB_UNITS,
            'created_by' => Auth::id(),
            'file_path' => $storedPath,
            'original_file_name' => $request->file('file')->getClientOriginalName(),
        ]);

        ImportJobUnitsJob::dispatch($importazione->getKey());

        return redirect()
            ->route('admin.imports.job-units')
            ->with('status', __('Import unità lavorative accodato. Controlla il monitor importazioni per l\'esito.'));
    }

    private function recentImports(): Collection
    {
        return Importazione::query()
            ->where('import_type', Importazione::TYPE_JOB_UNITS)
            ->where('created_by', Auth::id())
            ->latest()
            ->limit(8)
            ->get();
    }

    /**
     * @return array{region: string, province: string, city: string, postal_code: string}
     */
    private function templateExampleData(): array
    {
        $italy = WorldCountry::query()->where('code', 'it')->first();
        $region = WorldDivision::query()
            ->when($italy !== null, fn ($query) => $query->where('country_id', $italy->getKey()))
            ->orderBy('name')
            ->first();
        $province = Province::query()
            ->when($region !== null, fn ($query) => $query->where('region_id', $region->getKey()))
            ->orderBy('name')
            ->first();
        $city = WorldCity::query()
            ->when($region !== null, fn ($query) => $query->where('division_id', $region->getKey()))
            ->when($province !== null, fn ($query) => $query->where('province_id', $province->getKey()))
            ->orderBy('name')
            ->first();

        return [
            'region' => $region?->name ?? 'Lazio',
            'province' => $province?->name ?? 'Roma',
            'city' => $city?->name ?? 'Roma',
            'postal_code' => '00100',
        ];
    }
}
