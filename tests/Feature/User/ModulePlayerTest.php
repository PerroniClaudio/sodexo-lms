<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use App\Models\ModuleQuizSubmission;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoTrackingEvent;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function enrollUserInCourseWithModule(string $moduleType = 'video', array $moduleAttributes = []): array
{
    test()->seed(RoleAndPermissionSeeder::class);

    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Player',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'fiscal_code' => strtoupper(Str::random(16)),
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('superadmin');
    test()->actingAs($user);

    $course = Course::factory()->create([
        'status' => 'draft',
    ]);
    $video = null;

    if ($moduleType === 'video' && ! array_key_exists('video_id', $moduleAttributes)) {
        $video = Video::factory()->create();
        $moduleAttributes['video_id'] = $video->getKey();
    }

    $module = Module::factory()->create(array_merge([
        'type' => $moduleType,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ], $moduleAttributes));

    $enrollment = CourseEnrollment::enroll($user, $course);

    return [$user, $course, $module, $enrollment, $video];
}

test('module player page is accessible for enrolled user on current module', function () {
    [, $course, $module] = enrollUserInCourseWithModule('video');

    $this->get(route('user.courses.modules.player', [$course, $module]))
        ->assertOk();
});

test('scorm module player page renders the scorm template and packages endpoint', function () {
    [, $course, $module] = enrollUserInCourseWithModule('scorm');
    $this->withoutVite();

    $this->get(route('user.courses.modules.player', [$course, $module]))
        ->assertOk()
        ->assertSee('tpl-scorm', escape: false)
        ->assertSee(route('user.courses.modules.scorm.packages.index', [$course, $module]), escape: false);
});

test('module player page returns 403 if module is not the current module', function () {
    [$user, $course, $module] = enrollUserInCourseWithModule('video');

    $otherModule = Module::factory()->create([
        'type' => 'video',
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this->get(route('user.courses.modules.player', [$course, $otherModule]))
        ->assertForbidden();
});

test('module player page repairs missing progress records before rendering quiz module', function () {
    [$user, $course, $videoModule, $enrollment] = enrollUserInCourseWithModule('video');

    $quizModule = Module::factory()->create([
        'type' => 'learning_quiz',
        'order' => 2,
        'passing_score' => 7,
        'max_score' => 10,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $videoProgress = $enrollment->moduleProgresses()->where('module_id', $videoModule->getKey())->firstOrFail();
    $videoProgress->markCompleted();

    $enrollment->refresh();
    $enrollment->moduleProgresses()->where('module_id', $quizModule->getKey())->delete();

    $this->actingAs($user)
        ->get(route('user.courses.modules.player', [$course, $quizModule]))
        ->assertOk();

    expect(
        $enrollment->fresh()
            ->moduleProgresses()
            ->where('module_id', $quizModule->getKey())
            ->value('status')
    )->toBe(ModuleProgress::STATUS_AVAILABLE);
});

test('module player page requires authentication', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => 'video',
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this->get(route('user.courses.modules.player', [$course, $module]))
        ->assertRedirect(route('login'));
});

test('module player sidebar shows review button for completed video modules', function () {
    test()->seed(RoleAndPermissionSeeder::class);

    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Player',
        'email' => fake()->unique()->safeEmail(),
        'password' => bcrypt('password'),
        'fiscal_code' => strtoupper(Str::random(16)),
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);
    $user->assignRole('superadmin');

    $course = Course::factory()->create([
        'status' => 'draft',
    ]);
    $video = Video::factory()->create();

    $completedVideoModule = Module::factory()->create([
        'title' => 'Video completato',
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
        'video_id' => $video->getKey(),
    ]);

    $currentQuizModule = Module::factory()->create([
        'title' => 'Quiz corrente',
        'type' => Module::TYPE_LEARNING_QUIZ,
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
        'passing_score' => 7,
        'max_score' => 10,
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);

    $enrollment->moduleProgresses()
        ->where('module_id', $completedVideoModule->getKey())
        ->firstOrFail()
        ->markCompleted();

    $response = $this->actingAs($user)
        ->get(route('user.courses.modules.player', [$course, $currentQuizModule]));

    $response->assertOk()
        ->assertSee('Video completato')
        ->assertSee(route('user.courses.modules.player', [$course, $completedVideoModule]), escape: false)
        ->assertSee('Rivedi');
});

test('video progress endpoint updates module progress', function () {
    [, $course, $module, $enrollment] = enrollUserInCourseWithModule('video');

    $this->postJson(
        route('user.courses.modules.video.progress', [$course, $module]),
        ['current_second' => 30]
    )->assertOk()
        ->assertJson(['success' => true]);

    expect(
        $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->value('video_current_second')
    )->toBe(30);
});

test('video tracking state returns resume, max allowed and duration', function () {
    [, $course, $module, $enrollment, $video] = enrollUserInCourseWithModule('video');

    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();
    $progress->syncVideoTrackingState(42, 57, 30);
    $video?->update(['duration_seconds' => 120]);

    $this->getJson(route('user.courses.modules.video.tracking', [$course, $module]))
        ->assertOk()
        ->assertJson([
            'resume_second' => 42,
            'max_allowed_second' => 57,
            'duration_seconds' => 120,
            'completion_threshold_percent' => 95,
            'is_completed' => false,
        ]);
});

test('video tracking event updates progress and time spent', function () {
    [, $course, $module, $enrollment] = enrollUserInCourseWithModule('video');

    $this->postJson(route('user.courses.modules.video.events', [$course, $module]), [
        'session_uuid' => (string) Str::uuid(),
        'event_uuid' => (string) Str::uuid(),
        'event_type' => VideoTrackingEvent::TYPE_HEARTBEAT,
        'occurred_at' => now()->toIso8601String(),
        'position_second' => 30,
        'max_second_client' => 30,
        'delta_watched_seconds' => 30,
    ])->assertOk()
        ->assertJson([
            'resume_second' => 30,
            'max_allowed_second' => 30,
            'was_blocked' => false,
        ]);

    expect(
        $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->value('video_current_second')
    )->toBe(30);

    expect(
        $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->value('video_max_second')
    )->toBe(30);

    expect(
        $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->value('time_spent_seconds')
    )->toBe(30);
});

test('video tracking completes the module when ended arrives after a pause event', function () {
    [, $course, $module, $enrollment, $video] = enrollUserInCourseWithModule('video');

    $video?->update(['duration_seconds' => 30]);

    $sessionUuid = (string) Str::uuid();

    $this->postJson(route('user.courses.modules.video.events', [$course, $module]), [
        'session_uuid' => $sessionUuid,
        'event_uuid' => (string) Str::uuid(),
        'event_type' => VideoTrackingEvent::TYPE_PAUSE,
        'occurred_at' => now()->toIso8601String(),
        'position_second' => 30,
        'max_second_client' => 30,
        'delta_watched_seconds' => 30,
        'player_ended' => false,
    ])->assertOk()
        ->assertJson([
            'is_completed' => false,
        ]);

    $this->postJson(route('user.courses.modules.video.events', [$course, $module]), [
        'session_uuid' => $sessionUuid,
        'event_uuid' => (string) Str::uuid(),
        'event_type' => VideoTrackingEvent::TYPE_ENDED,
        'occurred_at' => now()->toIso8601String(),
        'position_second' => 30,
        'max_second_client' => 30,
        'delta_watched_seconds' => 0,
        'player_ended' => true,
    ])->assertOk()
        ->assertJson([
            'is_completed' => true,
        ]);

    expect($enrollment->fresh()->status)->toBe(CourseEnrollment::STATUS_COMPLETED);
    expect(
        $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->value('status')
    )->toBe(ModuleProgress::STATUS_COMPLETED);
});

test('video tracking blocks seek beyond unlocked point', function () {
    [, $course, $module, $enrollment] = enrollUserInCourseWithModule('video');

    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();
    $progress->syncVideoTrackingState(20, 20, 20);

    $this->postJson(route('user.courses.modules.video.events', [$course, $module]), [
        'session_uuid' => (string) Str::uuid(),
        'event_uuid' => (string) Str::uuid(),
        'event_type' => VideoTrackingEvent::TYPE_SEEK,
        'occurred_at' => now()->toIso8601String(),
        'position_second' => 80,
        'from_second' => 20,
        'to_second' => 80,
        'max_second_client' => 80,
    ])->assertOk()
        ->assertJson([
            'was_blocked' => true,
            'rewind_to_second' => 20,
            'max_allowed_second' => 20,
        ]);

    expect($progress->fresh()->video_current_second)->toBe(20);
    expect($progress->fresh()->video_max_second)->toBe(20);
});

test('video complete endpoint does not complete below threshold', function () {
    [, $course, $module, $enrollment, $video] = enrollUserInCourseWithModule('video');

    $video?->update(['duration_seconds' => 100]);

    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();
    $progress->syncVideoTrackingState(80, 80, 80);

    $this->postJson(route('user.courses.modules.video.complete', [$course, $module]), [
        'current_second' => 80,
    ])->assertOk()
        ->assertJson([
            'success' => false,
            'is_completed' => false,
        ]);

    expect($progress->fresh()->status)->toBe(ModuleProgress::STATUS_IN_PROGRESS);
});

test('video complete endpoint marks module completed only after ended at threshold', function () {
    [, $course, $module, $enrollment, $video] = enrollUserInCourseWithModule('video');

    $video?->update(['duration_seconds' => 100]);

    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();
    $progress->syncVideoTrackingState(95, 95, 95);

    $this->postJson(route('user.courses.modules.video.complete', [$course, $module]), [
        'current_second' => 100,
    ])->assertOk()
        ->assertJson([
            'success' => true,
            'is_completed' => true,
        ]);

    expect($progress->fresh()->status)->toBe(ModuleProgress::STATUS_COMPLETED);
});

test('duplicate video tracking event does not double count watched time', function () {
    [, $course, $module, $enrollment] = enrollUserInCourseWithModule('video');

    $payload = [
        'session_uuid' => (string) Str::uuid(),
        'event_uuid' => (string) Str::uuid(),
        'event_type' => VideoTrackingEvent::TYPE_HEARTBEAT,
        'occurred_at' => now()->toIso8601String(),
        'position_second' => 15,
        'max_second_client' => 15,
        'delta_watched_seconds' => 15,
    ];

    $this->postJson(route('user.courses.modules.video.events', [$course, $module]), $payload)
        ->assertOk()
        ->assertJson(['duplicate' => false]);

    $this->postJson(route('user.courses.modules.video.events', [$course, $module]), $payload)
        ->assertOk()
        ->assertJson(['duplicate' => true]);

    expect($progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail()->fresh()->time_spent_seconds)
        ->toBe(15);
});

test('quiz show endpoint returns questions without correct answers', function () {
    [, $course, $module] = enrollUserInCourseWithModule('learning_quiz', [
        'passing_score' => 7,
        'max_score' => 10,
    ]);

    $question = ModuleQuizQuestion::create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda 1',
        'points' => 10,
    ]);

    $answer1 = ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Risposta A']);
    ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Risposta B']);
    ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Risposta C']);
    ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Risposta D']);
    $question->update(['correct_answer_id' => $answer1->getKey()]);

    $response = $this->getJson(route('user.courses.modules.quiz.show', [$course, $module]))
        ->assertOk();

    $response->assertJsonStructure([
        'passing_score',
        'max_score',
        'questions' => [
            '*' => ['id', 'text', 'points', 'answers'],
        ],
    ]);

    $response->assertJsonMissing(['correct_answer_id']);
});

test('quiz submit endpoint records a passing attempt', function () {
    [, $course, $module, $enrollment] = enrollUserInCourseWithModule('learning_quiz', [
        'passing_score' => 10,
        'max_score' => 10,
    ]);

    $question = ModuleQuizQuestion::create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda 1',
        'points' => 10,
    ]);

    $correctAnswer = ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Giusta']);
    ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Sbagliata 1']);
    ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Sbagliata 2']);
    ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Sbagliata 3']);
    $question->update(['correct_answer_id' => $correctAnswer->getKey()]);

    $this->postJson(
        route('user.courses.modules.quiz.submit', [$course, $module]),
        ['answers' => [$question->getKey() => $correctAnswer->getKey()]]
    )->assertOk()
        ->assertJson(['success' => true, 'passed' => true]);

    expect(
        $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->value('status')
    )->toBe(ModuleProgress::STATUS_COMPLETED);
});

test('quiz submit endpoint records a failing attempt', function () {
    [, $course, $module, $enrollment] = enrollUserInCourseWithModule('learning_quiz', [
        'passing_score' => 10,
        'max_score' => 10,
    ]);

    $question = ModuleQuizQuestion::create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda 1',
        'points' => 10,
    ]);

    $correctAnswer = ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Giusta']);
    $wrongAnswer = ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Sbagliata 1']);
    ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Sbagliata 2']);
    ModuleQuizAnswer::create(['question_id' => $question->getKey(), 'text' => 'Sbagliata 3']);
    $question->update(['correct_answer_id' => $correctAnswer->getKey()]);

    $this->postJson(
        route('user.courses.modules.quiz.submit', [$course, $module]),
        ['answers' => [$question->getKey() => $wrongAnswer->getKey()]]
    )->assertOk()
        ->assertJson(['success' => true, 'passed' => false, 'score' => 0]);

    expect(
        $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->value('status')
    )->toBe(ModuleProgress::STATUS_FAILED);
});

test('learning quiz player page abandons an active attempt when re-entering the module', function () {
    [, $course, $module, $enrollment] = enrollUserInCourseWithModule('learning_quiz', [
        'passing_score' => 7,
        'max_score' => 10,
        'max_attempts' => 2,
    ]);

    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();
    $progress->start();

    $submission = ModuleQuizSubmission::create([
        'module_id' => $module->getKey(),
        'source_type' => ModuleQuizSubmission::SOURCE_ONLINE,
        'user_id' => $enrollment->user_id,
        'course_enrollment_id' => $enrollment->getKey(),
        'status' => ModuleQuizSubmission::STATUS_IN_PROGRESS,
        'started_at' => now(),
        'provider_payload' => [
            'question_order' => [],
            'answer_order' => [],
        ],
    ]);

    $this->get(route('user.courses.modules.player', [$course, $module]))
        ->assertOk();

    expect($submission->fresh()->status)->toBe(ModuleQuizSubmission::STATUS_ABANDONED);
    expect($submission->fresh()->submitted_at)->not->toBeNull();
    expect($submission->fresh()->error_message)->toContain('ritorno al corso');

    expect($progress->fresh()->status)->toBe(ModuleProgress::STATUS_FAILED);
    expect($progress->fresh()->quiz_attempts)->toBe(1);
});

test('learning quiz questions and answers keep a stored randomized order during an attempt', function () {
    [, $course, $module] = enrollUserInCourseWithModule('learning_quiz', [
        'passing_score' => 10,
        'max_score' => 20,
        'max_attempts' => 3,
    ]);

    $firstQuestion = ModuleQuizQuestion::create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda 1',
        'points' => 10,
    ]);
    $firstAnswers = collect([
        ModuleQuizAnswer::create(['question_id' => $firstQuestion->getKey(), 'text' => 'A1']),
        ModuleQuizAnswer::create(['question_id' => $firstQuestion->getKey(), 'text' => 'A2']),
        ModuleQuizAnswer::create(['question_id' => $firstQuestion->getKey(), 'text' => 'A3']),
        ModuleQuizAnswer::create(['question_id' => $firstQuestion->getKey(), 'text' => 'A4']),
    ]);
    $firstQuestion->update(['correct_answer_id' => $firstAnswers[0]->getKey()]);

    $secondQuestion = ModuleQuizQuestion::create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda 2',
        'points' => 10,
    ]);
    $secondAnswers = collect([
        ModuleQuizAnswer::create(['question_id' => $secondQuestion->getKey(), 'text' => 'B1']),
        ModuleQuizAnswer::create(['question_id' => $secondQuestion->getKey(), 'text' => 'B2']),
        ModuleQuizAnswer::create(['question_id' => $secondQuestion->getKey(), 'text' => 'B3']),
        ModuleQuizAnswer::create(['question_id' => $secondQuestion->getKey(), 'text' => 'B4']),
    ]);
    $secondQuestion->update(['correct_answer_id' => $secondAnswers[1]->getKey()]);

    $startResponse = $this->postJson(route('user.courses.modules.quiz.start', [$course, $module]))
        ->assertOk();

    $submission = ModuleQuizSubmission::findOrFail($startResponse->json('submission_id'));
    $payload = $submission->provider_payload;

    expect($payload['question_order'])->toHaveCount(2)
        ->and(collect($payload['question_order'])->sort()->values()->all())
        ->toBe([
            $firstQuestion->getKey(),
            $secondQuestion->getKey(),
        ]);

    expect($payload['answer_order'][(string) $firstQuestion->getKey()] ?? [])
        ->toHaveCount(4)
        ->and(collect($payload['answer_order'][(string) $firstQuestion->getKey()])->sort()->values()->all())
        ->toBe($firstAnswers->pluck('id')->sort()->values()->all());

    expect($payload['answer_order'][(string) $secondQuestion->getKey()] ?? [])
        ->toHaveCount(4)
        ->and(collect($payload['answer_order'][(string) $secondQuestion->getKey()])->sort()->values()->all())
        ->toBe($secondAnswers->pluck('id')->sort()->values()->all());

    $questionResponse = $this->getJson(route('user.courses.modules.quiz.next-question', [$course, $module]))
        ->assertOk();

    expect($questionResponse->json('question.id'))->toBe($payload['question_order'][0]);
    expect(array_column($questionResponse->json('question.answers'), 'id'))
        ->toBe($payload['answer_order'][(string) $payload['question_order'][0]]);

    $secondQuestionId = $payload['question_order'][1];
    $wrongOrderAnswerId = $payload['answer_order'][(string) $secondQuestionId][0];

    $this->postJson(route('user.courses.modules.quiz.answer', [$course, $module]), [
        'question_id' => $secondQuestionId,
        'answer_id' => $wrongOrderAnswerId,
    ])->assertUnprocessable()
        ->assertJson([
            'error' => 'La domanda inviata non corrisponde a quella attesa.',
        ]);

    $firstQuestionId = $payload['question_order'][0];
    $firstAnswerId = $payload['answer_order'][(string) $firstQuestionId][0];

    $this->postJson(route('user.courses.modules.quiz.answer', [$course, $module]), [
        'question_id' => $firstQuestionId,
        'answer_id' => $firstAnswerId,
    ])->assertOk()
        ->assertJson(['success' => true]);

    $nextQuestionResponse = $this->getJson(route('user.courses.modules.quiz.next-question', [$course, $module]))
        ->assertOk();

    expect($nextQuestionResponse->json('question.id'))->toBe($secondQuestionId);
    expect(array_column($nextQuestionResponse->json('question.answers'), 'id'))
        ->toBe($payload['answer_order'][(string) $secondQuestionId]);
});
