<?php

use App\Enums\DocumentConversionJobStatus;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\CustomCertificate;
use App\Models\DocumentConversionJob;
use App\Models\Module;
use App\Models\ModuleProgress;
use App\Models\User;
use App\Services\Certificates\CourseCertificateGenerator;
use App\Services\CloudRunJobClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('creates document conversion job when enrollment completes on final module without required satisfaction survey', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    [$enrollment, $videoProgress] = createCertificateEnrollment(false);
    createGenericParticipationCertificateTemplate();

    $videoProgress->markCompleted();

    $enrollment->refresh();
    $job = DocumentConversionJob::query()->sole();

    expect($enrollment->status)->toBe(CourseEnrollment::STATUS_COMPLETED);
    expect($job->status)->toBe(DocumentConversionJobStatus::PENDING);
});

it('creates document conversion job when required satisfaction survey completes enrollment', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    [$enrollment, $videoProgress, $surveyProgress] = createCertificateEnrollment(true);
    createGenericParticipationCertificateTemplate();

    $videoProgress->markCompleted();
    $surveyProgress->refresh();
    $surveyProgress->completeSatisfactionSurvey();

    $enrollment->refresh();
    $job = DocumentConversionJob::query()->sole();

    expect($enrollment->status)->toBe(CourseEnrollment::STATUS_COMPLETED);
    expect($job->status)->toBe(DocumentConversionJobStatus::PENDING);
});

it('creates pending document conversion job for completed enrollment using active matching template', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    [$enrollment, $videoProgress] = createCertificateEnrollment(false);
    $videoProgress->markCompleted();
    $enrollment->refresh();

    $templateUpload = docxUpload([
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:r><w:t>${TITOLO}</w:t></w:r>
        </w:p>
    </w:body>
</w:document>
XML,
    ]);

    $storedPath = $templateUpload->store('custom-certificates/participation', 's3');

    CustomCertificate::factory()->create([
        'type' => CustomCertificate::TYPE_PARTICIPATION,
        'storage_disk' => 's3',
        'template_path' => $storedPath,
        'original_filename' => 'template.docx',
        'course_ids' => [$enrollment->course_id],
    ]);

    $job = app(CourseCertificateGenerator::class)->generateForEnrollment($enrollment)->sole();

    expect($job)->not->toBeNull()
        ->and($job->status)->toBe(DocumentConversionJobStatus::PENDING)
        ->and($job->input_disk)->toBe('s3')
        ->and($job->output_disk)->toBe('s3')
        ->and($job->input_path)->toBe('certificates/word/'.$enrollment->course_id.'_'.Str::upper($enrollment->user->fiscal_code).'_'.$enrollment->completed_at?->format('Ymd').'_participation.docx')
        ->and($job->output_path)->toBe('certificates/word/'.$enrollment->course_id.'_'.Str::upper($enrollment->user->fiscal_code).'_'.$enrollment->completed_at?->format('Ymd').'_participation.pdf');

    Storage::disk('s3')->assertExists($job->input_path);
});

it('creates only participation certificate when satisfaction survey is completed but learning quiz is not passed', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $enrollment = createCompletedEnrollmentWithLearningOutcome(false);
    createGenericCertificateTemplate(CustomCertificate::TYPE_PARTICIPATION);
    createGenericCertificateTemplate(CustomCertificate::TYPE_COMPLETION);

    $jobs = app(CourseCertificateGenerator::class)->generateForEnrollment($enrollment);

    expect($jobs)->toHaveCount(1);

    $job = $jobs->sole();

    expect($job->input_path)->toEndWith('_participation.docx')
        ->and($job->output_path)->toEndWith('_participation.pdf');
});

it('creates participation and completion certificates when learning quiz is passed and satisfaction survey is completed', function () {
    config(['filesystems.default' => 's3']);
    Storage::fake('s3');

    $enrollment = createCompletedEnrollmentWithLearningOutcome(true);
    createGenericCertificateTemplate(CustomCertificate::TYPE_PARTICIPATION);
    createGenericCertificateTemplate(CustomCertificate::TYPE_COMPLETION);

    $jobs = app(CourseCertificateGenerator::class)->generateForEnrollment($enrollment);

    expect($jobs)->toHaveCount(2)
        ->and($jobs->pluck('input_path')->all())->toBe([
            'certificates/word/'.$enrollment->course_id.'_'.Str::upper($enrollment->user->fiscal_code).'_'.$enrollment->completed_at?->format('Ymd').'_participation.docx',
            'certificates/word/'.$enrollment->course_id.'_'.Str::upper($enrollment->user->fiscal_code).'_'.$enrollment->completed_at?->format('Ymd').'_completion.docx',
        ])
        ->and($jobs->pluck('output_path')->all())->toBe([
            'certificates/word/'.$enrollment->course_id.'_'.Str::upper($enrollment->user->fiscal_code).'_'.$enrollment->completed_at?->format('Ymd').'_participation.pdf',
            'certificates/word/'.$enrollment->course_id.'_'.Str::upper($enrollment->user->fiscal_code).'_'.$enrollment->completed_at?->format('Ymd').'_completion.pdf',
        ]);
});

it('starts cloud run only once when pending document conversion jobs exist', function () {
    $firstPendingJob = DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::PENDING,
        'input_disk' => 's3',
        'input_path' => 'certificates/word/pending-1.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/pending-1.pdf',
    ]);

    $secondPendingJob = DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::PENDING,
        'input_disk' => 's3',
        'input_path' => 'certificates/word/pending-2.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/pending-2.pdf',
    ]);

    $failedJob = DocumentConversionJob::query()->create([
        'status' => DocumentConversionJobStatus::FAILED,
        'input_disk' => 's3',
        'input_path' => 'certificates/word/failed.docx',
        'output_disk' => 's3',
        'output_path' => 'certificates/word/failed.pdf',
    ]);

    $this->mock(CloudRunJobClient::class, function ($mock) use ($firstPendingJob): void {
        $mock->shouldReceive('runDocumentConversionJob')
            ->once()
            ->withArgs(fn (DocumentConversionJob $job): bool => $job->is($firstPendingJob)
                && $job->status === DocumentConversionJobStatus::PENDING
                && $job->attempts === 0
                && $job->started_at === null)
            ->andReturn([
                'operation_name' => 'operations/course-certificate',
                'payload' => ['name' => 'operations/course-certificate'],
            ]);
    });

    Artisan::call('app:start-pending-document-conversion-jobs');

    expect($firstPendingJob->fresh())
        ->status->toBe(DocumentConversionJobStatus::PENDING)
        ->and($firstPendingJob->fresh()->attempts)->toBe(0)
        ->and($secondPendingJob->fresh()->status)->toBe(DocumentConversionJobStatus::PENDING)
        ->and($failedJob->fresh()->status)->toBe(DocumentConversionJobStatus::FAILED);
});

function createCertificateEnrollment(bool $requiresSatisfactionSurvey): array
{
    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Certificate',
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'fiscal_code' => strtoupper(Str::random(16)),
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);

    $course = Course::factory()->create([
        'has_satisfaction_survey' => true,
        'satisfaction_survey_required_for_certificate' => $requiresSatisfactionSurvey,
    ]);

    $videoModule = Module::factory()->create([
        'type' => Module::TYPE_VIDEO,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $surveyModule = Module::factory()->create([
        'type' => Module::TYPE_SATISFACTION_QUIZ,
        'title' => Module::defaultTitleForType(Module::TYPE_SATISFACTION_QUIZ),
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);

    $videoProgress = $enrollment->moduleProgresses()->where('module_id', $videoModule->getKey())->firstOrFail();
    $surveyProgress = $enrollment->moduleProgresses()->where('module_id', $surveyModule->getKey())->firstOrFail();

    return [$enrollment, $videoProgress, $surveyProgress];
}

function createCompletedEnrollmentWithLearningOutcome(bool $passedLearningQuiz): CourseEnrollment
{
    $user = User::forceCreate([
        'name' => 'Test',
        'surname' => 'Certificate',
        'email' => fake()->unique()->safeEmail(),
        'password' => Hash::make('password'),
        'fiscal_code' => strtoupper(Str::random(16)),
        'email_verified_at' => now(),
        'profile_completed_at' => now(),
        'account_state' => 'active',
        'is_foreigner_or_immigrant' => false,
    ]);

    $course = Course::factory()->create([
        'has_satisfaction_survey' => true,
        'satisfaction_survey_required_for_certificate' => true,
    ]);

    $learningModule = Module::factory()->create([
        'type' => Module::TYPE_LEARNING_QUIZ,
        'passing_score' => 7,
        'max_score' => 10,
        'order' => 1,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $surveyModule = Module::factory()->create([
        'type' => Module::TYPE_SATISFACTION_QUIZ,
        'title' => Module::defaultTitleForType(Module::TYPE_SATISFACTION_QUIZ),
        'order' => 2,
        'belongsTo' => (string) $course->getKey(),
    ]);

    $enrollment = CourseEnrollment::enroll($user, $course);

    $enrollment->moduleProgresses()->where('module_id', $learningModule->getKey())->firstOrFail()
        ->forceFill([
            'status' => $passedLearningQuiz ? ModuleProgress::STATUS_COMPLETED : ModuleProgress::STATUS_FAILED,
            'started_at' => now(),
            'completed_at' => $passedLearningQuiz ? now() : null,
            'last_accessed_at' => now(),
            'quiz_attempts' => 1,
            'quiz_score' => $passedLearningQuiz ? 10 : 4,
            'quiz_total_score' => 10,
            'passed_at' => $passedLearningQuiz ? now() : null,
        ])->save();

    $enrollment->moduleProgresses()->where('module_id', $surveyModule->getKey())->firstOrFail()
        ->forceFill([
            'status' => ModuleProgress::STATUS_COMPLETED,
            'started_at' => now(),
            'completed_at' => now(),
            'last_accessed_at' => now(),
        ])->save();

    $enrollment->forceFill([
        'current_module_id' => $surveyModule->getKey(),
        'status' => CourseEnrollment::STATUS_COMPLETED,
        'completed_at' => now(),
        'completion_percentage' => 100,
    ])->save();

    return $enrollment->fresh(['course.modules', 'moduleProgresses', 'user']);
}

function createGenericParticipationCertificateTemplate(): void
{
    createGenericCertificateTemplate(CustomCertificate::TYPE_PARTICIPATION);
}

function createGenericCertificateTemplate(string $type): void
{
    $templateUpload = docxUpload([
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
    <w:body>
        <w:p>
            <w:r><w:t>${TITOLO}</w:t></w:r>
        </w:p>
    </w:body>
</w:document>
XML,
    ]);

    $storedPath = $templateUpload->store('custom-certificates/'.$type, 's3');

    CustomCertificate::factory()->create([
        'type' => $type,
        'storage_disk' => 's3',
        'template_path' => $storedPath,
        'original_filename' => 'template.docx',
        'course_ids' => null,
    ]);
}
