<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\LiveStreamHandRaise;
use App\Models\LiveStreamMessage;
use App\Models\LiveStreamParticipant;
use App\Models\LiveStreamSession;
use App\Models\Module;
use App\Models\User;
use App\Services\TwilioVideoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createLiveModuleWithCourse(): Module
{
    $course = Course::factory()->create();

    return Module::factory()->create([
        'type' => 'live',
        'status' => 'published',
        'belongsTo' => (string) $course->getKey(),
        'appointment_start_time' => now()->subMinute(),
        'appointment_end_time' => now()->addHour(),
    ]);
}

function enrollUserForModule($user, Module $module): void
{
    CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => (int) $module->belongsTo,
    ]);
}

test('teacher can start a live session only once for the same module', function () {
    $teacher = actingAsRole('docente');
    $module = createLiveModuleWithCourse();

    $service = Mockery::mock(TwilioVideoService::class);
    $service->shouldReceive('createRoom')
        ->once()
        ->andReturn([
            'sid' => 'RMteacherroom',
            'name' => 'teacher-room',
        ]);

    $this->app->instance(TwilioVideoService::class, $service);

    $this->actingAs($teacher)
        ->postJson(route('teacher.live-stream.session.start', $module))
        ->assertSuccessful()
        ->assertJsonPath('session.twilio_room_name', 'teacher-room');

    $this->actingAs($teacher)
        ->postJson(route('teacher.live-stream.session.start', $module))
        ->assertSuccessful()
        ->assertJsonPath('session.twilio_room_name', 'teacher-room');

    expect(LiveStreamSession::query()->count())->toBe(1);
});

test('user join requires an enrollment in the live course', function () {
    $user = actingAsRole('user');
    $module = createLiveModuleWithCourse();

    LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $service = Mockery::mock(TwilioVideoService::class);
    $this->app->instance(TwilioVideoService::class, $service);

    $this->actingAs($user)
        ->postJson(route('user.live-stream.join', $module))
        ->assertForbidden();
});

test('enrolled user can join while tutor joins as hidden observer', function () {
    $module = createLiveModuleWithCourse();
    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $this->seed(RoleAndPermissionSeeder::class);

    $user = User::factory()->create();
    $user->assignRole('user');
    enrollUserForModule($user, $module);

    $service = Mockery::mock(TwilioVideoService::class);
    $service->shouldReceive('createAccessToken')->once()->andReturn('user-token');
    $this->app->instance(TwilioVideoService::class, $service);

    $this->actingAs($user)
        ->postJson(route('user.live-stream.join', $module))
        ->assertSuccessful()
        ->assertJsonPath('twilio_token', 'user-token')
        ->assertJsonPath('permissions.can_raise_hand', true)
        ->assertJsonPath('permissions.is_hidden', false);

    $userParticipant = $session->participants()->where('user_id', $user->getKey())->first();

    expect($userParticipant)->not->toBeNull();
    expect($userParticipant->is_hidden)->toBeFalse();
    expect($userParticipant->audio_enabled)->toBeFalse();

    $tutor = User::factory()->create();
    $tutor->assignRole('tutor');

    $service = Mockery::mock(TwilioVideoService::class);
    $service->shouldReceive('createAccessToken')->once()->andReturn('tutor-token');
    $this->app->instance(TwilioVideoService::class, $service);

    $this->actingAs($tutor)
        ->postJson(route('tutor.live-stream.join', $module))
        ->assertSuccessful()
        ->assertJsonPath('permissions.is_hidden', true);

    $tutorParticipant = $session->participants()->where('user_id', $tutor->getKey())->first();

    expect($tutorParticipant)->not->toBeNull();
    expect($tutorParticipant->is_hidden)->toBeTrue();
    expect($tutorParticipant->video_enabled)->toBeFalse();
});

test('teacher state excludes hidden and stale participants', function () {
    $teacher = actingAsRole('docente');
    $module = createLiveModuleWithCourse();
    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'teacher_user_id' => $teacher->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $freshStudent = LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'last_seen_at' => now(),
    ]);

    LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'last_seen_at' => now()->subMinute(),
    ]);

    LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_TUTOR,
        'is_hidden' => true,
        'last_seen_at' => now(),
    ]);

    LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $teacher->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_TEACHER,
        'is_hidden' => false,
        'last_seen_at' => now(),
        'audio_enabled' => true,
    ]);

    $response = $this->actingAs($teacher)
        ->getJson(route('teacher.live-stream.state', $module))
        ->assertSuccessful()
        ->json();

    expect($response['status'])->toBe('live');
    expect($response['participants'])->toHaveCount(1);
    expect($response['participants'][0]['id'])->toBe($freshStudent->getKey());
    expect($response['teacher']['user_id'])->toBe($teacher->getKey());
});

test('hand raise is resolved when teacher grants microphone access', function () {
    $module = createLiveModuleWithCourse();
    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $this->seed(RoleAndPermissionSeeder::class);

    $teacher = User::factory()->create();
    $teacher->assignRole('docente');

    $student = User::factory()->create();
    $student->assignRole('user');
    enrollUserForModule($student, $module);

    $participant = LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $student->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'audio_enabled' => false,
    ]);

    $this->actingAs($student)
        ->postJson(route('user.live-stream.hand-raises.store', $module))
        ->assertSuccessful()
        ->assertJsonPath('hand_raise.status', LiveStreamHandRaise::STATUS_PENDING);

    $this->actingAs($teacher)
        ->patchJson(route('teacher.live-stream.participants.speaker', [$module, $participant]), [
            'enabled' => true,
        ])
        ->assertSuccessful()
        ->assertJsonPath('participant.audio_enabled', true);

    $participant->refresh();

    expect($participant->audio_enabled)->toBeTrue();
    expect(
        $session->handRaises()
            ->where('user_id', $student->getKey())
            ->latest('id')
            ->first()
            ?->status
    )->toBe(LiveStreamHandRaise::STATUS_RESOLVED);
});

test('user presence does not override teacher audio moderation', function () {
    $module = createLiveModuleWithCourse();
    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $this->seed(RoleAndPermissionSeeder::class);

    $student = User::factory()->create();
    $student->assignRole('user');
    enrollUserForModule($student, $module);

    $participant = LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $student->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'audio_enabled' => true,
        'video_enabled' => false,
    ]);

    $this->actingAs($student)
        ->postJson(route('user.live-stream.presence', $module), [
            'twilio_participant_sid' => 'PA1234567890abcdef1234567890abcd',
            'audio_enabled' => false,
            'video_enabled' => true,
        ])
        ->assertSuccessful();

    $participant->refresh();

    expect($participant->audio_enabled)->toBeTrue();
    expect($participant->video_enabled)->toBeTrue();
    expect($participant->twilio_participant_sid)->toBe('PA1234567890abcdef1234567890abcd');
});

test('joined user can send a live stream chat message', function () {
    $module = createLiveModuleWithCourse();
    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $this->seed(RoleAndPermissionSeeder::class);

    $student = User::factory()->create();
    $student->assignRole('user');
    enrollUserForModule($student, $module);

    LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $student->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
    ]);

    $this->actingAs($student)
        ->postJson(route('user.live-stream.messages.store', $module), [
            'body' => 'Buongiorno docente',
        ])
        ->assertCreated()
        ->assertJsonPath('chat_message.app_role', LiveStreamParticipant::ROLE_USER)
        ->assertJsonPath('chat_message.body', 'Buongiorno docente');

    expect(LiveStreamMessage::query()->count())->toBe(1);
    expect(LiveStreamMessage::query()->first()?->live_stream_session_id)->toBe($session->getKey());
});

test('joined tutor can send a live stream chat message with tutor role', function () {
    $module = createLiveModuleWithCourse();
    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $this->seed(RoleAndPermissionSeeder::class);

    $tutor = User::factory()->create();
    $tutor->assignRole('tutor');

    LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $tutor->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_TUTOR,
        'is_hidden' => true,
        'audio_enabled' => false,
        'video_enabled' => false,
    ]);

    $this->actingAs($tutor)
        ->postJson(route('tutor.live-stream.messages.store', $module), [
            'body' => 'Messaggio tutor in chat',
        ])
        ->assertCreated()
        ->assertJsonPath('chat_message.app_role', LiveStreamParticipant::ROLE_TUTOR)
        ->assertJsonPath('chat_message.body', 'Messaggio tutor in chat');

    expect(LiveStreamMessage::query()->count())->toBe(1);
    expect(LiveStreamMessage::query()->first()?->live_stream_session_id)->toBe($session->getKey());
    expect(LiveStreamMessage::query()->first()?->app_role)->toBe(LiveStreamParticipant::ROLE_TUTOR);
});

test('teacher state returns recent live stream chat messages in chronological order', function () {
    $teacher = actingAsRole('docente');
    $module = createLiveModuleWithCourse();
    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'teacher_user_id' => $teacher->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $student = User::factory()->create();

    LiveStreamMessage::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $teacher->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_TEACHER,
        'body' => 'Benvenuti a tutti',
        'sent_at' => now()->subMinute(),
    ]);

    LiveStreamMessage::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $student->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'body' => 'Buongiorno',
        'sent_at' => now(),
    ]);

    $response = $this->actingAs($teacher)
        ->getJson(route('teacher.live-stream.state', $module))
        ->assertSuccessful()
        ->json();

    expect($response['messages'])->toHaveCount(2);
    expect($response['messages'][0]['body'])->toBe('Benvenuti a tutti');
    expect($response['messages'][1]['body'])->toBe('Buongiorno');
});
