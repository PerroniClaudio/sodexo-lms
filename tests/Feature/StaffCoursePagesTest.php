<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleTeacherEnrollment;
use App\Models\ModuleTutorEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    config()->set('app.cipher', 'aes-256-cbc');
});

it('shows teacher courses derived from assigned modules', function () {
    $teacher = actingAsRole('teacher');

    $visibleCourse = Course::factory()->create([
        'title' => 'Corso visibile docente',
    ]);
    $hiddenCourse = Course::factory()->create([
        'title' => 'Corso nascosto docente',
    ]);

    $visibleLiveModule = Module::factory()->create([
        'belongsTo' => (string) $visibleCourse->getKey(),
        'title' => 'Live assegnata',
        'type' => 'live',
    ]);
    $visibleVideoModule = Module::factory()->create([
        'belongsTo' => (string) $visibleCourse->getKey(),
        'title' => 'Video assegnato',
        'type' => 'video',
    ]);
    $hiddenModule = Module::factory()->create([
        'belongsTo' => (string) $hiddenCourse->getKey(),
        'title' => 'Modulo non assegnato',
        'type' => 'video',
    ]);
    $unassignedVisibleModule = Module::factory()->create([
        'belongsTo' => (string) $visibleCourse->getKey(),
        'title' => 'Modulo visibile non assegnato',
        'type' => 'scorm',
    ]);

    ModuleTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'module_id' => $visibleLiveModule->getKey(),
    ]);
    ModuleTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'module_id' => $visibleVideoModule->getKey(),
    ]);

    $response = $this->get(route('teacher.courses.index'));

    $response->assertOk();
    $response->assertSeeText('Corso visibile docente');
    $response->assertDontSeeText('Corso nascosto docente');
    $response->assertSeeText('2');

    $detailResponse = $this->get(route('teacher.courses.show', $visibleCourse));

    $detailResponse->assertOk();
    $detailResponse->assertSeeText('Anno del corso');
    $detailResponse->assertSeeText('Data scadenza');
    $detailResponse->assertSeeText('Questionario di gradimento');
    $detailResponse->assertSeeText('Questionario obbligatorio');
    $detailResponse->assertSeeText('Moduli del corso');
    $detailResponse->assertSeeText('Live assegnata');
    $detailResponse->assertSeeText('Video assegnato');
    $detailResponse->assertSeeText('Modulo visibile non assegnato');
    $detailResponse->assertSeeText('Assegnato a te');
    $detailResponse->assertSeeText('Non assegnato');
    $detailResponse->assertSeeText('Assegnato il');
    $detailResponse->assertSeeText('Dettaglio modulo');
    $detailResponse->assertSeeText('Da implementare');
    $detailResponse->assertSeeText('Iscritti');
    $detailResponse->assertSee('data-staff-enrollments-table', escape: false);
    $detailResponse->assertSee(route('teacher.api.courses.enrollments.index', $visibleCourse), escape: false);
    $detailResponse->assertDontSeeText($hiddenModule->title);
});

it('shows tutor courses derived from assigned modules', function () {
    $tutor = actingAsRole('tutor');

    $visibleCourse = Course::factory()->create([
        'title' => 'Corso visibile tutor',
    ]);
    $hiddenCourse = Course::factory()->create([
        'title' => 'Corso nascosto tutor',
    ]);

    $visibleModule = Module::factory()->create([
        'belongsTo' => (string) $visibleCourse->getKey(),
        'title' => 'Modulo tutor assegnato',
        'type' => 'scorm',
    ]);
    $hiddenModule = Module::factory()->create([
        'belongsTo' => (string) $hiddenCourse->getKey(),
        'title' => 'Modulo tutor non assegnato',
        'type' => 'live',
    ]);
    $unassignedVisibleModule = Module::factory()->create([
        'belongsTo' => (string) $visibleCourse->getKey(),
        'title' => 'Modulo tutor visibile non assegnato',
        'type' => 'video',
    ]);

    ModuleTutorEnrollment::factory()->create([
        'user_id' => $tutor->getKey(),
        'module_id' => $visibleModule->getKey(),
    ]);

    $response = $this->get(route('tutor.courses.index'));

    $response->assertOk();
    $response->assertSeeText('Corso visibile tutor');
    $response->assertDontSeeText('Corso nascosto tutor');

    $detailResponse = $this->get(route('tutor.courses.show', $visibleCourse));

    $detailResponse->assertOk();
    $detailResponse->assertSeeText('Moduli del corso');
    $detailResponse->assertSeeText('Modulo tutor assegnato');
    $detailResponse->assertSeeText('Modulo tutor visibile non assegnato');
    $detailResponse->assertSeeText('Dettaglio modulo');
    $detailResponse->assertSeeText('Da implementare');
    $detailResponse->assertSeeText('Iscritti');
    $detailResponse->assertSee(route('tutor.api.courses.enrollments.index', $visibleCourse), escape: false);
    $detailResponse->assertSeeText('Assegnato il');
    $detailResponse->assertDontSeeText($hiddenModule->title);
});

it('returns read only enrollments api for teacher assigned course', function () {
    $teacher = actingAsRole('teacher');

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'type' => 'live',
    ]);

    ModuleTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'module_id' => $module->getKey(),
    ]);

    $enrolledUser = User::query()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.test',
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'is_foreigner_or_immigrant' => false,
    ]);

    CourseEnrollment::enroll($enrolledUser, $course);

    $response = $this->getJson(route('teacher.api.courses.enrollments.index', $course));

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.user.email', 'mario.rossi@example.test')
        ->assertJsonPath('data.0.status.key', CourseEnrollment::STATUS_ASSIGNED);
});

it('returns read only enrollments api for tutor assigned course', function () {
    $tutor = actingAsRole('tutor');

    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'belongsTo' => (string) $course->getKey(),
        'type' => 'video',
    ]);

    ModuleTutorEnrollment::factory()->create([
        'user_id' => $tutor->getKey(),
        'module_id' => $module->getKey(),
    ]);

    $enrolledUser = User::query()->create([
        'name' => 'Sara',
        'surname' => 'Blu',
        'email' => 'sara.blu@example.test',
        'fiscal_code' => 'BLUSRA80A01H501Z',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'is_foreigner_or_immigrant' => false,
    ]);

    CourseEnrollment::enroll($enrolledUser, $course);

    $response = $this->getJson(route('tutor.api.courses.enrollments.index', $course));

    $response->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.user.email', 'sara.blu@example.test');
});

it('forbids enrollments api for staff not assigned to course', function () {
    $teacher = actingAsRole('teacher');

    $course = Course::factory()->create();

    $this->getJson(route('teacher.api.courses.enrollments.index', $course))->assertForbidden();
});
