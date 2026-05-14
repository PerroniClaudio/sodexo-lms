<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use App\Models\ModuleQuizSubmission;
use App\Models\User;
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

    $course = Course::factory()->create();
    $module = Module::factory()->create(array_merge([
        'type' => $moduleType,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ], $moduleAttributes));

    $enrollment = CourseEnrollment::enroll($user, $course);

    return [$user, $course, $module, $enrollment];
}

test('module player page is accessible for enrolled user on current module', function () {
    [, $course, $module] = enrollUserInCourseWithModule('video');

    $this->get(route('user.courses.modules.player', [$course, $module]))
        ->assertOk();
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

test('video complete endpoint marks module as completed', function () {
    [, $course, $module, $enrollment] = enrollUserInCourseWithModule('video');

    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();
    $progress->recordVideoProgress(0);

    $this->postJson(
        route('user.courses.modules.video.complete', [$course, $module])
    )->assertOk()
        ->assertJson(['success' => true]);

    expect(
        $enrollment->moduleProgresses()
            ->where('module_id', $module->getKey())
            ->value('status')
    )->toBe(ModuleProgress::STATUS_COMPLETED);
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

    expect($submission->fresh()->status)->toBe(ModuleQuizSubmission::STATUS_FAILED);
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
