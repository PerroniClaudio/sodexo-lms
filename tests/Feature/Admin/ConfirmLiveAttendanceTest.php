<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LiveStreamAttendanceMinute;
use App\Models\LiveStreamSession;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
});

it('confirms live attendance and unlocks the next module for users over the threshold', function () {
    $course = Course::factory()->create();
    $liveModule = Module::factory()->create([
        'title' => 'Diretta sicurezza',
        'type' => 'live',
        'order' => 1,
        'appointment_date' => Carbon::parse('2026-05-20 00:00:00'),
        'appointment_start_time' => Carbon::parse('2026-05-20 10:00:00'),
        'appointment_end_time' => Carbon::parse('2026-05-20 10:04:00'),
        'belongsTo' => (string) $course->getKey(),
    ]);
    $nextModule = Module::factory()->create([
        'title' => 'Modulo successivo',
        'type' => 'video',
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $session = LiveStreamSession::factory()->create([
        'module_id' => $liveModule->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $qualifiedUser = User::factory()->create();
    $nonQualifiedUser = User::factory()->create();

    $qualifiedEnrollment = CourseEnrollment::enroll($qualifiedUser, $course);
    $nonQualifiedEnrollment = CourseEnrollment::enroll($nonQualifiedUser, $course);

    foreach (['10:00:00', '10:01:00', '10:02:00'] as $minuteAt) {
        LiveStreamAttendanceMinute::query()->create([
            'live_stream_session_id' => $session->getKey(),
            'module_id' => $liveModule->getKey(),
            'user_id' => $qualifiedUser->getKey(),
            'minute_at' => Carbon::parse('2026-05-20 '.$minuteAt),
            'first_seen_at' => Carbon::parse('2026-05-20 '.$minuteAt)->addSeconds(5),
            'last_seen_at' => Carbon::parse('2026-05-20 '.$minuteAt)->addSeconds(45),
            'heartbeat_count' => 2,
        ]);
    }

    LiveStreamAttendanceMinute::query()->create([
        'live_stream_session_id' => $session->getKey(),
        'module_id' => $liveModule->getKey(),
        'user_id' => $nonQualifiedUser->getKey(),
        'minute_at' => Carbon::parse('2026-05-20 10:00:00'),
        'first_seen_at' => Carbon::parse('2026-05-20 10:00:05'),
        'last_seen_at' => Carbon::parse('2026-05-20 10:00:25'),
        'heartbeat_count' => 1,
    ]);

    $response = $this->post(route('admin.courses.modules.attendance.confirm', [$course, $liveModule]), [
        'effective_start_time' => '10:00',
        'effective_end_time' => '10:04',
        'minimum_attendance_percentage' => 50,
    ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $liveModule]));
    $response->assertSessionHas('status', 'Presenze confermate. 1 utenti abilitati, 0 già completati, 0 sopra soglia ma non ancora sul modulo corrente.');

    $qualifiedLiveProgress = $qualifiedEnrollment->moduleProgresses()->where('module_id', $liveModule->getKey())->firstOrFail();
    $qualifiedNextProgress = $qualifiedEnrollment->moduleProgresses()->where('module_id', $nextModule->getKey())->firstOrFail();
    $nonQualifiedLiveProgress = $nonQualifiedEnrollment->moduleProgresses()->where('module_id', $liveModule->getKey())->firstOrFail();
    $nonQualifiedNextProgress = $nonQualifiedEnrollment->moduleProgresses()->where('module_id', $nextModule->getKey())->firstOrFail();

    $qualifiedEnrollment->refresh();
    $nonQualifiedEnrollment->refresh();
    $qualifiedLiveProgress->refresh();
    $qualifiedNextProgress->refresh();
    $nonQualifiedLiveProgress->refresh();
    $nonQualifiedNextProgress->refresh();

    expect($qualifiedLiveProgress->status)->toBe(ModuleProgress::STATUS_COMPLETED);
    expect($qualifiedLiveProgress->completed_at)->not->toBeNull();
    expect($qualifiedNextProgress->status)->toBe(ModuleProgress::STATUS_AVAILABLE);
    expect($qualifiedEnrollment->current_module_id)->toBe($nextModule->getKey());

    expect($nonQualifiedLiveProgress->status)->toBe(ModuleProgress::STATUS_AVAILABLE);
    expect($nonQualifiedLiveProgress->completed_at)->toBeNull();
    expect($nonQualifiedNextProgress->status)->toBe(ModuleProgress::STATUS_LOCKED);
    expect($nonQualifiedEnrollment->current_module_id)->toBe($liveModule->getKey());
});

it('validates the effective live window before confirming attendance', function () {
    $course = Course::factory()->create();
    $liveModule = Module::factory()->create([
        'type' => 'live',
        'order' => 1,
        'appointment_date' => Carbon::parse('2026-05-20 00:00:00'),
        'appointment_start_time' => Carbon::parse('2026-05-20 10:00:00'),
        'appointment_end_time' => Carbon::parse('2026-05-20 11:00:00'),
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->from(route('admin.courses.modules.edit', [$course, $liveModule]))
        ->post(route('admin.courses.modules.attendance.confirm', [$course, $liveModule]), [
            'effective_start_time' => '11:00',
            'effective_end_time' => '10:00',
            'minimum_attendance_percentage' => 80,
        ]);

    $response->assertRedirect(route('admin.courses.modules.edit', [$course, $liveModule]));
    $response->assertSessionHasErrors(['effective_end_time']);
});
