<?php

use App\Jobs\ImportTrainingPathsJob;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Importazione;
use App\Models\JobRole;
use App\Models\TrainingPath;
use App\Models\TrainingPathEnrollment;
use App\Models\User;
use App\Services\TrainingPathImportService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('queues a training path import from excel upload', function () {
    Storage::fake('s3');
    Queue::fake();

    actingAsRole('admin');

    $response = $this->post(route('admin.imports.user-training-paths.store'), [
        'file' => trainingPathImportFile([
            ['Codice fiscale', 'Codice percorso formativo'],
            ['RSSMRA80A01H501Z', 'PATH-001'],
        ]),
    ]);

    $response
        ->assertRedirect(route('admin.imports.user-training-paths'))
        ->assertSessionHas('status');

    $importazione = Importazione::query()->sole();

    expect($importazione->import_type)->toBe(Importazione::TYPE_USER_TRAINING_PATHS)
        ->and($importazione->status)->toBe(Importazione::STATUS_PENDING)
        ->and($importazione->created_by)->toBe(auth()->id())
        ->and($importazione->original_file_name)->toBe('associa-utenti-percorsi.xlsx');

    Storage::disk('s3')->assertExists($importazione->file_path);

    Queue::assertPushed(ImportTrainingPathsJob::class, fn (ImportTrainingPathsJob $job): bool => $job->importazioneId === $importazione->getKey());
});

it('downloads training path import template', function () {
    actingAsRole('admin');

    TrainingPath::factory()->create([
        'code' => 'PATH-001',
        'title' => 'Percorso sicurezza',
        'status' => 'published',
    ]);

    TrainingPath::factory()->create([
        'code' => 'PATH-002',
        'title' => 'Percorso onboarding',
        'status' => 'draft',
    ]);

    $response = $this->get(route('admin.imports.user-training-paths.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('content-disposition', 'attachment; filename=template-import-associazione-utenti-percorsi-formativi.xlsx');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'template-training-path-import-test-');
    file_put_contents($temporaryFile, $response->streamedContent());

    $spreadsheet = IOFactory::load($temporaryFile);
    $importSheet = $spreadsheet->getSheetByName('Associa percorsi utenti');
    $lookupSheet = $spreadsheet->getSheetByName('Percorsi disponibili');

    expect($importSheet?->getCell('A1')->getValue())->toBe('Codice fiscale')
        ->and($importSheet?->getCell('B1')->getValue())->toBe('Codice percorso formativo')
        ->and($lookupSheet?->getCell('A2')->getValue())->toBe('PATH-001')
        ->and($lookupSheet?->getCell('B2')->getValue())->toBe('Percorso sicurezza')
        ->and($lookupSheet?->getCell('C2')->getValue())->toBe('published')
        ->and($lookupSheet?->getCell('A3')->getValue())->toBe('PATH-002');

    $spreadsheet->disconnectWorksheets();
    @unlink($temporaryFile);
});

it('enrolls user into published training path and linked courses', function () {
    Storage::fake('s3');

    test()->seed(RoleAndPermissionSeeder::class);

    $course = Course::factory()->published()->create();
    $trainingPath = TrainingPath::factory()->create([
        'code' => 'PATH-001',
        'status' => 'published',
    ]);
    $trainingPath->courses()->attach($course->getKey(), ['sort_order' => 1]);

    $user = User::factory()->asUser()->state([
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ])->create();

    Storage::disk('s3')->put('imports/user-training-paths/user-training-paths.xlsx', file_get_contents(
        trainingPathImportFile([
            ['Codice fiscale', 'Codice percorso formativo'],
            ['RSSMRA80A01H501Z', 'PATH-001'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_USER_TRAINING_PATHS,
        'file_path' => 'imports/user-training-paths/user-training-paths.xlsx',
        'original_file_name' => 'associazioni-percorsi.xlsx',
    ]);

    app(ImportTrainingPathsJob::class, ['importazioneId' => $importazione->getKey()])
        ->handle(app(TrainingPathImportService::class));

    $trainingPathEnrollment = TrainingPathEnrollment::query()->sole();
    $courseEnrollment = CourseEnrollment::query()->sole();

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FINISHED)
        ->and($trainingPathEnrollment->user_id)->toBe($user->getKey())
        ->and($trainingPathEnrollment->training_path_id)->toBe($trainingPath->getKey())
        ->and($courseEnrollment->user_id)->toBe($user->getKey())
        ->and($courseEnrollment->course_id)->toBe($course->getKey())
        ->and($courseEnrollment->pathway_origin)->toBeTrue()
        ->and($courseEnrollment->direct_origin)->toBeFalse();
});

it('fails the import when the training path contains a linked course not assignable to the user', function () {
    Storage::fake('s3');

    test()->seed(RoleAndPermissionSeeder::class);

    $allowedRole = JobRole::factory()->create();
    $otherRole = JobRole::factory()->create();
    $course = Course::factory()->published()->create([
        'title' => 'Corso riservato',
        'visible_to_all' => false,
    ]);
    $course->jobRoles()->attach($allowedRole);

    $trainingPath = TrainingPath::factory()->create([
        'code' => 'PATH-001',
        'status' => 'published',
    ]);
    $trainingPath->courses()->attach($course->getKey(), ['sort_order' => 1]);

    $user = User::factory()->asUser()->state([
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'job_role_id' => $otherRole->getKey(),
    ])->create();

    Storage::disk('s3')->put('imports/user-training-paths/user-training-paths.xlsx', file_get_contents(
        trainingPathImportFile([
            ['Codice fiscale', 'Codice percorso formativo'],
            ['RSSMRA80A01H501Z', 'PATH-001'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_USER_TRAINING_PATHS,
        'file_path' => 'imports/user-training-paths/user-training-paths.xlsx',
        'original_file_name' => 'associazioni-percorsi.xlsx',
    ]);

    app(ImportTrainingPathsJob::class, ['importazioneId' => $importazione->getKey()])
        ->handle(app(TrainingPathImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FAILED)
        ->and($importazione->fresh()->error_message)->toContain('Il percorso contiene un corso non assegnabile')
        ->and(TrainingPathEnrollment::query()->count())->toBe(0)
        ->and(CourseEnrollment::query()->count())->toBe(0);
});

function trainingPathImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    foreach ($rows as $index => $row) {
        $sheet->fromArray([$row], null, 'A'.($index + 1));
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'training-path-import-');
    (new Xlsx($spreadsheet))->save($temporaryFile);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile(
        $temporaryFile,
        'associa-utenti-percorsi.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}
