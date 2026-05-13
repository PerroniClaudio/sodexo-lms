<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders quiz question update and delete forms without nesting forms', function () {
    $course = Course::factory()->create([
        'title' => 'Corso test',
    ]);
    $module = Module::factory()->create([
        'type' => 'learning_quiz',
        'title' => 'Quiz di apprendimento',
        'belongsTo' => (string) $course->getKey(),
    ]);
    $question = ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda da aggiornare',
        'points' => 1,
    ]);
    ModuleQuizAnswer::query()->create([
        'question_id' => $question->getKey(),
        'text' => 'Risposta 1',
    ]);

    $html = view('admin.module.partials.quiz-questions', [
        'course' => $course,
        'module' => $module->load('quizQuestions.answers'),
    ])->render();

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    $updateFormAction = route('admin.courses.modules.quiz.questions.update', [$course, $module, $question]);
    $deleteFormAction = route('admin.courses.modules.quiz.questions.delete', [$course, $module, $question]);

    $updateForms = $xpath->query(sprintf('//form[@action="%s"][.//input[@name="_method" and @value="PUT"]]', $updateFormAction));
    $deleteForms = $xpath->query(sprintf('//form[@action="%s"][.//input[@name="_method" and @value="DELETE"]]', $deleteFormAction));

    expect($updateForms->length)->toBe(1);
    expect($deleteForms->length)->toBe(1);
    expect($xpath->query(sprintf('//form[@action="%s"][.//input[@name="_method" and @value="PUT"]]//form', $updateFormAction))->length)->toBe(0);
});

it('shows disabled quiz editing controls when the learning quiz is published', function () {
    $course = Course::factory()->create([
        'title' => 'Corso test',
    ]);
    $module = Module::factory()->create([
        'type' => 'learning_quiz',
        'status' => 'published',
        'title' => 'Quiz di apprendimento',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $html = view('admin.module.partials.quiz-questions', [
        'course' => $course,
        'module' => $module,
    ])->render();

    expect($html)->toContain('New question');
    expect($html)->toContain('Le domande e le risposte non possono essere modificate quando il quiz');
    expect($html)->toContain('data-quiz-editable="false"');
});
