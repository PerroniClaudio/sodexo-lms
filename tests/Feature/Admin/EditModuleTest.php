<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LiveStreamAttendanceMinute;
use App\Models\LiveStreamSession;
use App\Models\Module;
use App\Models\ModuleTeacherEnrollment;
use App\Models\ModuleTutorEnrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
    config()->set('app.key', 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=');
    config()->set('app.cipher', 'aes-256-cbc');
});

it('shows the edit module page', function () {
    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);
    $teacher = User::query()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $teacher->assignRole('teacher');

    $availableTeacher = User::query()->create([
        'name' => 'Giulia',
        'surname' => 'Neri',
        'email' => 'giulia.neri@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => 'NERGLI80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $availableTeacher->assignRole('teacher');

    $assignedTutor = User::query()->create([
        'name' => 'Paolo',
        'surname' => 'Blu',
        'email' => 'paolo.blu@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => 'BLUPLA80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $assignedTutor->assignRole('tutor');

    $availableTutor = User::query()->create([
        'name' => 'Sara',
        'surname' => 'Gialli',
        'email' => 'sara.gialli@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => 'GLLSRA80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $availableTutor->assignRole('tutor');

    $participant = User::query()->create([
        'name' => 'Luca',
        'surname' => 'Verdi',
        'email' => 'luca.verdi@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => 'VRDLCU80A01H501Z',
        'is_foreigner_or_immigrant' => false,
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

    $teacherEnrollment = ModuleTeacherEnrollment::query()->create([
        'module_id' => $module->getKey(),
        'user_id' => $teacher->getKey(),
        'assigned_at' => now(),
    ]);

    $tutorEnrollment = ModuleTutorEnrollment::query()->create([
        'module_id' => $module->getKey(),
        'user_id' => $assignedTutor->getKey(),
        'assigned_at' => now(),
    ]);

    $session = LiveStreamSession::query()->create([
        'module_id' => $module->getKey(),
        'teacher_user_id' => $teacher->getKey(),
        'twilio_room_sid' => 'RM12345678901234567890123456789012',
        'twilio_room_name' => 'live-module-test-room',
        'status' => LiveStreamSession::STATUS_LIVE,
        'started_at' => now(),
    ]);

    CourseEnrollment::enroll($participant, $course);

    LiveStreamAttendanceMinute::query()->create([
        'live_stream_session_id' => $session->getKey(),
        'module_id' => $module->getKey(),
        'user_id' => $participant->getKey(),
        'minute_at' => Carbon::parse('2026-05-20 14:30:00'),
        'first_seen_at' => Carbon::parse('2026-05-20 14:30:05'),
        'last_seen_at' => Carbon::parse('2026-05-20 14:30:40'),
        'heartbeat_count' => 2,
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
    $response->assertSeeText('Docenti assegnati');
    $response->assertSeeText('I docenti assegnati potranno accedere e trasmettere le dirette.');
    $response->assertSeeText('Mario Rossi');
    $response->assertSeeText('mario.rossi@example.test');
    $response->assertSee('data-open-teacher-assignment-modal', escape: false);
    $response->assertSee('id="assign-teachers-modal"', escape: false);
    $response->assertSee(route('admin.courses.modules.teachers.destroy', [$course, $module, $teacherEnrollment]), escape: false);
    $response->assertSee('data-open-staff-removal-modal', escape: false);
    $response->assertSee('id="remove-teacher-modal-'.$teacherEnrollment->getKey().'"', escape: false);
    $response->assertSeeText('Conferma rimozione docente');
    $response->assertSeeText('Giulia Neri');
    $response->assertSee('name="teacher_ids[]"', escape: false);
    $response->assertSeeText('Tutor assegnati');
    $response->assertSeeText('I tutor assegnati potranno accedere e moderare le dirette.');
    $response->assertSeeText('Paolo Blu');
    $response->assertSeeText('paolo.blu@example.test');
    $response->assertSee('data-open-tutor-assignment-modal', escape: false);
    $response->assertSee('id="assign-tutors-modal"', escape: false);
    $response->assertSee(route('admin.courses.modules.tutors.destroy', [$course, $module, $tutorEnrollment]), escape: false);
    $response->assertSee('id="remove-tutor-modal-'.$tutorEnrollment->getKey().'"', escape: false);
    $response->assertSeeText('Conferma rimozione tutor');
    $response->assertSeeText('Sara Gialli');
    $response->assertSee('name="tutor_ids[]"', escape: false);
    $response->assertSeeText('Partecipazione alla live');
    $response->assertSeeText('Tempo registrato');
    $response->assertSeeText('Luca Verdi');
    $response->assertSeeText('00:01');
    $response->assertSee('data-open-attendance-confirmation-modal', escape: false);
    $response->assertSee('id="confirm-attendance-modal"', escape: false);
    $response->assertSeeText('Conferma partecipanti');
    $response->assertSee('name="effective_start_time"', escape: false);
    $response->assertSee('name="effective_end_time"', escape: false);
    $response->assertSee('name="minimum_attendance_percentage"', escape: false);
    $response->assertSeeText('Conferma presenti');
    $response->assertSeeText('Salva modulo');

    $availableTeachers = $response->viewData('availableTeachers');

    expect($availableTeachers->pluck('id')->all())->toContain($availableTeacher->getKey());
    expect($availableTeachers->pluck('id')->all())->not->toContain($teacher->getKey());

    $availableTutors = $response->viewData('availableTutors');

    expect($availableTutors->pluck('id')->all())->toContain($availableTutor->getKey());
    expect($availableTutors->pluck('id')->all())->not->toContain($assignedTutor->getKey());

    $liveAttendanceRows = $response->viewData('liveAttendanceRows');

    expect($liveAttendanceRows)->toHaveCount(1);
    expect($liveAttendanceRows->first()['attendance_seconds'])->toBe(60);
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
    $response->assertDontSeeText('Docenti assegnati');
    $response->assertDontSeeText('Tutor assegnati');
});

it('resolves the dedicated edit view for video modules', function () {
    $course = Course::factory()->create();
    $assignedTeacher = User::query()->create([
        'name' => 'Giulia',
        'surname' => 'Neri',
        'email' => 'giulia.neri@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => 'NERGLI80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $assignedTeacher->assignRole('teacher');

    $availableTeacher = User::query()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => 'RSSMRA80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $availableTeacher->assignRole('teacher');

    $assignedTutor = User::query()->create([
        'name' => 'Paolo',
        'surname' => 'Blu',
        'email' => 'paolo.blu@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => 'BLUPLA80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $assignedTutor->assignRole('tutor');

    $availableTutor = User::query()->create([
        'name' => 'Sara',
        'surname' => 'Gialli',
        'email' => 'sara.gialli@example.test',
        'password' => Hash::make('password'),
        'email_verified_at' => now(),
        'account_state' => 'active',
        'profile_completed_at' => now(),
        'fiscal_code' => 'GLLSRA80A01H501Z',
        'is_foreigner_or_immigrant' => false,
    ]);
    $availableTutor->assignRole('tutor');

    $module = Module::factory()->create([
        'type' => 'video',
        'belongsTo' => (string) $course->getKey(),
    ]);

    ModuleTeacherEnrollment::query()->create([
        'module_id' => $module->getKey(),
        'user_id' => $assignedTeacher->getKey(),
        'assigned_at' => now(),
    ]);

    ModuleTutorEnrollment::query()->create([
        'module_id' => $module->getKey(),
        'user_id' => $assignedTutor->getKey(),
        'assigned_at' => now(),
    ]);

    $response = $this->get(route('admin.courses.modules.edit', [$course, $module]));

    $response->assertOk();
    $response->assertViewHas('moduleEditView', 'admin.module.types.video');
    $response->assertSee('name="title"', escape: false);
    $response->assertDontSee('name="is_live_teacher"', escape: false);
    $response->assertDontSee('name="appointment_date"', escape: false);
    $response->assertSeeText('Docenti assegnati');
    $response->assertSeeText('Tutor assegnati');
    $response->assertSeeText('Giulia Neri');
    $response->assertSeeText('Paolo Blu');
    $response->assertSeeText('Mario Rossi');
    $response->assertSeeText('Sara Gialli');
    $response->assertSee('id="assign-teachers-modal"', escape: false);
    $response->assertSee('id="assign-tutors-modal"', escape: false);
    $response->assertDontSeeText('I docenti assegnati potranno accedere e trasmettere le dirette.');
    $response->assertDontSeeText('I tutor assegnati potranno accedere e moderare le dirette.');
});
