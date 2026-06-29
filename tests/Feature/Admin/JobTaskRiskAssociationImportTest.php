<?php

use App\Jobs\ImportJobTaskRiskAssociationsJob;
use App\Models\Importazione;
use App\Models\JobSector;
use App\Models\JobTask;
use App\Services\JobTaskRiskAssociationImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('queues a job task risk association import from excel upload', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Queue::fake();

    actingAsRole('admin');

    $response = $this->post(route('admin.imports.job-task-risk-associations.store'), [
        'file' => jobTaskRiskAssociationImportFile([
            ['TASK-001', 'Logistica', 'medio', 'SI'],
        ]),
    ]);

    $response
        ->assertRedirect(route('admin.imports.job-task-risk-associations'))
        ->assertSessionHas('status');

    $importazione = Importazione::query()->sole();

    expect($importazione->import_type)->toBe(Importazione::TYPE_JOB_TASK_RISK_ASSOCIATIONS)
        ->and($importazione->status)->toBe(Importazione::STATUS_PENDING)
        ->and($importazione->created_by)->toBe(auth()->id())
        ->and($importazione->original_file_name)->toBe('associazione-mansioni-rischio.xlsx');

    Storage::disk('s3')->assertExists($importazione->file_path);

    Queue::assertPushed(ImportJobTaskRiskAssociationsJob::class, fn (ImportJobTaskRiskAssociationsJob $job): bool => $job->importazioneId === $importazione->getKey());
});

it('downloads job task risk association import template', function () {
    actingAsRole('admin');

    JobTask::factory()->create(['code' => 'TASK-001']);
    JobSector::factory()->create(['name' => 'Logistica']);

    $response = $this->get(route('admin.imports.job-task-risk-associations.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('content-disposition', 'attachment; filename=template-associazione-mansioni-rischio.xlsx');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'template-job-task-risk-association-import-test-');
    file_put_contents($temporaryFile, $response->streamedContent());

    $spreadsheet = IOFactory::load($temporaryFile);
    $importSheet = $spreadsheet->getSheetByName('Associazione mansioni rischio');
    $lookupSheet = $spreadsheet->getSheetByName('Valori disponibili');

    expect($importSheet?->getCell('A2')->getValue())->toBe('TASK-001')
        ->and($importSheet?->getCell('B2')->getValue())->toBe('Logistica')
        ->and($importSheet?->getCell('C2')->getValue())->toBe('medio')
        ->and($importSheet?->getCell('D2')->getValue())->toBe('SI')
        ->and($lookupSheet?->getCell('A2')->getValue())->toBe('TASK-001')
        ->and($lookupSheet?->getCell('B2')->getValue())->toBe('Logistica');

    $spreadsheet->disconnectWorksheets();
    @unlink($temporaryFile);
});

it('returns job task risk association import status card payload', function () {
    actingAsRole('admin');

    Importazione::query()->create([
        'import_type' => Importazione::TYPE_JOB_TASK_RISK_ASSOCIATIONS,
        'created_by' => auth()->id(),
        'status' => Importazione::STATUS_PROGRESS,
        'file_path' => 'imports/job-task-risk-associations/progress.xlsx',
        'original_file_name' => 'progress-originale.xlsx',
    ]);

    $this->get(route('admin.imports.job-task-risk-associations.status-card'))
        ->assertOk()
        ->assertSeeText('Import associazione mansioni rischio recenti')
        ->assertSeeText('In lavorazione')
        ->assertSeeText('progress-originale.xlsx');
});

it('imports job task risk associations from excel', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $jobTask = JobTask::factory()->create(['code' => 'TASK-001']);
    $jobSector = JobSector::factory()->create(['name' => 'Logistica']);

    Storage::disk('s3')->put('imports/job-task-risk-associations/job-task-risk-associations.xlsx', file_get_contents(
        jobTaskRiskAssociationImportFile([
            ['task-001', 'Logistica', 'alto', 'SI'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_JOB_TASK_RISK_ASSOCIATIONS,
        'file_path' => 'imports/job-task-risk-associations/job-task-risk-associations.xlsx',
        'original_file_name' => 'associazioni-reali.xlsx',
    ]);

    app(ImportJobTaskRiskAssociationsJob::class, ['importazioneId' => $importazione->getKey()])
        ->handle(app(JobTaskRiskAssociationImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FINISHED)
        ->and($importazione->fresh()->error_message)->toBeNull();

    $pivot = DB::table('job_task_job_sector')
        ->where('job_task_id', $jobTask->getKey())
        ->where('job_sector_id', $jobSector->getKey())
        ->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot->task_risk_level)->toBe('high')
        ->and((bool) $pivot->sector_risk_override)->toBeTrue();
});

it('sets sector risk override to false when file says no', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $jobTask = JobTask::factory()->create(['code' => 'TASK-001']);
    $jobSector = JobSector::factory()->create(['name' => 'Logistica']);
    DB::table('job_task_job_sector')->insert([
        'job_task_id' => $jobTask->getKey(),
        'job_sector_id' => $jobSector->getKey(),
        'task_risk_level' => 'low',
        'sector_risk_override' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Storage::disk('s3')->put('imports/job-task-risk-associations/job-task-risk-associations-no.xlsx', file_get_contents(
        jobTaskRiskAssociationImportFile([
            ['TASK-001', 'Logistica', 'basso', 'NO'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_JOB_TASK_RISK_ASSOCIATIONS,
        'file_path' => 'imports/job-task-risk-associations/job-task-risk-associations-no.xlsx',
        'original_file_name' => 'associazioni-no.xlsx',
    ]);

    app(ImportJobTaskRiskAssociationsJob::class, ['importazioneId' => $importazione->getKey()])
        ->handle(app(JobTaskRiskAssociationImportService::class));

    $pivot = DB::table('job_task_job_sector')
        ->where('job_task_id', $jobTask->getKey())
        ->where('job_sector_id', $jobSector->getKey())
        ->first();

    expect($pivot)->not->toBeNull()
        ->and($pivot->task_risk_level)->toBe('low')
        ->and((bool) $pivot->sector_risk_override)->toBeFalse();
});

it('fails job task risk association import when required data is missing', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    Storage::disk('s3')->put('imports/job-task-risk-associations/invalid-job-task-risk-associations.xlsx', file_get_contents(
        jobTaskRiskAssociationImportFile([
            ['TASK-001', null, 'alto', 'SI'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_JOB_TASK_RISK_ASSOCIATIONS,
        'file_path' => 'imports/job-task-risk-associations/invalid-job-task-risk-associations.xlsx',
        'original_file_name' => 'associazioni-non-valide.xlsx',
    ]);

    app(ImportJobTaskRiskAssociationsJob::class, ['importazioneId' => $importazione->getKey()])
        ->handle(app(JobTaskRiskAssociationImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FAILED)
        ->and($importazione->fresh()->error_message)->toContain('Riga 2');
});

function jobTaskRiskAssociationImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([[
        'Codice mansione',
        'Nome settore',
        'Livello di rischio',
        'Sovrascrivi rischio settore',
    ]]);

    foreach ($rows as $index => $row) {
        $sheet->fromArray([$row], null, 'A'.($index + 2));
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'job-task-risk-association-import-');
    (new Xlsx($spreadsheet))->save($temporaryFile);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile(
        $temporaryFile,
        'associazione-mansioni-rischio.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}
