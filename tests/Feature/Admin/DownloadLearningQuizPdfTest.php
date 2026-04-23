<?php

use App\Models\Course;
use App\Models\Module;
use App\Models\ModuleQuizQuestion;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Middleware\RoleMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        Authenticate::class,
        RoleMiddleware::class,
    ]);
});

it('downloads pdf for learning quiz modules in res courses', function () {
    $course = Course::factory()->create([
        'title' => 'Corso RES sicurezza',
        'type' => 'res',
    ]);
    $module = Module::factory()->create([
        'title' => 'Quiz di apprendimento',
        'type' => 'learning_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);

    ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda esempio',
        'points' => 1,
    ]);

    $response = $this->get(route('admin.courses.modules.quiz.pdf.download', [$course, $module]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertDownload('corso-res-sicurezza-quiz-di-apprendimento-quiz.pdf');
});

it('returns 404 when course is not res', function () {
    $course = Course::factory()->create([
        'type' => 'fad',
    ]);
    $module = Module::factory()->create([
        'type' => 'learning_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this->get(route('admin.courses.modules.quiz.pdf.download', [$course, $module]))
        ->assertNotFound();
});

it('returns 404 when module is not a learning quiz', function (string $moduleType) {
    $course = Course::factory()->create([
        'type' => 'res',
    ]);
    $module = Module::factory()->create([
        'type' => $moduleType,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $this->get(route('admin.courses.modules.quiz.pdf.download', [$course, $module]))
        ->assertNotFound();
})->with([
    'satisfaction quiz' => 'satisfaction_quiz',
    'video' => 'video',
    'residential' => 'res',
]);

it('renders course title questions and first four answers without revealing the correct answer', function () {
    Pdf::fake();

    $course = Course::factory()->create([
        'title' => 'Corso RES con quiz',
        'type' => 'res',
    ]);
    $module = Module::factory()->create([
        'title' => 'Quiz di apprendimento',
        'type' => 'learning_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);
    $question = ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Quale procedura segue il corso?',
        'points' => 2,
    ]);

    $answers = collect([
        'Prima risposta',
        'Seconda risposta',
        'Terza risposta',
        'Quarta risposta',
        'Quinta risposta',
    ])->map(fn (string $text) => $question->answers()->create(['text' => $text]));

    $question->update([
        'correct_answer_id' => $answers[2]->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.quiz.pdf.download', [$course, $module]));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($course, $module): bool {
        $html = $pdf->getHtml();

        expect($pdf->viewName)->toBe('pdf.learning-quiz');
        expect($pdf->viewData['course']->is($course))->toBeTrue();
        expect($pdf->viewData['module']->is($module))->toBeTrue();
        expect($pdf->downloadName)->toBe('corso-res-con-quiz-quiz-di-apprendimento-quiz.pdf');
        expect($html)->toContain('Corso RES con quiz');
        expect($html)->toContain('Quale procedura segue il corso?');
        expect($html)->toContain('Prima risposta');
        expect($html)->toContain('Seconda risposta');
        expect($html)->toContain('Terza risposta');
        expect($html)->toContain('Quarta risposta');
        expect($html)->not->toContain('Quinta risposta');
        expect($html)->not->toContain('Corretta');
        expect($html)->not->toContain('Sbagliata');
        expect($html)->not->toContain('correct_answer_id');

        return true;
    });
});

it('includes only existing answers when question has fewer than four answers', function () {
    Pdf::fake();

    $course = Course::factory()->create([
        'type' => 'res',
    ]);
    $module = Module::factory()->create([
        'type' => 'learning_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);
    $question = ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda con poche risposte',
        'points' => 1,
    ]);

    $question->answers()->create(['text' => 'Risposta uno']);
    $question->answers()->create(['text' => 'Risposta due']);

    $response = $this->get(route('admin.courses.modules.quiz.pdf.download', [$course, $module]));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        $html = $pdf->getHtml();

        expect($html)->toContain('Risposta uno');
        expect($html)->toContain('Risposta due');
        expect($html)->not->toContain('Risposta tre');
        expect($html)->not->toContain('Risposta quattro');

        return true;
    });
});
