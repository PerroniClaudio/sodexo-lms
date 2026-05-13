<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    actingAsRole('admin');
});

it('blocks creating quiz questions when the learning quiz is published', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => Module::TYPE_LEARNING_QUIZ,
        'status' => 'published',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this->postJson(route('admin.api.courses.modules.quiz.questions.store', [$course, $module]), [
        'text' => 'Nuova domanda',
        'points' => 1,
    ])->assertStatus(422);
});

it('blocks editing quiz answers when the learning quiz is published', function () {
    $course = Course::factory()->create();
    $module = Module::factory()->create([
        'type' => Module::TYPE_LEARNING_QUIZ,
        'status' => 'published',
        'belongsTo' => (string) $course->getKey(),
    ]);
    $question = ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda',
        'points' => 1,
    ]);
    $answer = ModuleQuizAnswer::query()->create([
        'question_id' => $question->getKey(),
        'text' => 'Risposta',
    ]);

    $this->putJson(route('admin.api.courses.modules.quiz.answers.update', [$course, $module, $question, $answer]), [
        'text' => 'Risposta aggiornata',
    ])->assertStatus(422);
});
