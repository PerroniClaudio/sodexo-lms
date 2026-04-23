<?php

use App\Models\Course;
use App\Models\Module;
use Database\Seeders\ResCourseDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it seeds a res course with a residential module, a learning quiz with 20 questions, and a satisfaction quiz', function () {
    $this->seed(ResCourseDemoSeeder::class);

    $course = Course::query()
        ->where('title', 'Corso demo RES con quiz')
        ->first();

    expect($course)->not->toBeNull();
    expect($course->type)->toBe('res');
    expect($course->status)->toBe('published');

    $modules = Module::query()
        ->where('belongsTo', (string) $course->getKey())
        ->orderBy('order')
        ->get();

    expect($modules)->toHaveCount(3);
    expect($modules->pluck('type')->all())->toBe([
        'res',
        'learning_quiz',
        'satisfaction_quiz',
    ]);

    $resModule = $modules->firstWhere('type', 'res');
    $learningQuizModule = $modules->firstWhere('type', 'learning_quiz');
    $satisfactionQuizModule = $modules->firstWhere('type', 'satisfaction_quiz');

    expect($resModule)->not->toBeNull();
    expect($resModule->status)->toBe('published');
    expect($resModule->appointment_start_time)->not->toBeNull();
    expect($resModule->appointment_end_time)->not->toBeNull();

    expect($learningQuizModule)->not->toBeNull();
    expect($learningQuizModule->status)->toBe('published');
    expect($learningQuizModule->passing_score)->toBe(12);
    expect($learningQuizModule->max_score)->toBe(20);

    $questions = $learningQuizModule->quizQuestions()
        ->with(['answers', 'correctAnswer'])
        ->orderBy('id')
        ->get();

    expect($questions)->toHaveCount(20);
    expect($questions->every(fn ($question) => $question->answers->count() === 4))->toBeTrue();
    expect($questions->every(fn ($question) => $question->correctAnswer !== null))->toBeTrue();
    expect($questions->every(fn ($question) => $question->answers->contains('id', $question->correct_answer_id)))->toBeTrue();
    expect($questions->sum('points'))->toBe(20);

    expect($satisfactionQuizModule)->not->toBeNull();
    expect($satisfactionQuizModule->status)->toBe('published');
    expect($satisfactionQuizModule->passing_score)->toBe(1);
    expect($satisfactionQuizModule->max_score)->toBe(1);
    expect($satisfactionQuizModule->quizQuestions()->count())->toBe(0);
});
