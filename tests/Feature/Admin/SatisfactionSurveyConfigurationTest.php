<?php

use App\Models\SatisfactionSurveyQuestion;
use App\Models\SatisfactionSurveyTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('allows superadmin to manage the global satisfaction survey through api endpoints', function () {
    actingAsRole('superadmin');

    $storeResponse = $this->postJson(route('admin.api.satisfaction-survey.questions.store'), [
        'text' => 'Quanto è stato utile il corso?',
        'input_type' => 'radio',
        'excluded_course_types' => ['res'],
        'answers' => ['Per nulla', 'Poco', 'Abbastanza', 'Molto', 'Moltissimo'],
    ]);

    $storeResponse->assertCreated();
    $storeResponse->assertJsonPath('question.input_type', 'radio');

    $questionId = $storeResponse->json('question.id');

    $this->putJson(route('admin.api.satisfaction-survey.questions.update', $questionId), [
        'text' => 'Suggerimenti finali',
        'input_type' => 'textarea',
        'excluded_course_types' => ['async'],
    ])->assertOk()
        ->assertJsonPath('questions.0.input_type', 'textarea');

    $question = SatisfactionSurveyQuestion::query()->findOrFail($questionId);

    expect($question->text)->toBe('Suggerimenti finali');
    expect($question->input_type)->toBe('textarea');
    expect($question->excluded_course_types)->toBe(['async']);

    $this->deleteJson(route('admin.api.satisfaction-survey.questions.destroy', $questionId))
        ->assertOk();

    expect(SatisfactionSurveyQuestion::find($questionId))->toBeNull();
    expect(SatisfactionSurveyQuestion::withTrashed()->findOrFail($questionId)->trashed())->toBeTrue();
});

it('keeps textarea questions at the end during reorder', function () {
    actingAsRole('superadmin');

    $template = SatisfactionSurveyTemplate::query()->create([
        'is_active' => true,
        'activated_at' => now(),
    ]);

    $radioQuestion = $template->questions()->create([
        'sort_order' => 1,
        'text' => 'Domanda multipla',
        'input_type' => 'radio',
    ]);
    $radioQuestion->answers()->createMany(collect(range(1, 5))->map(fn (int $index): array => [
        'sort_order' => $index,
        'text' => "Risposta {$index}",
    ])->all());

    $textareaQuestion = $template->questions()->create([
        'sort_order' => 2,
        'text' => 'Domanda aperta',
        'input_type' => 'textarea',
    ]);

    $this->patchJson(route('admin.api.satisfaction-survey.questions.reorder'), [
        'question_ids' => [$textareaQuestion->getKey(), $radioQuestion->getKey()],
    ])->assertOk()
        ->assertJsonPath('questions.0.id', $radioQuestion->getKey())
        ->assertJsonPath('questions.1.id', $textareaQuestion->getKey());
});

it('rejects multiple choice questions with a number of answers different from five', function () {
    actingAsRole('superadmin');

    $this->postJson(route('admin.api.satisfaction-survey.questions.store'), [
        'text' => 'Domanda non valida',
        'input_type' => 'radio',
        'answers' => ['Una', 'Due', 'Tre', 'Quattro'],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['answers']);
});

it('forbids admins from editing the global satisfaction survey', function () {
    actingAsRole('admin');

    $this->get(route('admin.satisfaction-survey.edit'))->assertStatus(302);
});
