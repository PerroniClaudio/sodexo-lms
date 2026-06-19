<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LiveStreamAuditEvent;
use App\Models\LiveStreamSession;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
    $this->withoutVite();
});

function confirmAsyncLiveModule(Course $course, int $order = 1): Module
{
    return Module::factory()->create([
        'title' => 'Diretta asincrona',
        'type' => Module::TYPE_LIVE,
        'order' => $order,
        'appointment_date' => '2026-06-01',
        'appointment_start_time' => '2026-06-01 10:00:00',
        'appointment_end_time' => '2026-06-01 12:00:00',
        'belongsTo' => (string) $course->getKey(),
    ]);
}

function confirmAsyncAuditEvent(LiveStreamSession $session, Module $module, User $user, string $type, string $occurredAt): void
{
    LiveStreamAuditEvent::query()->create([
        'live_stream_session_id' => $session->getKey(),
        'module_id' => $module->getKey(),
        'user_id' => $user->getKey(),
        'event_type' => $type,
        'app_role' => 'participant',
        'occurred_at' => $occurredAt,
    ]);
}

it('shows async attendance controls with effective live times', function () {
    $course = Course::factory()->create(['type' => 'async']);
    $module = confirmAsyncLiveModule($course);
    $session = LiveStreamSession::factory()->create(['module_id' => $module->getKey()]);
    $user = User::factory()->create([
        'name' => 'Mario',
        'surname' => 'Rossi',
        'email' => 'mario.rossi@example.test',
    ]);
    CourseEnrollment::enroll($user, $course);
    confirmAsyncAuditEvent($session, $module, $user, LiveStreamAuditEvent::TYPE_PARTICIPANT_JOINED, '2026-06-01 10:00:00');
    confirmAsyncAuditEvent($session, $module, $user, LiveStreamAuditEvent::TYPE_PARTICIPANT_DISCONNECTED, '2026-06-01 12:00:00');

    $this->get(route('admin.courses.edit', [$course, 'section' => 'attendees']))
        ->assertOk()
        ->assertSeeText('Presenti')
        ->assertSeeText('Conferma presenti')
        ->assertSeeText('Ora di inizio effettiva')
        ->assertSeeText('Ora di fine effettiva')
        ->assertSee('name="effective_start_time"', false)
        ->assertSee('value="10:00"', false)
        ->assertSeeText('Rossi Mario')
        ->assertSeeText('02:00');
});

it('validates async effective live times', function () {
    $course = Course::factory()->create(['type' => 'async']);
    $module = confirmAsyncLiveModule($course);

    $this->from(route('admin.courses.edit', [$course, 'section' => 'attendees']))
        ->post(route('admin.courses.attendance.confirm', $course), [
            'module_id' => $module->getKey(),
            'minimum_attendance_percentage' => 90,
            'effective_start_time' => '12:00',
            'effective_end_time' => '10:00',
        ])
        ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'attendees']))
        ->assertSessionHasErrors('effective_end_time');
});

it('confirms async live attendance from audit trail', function () {
    $course = Course::factory()->create(['type' => 'async']);
    $module = confirmAsyncLiveModule($course);
    $nextModule = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $session = LiveStreamSession::factory()->create(['module_id' => $module->getKey()]);
    $qualifiedUser = User::factory()->create();
    $nonQualifiedUser = User::factory()->create();
    $qualifiedEnrollment = CourseEnrollment::enroll($qualifiedUser, $course);
    $nonQualifiedEnrollment = CourseEnrollment::enroll($nonQualifiedUser, $course);

    confirmAsyncAuditEvent($session, $module, $qualifiedUser, LiveStreamAuditEvent::TYPE_PARTICIPANT_JOINED, '2026-06-01 09:30:00');
    confirmAsyncAuditEvent($session, $module, $qualifiedUser, LiveStreamAuditEvent::TYPE_PARTICIPANT_DISCONNECTED, '2026-06-01 11:50:00');
    confirmAsyncAuditEvent($session, $module, $nonQualifiedUser, LiveStreamAuditEvent::TYPE_PARTICIPANT_JOINED, '2026-06-01 10:00:00');
    confirmAsyncAuditEvent($session, $module, $nonQualifiedUser, LiveStreamAuditEvent::TYPE_PARTICIPANT_DISCONNECTED, '2026-06-01 10:30:00');

    $this->post(route('admin.courses.attendance.confirm', $course), [
        'module_id' => $module->getKey(),
        'minimum_attendance_percentage' => 90,
        'effective_start_time' => '10:00',
        'effective_end_time' => '12:00',
    ])
        ->assertRedirect(route('admin.courses.edit', [$course, 'section' => 'attendees']))
        ->assertSessionHas('status', 'Presenze confermate. 1 utenti abilitati, 0 già completati, 0 sopra soglia ma non ancora sul modulo corrente.');

    $qualifiedEnrollment->refresh();
    $nonQualifiedEnrollment->refresh();

    expect($qualifiedEnrollment->moduleProgresses()->where('module_id', $module->getKey())->first()->status)->toBe(ModuleProgress::STATUS_COMPLETED)
        ->and($qualifiedEnrollment->moduleProgresses()->where('module_id', $nextModule->getKey())->first()->status)->toBe(ModuleProgress::STATUS_AVAILABLE)
        ->and($nonQualifiedEnrollment->moduleProgresses()->where('module_id', $module->getKey())->first()->status)->toBe(ModuleProgress::STATUS_AVAILABLE)
        ->and($nonQualifiedEnrollment->moduleProgresses()->where('module_id', $nextModule->getKey())->first()->status)->toBe(ModuleProgress::STATUS_LOCKED);
});
