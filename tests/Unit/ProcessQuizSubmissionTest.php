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
use Illuminate\Http\Client\Request;
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

it('creates one submission for each QR detected in the uploaded document', function () {
    [$course, $module, $question, $firstUser, $documentUpload] = buildQuizSubmissionFixture();

    $secondUser = User::query()->create([
        'email' => 'second-ocr-user@example.test',
        'password' => bcrypt('password'),
        'account_state' => 'active',
        'name' => 'Second',
        'surname' => 'User',
        'fiscal_code' => 'OCRUSER00000002',
    ]);
    CourseEnrollment::enroll($secondUser, $course);

    Http::fake([
        'https://eu-documentai.googleapis.com/*' => Http::response([
            'document' => [
                'entities' => [
                    [
                        'type' => 'submission_qr',
                        'mentionText' => base64_encode($course->getKey().'*'.$module->getKey().'*'.$firstUser->getKey()),
                        'pageAnchor' => ['pageRefs' => [['page' => '0']]],
                    ],
                    [
                        'type' => 'q_1',
                        'mentionText' => 'A',
                        'confidence' => 0.97,
                        'pageAnchor' => ['pageRefs' => [['page' => '0']]],
                    ],
                    [
                        'type' => 'submission_qr',
                        'mentionText' => base64_encode($course->getKey().'*'.$module->getKey().'*'.$secondUser->getKey()),
                        'pageAnchor' => ['pageRefs' => [['page' => '1']]],
                    ],
                    [
                        'type' => 'q_1',
                        'mentionText' => 'B',
                        'confidence' => 0.88,
                        'pageAnchor' => ['pageRefs' => [['page' => '1']]],
                    ],
                ],
            ],
        ]),
    ]);

    (new ProcessQuizSubmission($documentUpload))->handle(app(GoogleDocumentAiQuizService::class));

    $documentUpload->refresh();

    $submissions = ModuleQuizSubmission::query()
        ->with('answers')
        ->where('module_id', $module->getKey())
        ->orderBy('id')
        ->get();

    expect($submissions)->toHaveCount(2);
    expect($documentUpload->status)->toBe(ModuleQuizDocumentUpload::STATUS_PROCESSED);

    expect($submissions[0]->user_id)->toBe($firstUser->getKey());
    expect($submissions[0]->status)->toBe(ModuleQuizSubmission::STATUS_NEEDS_REVIEW);
    expect($submissions[0]->document_upload_id)->toBe($documentUpload->getKey());
    expect($submissions[0]->answers)->toHaveCount(1);
    expect($submissions[0]->answers->first()->module_quiz_question_id)->toBe($question->getKey());
    expect($submissions[0]->answers->first()->selected_option_key)->toBe('A');

    expect($submissions[1]->user_id)->toBe($secondUser->getKey());
    expect($submissions[1]->status)->toBe(ModuleQuizSubmission::STATUS_NEEDS_REVIEW);
    expect($submissions[1]->document_upload_id)->toBe($documentUpload->getKey());
    expect($submissions[1]->answers)->toHaveCount(1);
    expect($submissions[1]->answers->first()->module_quiz_question_id)->toBe($question->getKey());
    expect($submissions[1]->answers->first()->selected_option_key)->toBe('B');
});

it('marks a submission as failed when QR binding is invalid', function () {
    [, , , $user, $documentUpload] = buildQuizSubmissionFixture();

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

it('can authenticate document ai with the shared google service account credentials', function () {
    config()->set('services.google_document_ai.access_token', null);
    config()->set('services.google.service_account_json_base64', base64_encode(json_encode([
        'client_email' => 'document-ai@example.iam.gserviceaccount.com',
        'private_key' => processQuizSubmissionPrivateKey(),
        'token_uri' => 'https://oauth2.googleapis.com/token',
    ], JSON_THROW_ON_ERROR)));

    [$course, $module, , $user, $documentUpload] = buildQuizSubmissionFixture();

    Http::fake([
        'https://oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'shared-service-account-token',
            'expires_in' => 3600,
            'token_type' => 'Bearer',
        ]),
        'https://eu-documentai.googleapis.com/*' => Http::response([
            'document' => [
                'entities' => [
                    [
                        'type' => 'submission_qr',
                        'mentionText' => base64_encode($course->getKey().'*'.$module->getKey().'*'.$user->getKey()),
                    ],
                ],
            ],
        ]),
    ]);

    (new ProcessQuizSubmission($documentUpload))->handle(app(GoogleDocumentAiQuizService::class));

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://eu-documentai.googleapis.com/v1/projects/test-project/locations/eu/processors/processor-123:process'
        && $request->hasHeader('Authorization', 'Bearer shared-service-account-token'));
});

function processQuizSubmissionPrivateKey(): string
{
    $key = openssl_pkey_new([
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ]);

    openssl_pkey_export($key, $privateKey);

    return $privateKey;
}
