<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserImportRequest;
use App\Jobs\ImportUsersJob;
use App\Models\Importazione;
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
        'Mansione (codice)',
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
            'Scuole',
            'Impiegati',
            'Quadro',
            'Operatore',
            'TASK-001',
            'UNIT-001',
            'NO',
            '01/01/2024',
            null,
            'A2',
        ]], null, 'A2');

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
        $storedPath = $request->file('file')->store('imports/users', Importazione::STORAGE_DISK);

        $importazione = Importazione::query()->create([
            'import_type' => Importazione::TYPE_USERS,
            'created_by' => Auth::id(),
            'file_path' => $storedPath,
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
}
