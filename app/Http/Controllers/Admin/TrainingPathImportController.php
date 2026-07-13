<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTrainingPathImportRequest;
use App\Jobs\ImportTrainingPathsJob;
use App\Models\Importazione;
use App\Models\TrainingPath;
use App\Models\TrainingPathCourseApproval;
use App\Models\User;
use App\Services\TrainingPathEnrollmentApprovalService;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TrainingPathImportController extends Controller
{
    public function __construct(
        private readonly TrainingPathEnrollmentApprovalService $trainingPathEnrollmentApprovalService,
    ) {}

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
        return view('components.admin.imports.user-training-paths-status-card', [
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

    public function approvals(Importazione $importazione): JsonResponse
    {
        $this->authorizeImportReview($importazione);

        $groups = TrainingPathCourseApproval::query()
            ->whereBelongsTo($importazione)
            ->where('status', TrainingPathCourseApproval::STATUS_PENDING)
            ->with([
                'user:id,name,surname,fiscal_code,email',
                'trainingPath:id,title,code',
                'course:id,title,code',
            ])
            ->orderBy('user_id')
            ->orderBy('training_path_id')
            ->orderBy('course_id')
            ->get()
            ->groupBy(fn (TrainingPathCourseApproval $approval): string => $approval->user_id.'-'.$approval->training_path_id)
            ->map(function (Collection $approvals): array {
                /** @var TrainingPathCourseApproval $first */
                $first = $approvals->first();

                return [
                    'user' => [
                        'id' => $first->user?->getKey(),
                        'name' => $first->user?->name,
                        'surname' => $first->user?->surname,
                        'fiscal_code' => $first->user?->fiscal_code,
                        'email' => $first->user?->email,
                    ],
                    'training_path' => [
                        'id' => $first->trainingPath?->getKey(),
                        'title' => $first->trainingPath?->title,
                        'code' => $first->trainingPath?->code,
                    ],
                    'courses' => $approvals->map(fn (TrainingPathCourseApproval $approval): array => [
                        'id' => $approval->course?->getKey(),
                        'title' => $approval->course?->title,
                        'code' => $approval->course?->code,
                        'reasons' => $approval->reasons,
                    ])->values(),
                ];
            })
            ->values();

        return response()->json(['data' => $groups]);
    }

    public function decideApproval(Request $request, Importazione $importazione): JsonResponse
    {
        $this->authorizeImportReview($importazione);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'training_path_id' => ['required', 'integer', 'exists:training_paths,id'],
            'approved' => ['required', 'boolean'],
        ]);

        $this->trainingPathEnrollmentApprovalService->decideImportEnrollment(
            $importazione,
            User::query()->findOrFail($validated['user_id']),
            TrainingPath::query()->findOrFail($validated['training_path_id']),
            $request->user(),
            (bool) $validated['approved'],
        );

        return response()->json([
            'success' => true,
            'message' => $validated['approved']
                ? __('Iscrizione approvata e corsi non idonei saltati nel percorso.')
                : __('Iscrizione non approvata.'),
        ]);
    }

    public function approveAll(Request $request, Importazione $importazione): JsonResponse
    {
        $this->authorizeImportReview($importazione);
        $this->trainingPathEnrollmentApprovalService->approveAllPending($importazione, $request->user());

        return response()->json([
            'success' => true,
            'message' => __('Tutte le iscrizioni in attesa sono state approvate.'),
        ]);
    }

    private function recentImports(): Collection
    {
        return Importazione::query()
            ->where('import_type', Importazione::TYPE_USER_TRAINING_PATHS)
            ->where('created_by', Auth::id())
            ->withCount([
                'trainingPathCourseApprovals as pending_approvals_count' => fn ($query) => $query
                    ->where('status', TrainingPathCourseApproval::STATUS_PENDING),
            ])
            ->latest()
            ->limit(8)
            ->get();
    }

    private function exampleFiscalCode(): string
    {
        return User::query()->orderBy('id')->value('fiscal_code') ?? 'RSSMRA80A01H501Z';
    }

    private function authorizeImportReview(Importazione $importazione): void
    {
        abort_unless($importazione->import_type === Importazione::TYPE_USER_TRAINING_PATHS, 404);
        abort_unless(
            (int) $importazione->created_by === (int) Auth::id() || Auth::user()?->hasRole('superadmin'),
            403,
        );
    }
}
