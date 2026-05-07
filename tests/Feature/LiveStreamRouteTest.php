<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseTeacherEnrollment;
use App\Models\CourseTutorEnrollment;
use App\Models\LiveStreamDocument;
use App\Models\LiveStreamSession;
use App\Models\Module;
use Database\Seeders\RoleAndPermissionSeeder;
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
    $response->assertSeeText('Orario live');
    $response->assertSeeText('Aggiorna stato live');
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
    $response->assertSeeText('Orario live');
    $response->assertDontSeeText('Aggiorna stato live');
});

test('user regia live route stays in waiting view until mux broadcast is active', function () {
    $user = actingAsRole('user');
    $course = Course::factory()->create([
        'title' => 'Corso regia',
    ]);

    $module = Module::factory()->create([
        'title' => 'Live MUX',
        'type' => 'live',
        'is_live_teacher' => false,
        'status' => 'published',
        'mux_live_stream_id' => 'live_123',
        'mux_playback_id' => 'playback_123',
        'mux_stream_key' => 'stream-key',
        'mux_ingest_url' => 'rtmps://global-live.mux.com:443/app',
        'appointment_start_time' => now()->subMinutes(5),
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
        'mux_playback_id' => 'playback_123',
        'mux_broadcast_status' => LiveStreamSession::BROADCAST_STATUS_IDLE,
    ]);

    $response = $this
        ->actingAs($user)
        ->get(route('user.live-stream.player', $module));

    $response->assertSuccessful();
    $response->assertViewIs('user.live-stream.waiting');
    $response->assertSeeText('Live MUX');
    $response->assertSeeText('Aggiorna stato live');
});

test('admin regia index lists only today non teacher live modules', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = actingAsRole('admin');
    $course = Course::factory()->create();
    $now = now();

    Module::factory()->create([
        'title' => 'Live regia oggi',
        'type' => 'live',
        'is_live_teacher' => false,
        'appointment_start_time' => $now->copy()->addHour(),
        'appointment_end_time' => $now->copy()->addHours(2),
        'belongsTo' => (string) $course->getKey(),
    ]);

    Module::factory()->create([
        'title' => 'Live docente oggi',
        'type' => 'live',
        'is_live_teacher' => true,
        'appointment_start_time' => $now->copy()->addHours(3),
        'appointment_end_time' => $now->copy()->addHours(4),
        'belongsTo' => (string) $course->getKey(),
    ]);

    Module::factory()->create([
        'title' => 'Live regia domani',
        'type' => 'live',
        'is_live_teacher' => false,
        'appointment_start_time' => $now->copy()->addDay()->setTime(10, 0),
        'appointment_end_time' => $now->copy()->addDay()->setTime(11, 0),
        'belongsTo' => (string) $course->getKey(),
    ]);

    Module::factory()->create([
        'title' => 'Live regia terminata',
        'type' => 'live',
        'is_live_teacher' => false,
        'appointment_start_time' => $now->copy()->subHours(2),
        'appointment_end_time' => $now->copy()->subHour(),
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.regia.index'));

    $response->assertSuccessful();
    $response->assertSeeText('Live regia oggi');
    $response->assertDontSeeText('Live docente oggi');
    $response->assertDontSeeText('Live regia domani');
    $response->assertDontSeeText('Live regia terminata');
});

test('admin regia index excludes live modules whose course was soft deleted', function () {
    $admin = actingAsRole('admin');
    $course = Course::factory()->create();

    Module::factory()->create([
        'title' => 'Live regia corso eliminato',
        'type' => 'live',
        'is_live_teacher' => false,
        'appointment_start_time' => now()->setTime(10, 0),
        'appointment_end_time' => now()->setTime(11, 0),
        'belongsTo' => (string) $course->getKey(),
    ]);

    $course->delete();

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.regia.index'));

    $response->assertSuccessful();
    $response->assertDontSeeText('Live regia corso eliminato');
});

test('admin regia player renders mux credentials daisyui modal', function () {
    $this->seed(RoleAndPermissionSeeder::class);

    $admin = actingAsRole('admin');
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'title' => 'Live regia modal',
        'type' => 'live',
        'is_live_teacher' => false,
        'mux_live_stream_id' => 'live_123',
        'mux_playback_id' => 'playback_123',
        'mux_stream_key' => 'stream-key',
        'mux_ingest_url' => 'rtmps://global-live.mux.com:443/app',
        'appointment_start_time' => now()->subMinutes(5),
        'appointment_end_time' => now()->addHour(),
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this
        ->actingAs($admin)
        ->get(route('admin.regia.show', $module));

    $response->assertSuccessful();
    $response->assertSee('id="regia-live-modal"', false);
    $response->assertSee('class="modal"', false);
    $response->assertSee('data-live-stream-regia-modal-close', false);
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
    $response->assertSeeText('Materiale didattico');
    $response->assertSeeText('Carica PDF');
    $response->assertSeeText('Attiva videocamera e microfono');
    $response->assertSeeText('Puoi continuare anche senza videocamera.');
    $response->assertSee('aria-label="Disattiva microfono"', false);
    $response->assertSee('data-live-stream-background-button-label', false);
    $response->assertDontSeeText('In attesa');
    $response->assertDontSeeText('Microfono attivo');
    $response->assertDontSeeText('Microfono in ascolto');
    $response->assertDontSeeText('Preflight');
    $response->assertDontSee('data-live-stream-status-badge', false);
    $response->assertSee('data-live-stream-preview-content', false);
    $response->assertSee('data-live-stream-teacher-local-mic-toggle', false);
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
