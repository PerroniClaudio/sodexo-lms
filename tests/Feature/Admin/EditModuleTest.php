<?php

use App\Models\Course;
use App\Models\CourseTeacherEnrollment;
use App\Models\CourseTutorEnrollment;
use App\Models\Module;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
});

it('shows the edit module page', function () {
    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);
    $teacher = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.test',
    ]);
    $teacher->assignRole('docente');

    $availableTeacher = User::factory()->create([
        'name' => 'Giulia',
        'surname' => 'Neri',
        'email' => 'giulia.neri@example.test',
    ]);
    $availableTeacher->assignRole('docente');

    $assignedTutor = User::factory()->create([
        'name' => 'Paolo',
        'surname' => 'Blu',
        'email' => 'paolo.blu@example.test',
    ]);
    $assignedTutor->assignRole('tutor');

    $availableTutor = User::factory()->create([
        'name' => 'Sara',
        'surname' => 'Gialli',
        'email' => 'sara.gialli@example.test',
    ]);
    $availableTutor->assignRole('tutor');

    CourseTeacherEnrollment::factory()->create([
        'course_id' => $course->getKey(),
        'user_id' => $teacher->getKey(),
    ]);

    CourseTutorEnrollment::factory()->create([
        'course_id' => $course->getKey(),
        'user_id' => $assignedTutor->getKey(),
    ]);

    $module = Module::factory()->create([
        'title' => 'Modulo iniziale',
        'description' => 'Descrizione modulo',
        'type' => 'live',
        'is_live_teacher' => true,
        'status' => 'draft',
        'appointment_date' => Carbon::parse('2026-05-20 00:00:00'),
        'appointment_start_time' => Carbon::parse('2026-05-20 14:30:00'),
        'appointment_end_time' => Carbon::parse('2026-05-20 16:00:00'),
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.edit', [$course, $module]));

    $response->assertOk();
    $response->assertViewHas('moduleEditView', 'admin.module.types.live');
    $response->assertSeeText('Modifica modulo');
    $response->assertSeeText('Corso: Corso sicurezza. Tipologia: Live.');
    $response->assertSee('value="Modulo iniziale"', escape: false);
    $response->assertSeeText('Descrizione modulo');
    $response->assertSee('name="status"', escape: false);
    $response->assertSeeText('Bozza');
    $response->assertSeeText('Pubblicato');
    $response->assertSeeText('Archiviato');
    $response->assertSee('name="is_live_teacher"', escape: false);
    $response->assertSee('checked', escape: false);
    $response->assertSee('name="appointment_date"', escape: false);
    $response->assertSee('value="2026-05-20"', escape: false);
    $response->assertSee('name="appointment_start_time"', escape: false);
    $response->assertSee('value="14:30"', escape: false);
    $response->assertSee('name="appointment_end_time"', escape: false);
    $response->assertSee('value="16:00"', escape: false);
    $response->assertSeeText('Docenti assegnati alla live');
    $response->assertSeeText('Mario Rossi');
    $response->assertSeeText('mario.rossi@example.test');
    $response->assertSee('data-open-teacher-assignment-modal', escape: false);
    $response->assertSee('id="assign-teachers-modal"', escape: false);
    $response->assertSeeText('Giulia Neri');
    $response->assertSee('name="teacher_ids[]"', escape: false);
    $response->assertSeeText('Tutor assegnati alla live');
    $response->assertSeeText('Paolo Blu');
    $response->assertSeeText('paolo.blu@example.test');
    $response->assertSee('data-open-tutor-assignment-modal', escape: false);
    $response->assertSee('id="assign-tutors-modal"', escape: false);
    $response->assertSeeText('Sara Gialli');
    $response->assertSee('name="tutor_ids[]"', escape: false);
    $response->assertSeeText('Salva modulo');

    $availableTeachers = $response->viewData('availableTeachers');

    expect($availableTeachers->pluck('id')->all())->toContain($availableTeacher->getKey());
    expect($availableTeachers->pluck('id')->all())->not->toContain($teacher->getKey());

    $availableTutors = $response->viewData('availableTutors');

    expect($availableTutors->pluck('id')->all())->toContain($availableTutor->getKey());
    expect($availableTutors->pluck('id')->all())->not->toContain($assignedTutor->getKey());
});

it('does not show the editable title field for quiz modules', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'title' => 'Quiz di gradimento',
        'type' => 'satisfaction_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.edit', [$course, $module]));

    $response->assertOk();
    $response->assertViewHas('moduleEditView', 'admin.module.types.satisfaction_quiz');
    $response->assertDontSee('name="title"', escape: false);
    $response->assertDontSee('name="is_live_teacher"', escape: false);
    $response->assertDontSee('name="appointment_date"', escape: false);
    $response->assertDontSee('name="appointment_start_time"', escape: false);
    $response->assertDontSee('name="appointment_end_time"', escape: false);
});

it('resolves the dedicated edit view for video modules', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'video',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.edit', [$course, $module]));

    $response->assertOk();
    $response->assertViewHas('moduleEditView', 'admin.module.types.video');
    $response->assertSee('name="title"', escape: false);
    $response->assertDontSee('name="is_live_teacher"', escape: false);
    $response->assertDontSee('name="appointment_date"', escape: false);
});
