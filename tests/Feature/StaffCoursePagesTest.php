<?php

use App\Models\Course;
use App\Models\CourseClass;
use App\Models\CourseClassTeacher;
use App\Models\CourseClassUser;
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

it('returns published teacher dashboard course classes with participant counts', function () {
    $teacher = actingAsRole('teacher');

    $publishedCourse = Course::factory()->create([
        'title' => 'Corso pubblicato docente',
        'type' => 'res',
        'status' => 'draft',
    ]);
    $draftCourse = Course::factory()->create([
        'title' => 'Corso bozza docente',
        'type' => 'res',
        'status' => 'draft',
    ]);

    $publishedModule = Module::factory()->create([
        'belongsTo' => (string) $publishedCourse->getKey(),
        'type' => Module::TYPE_RESIDENTIAL,
        'status' => 'published',
    ]);
    $draftModule = Module::factory()->create([
        'belongsTo' => (string) $draftCourse->getKey(),
        'type' => Module::TYPE_RESIDENTIAL,
    ]);

    $publishedClass = CourseClass::factory()->forModule($publishedModule)->create([
        'name' => 'Aula Roma 1',
    ]);
    $draftClass = CourseClass::factory()->forModule($draftModule)->create([
        'name' => 'Aula nascosta',
    ]);

    CourseClassTeacher::factory()->create([
        'course_class_id' => $publishedClass->getKey(),
        'user_id' => $teacher->getKey(),
    ]);
    CourseClassTeacher::factory()->create([
        'course_class_id' => $draftClass->getKey(),
        'user_id' => $teacher->getKey(),
    ]);

    foreach ([1 => 40, 2 => 80] as $index => $completionPercentage) {
        $participant = User::query()->create([
            'name' => 'Partecipante',
            'surname' => 'Pubblicato '.$index,
            'email' => "published-participant-{$index}@example.test",
            'fiscal_code' => sprintf('PBLCIP80A01H5%03dZ', $index),
            'password' => Hash::make('password'),
            'email_verified_at' => now(),
            'account_state' => 'active',
            'profile_completed_at' => now(),
            'is_foreigner_or_immigrant' => false,
        ]);

        CourseClassUser::query()->create([
            'course_class_id' => $publishedClass->getKey(),
            'user_id' => $participant->getKey(),
            'assigned_at' => now(),
        ]);

        CourseEnrollment::query()->create([
            'user_id' => $participant->getKey(),
            'course_id' => $publishedCourse->getKey(),
            'status' => CourseEnrollment::STATUS_IN_PROGRESS,
            'assigned_at' => now(),
            'completion_percentage' => $completionPercentage,
        ]);
    }

    $draftParticipant = User::query()->create([
        'name' => 'Partecipante',
        'surname' => 'Bozza',
        'email' => 'draft-participant@example.test',
        'fiscal_code' => 'DRFTPT80A01H501Z',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'is_foreigner_or_immigrant' => false,
    ]);

    CourseClassUser::query()->create([
        'course_class_id' => $draftClass->getKey(),
        'user_id' => $draftParticipant->getKey(),
        'assigned_at' => now(),
    ]);

    $publishedCourse->update([
        'status' => 'published',
    ]);

    $this->getJson(route('teacher.dashboard.your-courses'))
        ->assertSuccessful()
        ->assertJsonCount(1, 'courses')
        ->assertJsonPath('courses.0.title', 'Corso pubblicato docente')
        ->assertJsonPath('courses.0.class_name', 'Aula Roma 1')
        ->assertJsonPath('courses.0.participants_count', 2)
        ->assertJsonPath('courses.0.occupancy_label', '2/30 posti')
        ->assertJsonPath('courses.0.completion_percentage', 60);
});

it('uses fake teacher courses endpoint in dashboard when test flag is enabled', function () {
    actingAsRole('teacher');

    $this->get(route('teacher.dashboard', ['test' => 1]))
        ->assertSuccessful()
        ->assertSee(route('teacher.dashboard.your-courses.fake'), escape: false);
});

it('returns fake teacher dashboard courses payload', function () {
    actingAsRole('teacher');

    $this->getJson(route('teacher.dashboard.your-courses.fake'))
        ->assertSuccessful()
        ->assertJsonCount(3, 'courses')
        ->assertJsonPath('courses.0.title', 'React Avanzato')
        ->assertJsonPath('courses.0.participants_count', 18)
        ->assertJsonPath('courses.0.completion_percentage', 72);
});
