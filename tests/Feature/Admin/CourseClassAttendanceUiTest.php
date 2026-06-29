<?php

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassSchedule;
use App\Models\CourseClassUser;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
});

it('shows attendance management only for ended classes', function () {
    $course = Course::factory()->res()->create();
    $module = Module::factory()->create(['type' => 'res', 'belongsTo' => (string) $course->getKey()]);
    $courseClass = CourseClass::factory()->forModule($module)->create(['name' => 'Classe conclusa']);
    $futureClass = CourseClass::factory()->forModule($module)->create(['name' => 'Classe futura']);
    CourseClassSchedule::query()->where('course_class_id', $courseClass->getKey())->delete();
    CourseClassSchedule::factory()->forCourseClass($courseClass)->create([
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
    ]);
    CourseClassSchedule::query()->where('course_class_id', $futureClass->getKey())->delete();
    CourseClassSchedule::factory()->forCourseClass($futureClass)->create([
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDays(2),
    ]);

    $this->get(route('admin.courses.classes.edit', [$course, $courseClass]))
        ->assertOk()
        ->assertSeeText('Gestisci presenze')
        ->assertSee(route('admin.courses.classes.attendance', [$course, $courseClass]), false);

    $this->get(route('admin.courses.classes.edit', [$course, $futureClass]))
        ->assertOk()
        ->assertDontSeeText('Gestisci presenze');

    $this->get(route('admin.courses.classes.attendance', [$course, $futureClass]))
        ->assertNotFound();
});

it('renders the attendance UI for assigned users', function () {
    $course = Course::factory()->res()->create();
    $courseClass = CourseClass::factory()->forCourse($course)->create(['name' => 'Classe A']);
    CourseClassSchedule::query()->where('course_class_id', $courseClass->getKey())->delete();
    CourseClassSchedule::factory()->forCourseClass($courseClass)->create([
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
    ]);

    $user = User::factory()->asUser()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);

    CourseClassUser::factory()->for($courseClass)->for($user)->create();

    $this->get(route('admin.courses.classes.attendance', [$course, $courseClass]))
        ->assertOk()
        ->assertViewIs('admin.course-classes.attendance')
        ->assertSeeText('Gestisci presenze')
        ->assertSeeText('Presenze registrate')
        ->assertSeeText('Partecipanti')
        ->assertSeeText('Importa presenze')
        ->assertSeeText('Mario')
        ->assertSeeText('Rossi')
        ->assertSeeText('RSSMRA80A01H501Z')
        ->assertSeeText('Scarica template')
        ->assertSeeText('Importa presenze');
});

it('imports attendance from excel and ignores users with existing records', function () {
    $course = Course::factory()->res()->create();
    $courseClass = CourseClass::factory()->forCourse($course)->create();
    CourseClassSchedule::query()->where('course_class_id', $courseClass->getKey())->delete();
    CourseClassSchedule::factory()->forCourseClass($courseClass)->create([
        'starts_at' => now()->subDays(2)->setTime(9, 0),
        'ends_at' => now()->subDay()->setTime(17, 0),
    ]);

    $freshUser = User::factory()->asUser()->create(['name' => 'Mario']);
    $existingUser = User::factory()->asUser()->create(['name' => 'Luca']);
    CourseClassUser::factory()->for($courseClass)->for($freshUser)->create();
    CourseClassUser::factory()->for($courseClass)->for($existingUser)->create();

    DB::table('course_attendance_records')->insert([
        'user_id' => $existingUser->getKey(),
        'course_id' => $course->getKey(),
        'type' => 'entry',
        'session_id' => fake()->uuid(),
        'created_by_user_id' => auth()->id(),
        'recorded_at' => now()->subDay(),
    ]);

    $this->get(route('admin.courses.classes.attendance', [$course, $courseClass]))
        ->assertOk()
        ->assertSeeText('Mario')
        ->assertSeeText('Luca')
        ->assertSeeText('Presenze registrate')
        ->assertSeeText('Partecipanti');

    $this->post(route('admin.courses.classes.attendance.store', [$course, $courseClass]), [
        'attendance_file' => attendanceImportFile([
            ['Mario', $freshUser->surname, $freshUser->fiscal_code, '09:00', '12:00'],
            ['Mario', $freshUser->surname, $freshUser->fiscal_code, '13:00', '17:00'],
            ['Luca', $existingUser->surname, $existingUser->fiscal_code, '08:00', '10:00'],
        ]),
    ])->assertRedirect(route('admin.courses.classes.attendance', [$course, $courseClass]));

    expect(DB::table('course_attendance_records')->where('user_id', $freshUser->getKey())->count())->toBe(4)
        ->and(DB::table('course_attendance_records')->where('user_id', $existingUser->getKey())->count())->toBe(1);

    $this->get(route('admin.courses.classes.attendance', [$course, $courseClass]))
        ->assertOk()
        ->assertSeeText('Tutte le presenze sono già state create.')
        ->assertDontSeeText('Nessun utente assegnato alla classe.');
});

it('downloads an attendance import template', function () {
    $course = Course::factory()->res()->create();
    $courseClass = CourseClass::factory()->forCourse($course)->create();
    CourseClassSchedule::query()->where('course_class_id', $courseClass->getKey())->delete();
    CourseClassSchedule::factory()->forCourseClass($courseClass)->create([
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
    ]);

    $user = User::factory()->asUser()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'RSSMRA80A01H501Z',
    ]);
    CourseClassUser::factory()->for($courseClass)->for($user)->create();

    $response = $this->get(route('admin.courses.classes.attendance.template', [$course, $courseClass]));

    $response->assertOk()->assertDownload('template-presenze.xlsx');

    $temporaryFile = tempnam(sys_get_temp_dir(), 'attendance-template-');
    file_put_contents($temporaryFile, $response->streamedContent());
    $spreadsheet = IOFactory::load($temporaryFile);

    expect($spreadsheet->getSheetCount())->toBe(2)
        ->and($spreadsheet->getSheet(0)->getTitle())->toBe('Presenze')
        ->and($spreadsheet->getSheet(0)->getCell('A1')->getValue())->toBe('nome')
        ->and($spreadsheet->getSheet(0)->getCell('C2')->getValue())->toBe('RSSMRA80A01H501Z')
        ->and($spreadsheet->getSheet(1)->getTitle())->toBe('Partecipanti')
        ->and($spreadsheet->getSheet(1)->getCell('A1')->getValue())->toBe('nome')
        ->and($spreadsheet->getSheet(1)->getCell('C2')->getValue())->toBe('RSSMRA80A01H501Z');

    $spreadsheet->disconnectWorksheets();
});

it('uploads and replaces the paper attendance register', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $course = Course::factory()->res()->create();
    $courseClass = CourseClass::factory()->forCourse($course)->create();
    CourseClassSchedule::query()->where('course_class_id', $courseClass->getKey())->delete();
    CourseClassSchedule::factory()->forCourseClass($courseClass)->create([
        'starts_at' => now()->subDays(2),
        'ends_at' => now()->subDay(),
    ]);

    $this->get(route('admin.courses.classes.attendance', [$course, $courseClass]))
        ->assertOk()
        ->assertSeeText('Registro presenze cartaceo')
        ->assertSeeText('Nessun registro caricato.');

    $this->post(route('admin.courses.classes.attendance.register.store', [$course, $courseClass]), [
        'register_file' => UploadedFile::fake()->create('registro.pdf', 128, 'application/pdf'),
    ])->assertRedirect(route('admin.courses.classes.attendance', [$course, $courseClass]));

    $firstFile = DB::table('course_class_attendance_register_files')->where('course_class_id', $courseClass->getKey())->first();

    expect($firstFile->original_name)->toBe('registro.pdf');
    Storage::disk('s3')->assertExists($firstFile->path);

    $this->post(route('admin.courses.classes.attendance.register.store', [$course, $courseClass]), [
        'register_file' => UploadedFile::fake()->image('registro-nuovo.jpg'),
    ])->assertRedirect(route('admin.courses.classes.attendance', [$course, $courseClass]));

    $replacementFile = DB::table('course_class_attendance_register_files')->where('course_class_id', $courseClass->getKey())->first();

    expect(DB::table('course_class_attendance_register_files')->where('course_class_id', $courseClass->getKey())->count())->toBe(1)
        ->and($replacementFile->original_name)->toBe('registro-nuovo.jpg');
    Storage::disk('s3')->assertMissing($firstFile->path);
    Storage::disk('s3')->assertExists($replacementFile->path);
});

function attendanceImportFile(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet;
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray([[
        'nome',
        'cognome',
        'codice fiscale',
        'orario entrata',
        'orario uscita',
    ]]);

    foreach ($rows as $index => $row) {
        $sheet->fromArray([$row], null, 'A'.($index + 2));
    }

    $temporaryFile = tempnam(sys_get_temp_dir(), 'attendance-import-');
    (new Xlsx($spreadsheet))->save($temporaryFile);
    $spreadsheet->disconnectWorksheets();

    return new UploadedFile($temporaryFile, 'presenze.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}
