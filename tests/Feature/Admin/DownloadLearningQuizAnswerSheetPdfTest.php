<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleQuizQuestion;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Middleware\RoleMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        Authenticate::class,
        RoleMiddleware::class,
    ]);

    $this->createTestUser = fn (array $attributes = []): User => User::query()->create(array_merge([
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'account_state' => 'active',
        'name' => fake()->firstName(),
        'surname' => fake()->lastName(),
        'fiscal_code' => strtoupper(fake()->bothify('??????##?##?####')),
    ], $attributes));
});

it('downloads answer sheet pdf for learning quiz modules in res courses', function () {
    $course = Course::factory()->create([
        'title' => 'Corso RES sicurezza',
        'type' => 'res',
    ]);
    $module = Module::factory()->create([
        'title' => 'Quiz di apprendimento',
        'type' => 'learning_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);
    $user = ($this->createTestUser)();

    CourseEnrollment::factory()->create([
        'user_id' => $user->getKey(),
        'course_id' => $course->getKey(),
    ]);
    ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda esempio',
        'points' => 1,
    ]);

    $response = $this->get(route('admin.courses.modules.quiz.answer-sheet.pdf.download', [$course, $module]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/pdf');
    $response->assertDownload('corso-res-sicurezza-quiz-di-apprendimento-answer-sheet.pdf');
});

it('renders one answer sheet for each enrolled user with twenty questions per sheet', function () {
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

    ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Prima domanda reale',
        'points' => 2,
    ]);
    ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Seconda domanda reale',
        'points' => 2,
    ]);

    $firstUser = ($this->createTestUser)([
        'email' => 'first@example.com',
        'name' => 'Anna',
        'surname' => 'Bianchi',
    ]);
    $secondUser = ($this->createTestUser)([
        'email' => 'second@example.com',
        'name' => 'Zeno',
        'surname' => 'Rossi',
    ]);

    CourseEnrollment::factory()->create([
        'user_id' => $firstUser->getKey(),
        'course_id' => $course->getKey(),
    ]);
    CourseEnrollment::factory()->create([
        'user_id' => $secondUser->getKey(),
        'course_id' => $course->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.quiz.answer-sheet.pdf.download', [$course, $module]));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($course, $module, $firstUser, $secondUser): bool {
        $html = $pdf->getHtml();

        expect($pdf->viewName)->toBe('pdf.learning-quiz-answer-sheet');
        expect($pdf->viewData['course']->is($course))->toBeTrue();
        expect($pdf->viewData['module']->is($module))->toBeTrue();
        expect($pdf->downloadName)->toBe('corso-res-con-quiz-quiz-di-apprendimento-answer-sheet.pdf');
        expect($pdf->viewData['userSheets'])->toHaveCount(2);
        expect($pdf->viewData['userSheets']->pluck('qrCodeContent')->all())->toBe([
            base64_encode($course->getKey().'*'.$module->getKey().'*'.$firstUser->getKey()),
            base64_encode($course->getKey().'*'.$module->getKey().'*'.$secondUser->getKey()),
        ]);
        expect($pdf->viewData['userSheets'][0]['questionNumbers']->all())->toBe([1, 2]);
        expect($pdf->viewData['userSheets'][1]['questionNumbers']->all())->toBe([1, 2]);
        expect(substr_count($html, 'data:image/svg+xml;base64,'))->toBe(2);
        expect(substr_count($html, '<span class="answer-square"'))->toBe(16);
        expect(substr_count($html, '<div class="sheet">'))->toBe(2);
        expect($html)->toContain('Corso RES con quiz');
        expect($html)->toContain('>2<');
        expect($html)->not->toContain('Prima domanda reale');
        expect($html)->not->toContain('Seconda domanda reale');

        return true;
    });
});

it('renders no answer sheets when course has no enrolled users', function () {
    Pdf::fake();

    $course = Course::factory()->create([
        'type' => 'res',
    ]);
    $module = Module::factory()->create([
        'type' => 'learning_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $response = $this->get(route('admin.courses.modules.quiz.answer-sheet.pdf.download', [$course, $module]));

    $response->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        $html = $pdf->getHtml();

        expect($pdf->viewData['userSheets'])->toHaveCount(0);
        expect($html)->not->toContain('<div class="sheet">');
        expect($html)->not->toContain('data:image/svg+xml;base64,');

        return true;
    });
});
