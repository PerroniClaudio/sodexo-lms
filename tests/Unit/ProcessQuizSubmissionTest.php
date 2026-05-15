<?php

use App\Jobs\ProcessQuizSubmission;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Module;
use App\Models\ModuleQuizAnswer;
use App\Models\ModuleQuizDocumentUpload;
use App\Models\ModuleQuizQuestion;
use App\Models\ModuleQuizSubmission;
use App\Models\User;
use App\Services\GoogleDocumentAiQuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('services.google_document_ai.access_token', 'test-token');
    config()->set('services.google_document_ai.project_id', 'test-project');
    config()->set('services.google_document_ai.location', 'eu');
    config()->set('services.google_document_ai.processor_id', 'processor-123');
});

function buildQuizSubmissionFixture(): array
{
    Storage::fake('local');

    $course = Course::factory()->create([
        'type' => 'res',
    ]);
    $module = Module::factory()->create([
        'type' => 'learning_quiz',
        'belongsTo' => (string) $course->getKey(),
    ]);

    $question = ModuleQuizQuestion::query()->create([
        'module_id' => $module->getKey(),
        'text' => 'Domanda uno',
        'points' => 1,
    ]);

    collect(['A', 'B', 'C', 'D'])->each(fn (string $label) => ModuleQuizAnswer::query()->create([
        'question_id' => $question->getKey(),
        'text' => 'Risposta '.$label,
    ]));

    $user = User::query()->create([
        'email' => 'ocr-user@example.test',
        'password' => bcrypt('password'),
        'account_state' => 'active',
        'name' => 'OCR',
        'surname' => 'User',
        'fiscal_code' => 'OCRUSER00000001',
    ]);
    CourseEnrollment::enroll($user, $course);

    Storage::disk('local')->put('quiz-submissions/test.pdf', 'pdf');

    $documentUpload = ModuleQuizDocumentUpload::query()->create([
        'module_id' => $module->getKey(),
        'uploaded_by' => $user->getKey(),
        'disk' => 'local',
        'path' => 'quiz-submissions/test.pdf',
        'status' => ModuleQuizDocumentUpload::STATUS_UPLOADED,
        'provider' => 'google_document_ai',
    ]);

    return [$course, $module, $question, $user, $documentUpload];
}

it('marks a document upload as processed and creates a submission when OCR succeeds', function () {
    [$course, $module, $question, $user, $documentUpload] = buildQuizSubmissionFixture();

    Http::fake([
        'https://eu-documentai.googleapis.com/*' => Http::response([
            'document' => [
                'entities' => [
                    [
                        'type' => 'submission_qr',
                        'mentionText' => base64_encode($course->getKey().'*'.$module->getKey().'*'.$user->getKey()),
                    ],
                    [
                        'type' => 'q_1',
                        'mentionText' => 'A',
                        'confidence' => 0.97,
                    ],
                ],
            ],
        ]),
    ]);

    $job = new ProcessQuizSubmission($documentUpload);
    $job->handle(app(GoogleDocumentAiQuizService::class));

    $documentUpload->refresh();

    expect($documentUpload->status)->toBe(ModuleQuizDocumentUpload::STATUS_PROCESSED);
    expect($documentUpload->provider_payload)->not->toBeNull();

    // Verifica che sia stata creata una submission
    expect($documentUpload->submissions)->toHaveCount(1);
    $submission = $documentUpload->submissions->first();
    expect($submission->status)->toBe(ModuleQuizSubmission::STATUS_NEEDS_REVIEW);
    expect($submission->user_id)->toBe($user->getKey());
    expect($submission->source_type)->toBe(ModuleQuizSubmission::SOURCE_UPLOAD);
    expect($submission->document_upload_id)->toBe($documentUpload->getKey());
    expect($submission->answers)->toHaveCount(1);
    expect($submission->answers->first()->module_quiz_question_id)->toBe($question->getKey());
    expect($submission->answers->first()->selected_option_key)->toBe('A');
});

it('marks a document upload as failed when QR binding is invalid', function () {
    [, $module, , $user, $documentUpload] = buildQuizSubmissionFixture();

    Http::fake([
        'https://eu-documentai.googleapis.com/*' => Http::response([
            'document' => [
                'entities' => [
                    [
                        'type' => 'submission_qr',
                        'mentionText' => base64_encode('999*999*'.$user->getKey()),
                    ],
                ],
            ],
        ]),
    ]);

    expect(fn () => (new ProcessQuizSubmission($documentUpload))->handle(app(GoogleDocumentAiQuizService::class)))
        ->toThrow(RuntimeException::class);

    $documentUpload->refresh();

    expect($documentUpload->status)->toBe(ModuleQuizDocumentUpload::STATUS_FAILED);
    expect($documentUpload->error_message)->toContain('QR');
});
