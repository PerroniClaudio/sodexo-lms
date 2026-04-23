<?php

use App\Jobs\ProcessQuizSubmission;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizQuestion;
use App\Models\ModuleQuizSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Middleware\RoleMiddleware;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware(RoleMiddleware::class);
    $this->withoutVite();

    $user = User::query()->create([
        'email' => 'admin@example.test',
        'password' => Hash::make('password'),
        'account_state' => 'active',
        'name' => 'Admin',
        'surname' => 'User',
        'fiscal_code' => 'ADMINUSER0000001',
    ]);

    $this->actingAs($user);
});

function createLearningQuizModule(): array
{
    $course = Course::factory()->create([
        'type' => 'res',
        'title' => 'Corso quiz OCR',
    ]);

    $module = Module::factory()->create([
        'type' => 'learning_quiz',
        'belongsTo' => (string) $course->getKey(),
        'passing_score' => 2,
        'max_score' => 3,
        'title' => 'Quiz OCR',
    ]);

    $firstQuestion = ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Prima domanda',
        'points' => 2,
    ]);
    $firstAnswers = collect([
        'Risposta A1',
        'Risposta B1',
        'Risposta C1',
        'Risposta D1',
    ])->map(fn (string $text) => ModuleQuizAnswer::query()->create([
        'question_id' => $firstQuestion->getKey(),
        'text' => $text,
    ]));
    $firstQuestion->update([
        'correct_answer_id' => $firstAnswers[0]->getKey(),
    ]);

    $secondQuestion = ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Seconda domanda',
        'points' => 1,
    ]);
    $secondAnswers = collect([
        'Risposta A2',
        'Risposta B2',
        'Risposta C2',
        'Risposta D2',
    ])->map(fn (string $text) => ModuleQuizAnswer::query()->create([
        'question_id' => $secondQuestion->getKey(),
        'text' => $text,
    ]));
    $secondQuestion->update([
        'correct_answer_id' => $secondAnswers[1]->getKey(),
    ]);

    return [$course, $module, $firstQuestion, $secondQuestion];
}

it('shows OCR upload controls on the learning quiz module page', function () {
    [$course, $module] = createLearningQuizModule();

    $response = $this->get(route('admin.courses.modules.edit', [$course, $module]));

    $response->assertOk();
    $response->assertSee(route('admin.courses.modules.quiz.submissions.store', [$course, $module]), escape: false);
    $response->assertSeeText('Avvia OCR');
    $response->assertSeeText('Vedi submission OCR');
});

it('stores an uploaded quiz submission and dispatches the OCR job', function () {
    Storage::fake('local');
    Queue::fake();
    [$course, $module] = createLearningQuizModule();

    $response = $this->post(route('admin.courses.modules.quiz.submissions.store', [$course, $module]), [
        'submission' => UploadedFile::fake()->create('quiz.pdf', 200, 'application/pdf'),
    ]);

    $response
        ->assertRedirect(route('admin.courses.modules.edit', [$course, $module]))
        ->assertSessionHas('status');

    $submission = ModuleQuizSubmission::query()->firstOrFail();

    expect($submission->module_id)->toBe($module->getKey());
    expect($submission->status)->toBe(ModuleQuizSubmission::STATUS_UPLOADED);
    Storage::disk('local')->assertExists($submission->path);

    Queue::assertPushed(ProcessQuizSubmission::class, function (ProcessQuizSubmission $job) use ($submission): bool {
        return $job->submission->is($submission);
    });
});

it('renders the OCR submissions list and review page', function () {
    [$course, $module, $firstQuestion, $secondQuestion] = createLearningQuizModule();
    $user = User::query()->create([
        'email' => 'mario@example.test',
        'password' => Hash::make('password'),
        'account_state' => 'active',
        'name' => 'Mario',
        'surname' => 'Rossi',
        'fiscal_code' => 'MARIORSS0000001',
    ]);

    $submission = ModuleQuizSubmission::query()->create([
        'module_id' => $module->getKey(),
        'user_id' => $user->getKey(),
        'uploaded_by' => auth()->id(),
        'disk' => 'local',
        'path' => 'quiz-submissions/test.pdf',
        'status' => ModuleQuizSubmission::STATUS_NEEDS_REVIEW,
    ]);

    $submission->answers()->create([
        'module_quiz_question_id' => $firstQuestion->getKey(),
        'question_number' => 1,
        'selected_option_key' => 'A',
        'confidence' => 0.98,
    ]);
    $submission->answers()->create([
        'module_quiz_question_id' => $secondQuestion->getKey(),
        'question_number' => 2,
        'selected_option_key' => 'B',
        'confidence' => 0.87,
    ]);

    $this->get(route('admin.courses.modules.quiz.submissions.index', [$course, $module]))
        ->assertOk()
        ->assertSeeText('Mario Rossi')
        ->assertSeeText(ModuleQuizSubmission::STATUS_NEEDS_REVIEW);

    $this->get(route('admin.courses.modules.quiz.submissions.review', [$course, $module, $submission]))
        ->assertOk()
        ->assertSeeText('Prima domanda')
        ->assertSeeText('Seconda domanda')
        ->assertSee('value="A" selected', escape: false)
        ->assertSee('value="B" selected', escape: false);
});

it('finalizes a reviewed submission and updates module progress', function () {
    [$course, $module, $firstQuestion, $secondQuestion] = createLearningQuizModule();
    $user = User::query()->create([
        'email' => 'laura@example.test',
        'password' => Hash::make('password'),
        'account_state' => 'active',
        'name' => 'Laura',
        'surname' => 'Bianchi',
        'fiscal_code' => 'LAURABNC0000001',
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);
    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();

    $submission = ModuleQuizSubmission::query()->create([
        'module_id' => $module->getKey(),
        'user_id' => $user->getKey(),
        'uploaded_by' => auth()->id(),
        'disk' => 'local',
        'path' => 'quiz-submissions/test.pdf',
        'status' => ModuleQuizSubmission::STATUS_NEEDS_REVIEW,
    ]);

    $response = $this->post(route('admin.courses.modules.quiz.submissions.finalize', [$course, $module, $submission]), [
        'answers' => [
            [
                'question_id' => $firstQuestion->getKey(),
                'selected_option_key' => 'A',
            ],
            [
                'question_id' => $secondQuestion->getKey(),
                'selected_option_key' => 'B',
            ],
        ],
    ]);

    $response
        ->assertRedirect(route('admin.courses.modules.quiz.submissions.show', [$course, $module, $submission]))
        ->assertSessionHas('status');

    $submission->refresh();
    $progress->refresh();

    expect($submission->status)->toBe(ModuleQuizSubmission::STATUS_FINALIZED);
    expect($submission->score)->toBe(3);
    expect($submission->total_score)->toBe(3);
    expect($submission->finalized_by)->toBe(auth()->id());
    expect($progress->status)->toBe(ModuleProgress::STATUS_COMPLETED);
    expect($progress->quiz_score)->toBe(3);
    expect($progress->quiz_total_score)->toBe(3);
});
