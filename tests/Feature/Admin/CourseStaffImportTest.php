<?php

use App\Jobs\ImportCourseStaffJob;
use App\Models\Course;
use App\Models\CourseTeacherEnrollment;
use App\Models\CourseTutorEnrollment;
use App\Models\Importazione;
use App\Models\User;
use App\Services\CourseStaffImportService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

it('shows the course staff import page and links it from teacher and tutor sections', function () {
    $course = Course::factory()->create();

    $this->get(route('admin.imports.course-staff'))
        ->assertOk()
        ->assertSeeText('Docenti e tutor corsi')
        ->assertSeeText('Anagrafiche organizzative')
        ->assertSeeText('Associazioni')
        ->assertSee(route('admin.imports.course-staff.template'), escape: false)
        ->assertSee(route('admin.imports.course-staff.store'), escape: false);

    foreach (['teachers', 'tutors'] as $section) {
        $this->get(route('admin.courses.edit', [$course, 'section' => $section]))
            ->assertOk()
            ->assertSeeText('Importa Excel')
            ->assertSee(route('admin.imports.course-staff'), escape: false);
    }
});

it('queues a course staff import from an excel upload', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');
    Queue::fake();

    $response = $this->post(route('admin.imports.course-staff.store'), [
        'file' => courseStaffImportFile([
            ['Email', 'Codice corso', 'Ruolo'],
            ['docente@example.com', 'COURSE-001', 'docente'],
        ]),
    ]);

    $response
        ->assertRedirect(route('admin.imports.course-staff'))
        ->assertSessionHas('status');

    $importazione = Importazione::query()
        ->where('import_type', Importazione::TYPE_COURSE_STAFF)
        ->where('created_by', auth()->id())
        ->latest('id')
        ->firstOrFail();

    Storage::disk('s3')->assertExists($importazione->file_path);
    Queue::assertPushed(ImportCourseStaffJob::class, fn (ImportCourseStaffJob $job): bool => $job->importazioneId === $importazione->getKey());
});

it('downloads the course staff import template', function () {
    Course::factory()->create(['code' => 'STAFF-'.fake()->unique()->numerify('#####'), 'title' => 'Corso staff']);

    $response = $this->get(route('admin.imports.course-staff.template'));

    $temporaryFile = tempnam(sys_get_temp_dir(), 'course-staff-template-test-');
    file_put_contents($temporaryFile, $response->streamedContent());
    $spreadsheet = IOFactory::load($temporaryFile);
    $sheet = $spreadsheet->getSheetByName('Docenti e tutor corsi');

    expect($response->headers->get('content-type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($sheet?->getCell('A1')->getValue())->toBe('Email')
        ->and($sheet?->getCell('B1')->getValue())->toBe('Codice corso')
        ->and($sheet?->getCell('C1')->getValue())->toBe('Ruolo');

    $spreadsheet->disconnectWorksheets();
    @unlink($temporaryFile);
});

it('imports teachers and tutors at course level without duplicates', function () {
    $course = Course::factory()->create(['code' => 'STAFF-'.fake()->unique()->numerify('#####')]);
    $teacher = User::factory()->create(['email' => fake()->unique()->safeEmail()]);
    $teacher->assignRole('teacher');
    $tutor = User::factory()->create(['email' => fake()->unique()->safeEmail()]);
    $tutor->assignRole('tutor');

    $file = courseStaffImportFile([
        ['Email', 'Codice corso', 'Ruolo'],
        [$teacher->email, $course->code, 'docente'],
        [$tutor->email, $course->code, 'tutor'],
    ]);

    $summary = app(CourseStaffImportService::class)->import($file->getRealPath());
    app(CourseStaffImportService::class)->import($file->getRealPath());

    expect($summary)->toBe(['processed_records' => 2])
        ->and(CourseTeacherEnrollment::query()->whereBelongsTo($course)->whereBelongsTo($teacher)->count())->toBe(1)
        ->and(CourseTutorEnrollment::query()->whereBelongsTo($course)->whereBelongsTo($tutor)->count())->toBe(1);
});

it('rejects a course staff assignment when the user lacks the requested role', function () {
    $course = Course::factory()->create(['code' => 'STAFF-'.fake()->unique()->numerify('#####')]);
    $user = User::factory()->create(['email' => fake()->unique()->safeEmail()]);
    $file = courseStaffImportFile([
        ['Email', 'Codice corso', 'Ruolo'],
        [$user->email, $course->code, 'docente'],
    ]);

    expect(fn () => app(CourseStaffImportService::class)->import($file->getRealPath()))
        ->toThrow(ValidationException::class, 'non ha il ruolo docente');

    expect(CourseTeacherEnrollment::query()->whereBelongsTo($course)->whereBelongsTo($user)->exists())->toBeFalse();
});

function courseStaffImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();

    foreach ($rows as $index => $row) {
        $sheet->fromArray([$row], null, 'A'.($index + 1));
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'course-staff-import-test-');
    (new Xlsx($spreadsheet))->save($temporaryFile);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile(
        $temporaryFile,
        'docenti-tutor-corsi.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}
