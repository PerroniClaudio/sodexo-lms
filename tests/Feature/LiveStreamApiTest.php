<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CourseTeacherEnrollment;
use App\Models\CourseTutorEnrollment;
use App\Models\LiveStreamAttendanceMinute;
use App\Models\LiveStreamDocument;
use App\Models\LiveStreamHandRaise;
use App\Models\LiveStreamMessage;
use App\Models\LiveStreamParticipant;
use App\Models\LiveStreamPoll;
use App\Models\LiveStreamSession;
use App\Models\Module;
use App\Models\User;
use App\Services\TwilioVideoService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

function assignTeacherToModule($user, Module $module): void
{
    CourseTeacherEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => (int) $module->belongsTo,
    ]);
}

function assignTutorToModule($user, Module $module): void
{
    CourseTutorEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => (int) $module->belongsTo,
    ]);
}

test('teacher can start a live session only once for the same module', function () {
    $teacher = actingAsRole('docente');
    $module = createLiveModuleWithCourse();

    assignTeacherToModule($teacher, $module);

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

test('teacher start session requires an enrollment in the course', function () {
    $teacher = actingAsRole('docente');
    $module = createLiveModuleWithCourse();

    $service = Mockery::mock(TwilioVideoService::class);
    $this->app->instance(TwilioVideoService::class, $service);

    $this->actingAs($teacher)
        ->postJson(route('teacher.live-stream.session.start', $module))
        ->assertForbidden();
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
    assignTutorToModule($tutor, $module);

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

test('tutor join requires an enrollment in the course', function () {
    $tutor = actingAsRole('tutor');
    $module = createLiveModuleWithCourse();

    LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $service = Mockery::mock(TwilioVideoService::class);
    $this->app->instance(TwilioVideoService::class, $service);

    $this->actingAs($tutor)
        ->postJson(route('tutor.live-stream.join', $module))
        ->assertForbidden();
});

test('teacher state excludes hidden and stale participants', function () {
    $teacher = actingAsRole('docente');
    $module = createLiveModuleWithCourse();
    assignTeacherToModule($teacher, $module);
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

test('teacher can upload a pdf and state returns the shared live materials', function () {
    Storage::fake('local');

    $teacher = actingAsRole('docente');
    $module = createLiveModuleWithCourse();
    assignTeacherToModule($teacher, $module);

    $file = UploadedFile::fake()->create('slide-live.pdf', 128, 'application/pdf');

    $this->actingAs($teacher)
        ->post(route('teacher.live-stream.documents.store', $module), [
            'document' => $file,
        ])
        ->assertCreated()
        ->assertJsonPath('document.name', 'slide-live.pdf');

    $document = LiveStreamDocument::query()->sole();

    Storage::disk('local')->assertExists($document->path);

    $response = $this->actingAs($teacher)
        ->getJson(route('teacher.live-stream.state', $module))
        ->assertSuccessful()
        ->json();

    expect($response['documents'])->toHaveCount(1);
    expect($response['documents'][0]['name'])->toBe('slide-live.pdf');
    expect($response['documents'][0]['uploaded_by'])->toBe($teacher->full_name);
});

test('enrolled user can download a shared live pdf', function () {
    Storage::fake('local');

    $user = actingAsRole('user');
    $module = createLiveModuleWithCourse();
    enrollUserForModule($user, $module);

    $document = LiveStreamDocument::factory()->create([
        'module_id' => $module->getKey(),
        'user_id' => $user->getKey(),
        'path' => 'live-stream-documents/'.$module->getKey().'/programma-live.pdf',
        'original_name' => 'programma-live.pdf',
    ]);

    Storage::disk('local')->put($document->path, 'fake pdf content');

    $this->actingAs($user)
        ->get(route('user.live-stream.documents.download', [$module, $document]))
        ->assertOk()
        ->assertDownload('programma-live.pdf');
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
    assignTeacherToModule($teacher, $module);

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

test('presence heartbeats in the same minute update a single attendance audit row', function () {
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
    ]);

    $this->travelTo(now()->startOfMinute()->addSeconds(5));

    $this->actingAs($student)
        ->postJson(route('user.live-stream.presence', $module), [
            'twilio_participant_sid' => 'PA1234567890abcdef1234567890abcd',
            'audio_enabled' => false,
            'video_enabled' => true,
        ])
        ->assertSuccessful();

    $this->travel(20)->seconds();

    $this->actingAs($student)
        ->postJson(route('user.live-stream.presence', $module), [
            'twilio_participant_sid' => 'PA1234567890abcdef1234567890abcd',
            'audio_enabled' => false,
            'video_enabled' => true,
        ])
        ->assertSuccessful();

    $participant->refresh();

    $attendanceMinute = LiveStreamAttendanceMinute::query()->sole();

    expect($participant->last_seen_at)->not->toBeNull();
    expect($attendanceMinute->live_stream_session_id)->toBe($session->getKey());
    expect($attendanceMinute->module_id)->toBe($module->getKey());
    expect($attendanceMinute->user_id)->toBe($student->getKey());
    expect($attendanceMinute->minute_at?->format('Y-m-d H:i:s'))->toBe(now()->startOfMinute()->format('Y-m-d H:i:s'));
    expect($attendanceMinute->heartbeat_count)->toBe(2);
    expect($attendanceMinute->first_seen_at?->format('Y-m-d H:i:s'))->toBe(now()->subSeconds(20)->format('Y-m-d H:i:s'));
    expect($attendanceMinute->last_seen_at?->format('Y-m-d H:i:s'))->toBe(now()->format('Y-m-d H:i:s'));

    $this->travelBack();
});

test('presence heartbeats in a new minute create a new attendance audit row', function () {
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

    $this->travelTo(now()->startOfMinute()->addSeconds(50));

    $this->actingAs($student)
        ->postJson(route('user.live-stream.presence', $module), [
            'twilio_participant_sid' => 'PA1234567890abcdef1234567890abcd',
            'audio_enabled' => false,
            'video_enabled' => true,
        ])
        ->assertSuccessful();

    $this->travel(15)->seconds();

    $this->actingAs($student)
        ->postJson(route('user.live-stream.presence', $module), [
            'twilio_participant_sid' => 'PA1234567890abcdef1234567890abcd',
            'audio_enabled' => false,
            'video_enabled' => true,
        ])
        ->assertSuccessful();

    expect(
        LiveStreamAttendanceMinute::query()
            ->where('live_stream_session_id', $session->getKey())
            ->where('user_id', $student->getKey())
            ->count()
    )->toBe(2);

    expect(
        LiveStreamAttendanceMinute::query()
            ->where('live_stream_session_id', $session->getKey())
            ->where('user_id', $student->getKey())
            ->orderBy('minute_at')
            ->pluck('heartbeat_count')
            ->all()
    )->toBe([1, 1]);

    $this->travelBack();
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
    assignTutorToModule($tutor, $module);

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

test('joined tutor can remove a live stream chat message for moderation', function () {
    $module = createLiveModuleWithCourse();
    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $this->seed(RoleAndPermissionSeeder::class);

    $tutor = User::factory()->create();
    $tutor->assignRole('tutor');
    assignTutorToModule($tutor, $module);

    LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $tutor->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_TUTOR,
        'is_hidden' => true,
        'audio_enabled' => false,
        'video_enabled' => false,
    ]);

    $student = User::factory()->create();

    $message = LiveStreamMessage::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $student->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'body' => 'Messaggio da moderare',
    ]);

    $this->actingAs($tutor)
        ->deleteJson(route('tutor.live-stream.messages.destroy', [$module, $message]))
        ->assertSuccessful()
        ->assertJsonPath('message', 'Messaggio rimosso.');

    expect(LiveStreamMessage::query()->whereKey($message->getKey())->exists())->toBeFalse();
});

test('teacher state returns recent live stream chat messages in chronological order', function () {
    $teacher = actingAsRole('docente');
    $module = createLiveModuleWithCourse();
    assignTeacherToModule($teacher, $module);
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

test('teacher can publish a poll and teacher state shows aggregated response percentages', function () {
    $module = createLiveModuleWithCourse();
    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $this->seed(RoleAndPermissionSeeder::class);

    $teacher = User::factory()->create();
    $teacher->assignRole('docente');
    assignTeacherToModule($teacher, $module);

    $firstStudent = User::factory()->create();
    $firstStudent->assignRole('user');
    enrollUserForModule($firstStudent, $module);

    $secondStudent = User::factory()->create();
    $secondStudent->assignRole('user');
    enrollUserForModule($secondStudent, $module);

    LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $firstStudent->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'last_seen_at' => now(),
    ]);

    LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $secondStudent->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'last_seen_at' => now(),
    ]);

    $this->actingAs($teacher)
        ->postJson(route('teacher.live-stream.polls.store', $module), [
            'question' => 'Quale risposta preferisci?',
            'options' => ['Risposta A', 'Risposta B', '', ''],
        ])
        ->assertCreated()
        ->assertJsonPath('poll.question', 'Quale risposta preferisci?')
        ->assertJsonPath('poll.is_open', true)
        ->assertJsonPath('poll.options.0.label', 'Risposta A')
        ->assertJsonPath('poll.options.1.label', 'Risposta B');

    $poll = LiveStreamPoll::query()->sole();

    $this->actingAs($firstStudent)
        ->postJson(route('user.live-stream.polls.responses.store', [$module, $poll]), [
            'answer_index' => 0,
        ])
        ->assertCreated();

    $this->actingAs($secondStudent)
        ->postJson(route('user.live-stream.polls.responses.store', [$module, $poll]), [
            'answer_index' => 1,
        ])
        ->assertCreated();

    $teacherState = $this->actingAs($teacher)
        ->getJson(route('teacher.live-stream.state', $module))
        ->assertSuccessful()
        ->json();

    expect($teacherState['polls'])->toHaveCount(1);
    expect($teacherState['polls'][0]['total_responses'])->toBe(2);
    expect($teacherState['polls'][0]['options'][0]['responses_count'])->toBe(1);
    expect($teacherState['polls'][0]['options'][0]['percentage'])->toBe(50);
    expect($teacherState['polls'][0]['options'][1]['responses_count'])->toBe(1);
    expect($teacherState['polls'][0]['options'][1]['percentage'])->toBe(50);

    $firstStudentState = $this->actingAs($firstStudent)
        ->getJson(route('user.live-stream.state', $module))
        ->assertSuccessful()
        ->json();

    expect($firstStudentState['active_poll'])->toBeNull();
});

test('teacher can close an open poll and unanswered users no longer receive it', function () {
    $module = createLiveModuleWithCourse();
    $session = LiveStreamSession::factory()->create([
        'module_id' => $module->getKey(),
        'status' => LiveStreamSession::STATUS_LIVE,
    ]);

    $this->seed(RoleAndPermissionSeeder::class);

    $teacher = User::factory()->create();
    $teacher->assignRole('docente');
    assignTeacherToModule($teacher, $module);

    $student = User::factory()->create();
    $student->assignRole('user');
    enrollUserForModule($student, $module);

    LiveStreamParticipant::factory()->create([
        'live_stream_session_id' => $session->getKey(),
        'user_id' => $student->getKey(),
        'app_role' => LiveStreamParticipant::ROLE_USER,
        'last_seen_at' => now(),
    ]);

    $this->actingAs($teacher)
        ->postJson(route('teacher.live-stream.polls.store', $module), [
            'question' => 'Il sondaggio è ancora aperto?',
            'options' => ['Sì', 'No'],
        ])
        ->assertCreated();

    $poll = LiveStreamPoll::query()->sole();

    $this->actingAs($student)
        ->getJson(route('user.live-stream.state', $module))
        ->assertSuccessful()
        ->assertJsonPath('active_poll.id', $poll->getKey());

    $this->actingAs($teacher)
        ->patchJson(route('teacher.live-stream.polls.close', [$module, $poll]))
        ->assertSuccessful()
        ->assertJsonPath('poll.is_open', false)
        ->assertJsonPath('poll.status', LiveStreamPoll::STATUS_CLOSED);

    $this->actingAs($student)
        ->getJson(route('user.live-stream.state', $module))
        ->assertSuccessful()
        ->assertJsonPath('active_poll', null);

    $this->actingAs($student)
        ->postJson(route('user.live-stream.polls.responses.store', [$module, $poll]), [
            'answer_index' => 0,
        ])
        ->assertConflict()
        ->assertJsonPath('message', 'Il sondaggio è chiuso.');
});
