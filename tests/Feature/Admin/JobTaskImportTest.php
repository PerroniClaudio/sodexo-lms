<?php

use App\Jobs\ImportJobTasksJob;
use App\Models\Importazione;
use App\Models\JobTask;
use App\Services\JobTaskImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('queues a job task import from excel upload', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Queue::fake();

    actingAsRole('admin');

    $response = $this->post(route('admin.imports.job-tasks.store'), [
        'file' => jobTaskImportFile([
            ['Addetto magazzino', 'Gestione merce', 'TASK-001'],
        ]),
    ]);

    $response
        ->assertRedirect(route('admin.imports.job-tasks'))
        ->assertSessionHas('status');

    $importazione = Importazione::query()->sole();

    expect($importazione->import_type)->toBe(Importazione::TYPE_JOB_TASKS)
        ->and($importazione->status)->toBe(Importazione::STATUS_PENDING)
        ->and($importazione->created_by)->toBe(auth()->id())
        ->and($importazione->original_file_name)->toBe('mansioni.xlsx');

    Storage::disk('s3')->assertExists($importazione->file_path);

    Queue::assertPushed(ImportJobTasksJob::class, fn (ImportJobTasksJob $job): bool => $job->importazioneId === $importazione->getKey());
});

it('downloads job task import template', function () {
    actingAsRole('admin');

    $response = $this->get(route('admin.imports.job-tasks.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('content-disposition', 'attachment; filename=template-import-mansioni.xlsx');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'template-job-task-import-test-');
    file_put_contents($temporaryFile, $response->streamedContent());

    $spreadsheet = IOFactory::load($temporaryFile);
    $importSheet = $spreadsheet->getSheetByName('Import mansioni');

    expect($importSheet?->getCell('A2')->getValue())->toBe('Addetto magazzino')
        ->and($importSheet?->getCell('B2')->getValue())->toBe('Gestione merce e movimentazione interna')
        ->and($importSheet?->getCell('C2')->getValue())->toBe('TASK-001');

    $spreadsheet->disconnectWorksheets();
    @unlink($temporaryFile);
});

it('returns job task import status card payload', function () {
    actingAsRole('admin');

    Importazione::query()->create([
        'import_type' => Importazione::TYPE_JOB_TASKS,
        'created_by' => auth()->id(),
        'status' => Importazione::STATUS_PROGRESS,
        'file_path' => 'imports/job-tasks/progress.xlsx',
        'original_file_name' => 'progress-originale.xlsx',
    ]);

    $this->get(route('admin.imports.job-tasks.status-card'))
        ->assertOk()
        ->assertSeeText('Import mansioni recenti')
        ->assertSeeText('In lavorazione')
        ->assertSeeText('progress-originale.xlsx');
});

it('imports job tasks from excel', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    Storage::disk('s3')->put('imports/job-tasks/job-tasks.xlsx', file_get_contents(
        jobTaskImportFile([
            ['Addetto magazzino', 'Gestione merce', 'task-001'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_JOB_TASKS,
        'file_path' => 'imports/job-tasks/job-tasks.xlsx',
        'original_file_name' => 'mansioni-reali.xlsx',
    ]);

    app(ImportJobTasksJob::class, ['importazioneId' => $importazione->getKey()])->handle(app(JobTaskImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FINISHED)
        ->and($importazione->fresh()->error_message)->toBeNull();

    $jobTask = JobTask::query()->where('code', 'TASK-001')->firstOrFail();

    expect($jobTask->name)->toBe('Addetto magazzino')
        ->and($jobTask->description)->toBe('Gestione merce');
});

it('fails job task import when required data is missing', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    Storage::disk('s3')->put('imports/job-tasks/invalid-job-tasks.xlsx', file_get_contents(
        jobTaskImportFile([
            [null, 'Gestione merce', 'TASK-001'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_JOB_TASKS,
        'file_path' => 'imports/job-tasks/invalid-job-tasks.xlsx',
        'original_file_name' => 'mansioni-non-valide.xlsx',
    ]);

    app(ImportJobTasksJob::class, ['importazioneId' => $importazione->getKey()])->handle(app(JobTaskImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FAILED)
        ->and($importazione->fresh()->error_message)->toContain('Riga 2')
        ->and(JobTask::query()->where('code', 'TASK-001')->exists())->toBeFalse();
});

function jobTaskImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([[
        'Nome',
        'Breve descrizione',
        'Codice',
    ]]);

    foreach ($rows as $index => $row) {
        $sheet->fromArray([$row], null, 'A'.($index + 2));
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'job-task-import-');
    (new Xlsx($spreadsheet))->save($temporaryFile);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile(
        $temporaryFile,
        'mansioni.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}
