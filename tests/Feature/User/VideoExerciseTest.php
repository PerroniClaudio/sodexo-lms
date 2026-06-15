<?php

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoExercise;
use App\Models\VideoExerciseSubmission;
use App\Models\VideoTrackingEvent;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

beforeEach(function () {
    Storage::fake('s3');
    $this->withoutVite();
});

function videoExerciseScenario(): array
{
    test()->seed(RoleAndPermissionSeeder::class);

    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Exercise',
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
    $video = Video::factory()->create(['duration_seconds' => 100]);
    $module = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'belongsTo' => (string) $course->getKey(),
        'video_id' => $video->getKey(),
    ]);
    $enrollment = CourseEnrollment::enroll($user, $course);
    $exercise = $module->videoExercises()->create([
        'title' => 'Esercitazione sicurezza',
        'appears_at_seconds' => 10,
        'minimum_seconds' => 60,
    ]);
    $question = $exercise->questions()->create([
        'text' => 'Cosa hai imparato?',
        'minimum_characters' => 10,
        'order' => 1,
    ]);

    return [$user, $course, $module, $enrollment, $exercise, $question];
}

it('stores and edits video exercises from dedicated admin pages', function () {
    [, $course, $module] = videoExerciseScenario();

    $response = $this->post(route('admin.courses.modules.video-exercises.store', [$course, $module]), [
        'title' => 'Nuova esercitazione',
    ]);

    $exercise = VideoExercise::query()->where('title', 'Nuova esercitazione')->firstOrFail();

    $response->assertRedirect(route('admin.courses.modules.video-exercises.edit', [$course, $module, $exercise]));

    $this->put(route('admin.courses.modules.video-exercises.update', [$course, $module, $exercise]), [
        'title' => 'Nuova esercitazione aggiornata',
        'appears_at' => '00:00:30',
        'minimum_time' => '00:02',
        'self_evaluation' => UploadedFile::fake()->create('autovalutazione.pdf', 12, 'application/pdf'),
    ])->assertRedirect(route('admin.courses.modules.video-exercises.edit', [$course, $module, $exercise]));

    $this->post(route('admin.courses.modules.video-exercises.materials.store', [$course, $module, $exercise]), [
        'type' => 'file',
        'title' => 'Documento',
        'file' => UploadedFile::fake()->create('supporto.docx', 12, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
    ])->assertRedirect(route('admin.courses.modules.video-exercises.edit', [$course, $module, $exercise]));

    $this->post(route('admin.courses.modules.video-exercises.questions.store', [$course, $module, $exercise]), [
        'text' => 'Rispondi alla domanda',
        'minimum_characters' => 20,
    ])->assertRedirect(route('admin.courses.modules.video-exercises.edit', [$course, $module, $exercise]));

    $exercise->refresh()->load(['questions', 'materials']);

    expect($exercise->appears_at_seconds)->toBe(30)
        ->and($exercise->minimum_seconds)->toBe(120)
        ->and($exercise->questions)->toHaveCount(1)
        ->and($exercise->materials)->toHaveCount(1)
        ->and($exercise->self_evaluation_path)->not->toBeNull();
});

it('autosaves and restores video exercise drafts', function () {
    [, $course, $module, , $exercise, $question] = videoExerciseScenario();

    $this->postJson(route('user.courses.modules.video.exercises.autosave', [$course, $module, $exercise]), [
        'elapsed_seconds' => 20,
        'answers' => [
            $question->getKey() => 'bozza risposta',
        ],
    ])->assertOk()
        ->assertJsonPath('submission.status', VideoExerciseSubmission::STATUS_IN_PROGRESS);

    $this->getJson(route('user.courses.modules.video.exercises.index', [$course, $module]))
        ->assertOk()
        ->assertJsonPath('exercises.0.submission.elapsed_seconds', 20)
        ->assertJsonPath('exercises.0.submission.answers.'.$question->getKey(), 'bozza risposta');
});

it('blocks submit until minimum time and answer length are satisfied', function () {
    [, $course, $module, , $exercise, $question] = videoExerciseScenario();

    $this->postJson(route('user.courses.modules.video.exercises.submit', [$course, $module, $exercise]), [
        'elapsed_seconds' => 20,
        'answers' => [
            $question->getKey() => 'short',
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['elapsed_seconds', 'answers.'.$question->getKey()]);

    $this->postJson(route('user.courses.modules.video.exercises.submit', [$course, $module, $exercise]), [
        'elapsed_seconds' => 60,
        'answers' => [
            $question->getKey() => 'Risposta sufficientemente lunga',
        ],
    ])->assertOk()
        ->assertJsonPath('submission.status', VideoExerciseSubmission::STATUS_COMPLETED);
});

it('blocks submit until all support files are downloaded', function () {
    [, $course, $module, , $exercise, $question] = videoExerciseScenario();

    Storage::disk('s3')->put('modules/file.pdf', 'pdf');

    $material = $exercise->materials()->create([
        'type' => 'file',
        'title' => 'Documento di supporto',
        'disk' => 's3',
        'path' => 'modules/file.pdf',
        'original_name' => 'file.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 3,
        'uploaded_at' => now(),
    ]);

    $this->postJson(route('user.courses.modules.video.exercises.submit', [$course, $module, $exercise]), [
        'elapsed_seconds' => 60,
        'answers' => [
            $question->getKey() => 'Risposta sufficientemente lunga',
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['materials']);

    $this->get(route('user.courses.modules.video.exercises.materials.download', [$course, $module, $exercise, $material]))
        ->assertOk();

    $this->postJson(route('user.courses.modules.video.exercises.submit', [$course, $module, $exercise]), [
        'elapsed_seconds' => 60,
        'answers' => [
            $question->getKey() => 'Risposta sufficientemente lunga',
        ],
        'downloaded_material_ids' => [$material->getKey()],
    ])->assertOk()
        ->assertJsonPath('submission.status', VideoExerciseSubmission::STATUS_COMPLETED);
});

it('does not complete video module until exercises are completed', function () {
    [, $course, $module, $enrollment, $exercise, $question] = videoExerciseScenario();

    $payload = [
        'session_uuid' => (string) Str::uuid(),
        'event_uuid' => (string) Str::uuid(),
        'event_type' => VideoTrackingEvent::TYPE_ENDED,
        'occurred_at' => now()->toIso8601String(),
        'position_second' => 100,
        'max_second_client' => 100,
        'delta_watched_seconds' => 75,
        'player_ended' => true,
    ];
    $progress = $enrollment->moduleProgresses()->where('module_id', $module->getKey())->firstOrFail();
    $progress->syncVideoTrackingState(95, 95, 95);

    $this->postJson(route('user.courses.modules.video.events', [$course, $module]), $payload)
        ->assertOk()
        ->assertJsonPath('is_completed', false);

    expect($enrollment->moduleProgresses()->where('module_id', $module->getKey())->value('status'))
        ->toBe(ModuleProgress::STATUS_IN_PROGRESS);

    $this->postJson(route('user.courses.modules.video.exercises.submit', [$course, $module, $exercise]), [
        'elapsed_seconds' => 60,
        'answers' => [
            $question->getKey() => 'Risposta sufficientemente lunga',
        ],
    ])->assertOk();

    $payload['event_uuid'] = (string) Str::uuid();

    $this->postJson(route('user.courses.modules.video.events', [$course, $module]), $payload)
        ->assertOk()
        ->assertJsonPath('is_completed', true);
});

it('downloads exercise report pdf', function () {
    [, $course, $module, , $exercise, $question] = videoExerciseScenario();

    $this->postJson(route('user.courses.modules.video.exercises.submit', [$course, $module, $exercise]), [
        'elapsed_seconds' => 60,
        'answers' => [
            $question->getKey() => 'Risposta sufficientemente lunga',
        ],
    ])->assertOk();

    Pdf::fake();

    $this->get(route('user.courses.modules.video.exercises.report', [$course, $module, $exercise]))
        ->assertOk();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf) use ($course, $module, $exercise): bool {
        expect($pdf->viewName)->toBe('pdf.video-exercise-report')
            ->and($pdf->viewData['course']->is($course))->toBeTrue()
            ->and($pdf->viewData['module']->is($module))->toBeTrue()
            ->and($pdf->viewData['exercise']->is($exercise))->toBeTrue();

        return true;
    });
});
