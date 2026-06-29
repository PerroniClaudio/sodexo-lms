<?php

use App\Jobs\ImportUserJobTasksJob;
use App\Models\Importazione;
use App\Models\JobTask;
use App\Models\User;
use App\Services\UserJobTaskImportService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('queues a user job task import from excel upload', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Queue::fake();

    actingAsRole('admin');

    $response = $this->post(route('admin.imports.user-job-tasks.store'), [
        'file' => userJobTaskImportFile(
            ['Codice fiscale', 'TASK-001'],
            [['RSSMRA80A01H501Z', 'SI']],
        ),
    ]);

    $response
        ->assertRedirect(route('admin.imports.user-job-tasks'))
        ->assertSessionHas('status');

    $importazione = Importazione::query()->sole();

    expect($importazione->import_type)->toBe(Importazione::TYPE_USER_JOB_TASKS)
        ->and($importazione->status)->toBe(Importazione::STATUS_PENDING)
        ->and($importazione->created_by)->toBe(auth()->id())
        ->and($importazione->original_file_name)->toBe('associa-utenti-mansioni.xlsx');

    Storage::disk('s3')->assertExists($importazione->file_path);

    Queue::assertPushed(ImportUserJobTasksJob::class, fn (ImportUserJobTasksJob $job): bool => $job->importazioneId === $importazione->getKey());
});

it('downloads user job task import template', function () {
    actingAsRole('admin');

    JobTask::factory()->create(['name' => 'Addetto magazzino', 'code' => 'TASK-001']);
    JobTask::factory()->create(['name' => 'Cassiere', 'code' => 'TASK-002']);

    $response = $this->get(route('admin.imports.user-job-tasks.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('content-disposition', 'attachment; filename=template-import-associazione-utenti-mansioni.xlsx');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'template-user-job-task-import-test-');
    file_put_contents($temporaryFile, $response->streamedContent());

    $spreadsheet = IOFactory::load($temporaryFile);
    $importSheet = $spreadsheet->getSheetByName('Associa mansioni utenti');
    $lookupSheet = $spreadsheet->getSheetByName('Mansioni disponibili');

    expect($importSheet?->getCell('A1')->getValue())->toBe('Codice fiscale')
        ->and($importSheet?->getCell('B1')->getValue())->toBe('TASK-001')
        ->and($importSheet?->getCell('C1')->getValue())->toBe('TASK-002')
        ->and($lookupSheet?->getCell('A2')->getValue())->toBe('TASK-001')
        ->and($lookupSheet?->getCell('B2')->getValue())->toBe('Addetto magazzino')
        ->and($lookupSheet?->getCell('A3')->getValue())->toBe('TASK-002')
        ->and($lookupSheet?->getCell('B3')->getValue())->toBe('Cassiere');

    $spreadsheet->disconnectWorksheets();
    @unlink($temporaryFile);
});

it('associates new job tasks to existing workers without removing existing ones', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    test()->seed(RoleAndPermissionSeeder::class);

    $existingTask = JobTask::factory()->create(['code' => 'TASK-001']);
    $newTask = JobTask::factory()->create(['code' => 'TASK-002']);

    $user = User::factory()->asUser()->state([
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'employment_start_date' => '2024-01-01',
        'employment_end_date' => null,
        'job_task_id' => $existingTask->getKey(),
    ])->create();

    $user->jobTasks()->sync([
        $existingTask->getKey() => [
            'starts_at' => '2024-01-01',
            'ends_at' => null,
        ],
    ]);

    Storage::disk('s3')->put('imports/user-job-tasks/user-job-tasks.xlsx', file_get_contents(
        userJobTaskImportFile(
            ['Codice fiscale', 'TASK-001', 'TASK-002'],
            [['RSSMRA80A01H501Z', null, 'SI']],
        )->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_USER_JOB_TASKS,
        'file_path' => 'imports/user-job-tasks/user-job-tasks.xlsx',
        'original_file_name' => 'associazioni.xlsx',
    ]);

    app(ImportUserJobTasksJob::class, ['importazioneId' => $importazione->getKey()])
        ->handle(app(UserJobTaskImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FINISHED)
        ->and($user->fresh()->jobTasks()->pluck('job_tasks.id')->sort()->values()->all())->toBe([
            $existingTask->getKey(),
            $newTask->getKey(),
        ])
        ->and($user->fresh()->job_task_id)->toBe($existingTask->getKey());
});

it('ignores values different from SI', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    test()->seed(RoleAndPermissionSeeder::class);

    $existingTask = JobTask::factory()->create(['code' => 'TASK-001']);
    $newTask = JobTask::factory()->create(['code' => 'TASK-002']);

    $user = User::factory()->asUser()->state([
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'employment_start_date' => '2024-01-01',
        'employment_end_date' => null,
        'job_task_id' => $existingTask->getKey(),
    ])->create();

    $user->jobTasks()->sync([
        $existingTask->getKey() => [
            'starts_at' => '2024-01-01',
            'ends_at' => null,
        ],
    ]);

    Storage::disk('s3')->put('imports/user-job-tasks/user-job-tasks-invalid-flag.xlsx', file_get_contents(
        userJobTaskImportFile(
            ['Codice fiscale', 'TASK-002'],
            [['RSSMRA80A01H501Z', 'X']],
        )->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_USER_JOB_TASKS,
        'file_path' => 'imports/user-job-tasks/user-job-tasks-invalid-flag.xlsx',
        'original_file_name' => 'associazioni-invalid-flag.xlsx',
    ]);

    app(ImportUserJobTasksJob::class, ['importazioneId' => $importazione->getKey()])
        ->handle(app(UserJobTaskImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FAILED)
        ->and($importazione->fresh()->error_message)->toContain('Riga 2')
        ->and($user->fresh()->jobTasks()->pluck('job_tasks.id')->sort()->values()->all())->toBe([
            $existingTask->getKey(),
        ])
        ->and($user->fresh()->job_task_id)->toBe($existingTask->getKey());
});

function userJobTaskImportFile(array $headers, array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([$headers]);

    foreach ($rows as $index => $row) {
        $sheet->fromArray([$row], null, 'A'.($index + 2));
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'user-job-task-import-');
    (new Xlsx($spreadsheet))->save($temporaryFile);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile(
        $temporaryFile,
        'associa-utenti-mansioni.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}
