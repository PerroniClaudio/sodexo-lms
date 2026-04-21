<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseTeacherEnrollment;
use App\Models\CourseTutorEnrollment;
use App\Models\LiveStreamDocument;
use App\Models\LiveStreamSession;
use App\Models\Module;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('live stream player route renders module data for the requested live module', function () {
    $user = actingAsRole('user');

    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);

    $module = Module::factory()->create([
        'title' => 'Live onboarding',
        'description' => 'Sessione live con dati reali del modulo.',
        'type' => 'live',
        'status' => 'published',
        'appointment_start_time' => now()->subHour(),
        'appointment_end_time' => now()->addHour(),
        'belongsTo' => (string) $course->getKey(),
    ]);

    CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
    ]);

    LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    LiveStreamDocument::factory()->create([
        'module_id' => $module->getKey(),
        'user_id' => $user->getKey(),
        'original_name' => 'dispensa-live.pdf',
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('user.live-stream.player', $module));

    $response->assertSuccessful();
    $response->assertSeeText('Corso sicurezza');
    $response->assertSeeText('Sessione live con dati reali del modulo.');
    $response->assertSeeText('Materiale didattico');
    $response->assertSeeText('Dispositivi');
    $response->assertSeeText('Entra nella diretta');
    $response->assertSee('aria-label="Alza la mano"', false);
    $response->assertDontSeeText('In attesa');
    $response->assertDontSeeText('Microfono attivo');
    $response->assertDontSeeText('Microfono in ascolto');
    $response->assertDontSeeText('Live onboarding');
    $response->assertDontSeeText('La diretta è attiva. Puoi entrare ora.');
    $response->assertSee('data-live-stream-hand-raise-button', false);
    $response->assertSee('data-live-stream-documents-list', false);
    $response->assertSee('class="btn btn-square btn-outline hidden"', false);
    $response->assertDontSee('data-chat-delete', false);
    $response->assertDontSee('data-live-stream-status-badge', false);
    $response->assertDontSee('data-live-stream-message', false);
    $response->assertSeeText('Sondaggio live');
    $response->assertSee('data-live-stream-poll-modal', false);
    $response->assertSee('data-live-stream-poll-form', false);
    $response->assertSeeInOrder([
        'name="live-stream-sidebar-tabs"',
        'aria-label="Discenti"',
        'checked="checked"',
        'aria-label="Chat"',
    ], false);
    $response->assertSee('data-live-stream-main-stage', false);
    $response->assertSee('class="aspect-video w-full overflow-hidden rounded-[1.75rem]"', false);
    $response->assertSeeText('Docente non connesso');
    $response->assertSeeText('Il feed apparirà qui appena il docente entra in diretta');
});

test('user live stream route renders waiting view before the scheduled start time', function () {
    $user = actingAsRole('user');

    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);

    $module = Module::factory()->create([
        'title' => 'Live futura',
        'description' => 'Sessione live futura.',
        'type' => 'live',
        'status' => 'published',
        'appointment_start_time' => now()->addHour(),
        'appointment_end_time' => now()->addHours(2),
        'belongsTo' => (string) $course->getKey(),
    ]);

    CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('user.live-stream.player', $module));

    $response->assertSuccessful();
    $response->assertViewIs('user.live-stream.waiting');
    $response->assertSeeText('Live futura');
    $response->assertSeeText('Diretta non ancora disponibile');
    $response->assertSeeText('La diretta comincia all\'orario stabilito.');
    $response->assertSeeText('Corso sicurezza');
});

test('user live stream route renders ended view after the scheduled end time', function () {
    $user = actingAsRole('user');

    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);

    $module = Module::factory()->create([
        'title' => 'Live conclusa',
        'description' => 'Sessione live terminata.',
        'type' => 'live',
        'status' => 'published',
        'appointment_start_time' => now()->subHours(2),
        'appointment_end_time' => now()->subHour(),
        'belongsTo' => (string) $course->getKey(),
    ]);

    CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('user.live-stream.player', $module));

    $response->assertSuccessful();
    $response->assertViewIs('user.live-stream.waiting');
    $response->assertSeeText('Live conclusa');
    $response->assertSeeText('Diretta terminata');
    $response->assertSeeText('La diretta è terminata.');
    $response->assertSeeText('Corso sicurezza');
});

test('user live stream route requires an enrollment for the live course', function () {
    $user = actingAsRole('user');
    $course = Course::factory()->create();

    $module = Module::factory()->create([
        'type' => 'live',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this
        ->actingAs($user)
        ->get(route('user.live-stream.player', $module))
        ->assertForbidden();
});

test('live stream player route returns not found for non live modules', function () {
    $user = actingAsRole('user');

    $course = Course::factory()->create();

    $module = Module::factory()->create([
        'type' => 'video',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this
        ->actingAs($user)
        ->get(route('user.live-stream.player', $module))
        ->assertNotFound();
});

test('teacher live stream route renders the updated preview controls', function () {
    $teacher = actingAsRole('docente');

    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);

    $module = Module::factory()->create([
        'title' => 'Live onboarding docente',
        'description' => 'Sessione live docente.',
        'type' => 'live',
        'status' => 'published',
        'belongsTo' => (string) $course->getKey(),
    ]);

    CourseTeacherEnrollment::factory()->create([
        'user_id' => $teacher->getKey(),
        'course_id' => $course->getKey(),
    ]);

    $response = $this
        ->actingAs($teacher)
        ->get(route('teacher.live-stream.player', $module));

    $response->assertSuccessful();
    $response->assertSeeText('Live onboarding docente');
    $response->assertSeeText('Anteprima docente');
    $response->assertSeeText('Materiale didattico');
    $response->assertSeeText('Carica PDF');
    $response->assertSeeText('Attiva videocamera e microfono');
    $response->assertSeeText('Puoi continuare anche senza videocamera.');
    $response->assertSee('aria-label="Disattiva microfono"', false);
    $response->assertSee('class="btn btn-ghost btn-square btn-sm"', false);
    $response->assertDontSeeText('In attesa');
    $response->assertDontSeeText('Microfono attivo');
    $response->assertDontSeeText('Microfono in ascolto');
    $response->assertDontSeeText('Preflight');
    $response->assertDontSee('data-live-stream-status-badge', false);
    $response->assertSee('data-live-stream-preview-toggle', false);
    $response->assertSee('data-live-stream-preview-content', false);
    $response->assertSeeInOrder([
        'name="teacher-live-stream-sidebar-tabs"',
        'aria-label="Discenti"',
        'checked="checked"',
        'aria-label="Chat"',
        'aria-label="Sondaggi"',
    ], false);
    $response->assertSee('data-live-stream-document-form', false);
    $response->assertSee('data-live-stream-documents-list', false);
    $response->assertSeeText('Nuovo sondaggio');
    $response->assertSee('data-live-stream-poll-question-input', false);
    $response->assertSee('data-live-stream-polls-list', false);
    $response->assertSeeText('Schermo');
    $response->assertSeeText('Condividi schermo');
    $response->assertSee('data-live-stream-screen-share-toggle', false);
    $response->assertSee('data-live-stream-screen-share-status', false);
});

test('tutor live stream route renders the updated user player layout without hand raise controls', function () {
    $tutor = actingAsRole('tutor');

    $course = Course::factory()->create([
        'title' => 'Corso sicurezza',
    ]);

    $module = Module::factory()->create([
        'title' => 'Live onboarding tutor',
        'description' => 'Sessione live tutor.',
        'type' => 'live',
        'status' => 'published',
        'appointment_start_time' => now()->subHour(),
        'appointment_end_time' => now()->addHour(),
        'belongsTo' => (string) $course->getKey(),
    ]);

    LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    CourseTutorEnrollment::factory()->create([
        'user_id' => $tutor->getKey(),
        'course_id' => $course->getKey(),
    ]);

    $response = $this
        ->actingAs($tutor)
        ->get(route('tutor.live-stream.player', $module));

    $response->assertSuccessful();
    $response->assertSeeText('Corso sicurezza');
    $response->assertSeeText('Sessione live tutor.');
    $response->assertSeeText('Materiale didattico');
    $response->assertSeeText('Dispositivi');
    $response->assertSeeText('Entra nella diretta');
    $response->assertSeeText('Puoi continuare anche senza videocamera.');
    $response->assertDontSeeText('Sondaggio live');
    $response->assertSee('data-live-stream-chat-form', false);
    $response->assertSee('data-live-stream-chat-input', false);
    $response->assertSee('data-live-stream-chat-submit', false);
    $response->assertSee('data-chat-delete', false);
    $response->assertDontSee('data-live-stream-hand-raise-button', false);
    $response->assertDontSee('data-live-stream-hand-raise-status', false);
    $response->assertSee('data-live-stream-main-stage', false);
});
