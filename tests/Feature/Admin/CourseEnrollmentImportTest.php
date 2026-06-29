<?php

use App\Jobs\ImportCourseEnrollmentsJob;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Importazione;
use App\Models\JobRole;
use App\Models\User;
use App\Services\CourseEnrollmentImportService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

it('shows the user courses import page and navigation entry', function () {
    actingAsRole('admin');

    $response = $this->get(route('admin.imports.user-courses'));

    $response->assertOk()
        ->assertSeeText('Associazione utenti corsi')
        ->assertSeeText('Utenti corsi')
        ->assertSee(route('admin.imports.user-courses.template'), escape: false)
        ->assertSee(route('admin.imports.user-courses.store'), escape: false);
});

it('queues a course enrollment import from excel upload', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Queue::fake();

    actingAsRole('admin');

    $response = $this->post(route('admin.imports.user-courses.store'), [
        'file' => courseEnrollmentImportFile([
            ['Codice fiscale', 'Codice corso'],
            ['RSSMRA80A01H501Z', 'COURSE-001'],
        ]),
    ]);

    $response
        ->assertRedirect(route('admin.imports.user-courses'))
        ->assertSessionHas('status');

    $importazione = Importazione::query()->sole();

    expect($importazione->import_type)->toBe(Importazione::TYPE_USER_COURSES)
        ->and($importazione->status)->toBe(Importazione::STATUS_PENDING)
        ->and($importazione->created_by)->toBe(auth()->id())
        ->and($importazione->original_file_name)->toBe('associa-utenti-corsi.xlsx');

    Storage::disk('s3')->assertExists($importazione->file_path);

    Queue::assertPushed(ImportCourseEnrollmentsJob::class, fn (ImportCourseEnrollmentsJob $job): bool => $job->importazioneId === $importazione->getKey());
});

it('downloads course enrollment import template', function () {
    actingAsRole('admin');

    Course::factory()->create([
        'code' => 'COURSE-001',
        'title' => 'Corso sicurezza',
        'status' => 'published',
    ]);

    Course::factory()->create([
        'code' => 'COURSE-002',
        'title' => 'Corso onboarding',
        'status' => 'draft',
    ]);

    $response = $this->get(route('admin.imports.user-courses.template'));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $response->assertHeader('content-disposition', 'attachment; filename=template-import-associazione-utenti-corsi.xlsx');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'template-course-import-test-');
    file_put_contents($temporaryFile, $response->streamedContent());

    $spreadsheet = IOFactory::load($temporaryFile);
    $importSheet = $spreadsheet->getSheetByName('Associa corsi utenti');
    $lookupSheet = $spreadsheet->getSheetByName('Corsi disponibili');

    expect($importSheet?->getCell('A1')->getValue())->toBe('Codice fiscale')
        ->and($importSheet?->getCell('B1')->getValue())->toBe('Codice corso')
        ->and($lookupSheet?->getCell('A2')->getValue())->toBe('COURSE-001')
        ->and($lookupSheet?->getCell('B2')->getValue())->toBe('Corso sicurezza')
        ->and($lookupSheet?->getCell('C2')->getValue())->toBe('published')
        ->and($lookupSheet?->getCell('A3')->getValue())->toBe('COURSE-002');

    $spreadsheet->disconnectWorksheets();
    @unlink($temporaryFile);
});

it('enrolls user into a published course through the import', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    test()->seed(RoleAndPermissionSeeder::class);

    $course = Course::factory()->published()->create([
        'code' => 'COURSE-001',
    ]);

    $user = User::factory()->asUser()->state([
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ])->create();

    Storage::disk('s3')->put('imports/user-courses/user-courses.xlsx', file_get_contents(
        courseEnrollmentImportFile([
            ['Codice fiscale', 'Codice corso'],
            ['RSSMRA80A01H501Z', 'COURSE-001'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_USER_COURSES,
        'file_path' => 'imports/user-courses/user-courses.xlsx',
        'original_file_name' => 'associazioni-corsi.xlsx',
    ]);

    app(ImportCourseEnrollmentsJob::class, ['importazioneId' => $importazione->getKey()])
        ->handle(app(CourseEnrollmentImportService::class));

    $enrollment = CourseEnrollment::query()->sole();

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FINISHED)
        ->and($enrollment->user_id)->toBe($user->getKey())
        ->and($enrollment->course_id)->toBe($course->getKey())
        ->and($enrollment->direct_origin)->toBeTrue()
        ->and($enrollment->pathway_origin)->toBeFalse();
});

it('fails the course enrollment import when the user is outside the configured recipients', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    test()->seed(RoleAndPermissionSeeder::class);

    $allowedRole = JobRole::factory()->create();
    $otherRole = JobRole::factory()->create();
    $course = Course::factory()->published()->create([
        'code' => 'COURSE-001',
        'title' => 'Corso riservato',
        'visible_to_all' => false,
    ]);
    $course->jobRoles()->attach($allowedRole);

    User::factory()->asUser()->state([
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'job_role_id' => $otherRole->getKey(),
    ])->create();

    Storage::disk('s3')->put('imports/user-courses/user-courses.xlsx', file_get_contents(
        courseEnrollmentImportFile([
            ['Codice fiscale', 'Codice corso'],
            ['RSSMRA80A01H501Z', 'COURSE-001'],
        ])->getRealPath()
    ));

    $importazione = Importazione::query()->create([
        'import_type' => Importazione::TYPE_USER_COURSES,
        'file_path' => 'imports/user-courses/user-courses.xlsx',
        'original_file_name' => 'associazioni-corsi.xlsx',
    ]);

    app(ImportCourseEnrollmentsJob::class, ['importazioneId' => $importazione->getKey()])
        ->handle(app(CourseEnrollmentImportService::class));

    expect($importazione->fresh()->status)->toBe(Importazione::STATUS_FAILED)
        ->and($importazione->fresh()->error_message)->toContain('L\'utente non rientra tra i destinatari del corso "Corso riservato"')
        ->and(CourseEnrollment::query()->count())->toBe(0);
});

it('shows course enrollment imports in the monitor', function () {
    actingAsRole('superadmin');

    Importazione::query()->create([
        'import_type' => Importazione::TYPE_USER_COURSES,
        'status' => Importazione::STATUS_FINISHED,
        'file_path' => 'imports/user-courses/example.xlsx',
        'original_file_name' => 'example.xlsx',
    ]);

    $this->get(route('admin.importazioni-monitor.index'))
        ->assertOk()
        ->assertSeeText('Associazione utenti corsi')
        ->assertSeeText('example.xlsx');
});

function courseEnrollmentImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    foreach ($rows as $index => $row) {
        $sheet->fromArray([$row], null, 'A'.($index + 1));
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'course-enrollment-import-');
    (new Xlsx($spreadsheet))->save($temporaryFile);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile(
        $temporaryFile,
        'associa-utenti-corsi.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}
