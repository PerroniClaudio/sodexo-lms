<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\SatisfactionSurveyQuestion;
use App\Models\SatisfactionSurveySubmission;
use App\Models\SatisfactionSurveyTemplate;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Support\Str;

function createSatisfactionTemplate(): SatisfactionSurveyTemplate
{
    $template = SatisfactionSurveyTemplate::query()->create([
        'is_active' => true,
        'activated_at' => now(),
    ]);

    $firstQuestion = $template->questions()->create([
        'sort_order' => 1,
        'text' => 'Valutazione complessiva',
        'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
    ]);

    $firstQuestion->answers()->createMany(collect(range(1, 5))->map(fn (int $index): array => [
        'sort_order' => $index,
        'text' => "Opzione {$index}",
    ])->all());

    $secondQuestion = $template->questions()->create([
        'sort_order' => 2,
        'text' => 'Valutazione riservata ai corsi RES',
        'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_RADIO,
        'excluded_course_types' => ['fad', 'async'],
    ]);

    $secondQuestion->answers()->createMany(collect(range(1, 5))->map(fn (int $index): array => [
        'sort_order' => $index,
        'text' => "Risposta RES {$index}",
    ])->all());

    $template->questions()->create([
        'sort_order' => 3,
        'text' => 'Suggerimenti finali',
        'input_type' => SatisfactionSurveyQuestion::INPUT_TYPE_TEXTAREA,
    ]);

    return $template->fresh(['questions.answers']);
}

function createSurveyEnrollment(bool $required): array
{
    test()->seed(RoleAndPermissionSeeder::class);

    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Survey',
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
        'type' => 'fad',
        'status' => 'draft',
        'has_satisfaction_survey' => true,
        'satisfaction_survey_required_for_certificate' => $required,
    ]);

    $videoModule = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);
    $surveyModule = Module::factory()->create([
        'type' => Module::TYPE_SATISFACTION_QUIZ,
        'title' => Module::defaultTitleForType(Module::TYPE_SATISFACTION_QUIZ),
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);
    $videoProgress = $enrollment->moduleProgresses()->where('module_id', $videoModule->getKey())->firstOrFail();
    $videoProgress->markCompleted();

    return [$course, $surveyModule, $enrollment];
}

it('marks the enrollment completed after the last required module even if satisfaction survey is optional', function () {
    [, $surveyModule, $enrollment] = createSurveyEnrollment(false);

    $enrollment->refresh();
    $surveyProgress = $enrollment->moduleProgresses()->where('module_id', $surveyModule->getKey())->firstOrFail();

    expect($enrollment->status)->toBe(CourseEnrollment::STATUS_COMPLETED);
    expect($enrollment->completion_percentage)->toBe(100);
    expect($enrollment->current_module_id)->toBe($surveyModule->getKey());
    expect($surveyProgress->status)->toBe(ModuleProgress::STATUS_AVAILABLE);
});

it('stores anonymous satisfaction answers, skips excluded questions and accepts open text responses', function () {
    $template = createSatisfactionTemplate();
    [$course, $surveyModule, $enrollment] = createSurveyEnrollment(true);

    $response = $this->getJson(route('user.courses.modules.satisfaction-survey.show', [$course, $surveyModule]));
    $response->assertOk();
    $response->assertJson(['completed' => false]);
    $response->assertJsonCount(2, 'questions');

    $answers = [];

    foreach ($template->questions as $question) {
        if ($question->usesTextarea()) {
            $answers[$question->getKey()] = 'Ottima esperienza complessiva.';

            continue;
        }

        if ($question->isExcludedForCourseType($course->type)) {
            continue;
        }

        $answers[$question->getKey()] = $question->answers->first()->getKey();
    }

    $this->postJson(route('user.courses.modules.satisfaction-survey.submit', [$course, $surveyModule]), [
        'template_id' => $template->getKey(),
        'answers' => $answers,
    ])->assertOk()
        ->assertJson(['success' => true]);

    $enrollment->refresh();
    $surveyProgress = $enrollment->moduleProgresses()->where('module_id', $surveyModule->getKey())->firstOrFail();
    $submission = SatisfactionSurveySubmission::query()->first();

    expect($surveyProgress->status)->toBe(ModuleProgress::STATUS_COMPLETED);
    expect($enrollment->status)->toBe(CourseEnrollment::STATUS_COMPLETED);
    expect($submission)->not->toBeNull();
    expect($submission->course_id)->toBe($course->getKey());
    expect($submission->module_id)->toBe($surveyModule->getKey());
    expect($submission->answers)->toHaveCount(2);
    expect($submission->answers->firstWhere('open_text', 'Ottima esperienza complessiva.'))->not->toBeNull();
});
