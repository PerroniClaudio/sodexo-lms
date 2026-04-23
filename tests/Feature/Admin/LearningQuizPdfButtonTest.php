<?php

use App\Models\Course;
use App\Models\Module;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Middleware\RoleMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        Authenticate::class,
        RoleMiddleware::class,
    ]);
    $this->withoutVite();
});

it('shows the quiz pdf download button only for learning quiz modules in res courses', function () {
    $resCourse = Course::factory()->create([
        'type' => 'res',
    ]);
    $learningQuizModule = Module::factory()->create([
        'type' => 'learning_quiz',
        'title' => 'Quiz di apprendimento',
        'belongsTo' => (string) $resCourse->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.edit', [$resCourse, $learningQuizModule]));

    $response->assertOk();
    $response->assertSee(route('admin.courses.modules.quiz.pdf.download', [$resCourse, $learningQuizModule]), escape: false);
    $response->assertSeeText('Scarica PDF');

    $nonResCourse = Course::factory()->create([
        'type' => 'fad',
    ]);
    $nonResLearningQuizModule = Module::factory()->create([
        'type' => 'learning_quiz',
        'belongsTo' => (string) $nonResCourse->getKey(),
    ]);

    $nonResResponse = $this->get(route('admin.courses.modules.edit', [$nonResCourse, $nonResLearningQuizModule]));

    $nonResResponse->assertOk();
    $nonResResponse->assertDontSee('Scarica PDF');

    $resSatisfactionQuizModule = Module::factory()->create([
        'type' => 'satisfaction_quiz',
        'belongsTo' => (string) $resCourse->getKey(),
    ]);

    $resSatisfactionResponse = $this->get(route('admin.courses.modules.edit', [$resCourse, $resSatisfactionQuizModule]));

    $resSatisfactionResponse->assertOk();
    $resSatisfactionResponse->assertDontSee('Scarica PDF');
});
